/**
 * @jest-environment jsdom
 */
import { act, fireEvent, render, screen } from '@testing-library/react';
import { image, video } from '@wordpress/icons';

// The real @wordpress/components, so the account menu opens like it does in the browser.
jest.mock( './folder-dialogs', () => ( { NewFolderButton: () => null } ) );
jest.mock( '@wordpress/api-fetch', () => jest.fn() );

const Sidebar = require( './sidebar' ).default;
const { scopeQuery, viewToQuery } = require( './api' );
const { shotFields } = require( './fields' );

const appLayout = {
	on: true,
	siteName: 'Studio',
	siteIcon: '',
	homeUrl: 'https://example.test/',
	logoutUrl: 'https://example.test/wp-login.php?action=logout',
	userName: 'Derek',
	profileUrl: 'https://example.test/wp-admin/profile.php',
};

const folderTree = [
	{
		id: 4,
		name: 'Client work',
		parent: 0,
		shotCount: 2,
		children: [
			{ id: 5, name: 'Drafts', parent: 4, shotCount: 0, children: [] },
		],
	},
];
const tags = [ { id: 9, name: 'bug', shotCount: 3 } ];

function renderSidebar( props = {} ) {
	return render(
		<Sidebar
			scope={ { kind: 'all' } }
			onSelectScope={ () => {} }
			folderTree={ folderTree }
			tags={ tags }
			isLoadingTerms={ false }
			isMine={ false }
			onToggleMine={ () => {} }
			avatarUrl="https://example.test/avatar.png"
			appLayout={ { ...appLayout, on: false } }
			{ ...props }
		/>
	);
}

const itemLabels = ( container ) =>
	[ ...container.querySelectorAll( '.pressshot-sidebar__label' ) ].map(
		( label ) => label.textContent
	);

// The icon's drawing (its path data), so it's the same as @wordpress/icons' own. The real
// Button adds a size to the svg, so the whole markup would differ.
const iconPath = ( icon ) =>
	render( icon ).container.querySelector( 'path' ).getAttribute( 'd' );

describe( 'Sidebar type views', () => {
	it( 'type views follow All shots', () => {
		const { container } = renderSidebar();
		expect( itemLabels( container ).slice( 0, 4 ) ).toEqual( [
			'All shots',
			'Screenshots',
			'Recordings',
			'My shots',
		] );
		const button = ( name ) => screen.getByText( name ).closest( 'button' );
		expect(
			button( 'Screenshots' ).querySelector( 'path' ).getAttribute( 'd' )
		).toBe( iconPath( image ) );
		expect(
			button( 'Recordings' ).querySelector( 'path' ).getAttribute( 'd' )
		).toBe( iconPath( video ) );
	} );

	it( 'selecting Screenshots sets the scope and the list query type', () => {
		const onSelectScope = jest.fn();
		renderSidebar( { onSelectScope } );
		fireEvent.click( screen.getByText( 'Screenshots' ) );
		const scope = { kind: 'type', id: 'screenshot' };
		expect( onSelectScope ).toHaveBeenCalledWith( scope );
		expect(
			viewToQuery( { page: 1, filters: [] }, scopeQuery( scope ) ).type
		).toBe( 'screenshot' );
		expect(
			viewToQuery(
				{ page: 1, filters: [] },
				scopeQuery( { kind: 'type', id: 'recording' } )
			).type
		).toBe( 'recording' );
	} );

	it( 'marks the current type view', () => {
		renderSidebar( { scope: { kind: 'type', id: 'recording' } } );
		expect(
			screen
				.getByText( 'Recordings' )
				.closest( 'button' )
				.getAttribute( 'aria-current' )
		).toBe( 'page' );
		expect(
			screen
				.getByText( 'Screenshots' )
				.closest( 'button' )
				.getAttribute( 'aria-current' )
		).toBeNull();
	} );
} );

describe( 'Sidebar counts', () => {
	it( 'folders and tags show counts', () => {
		renderSidebar();
		const count = ( name ) =>
			screen
				.getByText( name )
				.closest( 'button' )
				.querySelector( '.pressshot-sidebar__count' )?.textContent;
		expect( count( 'Client work' ) ).toBe( '2' );
		expect( count( 'Drafts' ) ).toBe( '0' );
		expect( count( 'bug' ) ).toBe( '3' );
		expect( count( 'All shots' ) ).toBeUndefined();
	} );
} );

describe( 'Sidebar Trash', () => {
	const trashList = () => screen.getByText( 'Trash' ).closest( 'ul' );

	it( 'Trash comes right after the lists in the default layout', () => {
		renderSidebar();
		const trash = trashList();
		expect( trash.previousElementSibling ).toBe(
			screen.getByText( 'bug' ).closest( 'ul' )
		);
		expect( trash.nextElementSibling ).toBeNull();
	} );

	it( 'Trash comes right after the lists in the app layout', () => {
		renderSidebar( { appLayout } );
		const trash = trashList();
		expect( trash.previousElementSibling ).toBe(
			screen.getByText( 'bug' ).closest( 'ul' )
		);
		expect( trash.nextElementSibling.className ).toContain(
			'pressshot-sidebar__account'
		);
	} );
} );

describe( 'Sidebar account row', () => {
	// Like a pointer click: focus moves to the trigger, then it's clicked.
	async function press( element ) {
		await act( async () => {
			fireEvent.mouseDown( element );
			element.focus();
			fireEvent.mouseUp( element );
			fireEvent.click( element );
		} );
	}

	it( 'account button opens a menu', async () => {
		const { container } = renderSidebar( { appLayout } );
		const row = container.querySelector( '.pressshot-sidebar__account' );
		const button = screen.getByRole( 'button', {
			name: 'Account menu: Derek',
		} );
		expect( row.contains( button ) ).toBe( true );
		expect(
			button.querySelector( 'img.pressshot-sidebar__avatar' )
		).toBeTruthy();
		expect(
			button.querySelector( '.pressshot-sidebar__user-name' ).textContent
		).toBe( 'Derek' );
		expect(
			button.querySelector( '.pressshot-sidebar__account-label' )
				.textContent
		).toBe( 'Account' );
		expect( button.querySelector( 'svg' ) ).toBeTruthy();
		expect( screen.queryByRole( 'menu' ) ).toBeNull();

		await press( button );
		expect( button.getAttribute( 'aria-expanded' ) ).toBe( 'true' );
		expect( screen.getByRole( 'menu' ) ).toBeTruthy();
		const items = screen.getAllByRole( 'menuitem' );
		expect( items.map( ( item ) => item.textContent ) ).toEqual( [
			'Edit profile',
			'Sign out',
		] );
		expect( items[ 0 ].getAttribute( 'href' ) ).toBe(
			appLayout.profileUrl
		);
		expect( items[ 1 ].getAttribute( 'href' ) ).toBe( appLayout.logoutUrl );
	} );

	it( 'no arrow sign-out button remains', () => {
		const { container } = renderSidebar( { appLayout } );
		expect(
			container.querySelector( '.pressshot-sidebar__sign-out' )
		).toBeNull();
		expect( screen.queryByRole( 'link', { name: 'Sign out' } ) ).toBeNull();
		expect(
			screen.queryByRole( 'button', { name: 'Sign out' } )
		).toBeNull();
	} );

	it( 'extras slot is at the right end of the account row', () => {
		const { container } = renderSidebar( { appLayout } );
		const row = container.querySelector( '.pressshot-sidebar__account' );
		expect( row.children ).toHaveLength( 2 );
		expect(
			row.children[ 0 ].querySelector(
				'.pressshot-sidebar__account-button'
			)
		).toBeTruthy();
		expect( row.lastElementChild.className ).toContain(
			'pressshot-sidebar__extras'
		);
	} );

	it( 'menu opens above the button', () => {
		const { MENU_POPOVER_PROPS_ABOVE } = require( './menu-popover' );
		expect( MENU_POPOVER_PROPS_ABOVE.placement ).toBe( 'top-start' );
		expect( MENU_POPOVER_PROPS_ABOVE.offset ).toBeGreaterThanOrEqual( 4 );
	} );

	it( 'has no account row in the default layout', () => {
		const { container } = renderSidebar();
		expect(
			container.querySelector( '.pressshot-sidebar__account' )
		).toBeNull();
		expect( screen.queryByRole( 'link', { name: 'Sign out' } ) ).toBeNull();
	} );
} );

describe( 'toolbar', () => {
	it( 'no Type filter in the toolbar', () => {
		const type = shotFields( [], [], [] ).find(
			( field ) => field.id === 'type'
		);
		expect( type?.filterBy ?? false ).toBe( false );
	} );
} );
