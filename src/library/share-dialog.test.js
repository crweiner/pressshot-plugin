jest.mock( '@wordpress/api-fetch', () => jest.fn( () => Promise.resolve( {} ) ) );
// The dialog renders components; these tests only check the note, and the components
// package pulls in ES modules Jest can't load.
jest.mock( '@wordpress/components', () => ( {} ) );

const { folderShareNote } = require( './share-dialog' );

describe( 'folderShareNote', () => {
	it( 'names the folder whose link also shares the shot', () => {
		expect(
			folderShareNote( { folderLinked: true, folderLinkName: 'Q&A' } )
		).toBe( 'This shot is also shared through the link for the folder “Q&A”.' );
	} );

	it( 'is empty when no folder link shares the shot', () => {
		expect(
			folderShareNote( { folderLinked: false, folderLinkName: null } )
		).toBe( '' );
		expect( folderShareNote( {} ) ).toBe( '' );
	} );
} );
