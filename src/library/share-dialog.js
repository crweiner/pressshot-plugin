/**
 * Share link dialog for a shot or a folder: create a link, set or clear its expiry, copy it,
 * and revoke it. Changes refresh the Library when the dialog closes.
 */
import { useEffect, useRef, useState } from '@wordpress/element';
import { __, sprintf } from '@wordpress/i18n';
import { Button, Notice, Spinner, TextControl } from '@wordpress/components';
import { date as siteDate, getDate } from '@wordpress/date';
import { getLink, revokeLink, setLink } from './api';
import { errorMessage } from './delete-dialog';
import { useLibrary } from './library-context';

/**
 * The last second of a calendar day in the site's timezone, as an ISO string.
 *
 * @param {string} day A YYYY-MM-DD date from a date field.
 * @return {string|null} ISO 8601 time, or null for no date.
 */
export function endOfDayIso( day ) {
	if ( ! /^\d{4}-\d{2}-\d{2}$/.test( day || '' ) ) {
		return null;
	}
	return getDate( `${ day }T23:59:59` ).toISOString();
}

/**
 * Copies text to the clipboard, falling back to a hidden textarea where the Clipboard API
 * isn't available (sites served over plain http).
 *
 * @param {string} text Text to copy.
 * @return {Promise<boolean>} Whether it was copied.
 */
export async function copyText( text ) {
	try {
		if ( window.navigator?.clipboard?.writeText ) {
			await window.navigator.clipboard.writeText( text );
			return true;
		}
	} catch {
		// Fall through to the older way.
	}
	const area = document.createElement( 'textarea' );
	area.value = text;
	area.setAttribute( 'readonly', '' );
	area.style.position = 'fixed';
	area.style.opacity = '0';
	document.body.appendChild( area );
	area.select();
	let copied = false;
	try {
		copied = document.execCommand( 'copy' );
	} catch {
		copied = false;
	}
	area.remove();
	return copied;
}

const linkDay = ( link ) =>
	link && link.expiresAt ? siteDate( 'Y-m-d', link.expiresAt ) : '';

/**
 * @param {Object}      props
 * @param {string}      props.kind        'shot' or 'folder'.
 * @param {number}      props.id          The shot or folder id.
 * @param {Object|null} props.initialLink The link the caller already knows, or undefined to load it.
 * @param {string}      props.note        What the dialog says about who can see the item.
 * @param {string}      props.alsoShared  Optional second note, e.g. that a folder link shares it too.
 * @param {Function}    props.onClose     Closes the dialog.
 */
export function ShareLinkPanel( {
	kind,
	id,
	initialLink,
	note,
	alsoShared,
	onClose,
} ) {
	const { onChange, notify } = useLibrary();
	const [ link, setLinkState ] = useState( initialLink ?? null );
	const [ isLoading, setIsLoading ] = useState( initialLink === undefined );
	const [ day, setDay ] = useState( linkDay( initialLink ) );
	const [ isBusy, setIsBusy ] = useState( false );
	const [ error, setError ] = useState( null );
	const changed = useRef( false );

	useEffect( () => {
		if ( initialLink !== undefined ) {
			return;
		}
		let current = true;
		getLink( kind, id )
			.then( ( response ) => {
				if ( current ) {
					setLinkState( response.link );
					setDay( linkDay( response.link ) );
				}
			} )
			.catch( ( err ) => current && setError( errorMessage( err, '' ) ) )
			.finally( () => current && setIsLoading( false ) );
		return () => {
			current = false;
		};
	}, [ kind, id, initialLink ] );

	// Refresh the Library once, when the dialog closes, so the list doesn't reload under it.
	useEffect(
		() => () => {
			if ( changed.current ) {
				onChange( {} );
			}
		},
		[ onChange ]
	);

	const run = async ( request, message ) => {
		setIsBusy( true );
		setError( null );
		try {
			const result = await request();
			changed.current = true;
			notify( message );
			return result;
		} catch ( err ) {
			setError(
				errorMessage(
					err,
					__( 'That didn’t work. Try again.', 'pressshot' )
				)
			);
			return undefined;
		} finally {
			setIsBusy( false );
		}
	};

	const save = async () => {
		const result = await run(
			() => setLink( kind, id, endOfDayIso( day ) ),
			link
				? __( 'Link updated', 'pressshot' )
				: __( 'Link created', 'pressshot' )
		);
		if ( result ) {
			setLinkState( result );
			setDay( linkDay( result ) );
		}
	};

	const revoke = async () => {
		const result = await run(
			() => revokeLink( kind, id ),
			__( 'Link revoked', 'pressshot' )
		);
		if ( result ) {
			setLinkState( null );
			setDay( '' );
		}
	};

	const copy = async () => {
		notify(
			( await copyText( link.url ) )
				? __( 'Link copied', 'pressshot' )
				: __(
						'Couldn’t copy the link. Select it and copy it yourself.',
						'pressshot'
					)
		);
	};

	if ( isLoading ) {
		return <Spinner />;
	}

	const today = siteDate( 'Y-m-d' );
	const expiryChanged = day !== linkDay( link );

	return (
		<div className="pressshot-dialog pressshot-share">
			<p>{ note }</p>
			{ alsoShared && (
				<Notice status="info" isDismissible={ false }>
					{ alsoShared }
				</Notice>
			) }
			{ error && (
				<Notice status="error" isDismissible={ false }>
					{ error }
				</Notice>
			) }
			{ link && (
				<div className="pressshot-share__link">
					<TextControl
						label={ __( 'Link', 'pressshot' ) }
						value={ link.url }
						readOnly
						onFocus={ ( event ) => event.target.select() }
						onChange={ () => {} }
						__next40pxDefaultSize
						__nextHasNoMarginBottom
					/>
					<Button
						variant="secondary"
						onClick={ copy }
						__next40pxDefaultSize
					>
						{ __( 'Copy', 'pressshot' ) }
					</Button>
				</div>
			) }
			<TextControl
				type="date"
				label={ __( 'Expires (optional)', 'pressshot' ) }
				help={ __(
					'The link stops working at the end of this day. Leave it empty to keep the link until you revoke it.',
					'pressshot'
				) }
				value={ day }
				min={ today }
				onChange={ setDay }
				__next40pxDefaultSize
				__nextHasNoMarginBottom
			/>
			<div className="pressshot-dialog__buttons pressshot-dialog__buttons--split">
				<div>
					{ link && (
						<Button
							variant="tertiary"
							isDestructive
							onClick={ revoke }
							disabled={ isBusy }
							accessibleWhenDisabled
							__next40pxDefaultSize
						>
							{ __( 'Revoke link', 'pressshot' ) }
						</Button>
					) }
				</div>
				<div className="pressshot-dialog__buttons">
					<Button
						variant="tertiary"
						onClick={ onClose }
						__next40pxDefaultSize
					>
						{ __( 'Done', 'pressshot' ) }
					</Button>
					{ ! link && (
						<Button
							variant="primary"
							onClick={ save }
							isBusy={ isBusy }
							disabled={ isBusy }
							accessibleWhenDisabled
							__next40pxDefaultSize
						>
							{ __( 'Create link', 'pressshot' ) }
						</Button>
					) }
					{ link && expiryChanged && (
						<Button
							variant="primary"
							onClick={ save }
							isBusy={ isBusy }
							disabled={ isBusy }
							accessibleWhenDisabled
							__next40pxDefaultSize
						>
							{ __( 'Save expiry', 'pressshot' ) }
						</Button>
					) }
				</div>
			</div>
		</div>
	);
}

/**
 * The note for a shot that a folder link also shares, so revoking the shot's own link doesn't
 * stop people seeing it. Empty when no folder link covers it.
 *
 * @param {Object} shot A decoded ShotDTO.
 * @return {string} The note, or ''.
 */
export function folderShareNote( shot ) {
	if ( ! shot?.folderLinked ) {
		return '';
	}
	return sprintf(
		/* translators: %s: folder name. */
		__(
			'This shot is also shared through the link for the folder “%s”.',
			'pressshot'
		),
		shot.folderLinkName || ''
	);
}

/**
 * The Share link action's modal for one shot.
 *
 * @param {Object}   props
 * @param {Object[]} props.items      The shot, as a one-item list.
 * @param {Function} props.closeModal Closes the modal.
 */
export default function ShareShotDialog( { items, closeModal } ) {
	const shot = items[ 0 ];
	return (
		<ShareLinkPanel
			kind="shot"
			id={ shot.id }
			initialLink={ shot.link }
			alsoShared={ folderShareNote( shot ) }
			note={ __(
				'Everyone on this site can already see this shot. A link lets anyone who has it see the shot without signing in.',
				'pressshot'
			) }
			onClose={ closeModal }
		/>
	);
}
