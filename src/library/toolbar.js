/**
 * The Library's top bar search and the toolbar over the shots: Select all and the shot count
 * on the left; the sort menu and the grid and table switch on the right. The toolbar takes extra
 * controls as children (DataViews' filter toggle and view options) and puts them at the end.
 */
import { useEffect, useRef, useState } from '@wordpress/element';
import { __, _n, sprintf } from '@wordpress/i18n';
import {
	Button,
	CheckboxControl,
	MenuGroup,
	MenuItemsChoice,
	SearchControl,
} from '@wordpress/components';
import { arrowDown, arrowUp, grid, list } from '@wordpress/icons';
import { LibraryMenu } from './menu-popover';

const SEARCH_DELAY = 300;

export const SORT_OPTIONS = [
	{
		value: 'newest',
		label: __( 'Newest first', 'pressshot' ),
		sort: { field: 'date', direction: 'desc' },
	},
	{
		value: 'oldest',
		label: __( 'Oldest first', 'pressshot' ),
		sort: { field: 'date', direction: 'asc' },
	},
	{
		value: 'name',
		label: __( 'Name A–Z', 'pressshot' ),
		sort: { field: 'title', direction: 'asc' },
	},
];

const LAYOUTS = [
	{ type: 'grid', label: __( 'Grid', 'pressshot' ), icon: grid },
	{ type: 'table', label: __( 'Table', 'pressshot' ), icon: list },
];

/**
 * The search box in the top bar. It updates the view a moment after typing stops, back on page 1.
 *
 * @param {Object}                                            props
 * @param {Object}                                            props.view         The DataViews view.
 * @param {(view: Object|((prev: Object) => Object)) => void} props.onChangeView Sets the view; takes an updater too.
 */
export function LibrarySearch( { view, onChangeView } ) {
	const committed = view.search || '';
	const [ value, setValue ] = useState( committed );
	const lastSent = useRef( committed );

	// Follow the view when something else changes the search, without undoing typing in progress.
	useEffect( () => {
		if ( committed !== lastSent.current ) {
			lastSent.current = committed;
			setValue( committed );
		}
	}, [ committed ] );

	useEffect( () => {
		if ( value === lastSent.current ) {
			return;
		}
		const timer = setTimeout( () => {
			lastSent.current = value;
			onChangeView( ( prev ) => ( { ...prev, search: value, page: 1 } ) );
		}, SEARCH_DELAY );
		return () => clearTimeout( timer );
	}, [ value, onChangeView ] );

	return (
		<SearchControl
			className="pressshot-library__search"
			label={ __( 'Search shots', 'pressshot' ) }
			placeholder={ __( 'Search shots', 'pressshot' ) }
			value={ value }
			onChange={ setValue }
			size="compact"
			__nextHasNoMarginBottom
		/>
	);
}

function SortMenu( { view, onChangeView } ) {
	const current = SORT_OPTIONS.find(
		( option ) =>
			option.sort.field === view.sort?.field &&
			option.sort.direction === view.sort?.direction
	);
	return (
		<LibraryMenu
			className="pressshot-toolbar__sort"
			icon={ view.sort?.direction === 'asc' ? arrowUp : arrowDown }
			label={ __( 'Sort', 'pressshot' ) }
			text={ current ? current.label : __( 'Sort', 'pressshot' ) }
			toggleProps={ { size: 'compact' } }
		>
			{ ( { onClose } ) => (
				<MenuGroup>
					<MenuItemsChoice
						choices={ SORT_OPTIONS.map( ( { value, label } ) => ( {
							value,
							label,
						} ) ) }
						value={ current?.value }
						onSelect={ ( value ) => {
							const option = SORT_OPTIONS.find(
								( item ) => item.value === value
							);
							onChangeView( {
								...view,
								sort: { ...option.sort },
								page: 1,
							} );
							onClose();
						} }
					/>
				</MenuGroup>
			) }
		</LibraryMenu>
	);
}

function LayoutSwitch( { view, onChangeView, defaultLayouts } ) {
	const switchTo = ( type ) => {
		if ( type === view.type ) {
			return;
		}
		// Like DataViews' own switcher: drop the old layout settings, apply the new defaults.
		const { layout, ...rest } = view;
		onChangeView( { ...rest, type, ...defaultLayouts[ type ] } );
	};
	return (
		<div
			className="pressshot-toolbar__layouts"
			role="group"
			aria-label={ __( 'Layout', 'pressshot' ) }
		>
			{ LAYOUTS.map( ( { type, label, icon } ) => (
				<Button
					key={ type }
					icon={ icon }
					label={ label }
					isPressed={ view.type === type }
					onClick={ () => switchTo( type ) }
					size="compact"
				/>
			) ) }
		</div>
	);
}

/**
 * The toolbar over the shots.
 *
 * @param {Object}                  props
 * @param {Object}                  props.view              The DataViews view.
 * @param {(view: Object) => void}  props.onChangeView      Sets the view.
 * @param {Object}                  props.defaultLayouts    Settings applied when switching layouts.
 * @param {Array}                   props.shots             The shots on this page.
 * @param {Array}                   [props.actions]         Shot actions; Select all takes shots with a bulk action.
 * @param {number}                  props.total             Shots in all pages.
 * @param {boolean}                 props.isLoading         Whether shots are loading.
 * @param {string[]}                props.selection         Selected shot ids.
 * @param {(ids: string[]) => void} props.onChangeSelection Sets the selection.
 * @param {Object}                  props.children          Extra controls for the end of the toolbar.
 */
export function LibraryToolbar( {
	view,
	onChangeView,
	defaultLayouts,
	shots,
	actions,
	total,
	isLoading,
	selection,
	onChangeSelection,
	children,
} ) {
	const selectable = actions
		? shots.filter( ( shot ) =>
				actions.some(
					( action ) =>
						action.supportsBulk &&
						( ! action.isEligible || action.isEligible( shot ) )
				)
			)
		: shots;
	const ids = selectable.map( ( shot ) => String( shot.id ) );
	const selectedOnPage = ids.filter( ( id ) => selection.includes( id ) );
	const allSelected = ids.length > 0 && selectedOnPage.length === ids.length;
	const someSelected = selectedOnPage.length > 0 && ! allSelected;
	// The table has its own Select all in its header row.
	const showSelectAll = view.type !== 'table' && ids.length > 0;
	const showCount = ! ( isLoading && ! total );

	return (
		<div className="pressshot-toolbar">
			<div className="pressshot-toolbar__start">
				{ showSelectAll && (
					<CheckboxControl
						className="pressshot-toolbar__select-all"
						aria-label={ __( 'Select all', 'pressshot' ) }
						checked={ allSelected }
						indeterminate={ someSelected }
						onChange={ () =>
							onChangeSelection( allSelected ? [] : ids )
						}
						__nextHasNoMarginBottom
					/>
				) }
				{ showCount && (
					<span className="pressshot-toolbar__count">
						{ sprintf(
							/* translators: %d: number of shots. */
							_n( '%d shot', '%d shots', total, 'pressshot' ),
							total
						) }
					</span>
				) }
			</div>
			<div className="pressshot-toolbar__end">
				<SortMenu view={ view } onChangeView={ onChangeView } />
				<LayoutSwitch
					view={ view }
					onChangeView={ onChangeView }
					defaultLayouts={ defaultLayouts }
				/>
				{ children }
			</div>
		</div>
	);
}
