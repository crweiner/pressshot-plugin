/**
 * @jest-environment jsdom
 */
import fs from 'fs';
import path from 'path';
import { act, fireEvent, render, screen } from '@testing-library/react';

// The real @wordpress/components: these tests check how the Library's menus actually behave.
jest.mock( '@wordpress/api-fetch', () => jest.fn() );

const { LibraryToolbar } = require( './toolbar' );
const { FolderMenu } = require( './folder-dialogs' );
const {
	MENU_POPOVER_PROPS,
	MENU_POPOVER_PROPS_START,
} = require( './menu-popover' );

const view = {
	type: 'grid',
	search: '',
	page: 1,
	perPage: 20,
	sort: { field: 'date', direction: 'desc' },
	fields: [],
};

function renderMenus() {
	return render(
		<>
			<LibraryToolbar
				view={ view }
				onChangeView={ () => {} }
				defaultLayouts={ { grid: {}, table: {} } }
				shots={ [] }
				total={ 0 }
				isLoading={ false }
				selection={ [] }
				onChangeSelection={ () => {} }
			/>
			<FolderMenu
				folder={ { id: 3, name: 'Clients', parent: 0 } }
				onDeleted={ () => {} }
				onOpenFolder={ () => {} }
			/>
		</>
	);
}

// Like a pointer click: focus moves to the trigger, then it's clicked.
async function press( element ) {
	await act( async () => {
		fireEvent.mouseDown( element );
		element.focus();
		fireEvent.mouseUp( element );
		fireEvent.click( element );
	} );
}

describe( 'menus open below their trigger', () => {
	it( 'uses a below placement with a gap', () => {
		for ( const props of [
			MENU_POPOVER_PROPS,
			MENU_POPOVER_PROPS_START,
		] ) {
			expect( [ 'bottom-start', 'bottom-end' ] ).toContain(
				props.placement
			);
			expect( props.offset ).toBeGreaterThanOrEqual( 4 );
		}
	} );

	// jsdom has no layout, so it can't show a menu covering its trigger (the bug in the browser,
	// checked there with Playwright). It does run the real open and close logic.
	it( 'opens each Library menu and closes it when the trigger is clicked again', async () => {
		renderMenus();
		for ( const label of [ 'Sort', 'Folder actions' ] ) {
			const trigger = screen.getByRole( 'button', { name: label } );
			await press( trigger );
			expect( trigger.getAttribute( 'aria-expanded' ) ).toBe( 'true' );
			expect( screen.getByRole( 'menu' ) ).toBeTruthy();

			await press( trigger );
			expect( trigger.getAttribute( 'aria-expanded' ) ).toBe( 'false' );
			expect( screen.queryByRole( 'menu' ) ).toBeNull();
		}
	} );

	it( 'every menu in the Library goes through LibraryMenu', () => {
		// Files under src/library, in every folder, except tests and the wrapper itself.
		const files = [];
		const walk = ( dir ) => {
			for ( const entry of fs.readdirSync( dir, {
				withFileTypes: true,
			} ) ) {
				const full = path.join( dir, entry.name );
				if ( entry.isDirectory() ) {
					walk( full );
				} else if (
					/\.[jt]sx?$/.test( entry.name ) &&
					! /\.test\.[jt]sx?$/.test( entry.name ) &&
					entry.name !== 'menu-popover.js'
				) {
					files.push( full );
				}
			}
		};
		walk( __dirname );
		expect( files.length ).toBeGreaterThan( 5 );

		const offenders = files.filter( ( file ) => {
			const source = fs.readFileSync( file, 'utf8' );
			// The names imported from @wordpress/components, however the import is laid out.
			const imported = [
				...source.matchAll(
					/import\s*\{([^}]*)\}\s*from\s*['"]@wordpress\/components['"]/g
				),
			]
				.flatMap( ( match ) => match[ 1 ].split( ',' ) )
				.map( ( name ) => name.trim().split( /\s+as\s+/ )[ 0 ] );
			return (
				imported.includes( 'DropdownMenu' ) ||
				imported.includes( 'Dropdown' ) ||
				/<\s*(DropdownMenu|Dropdown)[\s/>]/.test( source )
			);
		} );
		expect( offenders.map( ( file ) => path.basename( file ) ) ).toEqual(
			[]
		);
	} );
} );
