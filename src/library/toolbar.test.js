/**
 * @jest-environment jsdom
 */
import { act, fireEvent, render, screen } from '@testing-library/react';
import { useState } from '@wordpress/element';

// The components package pulls in ES modules Jest can't load; plain stand-ins are enough here.
jest.mock( '@wordpress/components', () => {
	const { useState: useMockState } = require( '@wordpress/element' );
	return {
		Button: ( {
			icon,
			children,
			label,
			text,
			isPressed,
			showTooltip,
			size,
			__next40pxDefaultSize,
			...props
		} ) => (
			<button
				aria-label={ label }
				aria-pressed={
					isPressed === undefined ? undefined : String( isPressed )
				}
				{ ...props }
			>
				{ text }
				{ children }
			</button>
		),
		SearchControl: ( {
			label,
			value,
			onChange,
			__nextHasNoMarginBottom,
			size,
			...props
		} ) => (
			<input
				type="search"
				aria-label={ label }
				value={ value }
				onChange={ ( event ) => onChange( event.target.value ) }
				{ ...props }
			/>
		),
		CheckboxControl: ( {
			label,
			checked,
			indeterminate,
			onChange,
			__nextHasNoMarginBottom,
			...props
		} ) => (
			<input
				type="checkbox"
				aria-label={ label }
				checked={ checked }
				data-indeterminate={ indeterminate ? 'true' : 'false' }
				onChange={ ( event ) => onChange( event.target.checked ) }
				{ ...props }
			/>
		),
		DropdownMenu: ( { label, text, children, popoverProps } ) => {
			const [ open, setOpen ] = useMockState( false );
			return (
				<div data-placement={ popoverProps?.placement }>
					<button
						aria-label={ label }
						aria-expanded={ open }
						onClick={ () => setOpen( ! open ) }
					>
						{ text }
					</button>
					{ open && children( { onClose: () => setOpen( false ) } ) }
				</div>
			);
		},
		MenuGroup: ( { children } ) => <div role="menu">{ children }</div>,
		MenuItemsChoice: ( { choices, value, onSelect } ) =>
			choices.map( ( choice ) => (
				<button
					key={ choice.value }
					role="menuitemradio"
					aria-checked={ choice.value === value }
					onClick={ () => onSelect( choice.value ) }
				>
					{ choice.label }
				</button>
			) ),
	};
} );

const { LibrarySearch, LibraryToolbar } = require( './toolbar' );
const { SidebarButton, useSidebarCollapse, useSidebarToggle } =
	require( './app-layout' );

const DEFAULT_LAYOUTS = {
	grid: { fields: [], descriptionField: 'byline' },
	table: { fields: [ 'author', 'date' ], descriptionField: undefined },
};

const baseView = {
	type: 'grid',
	search: '',
	page: 3,
	perPage: 20,
	sort: { field: 'date', direction: 'desc' },
	fields: [],
	descriptionField: 'byline',
};

function shotsList( count ) {
	return Array.from( { length: count }, ( _, i ) => ( { id: i + 1 } ) );
}

function ToolbarHarness( { initialView = baseView, total = 17, shots } ) {
	const [ view, setView ] = useState( initialView );
	const [ selection, setSelection ] = useState( [] );
	return (
		<>
			<LibraryToolbar
				view={ view }
				onChangeView={ setView }
				defaultLayouts={ DEFAULT_LAYOUTS }
				shots={ shots || shotsList( Math.min( total, 20 ) ) }
				total={ total }
				isLoading={ false }
				selection={ selection }
				onChangeSelection={ setSelection }
			/>
			<output data-testid="view">{ JSON.stringify( view ) }</output>
			<output data-testid="selection">
				{ JSON.stringify( selection ) }
			</output>
		</>
	);
}

const readView = () =>
	JSON.parse( screen.getByTestId( 'view' ).textContent );
const readSelection = () =>
	JSON.parse( screen.getByTestId( 'selection' ).textContent );

describe( 'top bar search', () => {
	beforeEach( () => jest.useFakeTimers() );
	afterEach( () => jest.useRealTimers() );

	it( 'search box at top right updates view.search', () => {
		function Harness() {
			const [ view, setView ] = useState( baseView );
			return (
				<>
					<LibrarySearch view={ view } onChangeView={ setView } />
					<output data-testid="view">
						{ JSON.stringify( view ) }
					</output>
				</>
			);
		}
		render( <Harness /> );
		const box = screen.getByLabelText( 'Search shots' );
		fireEvent.change( box, { target: { value: 'invoice' } } );
		expect( box.value ).toBe( 'invoice' );
		act( () => {
			jest.advanceTimersByTime( 400 );
		} );
		expect( readView().search ).toBe( 'invoice' );
		expect( readView().page ).toBe( 1 );
	} );
} );

describe( 'toolbar', () => {
	it( 'sort menu offers Newest first, Oldest first, Name A–Z', () => {
		render( <ToolbarHarness /> );
		const toggle = screen.getByRole( 'button', { name: 'Sort' } );
		expect( toggle.textContent ).toBe( 'Newest first' );

		fireEvent.click( toggle );
		const options = screen
			.getAllByRole( 'menuitemradio' )
			.map( ( item ) => item.textContent );
		expect( options ).toEqual( [
			'Newest first',
			'Oldest first',
			'Name A–Z',
		] );

		fireEvent.click(
			screen.getByRole( 'menuitemradio', { name: 'Oldest first' } )
		);
		expect( readView().sort ).toEqual( {
			field: 'date',
			direction: 'asc',
		} );
		expect( readView().page ).toBe( 1 );
		expect(
			screen.getByRole( 'button', { name: 'Sort' } ).textContent
		).toBe( 'Oldest first' );

		fireEvent.click( screen.getByRole( 'button', { name: 'Sort' } ) );
		fireEvent.click(
			screen.getByRole( 'menuitemradio', { name: 'Name A–Z' } )
		);
		expect( readView().sort ).toEqual( {
			field: 'title',
			direction: 'asc',
		} );

		fireEvent.click( screen.getByRole( 'button', { name: 'Sort' } ) );
		fireEvent.click(
			screen.getByRole( 'menuitemradio', { name: 'Newest first' } )
		);
		expect( readView().sort ).toEqual( {
			field: 'date',
			direction: 'desc',
		} );
	} );

	it( 'sort menu opens below its trigger', () => {
		const { container } = render( <ToolbarHarness /> );
		expect(
			container.querySelector( '[data-placement]' ).dataset.placement
		).toBe( 'bottom-end' );
	} );

	it( 'grid and table are two icon buttons', () => {
		render( <ToolbarHarness /> );
		const grid = screen.getByRole( 'button', { name: 'Grid' } );
		const table = screen.getByRole( 'button', { name: 'Table' } );
		expect( grid.getAttribute( 'aria-pressed' ) ).toBe( 'true' );
		expect( table.getAttribute( 'aria-pressed' ) ).toBe( 'false' );

		fireEvent.click( table );
		expect( readView().type ).toBe( 'table' );
		// Switching applies the layout's defaults, like DataViews' own switcher.
		expect( readView().fields ).toEqual( [ 'author', 'date' ] );
		expect(
			screen.getByRole( 'button', { name: 'Table' } ).getAttribute(
				'aria-pressed'
			)
		).toBe( 'true' );
		expect(
			screen.getByRole( 'button', { name: 'Grid' } ).getAttribute(
				'aria-pressed'
			)
		).toBe( 'false' );

		fireEvent.click( screen.getByRole( 'button', { name: 'Grid' } ) );
		expect( readView().type ).toBe( 'grid' );
		expect( readView().descriptionField ).toBe( 'byline' );
	} );

	it( 'shot count and Select all', () => {
		render( <ToolbarHarness total={ 17 } /> );
		expect( screen.getByText( '17 shots' ) ).toBeTruthy();

		const selectAll = screen.getByRole( 'checkbox', {
			name: 'Select all',
		} );
		expect( selectAll.checked ).toBe( false );
		fireEvent.click( selectAll );
		expect( readSelection() ).toHaveLength( 17 );
		expect( readSelection()[ 0 ] ).toBe( '1' );
		expect(
			screen.getByRole( 'checkbox', { name: 'Select all' } ).checked
		).toBe( true );

		fireEvent.click(
			screen.getByRole( 'checkbox', { name: 'Select all' } )
		);
		expect( readSelection() ).toEqual( [] );
	} );

	it( 'counts one shot in the singular', () => {
		render( <ToolbarHarness total={ 1 } /> );
		expect( screen.getByText( '1 shot' ) ).toBeTruthy();
	} );
} );

describe( 'collapse button', () => {
	function Harness( { appLayoutOn = true } ) {
		const toggle = useSidebarToggle();
		const collapse = useSidebarCollapse();
		return (
			<div
				className={
					collapse.isCollapsed ? 'is-sidebar-collapsed' : undefined
				}
				data-testid="library"
			>
				<SidebarButton
					toggle={ toggle }
					collapse={ collapse }
					appLayoutOn={ appLayoutOn }
				/>
			</div>
		);
	}

	beforeEach( () => window.localStorage.clear() );

	it( 'collapse button hides and shows the sidebar', () => {
		const { unmount } = render( <Harness /> );
		const library = screen.getByTestId( 'library' );
		const hide = screen.getByRole( 'button', { name: 'Hide sidebar' } );
		expect( hide.getAttribute( 'aria-expanded' ) ).toBe( 'true' );
		expect( library.classList.contains( 'is-sidebar-collapsed' ) ).toBe(
			false
		);

		fireEvent.click( hide );
		const show = screen.getByRole( 'button', { name: 'Show sidebar' } );
		expect( show.getAttribute( 'aria-expanded' ) ).toBe( 'false' );
		expect( library.classList.contains( 'is-sidebar-collapsed' ) ).toBe(
			true
		);
		expect(
			window.localStorage.getItem( 'pressshot-sidebar-collapsed' )
		).toBe( '1' );

		// Remembered on the next visit.
		unmount();
		render( <Harness /> );
		expect(
			screen.getByRole( 'button', { name: 'Show sidebar' } )
		).toBeTruthy();
		fireEvent.click(
			screen.getByRole( 'button', { name: 'Show sidebar' } )
		);
		expect(
			screen.getByRole( 'button', { name: 'Hide sidebar' } )
		).toBeTruthy();
		expect(
			window.localStorage.getItem( 'pressshot-sidebar-collapsed' )
		).toBe( '0' );
	} );

	it( 'collapses with the app layout off too', () => {
		render( <Harness appLayoutOn={ false } /> );
		fireEvent.click(
			screen.getByRole( 'button', { name: 'Hide sidebar' } )
		);
		expect(
			screen
				.getByTestId( 'library' )
				.classList.contains( 'is-sidebar-collapsed' )
		).toBe( true );
	} );
} );
