/**
 * Pure helpers for the Library view: the folder tree, the DataViews fields, and media URLs.
 * Nothing here renders components, so it stays easy to test.
 */
import { __, sprintf } from '@wordpress/i18n';
import { dateI18n } from '@wordpress/date';
import { decodeEntities } from '@wordpress/html-entities';

/**
 * WordPress sends term names, display names, and titles HTML-encoded ("Q&amp;A"). React escapes
 * text itself, so decode once when data arrives and every place that renders it shows "Q&A".
 *
 * @param {Object} item Anything with a name, e.g. a folder, tag, or person.
 * @return {Object} A copy with the name decoded.
 */
export function decodeName( item ) {
	return { ...item, name: decodeEntities( item.name || '' ) };
}

/**
 * A shot from the API with its title, uploader name, and sharing folder name decoded.
 *
 * @param {Object} shot A ShotDTO.
 * @return {Object} A decoded copy.
 */
export function decodeShot( shot ) {
	return {
		...shot,
		title: decodeEntities( shot.title || '' ),
		author: shot.author ? decodeName( shot.author ) : shot.author,
		folderLinkName: shot.folderLinkName
			? decodeEntities( shot.folderLinkName )
			: shot.folderLinkName,
	};
}

/** The grid's preview field. shots-view.js defines it, since it renders a component. */
export const PREVIEW_FIELD = 'preview';

/** The grid card's one muted line under the title. shots-view.js defines it, from shotByline(). */
export const BYLINE_FIELD = 'byline';

export const defaultView = {
	type: 'grid',
	perPage: 40,
	page: 1,
	search: '',
	filters: [],
	sort: { field: 'date', direction: 'desc' },
	titleField: 'title',
	mediaField: PREVIEW_FIELD,
	descriptionField: BYLINE_FIELD,
	fields: [],
	layout: {},
};

const byName = ( a, b ) =>
	a.name.localeCompare( b.name, undefined, {
		sensitivity: 'base',
		numeric: true,
	} );

/**
 * Nests flat folder terms into a tree. Siblings sort by name; a folder whose parent isn't in the
 * list goes to the top level so it never disappears.
 *
 * @param {Array<{id: number, name: string, parent: number}>} folders Folder terms.
 * @return {Array<{id: number, name: string, parent: number, children: Array}>} Top-level nodes.
 */
export function buildFolderTree( folders ) {
	const nodes = new Map(
		folders.map( ( folder ) => [ folder.id, { ...folder, children: [] } ] )
	);
	const roots = [];
	for ( const node of nodes.values() ) {
		const parent = node.parent ? nodes.get( node.parent ) : null;
		if ( parent && parent !== node ) {
			parent.children.push( node );
		} else {
			roots.push( node );
		}
	}
	const sortTree = ( list ) => {
		list.sort( byName );
		list.forEach( ( node ) => sortTree( node.children ) );
		return list;
	};
	return sortTree( roots );
}

/**
 * A grid card's byline: "{uploader} · {short date}", e.g. "Avery Author · Nov 14". The year is
 * added when the shot is from another year than now (in the site's time zone).
 *
 * @param {{author: {name: string}, createdAt: string}} item The shot.
 * @param {Date}                                        now  Today, for the year check.
 * @return {string} The byline.
 */
export function shotByline( item, now = new Date() ) {
	const sameYear = dateI18n( 'Y', item.createdAt ) === dateI18n( 'Y', now );
	return sprintf(
		/* translators: 1: person's name, 2: short date, e.g. "Avery Author · Nov 14". */
		__( '%1$s · %2$s', 'pressshot' ),
		item.author.name,
		dateI18n( sameYear ? 'M j' : 'M j, Y', item.createdAt )
	);
}

export const SHOT_TYPES = [
	{ value: 'screenshot', label: __( 'Screenshot', 'pressshot' ) },
	{ value: 'recording', label: __( 'Recording', 'pressshot' ) },
];

/**
 * DataViews fields for shots. Only what the server can filter or sort on is offered: the person
 * filter; date and title sorting. Screenshots and recordings are sidebar views, not a filter.
 *
 * @param {Array<{id: number, name: string}>} authors People who can add shots.
 * @param {Array<{id: number, name: string}>} folders Folder terms.
 * @param {Array<{id: number, name: string}>} tags    Tag terms.
 * @return {Object[]} Field definitions.
 */
export function shotFields( authors, folders, tags ) {
	const folderNames = new Map(
		folders.map( ( folder ) => [ folder.id, folder.name ] )
	);
	const tagNames = new Map( tags.map( ( tag ) => [ tag.id, tag.name ] ) );
	return [
		{
			id: 'title',
			type: 'text',
			label: __( 'Title', 'pressshot' ),
			getValue: ( { item } ) =>
				item.title || __( '(no title)', 'pressshot' ),
			enableHiding: false,
			filterBy: false,
		},
		{
			id: 'author',
			type: 'integer',
			label: __( 'Person', 'pressshot' ),
			getValue: ( { item } ) => item.author.id,
			render: ( { item } ) => item.author.name,
			elements: authors.map( ( author ) => ( {
				value: author.id,
				label: author.name,
			} ) ),
			filterBy: { operators: [ 'is' ] },
			enableSorting: false,
		},
		{
			id: 'date',
			type: 'datetime',
			label: __( 'Date', 'pressshot' ),
			getValue: ( { item } ) => item.createdAt,
			filterBy: false,
		},
		{
			id: 'type',
			type: 'text',
			label: __( 'Type', 'pressshot' ),
			getValue: ( { item } ) => item.kind,
			elements: SHOT_TYPES,
			filterBy: false,
			enableSorting: false,
		},
		{
			id: 'folder',
			type: 'text',
			label: __( 'Folder', 'pressshot' ),
			getValue: ( { item } ) => folderNames.get( item.folderId ) || '',
			filterBy: false,
			enableSorting: false,
		},
		{
			id: 'tags',
			type: 'array',
			label: __( 'Tags', 'pressshot' ),
			getValue: ( { item } ) =>
				item.tagIds
					.map( ( id ) => tagNames.get( id ) )
					.filter( Boolean ),
			render: ( { item, field } ) =>
				field.getValue( { item } ).join( ', ' ),
			filterBy: false,
			enableSorting: false,
		},
	];
}

/**
 * A shot's media URL with query args added. The REST nonce lets the browser load
 * the media route with the signed-in person's cookie (WordPress ignores a REST cookie without it).
 *
 * @param {string} url  The shot's mediaUrl.
 * @param {Object} args Query args, e.g. { size: 'medium', _wpnonce: '...' }.
 * @return {string} The URL with the args appended.
 */
export function mediaSrc( url, args = {} ) {
	const parts = Object.keys( args )
		.filter( ( key ) => args[ key ] )
		.map(
			( key ) =>
				`${ encodeURIComponent( key ) }=${ encodeURIComponent( args[ key ] ) }`
		);
	if ( ! parts.length ) {
		return url;
	}
	return url + ( url.includes( '?' ) ? '&' : '?' ) + parts.join( '&' );
}
