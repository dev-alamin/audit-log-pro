import { __ } from '@wordpress/i18n';
import { registerPlugin } from '@wordpress/plugins';
import { PluginDocumentSettingPanel } from '@wordpress/editor';
import { useSelect } from '@wordpress/data';
import { store as coreDataStore } from '@wordpress/core-data';
import './store';

/**
 * SlotFill: injects a panel into the post editor sidebar (the document
 * settings panel, next to Status & Visibility, Categories, etc) showing
 * this post's own audit trail.
 *
 * useSelect here is doing real work: `getEditedPostAttribute` and the
 * current post id both come from core-data's already-resolved store —
 * no extra fetch, no loading state to manage ourselves for the post id.
 * We then use that id to build our own REST query. This is the correct
 * split: useSelect for data WordPress core already has in its store,
 * apiFetch/custom fetch for data only our plugin's endpoint knows about.
 */
function AuditHistoryPanel() {
	const { postId, postType } = useSelect( ( select ) => {
		const editor = select( 'core/editor' );
		return {
			postId: editor.getCurrentPostId(),
			postType: editor.getCurrentPostType(),
		};
	}, [] );

	// Re-resolve post edits from core-data so the panel reflects unsaved
	// changes too, not just what's persisted — matters if a future version
	// wants to show "modified since last save" state inline.
	const hasEdits = useSelect(
		( select ) => select( coreDataStore ).hasEditsForEntityRecord( 'postType', postType, postId ),
		[ postType, postId ]
	);

	return (
		<PluginDocumentSettingPanel
			name="alp-audit-history"
			title={ __( 'Edit History', 'audit-log-pro' ) }
			className="alp-audit-history-panel"
		>
			<AuditHistoryList postId={ postId } />
			{ hasEdits && (
				<p className="alp-audit-history-panel__notice">
					{ __( 'Unsaved changes — save to record this edit.', 'audit-log-pro' ) }
				</p>
			) }
		</PluginDocumentSettingPanel>
	);
}

/**
 * Split into its own component so the data fetch is scoped to postId
 * changing, not every render of the parent panel.
 */
function AuditHistoryList( { postId } ) {
	const entries = useSelect(
		( select ) => {
			// Custom REST routes aren't core entities, so core-data can't
			// resolve them automatically the way it does posts/users/terms.
			// select() here is still the right tool though — it's what lets
			// this component participate in the same data flow as the rest
			// of the editor rather than reaching for local component state.
			return select( 'audit-log-pro/store' )?.getEntriesForPost?.( postId ) ?? null;
		},
		[ postId ]
	);

	if ( null === entries ) {
		return <p>{ __( 'Loading…', 'audit-log-pro' ) }</p>;
	}

	if ( ! entries.length ) {
		return <p>{ __( 'No recorded changes for this post yet.', 'audit-log-pro' ) }</p>;
	}

	return (
		<ul className="alp-audit-history-panel__list">
			{ entries.map( ( entry ) => (
				<li key={ entry.id }>{ entry.message }</li>
			) ) }
		</ul>
	);
}

registerPlugin( 'audit-log-pro-history', {
	render: AuditHistoryPanel,
	icon: 'clock',
} );
