/**
 * @jest-environment jsdom
 */
// jsdom: decodeEntities() decodes through a textarea element.
const {
	buildFolderTree,
	shotFields,
	defaultView,
	mediaSrc,
	shotByline,
	decodeName,
	decodeShot,
} = require( './fields' );

describe( 'buildFolderTree', () => {
	it( 'nests three levels and sorts siblings by name', () => {
		const tree = buildFolderTree( [
			{ id: 3, name: 'Zebra', parent: 0 },
			{ id: 1, name: 'Apple', parent: 0 },
			{ id: 5, name: 'Mockups', parent: 1 },
			{ id: 4, name: 'Docs', parent: 1 },
			{ id: 6, name: 'Final', parent: 5 },
		] );

		expect( tree.map( ( node ) => node.name ) ).toEqual( [
			'Apple',
			'Zebra',
		] );
		expect( tree[ 0 ].children.map( ( node ) => node.name ) ).toEqual( [
			'Docs',
			'Mockups',
		] );
		expect(
			tree[ 0 ].children[ 1 ].children.map( ( node ) => node.id )
		).toEqual( [ 6 ] );
		expect( tree[ 0 ].children[ 1 ].children[ 0 ].children ).toEqual( [] );
		expect( tree[ 1 ].children ).toEqual( [] );
	} );

	it( 'lifts a folder whose parent is missing to the top level', () => {
		const tree = buildFolderTree( [
			{ id: 2, name: 'Beta', parent: 0 },
			{ id: 7, name: 'Alpha', parent: 99 },
		] );

		expect( tree.map( ( node ) => node.id ) ).toEqual( [ 7, 2 ] );
	} );
} );

describe( 'shotFields', () => {
	const authors = [ { id: 1, name: 'Ann' } ];
	const folders = [ { id: 4, name: 'Docs', parent: 0 } ];
	const tags = [ { id: 9, name: 'bug' } ];
	const fields = shotFields( authors, folders, tags );
	const byId = ( id ) => fields.find( ( field ) => field.id === id );

	it( 'has the Library fields', () => {
		[ 'title', 'author', 'date', 'type', 'folder', 'tags' ].forEach(
			( id ) => {
				expect( byId( id ) ).toBeDefined();
			}
		);
	} );

	it( 'makes only the person filterable', () => {
		expect( byId( 'author' ).filterBy ).toBeTruthy();
		expect( byId( 'author' ).elements ).toEqual( [
			{ value: 1, label: 'Ann' },
		] );
		expect(
			byId( 'type' ).elements.map( ( element ) => element.value )
		).toEqual( [ 'screenshot', 'recording' ] );
		[ 'title', 'date', 'type', 'folder', 'tags' ].forEach( ( id ) => {
			expect( byId( id ).filterBy ).toBe( false );
		} );
	} );

	it( 'makes date sortable and the person, type, folder, and tags not', () => {
		expect( byId( 'date' ).enableSorting ).not.toBe( false );
		[ 'author', 'type', 'folder', 'tags' ].forEach( ( id ) => {
			expect( byId( id ).enableSorting ).toBe( false );
		} );
	} );

	it( 'reads names for folder and tags from the lists', () => {
		const shot = {
			folderId: 4,
			tagIds: [ 9, 12 ],
			author: { id: 1, name: 'Ann' },
			kind: 'recording',
		};
		expect( byId( 'folder' ).getValue( { item: shot } ) ).toBe( 'Docs' );
		expect( byId( 'tags' ).getValue( { item: shot } ) ).toEqual( [
			'bug',
		] );
		expect( byId( 'author' ).getValue( { item: shot } ) ).toBe( 1 );
		expect( byId( 'type' ).getValue( { item: shot } ) ).toBe( 'recording' );
	} );
} );

describe( 'defaultView', () => {
	it( 'starts on a 40-per-page grid, newest first', () => {
		expect( defaultView ).toMatchObject( {
			type: 'grid',
			perPage: 40,
			sort: { field: 'date', direction: 'desc' },
		} );
	} );

	it( 'shows only the title and a one-line byline on grid cards', () => {
		expect( defaultView ).toMatchObject( {
			titleField: 'title',
			mediaField: 'preview',
			descriptionField: 'byline',
			fields: [],
		} );
	} );
} );

describe( 'shotByline', () => {
	const shot = {
		author: { id: 1, name: 'Avery Author' },
		createdAt: '2026-11-14T10:00:00Z',
	};

	it( 'reads "{name} · {month day}" for this year', () => {
		expect( shotByline( shot, new Date( '2026-12-01T00:00:00Z' ) ) ).toBe(
			'Avery Author · Nov 14'
		);
	} );

	it( 'adds the year for an older shot', () => {
		expect( shotByline( shot, new Date( '2027-02-01T00:00:00Z' ) ) ).toBe(
			'Avery Author · Nov 14, 2026'
		);
	} );
} );

describe( 'mediaSrc', () => {
	it( 'adds args with ? or & as the URL needs', () => {
		expect(
			mediaSrc( 'http://x/wp-json/pressshot/v1/shots/3/media', {
				size: 'medium',
				_wpnonce: 'abc',
			} )
		).toBe(
			'http://x/wp-json/pressshot/v1/shots/3/media?size=medium&_wpnonce=abc'
		);
		expect(
			mediaSrc( 'http://x/?rest_route=/pressshot/v1/shots/3/media', {
				size: 'medium',
			} )
		).toBe(
			'http://x/?rest_route=/pressshot/v1/shots/3/media&size=medium'
		);
		expect( mediaSrc( 'http://x/m', { _wpnonce: '' } ) ).toBe(
			'http://x/m'
		);
	} );
} );

describe( 'decoding names and titles', () => {
	it( 'decodes a folder, tag, or person name', () => {
		expect( decodeName( { id: 1, name: 'Q&amp;A', parent: 0 } ) ).toEqual( {
			id: 1,
			name: 'Q&A',
			parent: 0,
		} );
		expect( decodeName( { id: 2, name: 'Tom &#8217;n&#8217; Jerry' } ).name ).toBe(
			'Tom ’n’ Jerry'
		);
	} );

	it( 'decodes a shot title, its uploader, and its sharing folder', () => {
		const shot = decodeShot( {
			id: 7,
			title: 'Pricing &amp; plans',
			author: { id: 3, name: 'Ann &lt;Dev&gt;' },
			folderLinked: true,
			folderLinkName: 'Q&amp;A',
			link: null,
		} );
		expect( shot.title ).toBe( 'Pricing & plans' );
		expect( shot.author ).toEqual( { id: 3, name: 'Ann <Dev>' } );
		expect( shot.folderLinkName ).toBe( 'Q&A' );
		expect( shot.link ).toBeNull();
	} );

	it( 'leaves plain text and a missing folder name alone', () => {
		const shot = decodeShot( {
			title: 'Plain',
			author: { id: 1, name: 'Avery' },
			folderLinked: false,
			folderLinkName: null,
		} );
		expect( shot.title ).toBe( 'Plain' );
		expect( shot.folderLinkName ).toBeNull();
	} );
} );
