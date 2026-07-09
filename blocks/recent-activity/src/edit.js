import { __ } from '@wordpress/i18n';
import { useState, useEffect } from '@wordpress/element';
import { useBlockProps, InspectorControls } from '@wordpress/block-editor';
import { PanelBody, RangeControl, TextControl } from '@wordpress/components';
import apiFetch from '@wordpress/api-fetch';

/**
 * This block has no registered wp.data store of its own, so a live editor
 * preview goes through apiFetch directly against our custom REST route —
 * NOT useSelect, since useSelect only pays off against a registered store
 * (core's, or one we build with @wordpress/data createReduxStore). Building
 * a store for a single read-only preview call would be overhead without
 * benefit here. The SlotFill panel (src/slotfill) is the piece of this
 * plugin that actually demonstrates useSelect, against data resolved
 * through @wordpress/core-data's REST resolution.
 */
export default function Edit( { attributes, setAttributes } ) {
	const { numberOfItems, eventType } = attributes;
	const blockProps = useBlockProps( { className: 'alp-recent-activity-edit' } );

	const [ rows, setRows ] = useState( null );

	useEffect( () => {
		const query = new URLSearchParams( {
			per_page: numberOfItems,
			event_type: eventType || '',
		} );

		apiFetch( { path: `/audit-log-pro/v1/logs?${ query.toString() }` } )
			.then( ( response ) => setRows( response.data ) )
			.catch( () => setRows( [] ) );
	}, [ numberOfItems, eventType ] );

	return (
		<>
			<InspectorControls>
				<PanelBody title={ __( 'Activity Settings', 'audit-log-pro' ) }>
					<RangeControl
						label={ __( 'Number of items', 'audit-log-pro' ) }
						value={ numberOfItems }
						onChange={ ( value ) => setAttributes( { numberOfItems: value } ) }
						min={ 1 }
						max={ 20 }
					/>
					<TextControl
						label={ __( 'Filter by event type', 'audit-log-pro' ) }
						value={ eventType }
						onChange={ ( value ) => setAttributes( { eventType: value } ) }
						help={ __( 'Leave empty to show all event types.', 'audit-log-pro' ) }
					/>
				</PanelBody>
			</InspectorControls>
			<div { ...blockProps }>
				<p className="alp-recent-activity-edit__hint">
					{ __( 'Live preview — actual frontend output is rendered by render.php.', 'audit-log-pro' ) }
				</p>
				{ rows === null && <p>{ __( 'Loading…', 'audit-log-pro' ) }</p> }
				{ rows && rows.length === 0 && <p>{ __( 'No activity recorded yet.', 'audit-log-pro' ) }</p> }
				{ rows && rows.length > 0 && (
					<ul>
						{ rows.map( ( row ) => (
							<li key={ row.id }>
								<strong>{ row.event_type }</strong> — { row.message }
							</li>
						) ) }
					</ul>
				) }
			</div>
		</>
	);
}
