/**
 * Client layer for the PressShot Library. Every function returns the parsed JSON from
 * /wp-json/pressshot/v1 and throws the api-fetch error when the request fails.
 */
import apiFetch from '@wordpress/api-fetch';

const NAMESPACE = '/pressshot/v1';

const firstValue = ( value ) => ( Array.isArray( value ) ? value[ 0 ] : value );

/**
 * Maps a sidebar scope ({ kind: 'all' | 'type' | 'folder' | 'tag' | 'trash', id? }) to the scope
 * viewToQuery() takes.
 *
 * @param {Object} scope The sidebar scope.
 * @return {Object} { type?, folder?, tag?, trash? }.
 */
export function scopeQuery( scope ) {
	switch ( scope.kind ) {
		case 'type':
			return { type: scope.id };
		case 'folder':
			return { folder: scope.id };
		case 'tag':
			return { tag: scope.id };
		case 'trash':
			return { trash: true };
		default:
			return {};
	}
}

/**
 * Maps a DataViews view plus the sidebar scope to /shots query params.
 *
 * @param {Object} view  DataViews view: search, page, perPage, sort { field, direction }, filters.
 * @param {Object} scope Sidebar scope: { type?: string, folder?: number, tag?: number, trash?: boolean }.
 * @return {Object} Query params for GET /shots.
 */
export function viewToQuery( view, scope = {} ) {
	const query = {};
	if ( view.search ) {
		query.search = view.search;
	}
	if ( view.page ) {
		query.page = view.page;
	}
	if ( view.perPage ) {
		query.perPage = view.perPage;
	}
	if ( view.sort && view.sort.field ) {
		query.orderby = view.sort.field === 'title' ? 'title' : 'date';
		query.order = view.sort.direction === 'asc' ? 'asc' : 'desc';
	}
	for ( const filter of view.filters || [] ) {
		const value = firstValue( filter.value );
		if ( value === undefined || value === null || value === '' ) {
			continue;
		}
		if ( filter.field === 'author' ) {
			query.author = Number( value );
		}
	}
	if ( scope.type ) {
		query.type = scope.type;
	}
	if ( typeof scope.folder === 'number' ) {
		query.folder = scope.folder;
	}
	if ( scope.tag ) {
		query.tag = scope.tag;
	}
	if ( scope.trash ) {
		query.status = 'trash';
	}
	return query;
}

const queryString = ( query ) => {
	const parts = Object.keys( query )
		.filter( ( key ) => query[ key ] !== undefined && query[ key ] !== null )
		.map( ( key ) => `${ encodeURIComponent( key ) }=${ encodeURIComponent( query[ key ] ) }` );
	return parts.length ? `?${ parts.join( '&' ) }` : '';
};

const linkPath = ( kind, id ) => `${ NAMESPACE }/${ kind === 'folder' ? 'folders' : 'shots' }/${ id }/link`;

export function fetchShots( query = {} ) {
	return apiFetch( { path: `${ NAMESPACE }/shots${ queryString( query ) }` } );
}

export function organizeShots( ids, change = {} ) {
	return apiFetch( { path: `${ NAMESPACE }/shots/organize`, method: 'POST', data: { ids, ...change } } );
}

export function trashShot( id ) {
	return apiFetch( { path: `${ NAMESPACE }/captures/${ id }/trash`, method: 'POST' } );
}

export function restoreShot( id ) {
	return apiFetch( { path: `${ NAMESPACE }/captures/${ id }/restore`, method: 'POST' } );
}

/** Resolves to { link } for the shot or folder; link is null when it has none. */
export function getLink( kind, id ) {
	return apiFetch( { path: linkPath( kind, id ) } );
}

export function setLink( kind, id, expiresAt = null ) {
	return apiFetch( { path: linkPath( kind, id ), method: 'POST', data: { expiresAt } } );
}

export function revokeLink( kind, id ) {
	return apiFetch( { path: linkPath( kind, id ), method: 'DELETE' } );
}

/** The delete dialog makes the person type DELETE, so every call sends the confirmation. */
export function deleteFolder( id, mode, target ) {
	return apiFetch( {
		path: `${ NAMESPACE }/folders/${ id }/delete`,
		method: 'POST',
		data: { mode, target, confirm: 'DELETE' },
	} );
}
