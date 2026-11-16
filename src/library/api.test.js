jest.mock( '@wordpress/api-fetch', () => jest.fn( () => Promise.resolve( {} ) ) );

const apiFetch = require( '@wordpress/api-fetch' );
const {
	viewToQuery,
	fetchShots,
	organizeShots,
	trashShot,
	restoreShot,
	getLink,
	setLink,
	revokeLink,
	deleteFolder,
} = require( './api' );

beforeEach( () => {
	apiFetch.mockClear();
	apiFetch.mockImplementation( () => Promise.resolve( { ok: true } ) );
} );

describe( 'viewToQuery', () => {
	const view = {
		search: 'x',
		page: 2,
		perPage: 40,
		sort: { field: 'date', direction: 'desc' },
		filters: [],
	};

	it( 'maps the view and folder scope to /shots params', () => {
		expect( viewToQuery( view, { folder: 5, type: 'recording' } ) ).toEqual( {
			search: 'x',
			page: 2,
			perPage: 40,
			orderby: 'date',
			order: 'desc',
			type: 'recording',
			folder: 5,
		} );
	} );

	it( 'adds status=trash for the trash scope', () => {
		expect( viewToQuery( view, { trash: true } ).status ).toBe( 'trash' );
	} );

	it( 'maps title sort to orderby title', () => {
		const query = viewToQuery(
			{ ...view, sort: { field: 'title', direction: 'asc' } },
			{}
		);
		expect( query.orderby ).toBe( 'title' );
		expect( query.order ).toBe( 'asc' );
	} );

	it( 'maps the author filter and the tag scope, and drops empty values', () => {
		const query = viewToQuery(
			{ page: 1, perPage: 20, search: '', filters: [ { field: 'author', operator: 'is', value: '7' } ] },
			{ tag: 3, folder: 0 }
		);
		expect( query ).toEqual( { page: 1, perPage: 20, author: 7, tag: 3, folder: 0 } );
	} );
} );

describe( 'client calls', () => {
	it( 'fetchShots GETs /shots with the query string', async () => {
		await fetchShots( { page: 2, search: 'a b', folder: 0 } );
		const args = apiFetch.mock.calls[ 0 ][ 0 ];
		expect( args.path ).toBe( '/pressshot/v1/shots?page=2&search=a%20b&folder=0' );
	} );

	it( 'organizeShots POSTs ids and the change', async () => {
		await organizeShots( [ 1, 2 ], { folder: 4, addTags: [ 9 ] } );
		expect( apiFetch ).toHaveBeenCalledWith( {
			path: '/pressshot/v1/shots/organize',
			method: 'POST',
			data: { ids: [ 1, 2 ], folder: 4, addTags: [ 9 ] },
		} );
	} );

	it( 'trashShot and restoreShot POST to the capture routes', async () => {
		await trashShot( 3 );
		await restoreShot( 3 );
		expect( apiFetch.mock.calls[ 0 ][ 0 ] ).toMatchObject( { path: '/pressshot/v1/captures/3/trash', method: 'POST' } );
		expect( apiFetch.mock.calls[ 1 ][ 0 ] ).toMatchObject( { path: '/pressshot/v1/captures/3/restore', method: 'POST' } );
	} );

	it( 'setLink and revokeLink use the shot and folder link routes', async () => {
		await setLink( 'shot', 3, '2027-01-01T00:00:00Z' );
		await setLink( 'folder', 8, null );
		await revokeLink( 'shot', 3 );
		await revokeLink( 'folder', 8 );
		expect( apiFetch.mock.calls[ 0 ][ 0 ] ).toEqual( { path: '/pressshot/v1/shots/3/link', method: 'POST', data: { expiresAt: '2027-01-01T00:00:00Z' } } );
		expect( apiFetch.mock.calls[ 1 ][ 0 ] ).toEqual( { path: '/pressshot/v1/folders/8/link', method: 'POST', data: { expiresAt: null } } );
		expect( apiFetch.mock.calls[ 2 ][ 0 ] ).toMatchObject( { path: '/pressshot/v1/shots/3/link', method: 'DELETE' } );
		expect( apiFetch.mock.calls[ 3 ][ 0 ] ).toMatchObject( { path: '/pressshot/v1/folders/8/link', method: 'DELETE' } );
	} );

	it( 'getLink reads the folder link route', async () => {
		await getLink( 'folder', 8 );
		expect( apiFetch ).toHaveBeenCalledWith( { path: '/pressshot/v1/folders/8/link' } );
	} );

	it( 'deleteFolder always sends confirm DELETE', async () => {
		await deleteFolder( 8, 'move', 2 );
		expect( apiFetch ).toHaveBeenCalledWith( {
			path: '/pressshot/v1/folders/8/delete',
			method: 'POST',
			data: { mode: 'move', target: 2, confirm: 'DELETE' },
		} );
	} );

	it( 'passes the api-fetch error through', async () => {
		const error = { code: 'rest_forbidden' };
		apiFetch.mockImplementation( () => Promise.reject( error ) );
		await expect( trashShot( 1 ) ).rejects.toBe( error );
	} );
} );
