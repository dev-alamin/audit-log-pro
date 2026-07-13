<?php
namespace Amin\AuditLogPro\Services;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use WP_User;
use WC_Order;
use WC_Product;
use Amin\AuditLogPro\Utility\Helper;

/**
 * Class WPBridge
 *
 * Acts as a proxy wrapper around WordPress core functions and static
 * utilities to enable decoupled, isolated unit testing.
 *
 * @package Amin\AuditLogPro\Services
 * @since 1.0.0
 */
class WPBridge {

	/**
	 * Wrapper for get_userdata()
	 *
	 * @param int $user_id User ID.
	 * @return WP_User|false WP_User object on success, false on failure.
	 */
	public function get_userdata( int $user_id ) {
		return get_userdata( $user_id );
	}

	/**
	 * Wrapper for get_current_user_id()
	 *
	 * @return int Current user ID, or 0 if no user is logged in.
	 */
	public function get_current_user_id(): int {
		return get_current_user_id();
	}

	/**
	 * Wrapper for wp_get_current_user()
	 *
	 * @return WP_User Current user object.
	 */
	public function get_current_user(): WP_User {
		return wp_get_current_user();
	}

	/**
	 * Wrapper for static helper context.
	 * Moving this inside the bridge keeps the Logger clean of static side-effects.
	 *
	 * @return string The resolved client IP address.
	 */
	public function get_user_ip(): string {
		return Helper::get_user_ip();
	}

	public function actor_name(): string {
		$current = $this->get_current_user();
		return $current->exists() ? $current->user_login : 'System';
	}

	public function get_post( int $post ) {
		return get_post( $post );
	}

	/**
	 * Wrapper for wc_get_order()
	 *
	 * Deliberately untyped return (not `WC_Order|false`) because WooCommerce
	 * may be inactive when this class loads — a hard type hint referencing
	 * WC_Order would fatal on PHP's return-type check for stores without WC.
	 * Callers (WooCommerceLogger) already guard with `if ( ! $order )`.
	 *
	 * Note: wc_get_order() can also return WC_Order_Refund (a WC_Order
	 * subclass) when passed a refund ID — this is expected and used by
	 * WooCommerceLogger::order_refunded().
	 *
	 * @param int $order_id Order (or refund) ID.
	 * @return \WC_Order|\WC_Order_Refund|false Order object on success, false if not found or WC inactive.
	 */
	public function get_order( int $order_id ) {
		if ( ! function_exists( 'wc_get_order' ) ) {
			return false;
		}

		return wc_get_order( $order_id );
	}

	/**
	 * Wrapper for wc_get_product()
	 *
	 * Same reasoning as get_order() re: untyped return — guards against
	 * WooCommerce being inactive rather than assuming it's always loaded.
	 *
	 * @param int $product_id Product (or variation) ID.
	 * @return \WC_Product|false Product object on success, false if not found or WC inactive.
	 */
	public function get_product( int $product_id ) {
		if ( ! function_exists( 'wc_get_product' ) ) {
			return false;
		}

		return wc_get_product( $product_id );
	}
}
