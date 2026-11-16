/**
 * The Library's app layout: the full-window version the block shows when its `appLayout`
 * setting is on, with the site's brand and the account in the sidebar, the block's inner
 * blocks (the sidebar extras) moved into the sidebar, and the sidebar button: it collapses the
 * sidebar on wide screens and opens it over the shots on narrow ones.
 */
import { useCallback, useEffect, useRef, useState } from '@wordpress/element';
import { decodeEntities } from '@wordpress/html-entities';
import { __ } from '@wordpress/i18n';
import { Button } from '@wordpress/components';
import { drawerLeft } from '@wordpress/icons';

const COLLAPSED_KEY = 'pressshot-sidebar-collapsed';
// Matches the stylesheet's narrow-screen breakpoint.
const NARROW_QUERY = '(max-width: 781px)';

/**
 * The app layout settings from the Library's mount element.
 *
 * @param {HTMLElement} mount The `#pressshot-library` element.
 * @return {{on: boolean, siteName: string, siteIcon: string, homeUrl: string, logoutUrl: string, userName: string, profileUrl: string}} Settings.
 */
export function readAppLayout( mount ) {
	const data = mount.dataset;
	return {
		on: data.appLayout === '1',
		siteName: decodeEntities( data.siteName || '' ),
		siteIcon: data.siteIcon || '',
		homeUrl: data.homeUrl || '',
		logoutUrl: data.logoutUrl || '',
		userName: decodeEntities( data.userName || '' ),
		profileUrl: data.profileUrl || '',
	};
}

/**
 * Moves the block's inner blocks (rendered hidden after the mount) into the sidebar's slot and
 * shows them. Does nothing without a slot, so with the app layout off they stay hidden in place.
 *
 * @param {{current: ?HTMLElement}} slotRef  The slot element.
 * @param {?HTMLElement}            extrasEl The `.pressshot-library__extras` element, or null.
 */
export function useMoveExtras( slotRef, extrasEl ) {
	useEffect( () => {
		const slot = slotRef.current;
		if ( ! slot || ! extrasEl ) {
			return;
		}
		slot.appendChild( extrasEl );
		extrasEl.hidden = false;
	}, [ slotRef, extrasEl ] );
}

/**
 * Open state for the sidebar on narrow screens, where it slides in over the shots.
 * Escape, the close button, and the backdrop dismiss it and return focus to the menu button.
 * Choosing a view in it closes it with `closeTo`, which moves focus to the given element (the
 * page heading), since the item that had focus is hidden with the sidebar.
 * Widening the window past the breakpoint closes it, since there it's always shown.
 *
 * @return {{isOpen: boolean, toggle: () => void, close: () => void, dismiss: () => void, closeTo: (targetRef: {current: ?HTMLElement}) => void, buttonRef: Object}} State.
 */
export function useSidebarToggle() {
	const [ isOpen, setIsOpen ] = useState( false );
	const buttonRef = useRef( null );
	const toggle = useCallback( () => setIsOpen( ( open ) => ! open ), [] );
	const close = useCallback( () => setIsOpen( false ), [] );
	const dismiss = useCallback( () => {
		setIsOpen( false );
		buttonRef.current?.focus();
	}, [] );
	// Only when the drawer is open: on wide screens the sidebar stays put, and so does focus.
	const closeTo = ( targetRef ) => {
		if ( ! isOpen ) {
			return;
		}
		setIsOpen( false );
		targetRef?.current?.focus();
	};

	useEffect( () => {
		if ( ! isOpen ) {
			return;
		}
		const onKeyDown = ( event ) => {
			// A dialog or menu inside the sidebar may have handled Escape already.
			if ( event.key === 'Escape' && ! event.defaultPrevented ) {
				dismiss();
			}
		};
		document.addEventListener( 'keydown', onKeyDown );
		const query = window.matchMedia
			? window.matchMedia( NARROW_QUERY )
			: null;
		const onChange = ( event ) => {
			if ( ! event.matches ) {
				close();
			}
		};
		query?.addEventListener( 'change', onChange );
		return () => {
			document.removeEventListener( 'keydown', onKeyDown );
			query?.removeEventListener( 'change', onChange );
		};
	}, [ isOpen, close, dismiss ] );

	return { isOpen, toggle, close, dismiss, closeTo, buttonRef };
}

/**
 * Whether the sidebar is collapsed on wide screens. Remembered in localStorage.
 *
 * @return {{isCollapsed: boolean, toggle: () => void}} State.
 */
export function useSidebarCollapse() {
	const [ isCollapsed, setIsCollapsed ] = useState( () => {
		try {
			return window.localStorage.getItem( COLLAPSED_KEY ) === '1';
		} catch {
			return false;
		}
	} );
	const toggle = useCallback(
		() =>
			setIsCollapsed( ( collapsed ) => {
				const next = ! collapsed;
				try {
					window.localStorage.setItem(
						COLLAPSED_KEY,
						next ? '1' : '0'
					);
				} catch {
					// Private windows can refuse storage; collapsing still works for this visit.
				}
				return next;
			} ),
		[]
	);
	return { isCollapsed, toggle };
}

/**
 * Whether the window is at the narrow-screen breakpoint.
 *
 * @return {boolean} True on narrow screens.
 */
export function useIsNarrow() {
	const getMatch = () =>
		typeof window !== 'undefined' && window.matchMedia
			? window.matchMedia( NARROW_QUERY ).matches
			: false;
	const [ isNarrow, setIsNarrow ] = useState( getMatch );
	useEffect( () => {
		if ( ! window.matchMedia ) {
			return;
		}
		const query = window.matchMedia( NARROW_QUERY );
		const onChange = () => setIsNarrow( query.matches );
		onChange();
		query.addEventListener( 'change', onChange );
		return () => query.removeEventListener( 'change', onChange );
	}, [] );
	return isNarrow;
}

/**
 * The sidebar button at the start of the top bar. On wide screens it collapses and expands the
 * sidebar. On narrow screens in the app layout, where the sidebar slides in over the shots, it
 * opens and closes that.
 *
 * @param {Object}  props
 * @param {Object}  props.toggle      What useSidebarToggle returns.
 * @param {Object}  props.collapse    What useSidebarCollapse returns.
 * @param {boolean} props.appLayoutOn Whether the app layout is on.
 */
export function SidebarButton( { toggle, collapse, appLayoutOn } ) {
	const isNarrow = useIsNarrow();
	const offCanvas = appLayoutOn && isNarrow;
	const isShown = offCanvas ? toggle.isOpen : ! collapse.isCollapsed;
	return (
		<Button
			ref={ offCanvas ? toggle.buttonRef : undefined }
			className="pressshot-library__sidebar-button"
			icon={ drawerLeft }
			label={
				isShown
					? __( 'Hide sidebar', 'pressshot' )
					: __( 'Show sidebar', 'pressshot' )
			}
			aria-expanded={ isShown }
			aria-controls="pressshot-sidebar"
			onClick={ offCanvas ? toggle.toggle : collapse.toggle }
			size="compact"
		/>
	);
}

/**
 * The PressShot mark, for sites without a site icon.
 *
 * @param {Object} props
 * @param {number} props.size Width and height in pixels.
 */
export function PressShotMark( { size = 24 } ) {
	return (
		<svg
			className="pressshot-mark"
			xmlns="http://www.w3.org/2000/svg"
			viewBox="0 0 20 20"
			width={ size }
			height={ size }
			fill="currentColor"
			aria-hidden="true"
			focusable="false"
		>
			<path d="M3.5 3.2 11.5 1.5Q14 1 14 3.5v.3L6.5 5.4Q3.5 6 3.5 9v7.3Q1 16.5 1 14V6.2q0-2.5 2.5-3Z" />
			<path
				fillRule="evenodd"
				d="m7.5 7 8.5-1.8q3-.6 3 2.3V15q0 2.5-2.5 3L8 19.3q-3 .6-3-2.3V10q0-2.5 2.5-3Zm6.4 1.3 2-.4q.8-.2.8.6v1.8q0 .7-.7.9l-2 .4q-.8.2-.8-.6V9.2q0-.7.7-.9Z"
			/>
		</svg>
	);
}
