import { createReduxStore, register } from '@wordpress/data';
import apiFetch from '@wordpress/api-fetch';

/**
 * A minimal custom wp.data store, registered separately from core so the
 * SlotFill panel can useSelect() against it like any core store.
 *
 * The resolver pattern (resolveGetEntriesForPost) is what makes this work
 * with useSelect the way core entities do: the first call returns null/
 * empty state immediately and triggers the resolver in the background;
 * once the resolver's action lands, the store updates and the connected
 * component re-renders automatically. No manual loading-state juggling
 * in the component itself — that's the actual point of using wp.data
 * here instead of local useState + useEffect (which is what the block's
 * Edit component uses instead, deliberately, since it doesn't need
 * cross-component shared state).
 */
const DEFAULT_STATE = {
	entriesByPost: {},
};

const actions = {
	receiveEntries( postId, entries ) {
		return { type: 'RECEIVE_ENTRIES', postId, entries };
	},
};

const store = createReduxStore( 'audit-log-pro/store', {
	reducer( state = DEFAULT_STATE, action ) {
		switch ( action.type ) {
			case 'RECEIVE_ENTRIES':
				return {
					...state,
					entriesByPost: {
						...state.entriesByPost,
						[ action.postId ]: action.entries,
					},
				};
			default:
				return state;
		}
	},

	actions,

	selectors: {
		getEntriesForPost( state, postId ) {
			return state.entriesByPost[ postId ] ?? null;
		},
	},

	resolvers: {
		*getEntriesForPost( postId ) {
			if ( ! postId ) {
				return;
			}

			const response = yield apiFetch( {
				path: `/audit-log-pro/v1/logs?event_type=post_status_change&per_page=20`,
			} );

			const forThisPost = ( response.data || [] ).filter(
				( row ) => Number( row.object_id ) === Number( postId )
			);

			return actions.receiveEntries( postId, forThisPost );
		},
	},
} );

register( store );
