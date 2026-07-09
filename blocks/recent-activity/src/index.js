import { registerBlockType } from '@wordpress/blocks';
import metadata from '../block.json';
import Edit from './edit';

/**
 * Dynamic block: no `save` function registered on purpose. Returning null
 * (the default when `save` is omitted) tells WP "don't store static markup
 * in post_content, always call render.php instead." That's what makes this
 * a true dynamic block rather than a static one with a PHP fallback.
 */
registerBlockType( metadata.name, {
	edit: Edit,
} );
