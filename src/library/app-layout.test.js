/**
 * @jest-environment jsdom
 */
import { act, fireEvent, render, screen } from '@testing-library/react';
import { useRef } from '@wordpress/element';

jest.mock( './folder-dialogs', () => ( { NewFolderButton: () => null } ) );

const {
	SidebarButton,
	readAppLayout,
	useSidebarCollapse,
	useSidebarToggle,
} = require( './app-layout' );
const Sidebar = require( './sidebar' ).default;

const appLayout = {
	on: true,
	siteName: 'Studio & Co',
	siteIcon: 'https://example.test/icon.png',
	homeUrl: 'https://example.test/',
	logoutUrl: 'https://example.test/wp-login.php?action=logout',
	userName: 'Derek',
	profileUrl: 'https://example.test/wp-admin/profile.php',
};

function renderSidebar( props = {} ) {
	return render(
		<Sidebar
			scope={ { kind: 'all' } }
			onSelectScope={ () => {} }
			folderTree={ [] }
			tags={ [] }
			isLoadingTerms={ false }
			isMine={ false }
			onToggleMine={ () => {} }
			avatarUrl="https://example.test/avatar.png"
			appLayout={ appLayout }
			{ ...props }
		/>
	);
}

describe( 'readAppLayout', () => {
	it( 'reads the mount', () => {
		const mount = document.createElement( 'div' );
		mount.dataset.appLayout = '1';
		mount.dataset.siteName = 'Studio &amp; Co';
		mount.dataset.siteIcon = 'https://example.test/icon.png';
		mount.dataset.homeUrl = 'https://example.test/';
		mount.dataset.logoutUrl = 'https://example.test/logout';
		mount.dataset.userName = 'Derek';
		mount.dataset.profileUrl = 'https://example.test/profile';
		expect( readAppLayout( mount ) ).toEqual( {
			on: true,
			siteName: 'Studio & Co',
			siteIcon: 'https://example.test/icon.png',
			homeUrl: 'https://example.test/',
			logoutUrl: 'https://example.test/logout',
			userName: 'Derek',
			profileUrl: 'https://example.test/profile',
		} );
	} );

	it( 'is off when data-app-layout is 0 or missing', () => {
		const off = document.createElement( 'div' );
		off.dataset.appLayout = '0';
		expect( readAppLayout( off ).on ).toBe( false );
		expect( readAppLayout( document.createElement( 'div' ) ).on ).toBe(
			false
		);
	} );
} );

describe( 'Sidebar', () => {
	it( 'shows brand and account in app layout', () => {
		const { container } = renderSidebar();
		const home = screen.getByRole( 'link', { name: 'Studio & Co' } );
		expect( home.getAttribute( 'href' ) ).toBe( appLayout.homeUrl );
		expect( home.querySelector( 'img' ).getAttribute( 'src' ) ).toBe(
			appLayout.siteIcon
		);
		expect( screen.getByText( 'Derek' ) ).toBeTruthy();
		expect(
			screen.getByRole( 'button', { name: 'Account menu: Derek' } )
		).toBeTruthy();
		expect(
			container.querySelector( '.pressshot-sidebar__extras' )
		).toBeTruthy();
	} );

	it( 'shows the PressShot mark when the site has no icon', () => {
		renderSidebar( { appLayout: { ...appLayout, siteIcon: '' } } );
		const home = screen.getByRole( 'link', { name: 'Studio & Co' } );
		expect( home.querySelector( 'img' ) ).toBeNull();
		expect( home.querySelector( 'svg.pressshot-mark' ) ).toBeTruthy();
	} );

	it( 'hides brand and account when off', () => {
		const { container } = renderSidebar( {
			appLayout: { ...appLayout, on: false },
		} );
		expect( screen.queryByRole( 'link' ) ).toBeNull();
		expect( screen.queryByText( 'Derek' ) ).toBeNull();
		expect( screen.queryByText( 'Account' ) ).toBeNull();
		expect(
			container.querySelector( '.pressshot-sidebar__extras' )
		).toBeNull();
	} );

	it( 'hides brand and account when no app layout is passed', () => {
		renderSidebar( { appLayout: undefined } );
		expect( screen.queryByRole( 'link' ) ).toBeNull();
	} );
} );

describe( 'sidebar extras', () => {
	function makeExtras() {
		const extras = document.createElement( 'div' );
		extras.className = 'pressshot-library__extras';
		extras.hidden = true;
		extras.innerHTML = '<p class="wp-block-paragraph">Theme toggle</p>';
		document.body.appendChild( extras );
		return extras;
	}

	it( 'move into the slot', () => {
		const extras = makeExtras();
		const { container } = renderSidebar( { extras } );
		const slot = container.querySelector( '.pressshot-sidebar__extras' );
		expect( extras.parentElement ).toBe( slot );
		expect( extras.hidden ).toBe( false );
	} );

	it( 'stay hidden where they are when the app layout is off', () => {
		const extras = makeExtras();
		renderSidebar( { appLayout: { ...appLayout, on: false }, extras } );
		expect( extras.parentElement ).toBe( document.body );
		expect( extras.hidden ).toBe( true );
	} );
} );

describe( 'narrow screen', () => {
	// A matchMedia stand-in whose width can change, telling its listeners.
	let narrow;
	let listeners;
	beforeEach( () => {
		narrow = true;
		listeners = new Set();
		window.matchMedia = jest.fn( () => ( {
			get matches() {
				return narrow;
			},
			addEventListener: ( type, listener ) => listeners.add( listener ),
			removeEventListener: ( type, listener ) =>
				listeners.delete( listener ),
		} ) );
	} );
	afterEach( () => {
		delete window.matchMedia;
	} );

	function setNarrow( value ) {
		narrow = value;
		act( () => {
			listeners.forEach( ( listener ) => listener( { matches: value } ) );
		} );
	}

	function Harness() {
		const toggle = useSidebarToggle();
		const collapse = useSidebarCollapse();
		const headingRef = useRef( null );
		return (
			<>
				<h1 ref={ headingRef } tabIndex={ -1 }>
					All shots
				</h1>
				<SidebarButton
					toggle={ toggle }
					collapse={ collapse }
					appLayoutOn
				/>
				<Sidebar
					scope={ { kind: 'all' } }
					onSelectScope={ () => toggle.closeTo( headingRef ) }
					folderTree={ [] }
					tags={ [] }
					isLoadingTerms={ false }
					isMine={ false }
					onToggleMine={ () => {} }
					avatarUrl=""
					appLayout={ appLayout }
					isOpen={ toggle.isOpen }
					onClose={ toggle.dismiss }
				/>
			</>
		);
	}

	function openSidebar() {
		const utils = render( <Harness /> );
		const button = screen.getByRole( 'button', { name: 'Show sidebar' } );
		fireEvent.click( button );
		const sidebar = utils.container.querySelector( '.pressshot-sidebar' );
		return { ...utils, button, sidebar };
	}

	it( 'sidebar button opens the sidebar over the shots', () => {
		const { container } = render( <Harness /> );
		const button = screen.getByRole( 'button', { name: 'Show sidebar' } );
		const sidebar = container.querySelector( '.pressshot-sidebar' );
		expect( button.getAttribute( 'aria-expanded' ) ).toBe( 'false' );
		expect( button.getAttribute( 'aria-controls' ) ).toBe( sidebar.id );
		expect( sidebar.classList.contains( 'is-open' ) ).toBe( false );

		fireEvent.click( button );
		expect( button.getAttribute( 'aria-expanded' ) ).toBe( 'true' );
		expect( button.getAttribute( 'aria-label' ) ).toBe( 'Hide sidebar' );
		// Collapsing is for wide screens; it's left alone here.
		expect(
			window.localStorage.getItem( 'pressshot-sidebar-collapsed' )
		).toBeNull();
		expect( sidebar.classList.contains( 'is-open' ) ).toBe( true );

		fireEvent.keyDown( document, { key: 'Escape' } );
		expect( button.getAttribute( 'aria-expanded' ) ).toBe( 'false' );
		expect( sidebar.classList.contains( 'is-open' ) ).toBe( false );
		expect( document.activeElement ).toBe( button );
	} );

	it( 'opening the narrow-screen sidebar moves focus to its first item', () => {
		openSidebar();
		expect( document.activeElement ).toBe(
			screen.getByRole( 'button', { name: 'All shots' } )
		);
	} );

	it( 'choosing a view in the drawer closes it and moves focus to the heading', () => {
		const { button, sidebar } = openSidebar();
		fireEvent.click( screen.getByRole( 'button', { name: 'All shots' } ) );
		expect( sidebar.classList.contains( 'is-open' ) ).toBe( false );
		expect( button.getAttribute( 'aria-expanded' ) ).toBe( 'false' );
		expect( document.activeElement ).toBe(
			screen.getByRole( 'heading', { level: 1 } )
		);
	} );

	it( 'choosing a view with the drawer closed leaves focus alone', () => {
		render( <Harness /> );
		const item = screen.getByRole( 'button', { name: 'All shots' } );
		item.focus();
		fireEvent.click( item );
		expect( document.activeElement ).toBe( item );
	} );

	it( 'Escape with defaultPrevented leaves the sidebar open', () => {
		const { button, sidebar } = openSidebar();
		const event = new KeyboardEvent( 'keydown', {
			key: 'Escape',
			bubbles: true,
			cancelable: true,
		} );
		event.preventDefault();
		act( () => {
			document.dispatchEvent( event );
		} );
		expect( sidebar.classList.contains( 'is-open' ) ).toBe( true );
		expect( button.getAttribute( 'aria-expanded' ) ).toBe( 'true' );
	} );

	it( 'widening past 782px closes the sidebar', () => {
		const { sidebar } = openSidebar();
		expect( sidebar.classList.contains( 'is-open' ) ).toBe( true );
		const whileOpen = listeners.size;
		setNarrow( false );
		expect( sidebar.classList.contains( 'is-open' ) ).toBe( false );
		// The open sidebar's width listener is detached once it closes.
		expect( listeners.size ).toBe( whileOpen - 1 );
	} );

	it( 'the drawer has a close button', () => {
		const { button, sidebar } = openSidebar();
		const close = screen.getByRole( 'button', { name: 'Close sidebar' } );
		// WordPress's own close icon.
		const { close: closeIcon } = require( '@wordpress/icons' );
		const { container: icon } = render( closeIcon );
		expect( close.querySelector( 'path' ).getAttribute( 'd' ) ).toBe(
			icon.querySelector( 'path' ).getAttribute( 'd' )
		);
		fireEvent.click( close );
		expect( sidebar.classList.contains( 'is-open' ) ).toBe( false );
		expect( document.activeElement ).toBe( button );
		expect(
			screen.queryByRole( 'button', { name: 'Close sidebar' } )
		).toBeNull();
	} );

	it( 'clicking the backdrop closes the drawer', () => {
		const { button, sidebar, container } = openSidebar();
		const backdrop = container.querySelector(
			'.pressshot-sidebar-backdrop'
		);
		expect( backdrop ).toBeTruthy();
		fireEvent.click( backdrop );
		expect( sidebar.classList.contains( 'is-open' ) ).toBe( false );
		expect( document.activeElement ).toBe( button );
		expect(
			container.querySelector( '.pressshot-sidebar-backdrop' )
		).toBeNull();
	} );
} );
