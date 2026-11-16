/**
 * @jest-environment jsdom
 */

// The view renders DataViews and components, which pull in ES modules Jest can't load.
jest.mock( '@wordpress/components', () => ( { Icon: () => null } ) );
jest.mock( '@wordpress/dataviews', () => ( { DataViews: () => null } ) );
jest.mock( './toolbar', () => ( { LibraryToolbar: () => null } ) );

const { render, fireEvent } = require( '@testing-library/react' );
const { RecordingPreview } = require( './shots-view' );

const recording = { id: 7, kind: 'recording' };

describe( 'RecordingPreview', () => {
	it( 'shows the VideoPress still under the play badge', () => {
		const { container } = render(
			<RecordingPreview
				item={ { ...recording, posterUrl: 'https://site.test/wp-json/pressshot/v1/shots/7/poster' } }
				nonce="n1"
			/>
		);
		const poster = container.querySelector( '.pressshot-preview__poster' );
		expect( poster.getAttribute( 'src' ) ).toBe(
			'https://site.test/wp-json/pressshot/v1/shots/7/poster?_wpnonce=n1'
		);
		expect( container.querySelector( '.pressshot-play-badge' ) ).not.toBeNull();
	} );

	it( 'keeps the plain tile without a poster', () => {
		const { container } = render( <RecordingPreview item={ recording } nonce="n1" /> );
		expect( container.querySelector( 'img' ) ).toBeNull();
		expect( container.querySelector( '.pressshot-play-badge' ) ).not.toBeNull();
	} );

	it( 'falls back to the plain tile when the still fails to load', () => {
		const { container } = render(
			<RecordingPreview item={ { ...recording, posterUrl: '/poster' } } nonce="n1" />
		);
		fireEvent.error( container.querySelector( 'img' ) );
		expect( container.querySelector( 'img' ) ).toBeNull();
	} );
} );
