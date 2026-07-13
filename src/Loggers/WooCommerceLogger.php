<?php
namespace Amin\AuditLogPro\Loggers;

use Amin\AuditLogPro\Core\HookLoader;
use Amin\AuditLogPro\Registrable;
use Amin\AuditLogPro\Database\EventRepository;
use Amin\AuditLogPro\Database\Event;
use Amin\AuditLogPro\Services\WPBridge;
use WC_Order;
use WC_Product;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Logs WooCommerce order, product, and stock activity.
 *
 * Deliberately hooks the storage-agnostic `woocommerce_*` CRUD actions
 * rather than `save_post`/`transition_post_status`, since those are the
 * only ones guaranteed to fire whether the store uses legacy post-based
 * orders or HPOS (High-Performance Order Storage) custom tables.
 *
 * This logger should only be registered when WooCommerce is active —
 * that check belongs in the loader/bootstrap, not here.
 */
class WooCommerceLogger implements Registrable {

	/**
	 * Order statuses whose transitions are noise for an audit trail
	 * (WooCommerce cycles through these internally on every checkout).
	 */
	private const IGNORED_STATUS_TRANSITIONS = array(
		'auto-draft',
		'checkout-draft',
	);

	/**
	 * Event repository.
	 *
	 * @var EventRepository
	 */
	private EventRepository $repository;

	/**
	 * WPBridge for native WP functions.
	 *
	 * @var WPBridge
	 */
	private WPBridge $wp;

	/**
	 * Hook loader.
	 *
	 * @var HookLoader
	 */
	private HookLoader $loader;

	public function __construct( EventRepository $repository, WPBridge $wp, HookLoader $loader ) {
		$this->wp         = $wp;
		$this->repository = $repository;
		$this->loader     = $loader;
	}

	public function register(): void {
		// Orders — CRUD-level, HPOS-safe.
		$this->loader->add_action( 'woocommerce_new_order', array( $this, 'order_created' ), 10, 2 );
		$this->loader->add_action( 'woocommerce_order_status_changed', array( $this, 'order_status_changed' ), 10, 4 );
		$this->loader->add_action( 'woocommerce_trash_order', array( $this, 'order_trashed' ), 10, 1 );
		$this->loader->add_action( 'woocommerce_untrash_order', array( $this, 'order_restored' ), 10, 1 );
		$this->loader->add_action( 'woocommerce_delete_order', array( $this, 'order_deleted' ), 10, 1 );
		$this->loader->add_action( 'woocommerce_order_refunded', array( $this, 'order_refunded' ), 10, 2 );

		// Products.
		$this->loader->add_action( 'woocommerce_new_product', array( $this, 'product_created' ), 10, 1 );
		$this->loader->add_action( 'woocommerce_update_product', array( $this, 'product_updated' ), 10, 1 );
		$this->loader->add_action( 'woocommerce_trash_product', array( $this, 'product_trashed' ), 10, 1 );
		$this->loader->add_action( 'woocommerce_delete_product', array( $this, 'product_deleted' ), 10, 1 );

		// Stock — simple products and variations both route through set_stock,
		// low/no-stock are separate notification hooks worth capturing on their own.
		$this->loader->add_action( 'woocommerce_product_set_stock', array( $this, 'stock_changed' ), 10, 1 );
		$this->loader->add_action( 'woocommerce_variation_set_stock', array( $this, 'stock_changed' ), 10, 1 );
		$this->loader->add_action( 'woocommerce_low_stock', array( $this, 'low_stock' ), 10, 1 );
		$this->loader->add_action( 'woocommerce_no_stock', array( $this, 'no_stock' ), 10, 1 );
	}

	/*
	---------------------------------------------------------------------
	 * Orders
	 * ------------------------------------------------------------------- */

	/**
	 * Fires after a new order is created.
	 *
	 * @param int      $order_id Order ID.
	 * @param WC_Order $order    Order object.
	 */
	public function order_created( int $order_id, WC_Order $order ): void {
		$this->repository->insert(
			new Event(
				type       : 'order_created',
				actor_id   : $this->wp->get_current_user_id(),
				object_type: 'order',
				object_id  : $order_id,
				ip         : $this->wp->get_user_ip(),
				message    : sprintf( '%s created order #%d', $this->wp->actor_name(), $order_id ),
				meta       : array(
					'status'   => $order->get_status(),
					'total'    => $order->get_total(),
					'currency' => $order->get_currency(),
				),
			)
		);
	}

	/**
	 * Fires when an order's status changes.
	 *
	 * @param int      $order_id   Order ID.
	 * @param string   $old_status Previous status (no `wc-` prefix).
	 * @param string   $new_status New status (no `wc-` prefix).
	 * @param WC_Order $order      Order object.
	 */
	public function order_status_changed( int $order_id, string $old_status, string $new_status, WC_Order $order ): void {
		if ( $old_status === $new_status ) {
			return;   // guard FIRST — no real transition
		}

		if ( in_array( $old_status, self::IGNORED_STATUS_TRANSITIONS, true ) ) {
			return;
		}

		$message = sprintf(
			'%s changed order #%d status from %s to %s',
			$this->wp->actor_name(),
			$order_id,
			$old_status,
			$new_status
		);

		$this->repository->insert(
			new Event(
				type       : 'order_status_changed',
				actor_id   : $this->wp->get_current_user_id(),
				object_type: 'order',
				object_id  : $order_id,
				ip         : $this->wp->get_user_ip(),
				message    : $message,
				meta       : array(
					'old_status' => $old_status,
					'new_status' => $new_status,
					'total'      => $order->get_total(),
				),
			)
		);
	}

	/**
	 * Fires after an order is moved to trash.
	 *
	 * @param int $order_id Order ID.
	 */
	public function order_trashed( int $order_id ): void {
		$order = $this->wp->get_order( $order_id );

		if ( ! $order ) {
			return;   // guard FIRST, before anything touches $order
		}

		$this->repository->insert(
			new Event(
				type       : 'order_trashed',
				actor_id   : $this->wp->get_current_user_id(),
				object_type: 'order',
				object_id  : $order_id,
				ip         : $this->wp->get_user_ip(),
				message    : sprintf( '%s moved order #%d to trash', $this->wp->actor_name(), $order_id ),
				meta       : array( 'status' => $order->get_status() ),
			)
		);
	}

	/**
	 * Fires after an order is restored from trash.
	 *
	 * @param int $order_id Order ID.
	 */
	public function order_restored( int $order_id ): void {
		$order = $this->wp->get_order( $order_id );

		if ( ! $order ) {
			return;   // guard FIRST
		}

		$this->repository->insert(
			new Event(
				type       : 'order_restored',
				actor_id   : $this->wp->get_current_user_id(),
				object_type: 'order',
				object_id  : $order_id,
				ip         : $this->wp->get_user_ip(),
				message    : sprintf( '%s restored order #%d from trash', $this->wp->actor_name(), $order_id ),
				meta       : array( 'status' => $order->get_status() ),
			)
		);
	}

	/**
	 * Fires immediately before an order is permanently deleted.
	 * Must capture order data here — it won't be fetchable afterward.
	 *
	 * @param int $order_id Order ID.
	 */
	public function order_deleted( int $order_id ): void {
		$order = $this->wp->get_order( $order_id );

		if ( ! $order ) {
			return;   // guard FIRST
		}

		$this->repository->insert(
			new Event(
				type       : 'order_deleted',
				actor_id   : $this->wp->get_current_user_id(),
				object_type: 'order',
				object_id  : $order_id,
				ip         : $this->wp->get_user_ip(),
				message    : sprintf( '%s permanently deleted order #%d', $this->wp->actor_name(), $order_id ),
				meta       : array(
					'status' => $order->get_status(),
					'total'  => $order->get_total(),
				),
			)
		);
	}

	/**
	 * Fires after a refund is created against an order.
	 *
	 * @param int $order_id  Order ID.
	 * @param int $refund_id Refund ID (itself a WC_Order_Refund).
	 */
	public function order_refunded( int $order_id, int $refund_id ): void {
		$refund = $this->wp->get_order( $refund_id );

		if ( ! $refund ) {
			return;   // guard FIRST
		}

		$this->repository->insert(
			new Event(
				type       : 'order_refunded',
				actor_id   : $this->wp->get_current_user_id(),
				object_type: 'order',
				object_id  : $order_id,
				ip         : $this->wp->get_user_ip(),
				message    : sprintf(
					'%s refunded %s on order #%d',
					$this->wp->actor_name(),
					$refund->get_amount(),
					$order_id
				),
				meta       : array(
					'refund_id' => $refund_id,
					'amount'    => $refund->get_amount(),
					'reason'    => $refund->get_reason(),
				),
			)
		);
	}

	/*
	---------------------------------------------------------------------
	 * Products
	 * ------------------------------------------------------------------- */

	public function product_created( int $product_id ): void {
		$product = $this->wp->get_product( $product_id );

		if ( ! $product ) {
			return;   // guard FIRST
		}

		$this->repository->insert(
			new Event(
				type       : 'product_created',
				actor_id   : $this->wp->get_current_user_id(),
				object_type: 'product',
				object_id  : $product_id,
				ip         : $this->wp->get_user_ip(),
				message    : sprintf( '%s created product "%s"', $this->wp->actor_name(), $product->get_name() ),
				meta       : array(
					'sku'   => $product->get_sku(),
					'price' => $product->get_regular_price(),
				),
			)
		);
	}

	public function product_updated( int $product_id ): void {
		$product = $this->wp->get_product( $product_id );

		if ( ! $product ) {
			return;   // guard FIRST
		}

		$this->repository->insert(
			new Event(
				type       : 'product_updated',
				actor_id   : $this->wp->get_current_user_id(),
				object_type: 'product',
				object_id  : $product_id,
				ip         : $this->wp->get_user_ip(),
				message    : sprintf( '%s updated product "%s"', $this->wp->actor_name(), $product->get_name() ),
				meta       : array(
					'sku'   => $product->get_sku(),
					'price' => $product->get_regular_price(),
				),
			)
		);
	}

	public function product_trashed( int $product_id ): void {
		$product = $this->wp->get_product( $product_id );

		if ( ! $product ) {
			return;   // guard FIRST
		}

		$this->repository->insert(
			new Event(
				type       : 'product_trashed',
				actor_id   : $this->wp->get_current_user_id(),
				object_type: 'product',
				object_id  : $product_id,
				ip         : $this->wp->get_user_ip(),
				message    : sprintf( '%s moved product "%s" to trash', $this->wp->actor_name(), $product->get_name() ),
				meta       : array( 'sku' => $product->get_sku() ),
			)
		);
	}

	/**
	 * Fires after a product is permanently deleted. Note: unlike orders,
	 * this hook fires AFTER deletion, so $product may already be gone —
	 * we degrade gracefully and log by ID only if so.
	 *
	 * @param int $product_id Product ID.
	 */
	public function product_deleted( int $product_id ): void {
		$product = $this->wp->get_product( $product_id );

		$name = $product ? $product->get_name() : sprintf( '#%d', $product_id );

		$this->repository->insert(
			new Event(
				type       : 'product_deleted',
				actor_id   : $this->wp->get_current_user_id(),
				object_type: 'product',
				object_id  : $product_id,
				ip         : $this->wp->get_user_ip(),
				message    : sprintf( '%s permanently deleted product "%s"', $this->wp->actor_name(), $name ),
				meta       : array(),
			)
		);
	}

	/*
	---------------------------------------------------------------------
	 * Stock
	 * ------------------------------------------------------------------- */

	/**
	 * Fires after a product or variation's stock quantity is set.
	 * Handles both WC_Product and WC_Product_Variation — both share this API.
	 *
	 * @param WC_Product $product Product (or variation) object.
	 */
	public function stock_changed( WC_Product $product ): void {
		$this->repository->insert(
			new Event(
				type       : 'stock_changed',
				actor_id   : $this->wp->get_current_user_id(),
				object_type: 'product',
				object_id  : $product->get_id(),
				ip         : $this->wp->get_user_ip(),
				message    : sprintf(
					'%s set stock of "%s" to %s',
					$this->wp->actor_name(),
					$product->get_name(),
					$product->get_stock_quantity() ?? 'N/A'
				),
				meta       : array(
					'sku'          => $product->get_sku(),
					'stock_qty'    => $product->get_stock_quantity(),
					'stock_status' => $product->get_stock_status(),
				),
			)
		);
	}

	/**
	 * Fires when a product crosses into low-stock territory.
	 * Not user-attributable — this is a system-detected threshold event,
	 * so actor_id is 0 (system) rather than the current user.
	 *
	 * @param WC_Product $product Product object.
	 */
	public function low_stock( WC_Product $product ): void {
		$this->repository->insert(
			new Event(
				type       : 'product_low_stock',
				actor_id   : 0,
				object_type: 'product',
				object_id  : $product->get_id(),
				ip         : '',
				message    : sprintf( '"%s" is low on stock (%s remaining)', $product->get_name(), $product->get_stock_quantity() ),
				meta       : array(
					'sku'       => $product->get_sku(),
					'stock_qty' => $product->get_stock_quantity(),
				),
			)
		);
	}

	/**
	 * Fires when a product goes out of stock. System-detected, same as low_stock().
	 *
	 * @param WC_Product $product Product object.
	 */
	public function no_stock( WC_Product $product ): void {
		$this->repository->insert(
			new Event(
				type       : 'product_out_of_stock',
				actor_id   : 0,
				object_type: 'product',
				object_id  : $product->get_id(),
				ip         : '',
				message    : sprintf( '"%s" is out of stock', $product->get_name() ),
				meta       : array( 'sku' => $product->get_sku() ),
			)
		);
	}
}
