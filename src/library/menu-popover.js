/**
 * The Library's menus. Every menu goes through LibraryMenu so they all open below their trigger
 * (the account menu, at the window's bottom, opens above) with a small gap: the trigger stays visible, and a second click on it closes the menu.
 */
import { DropdownMenu } from '@wordpress/components';

export const MENU_POPOVER_PROPS = { placement: 'bottom-end', offset: 8 };

export const MENU_POPOVER_PROPS_START = {
	placement: 'bottom-start',
	offset: 8,
};

// For a trigger at the bottom of the window (the sidebar's account button): opens above it.
export const MENU_POPOVER_PROPS_ABOVE = {
	placement: 'top-start',
	offset: 8,
};

/**
 * A DropdownMenu that opens below its trigger, or above it with `above`.
 *
 * @param {Object}          props
 * @param {'start' | 'end'} [props.align] Which edge of the trigger the menu lines up with.
 * @param {boolean}         [props.above] Open above the trigger, lined up with its start edge.
 * @param {Object}          props.rest    Everything else goes to DropdownMenu.
 */
export function LibraryMenu( { align = 'end', above = false, ...rest } ) {
	let popoverProps = MENU_POPOVER_PROPS;
	if ( above ) {
		popoverProps = MENU_POPOVER_PROPS_ABOVE;
	} else if ( align === 'start' ) {
		popoverProps = MENU_POPOVER_PROPS_START;
	}
	return <DropdownMenu { ...rest } popoverProps={ popoverProps } />;
}
