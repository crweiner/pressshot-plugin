/**
 * Deleting shots: one shot asks once; several also make the person type DELETE.
 * Deleting moves shots to Trash, where they can be restored.
 */
import { useState } from '@wordpress/element';
import { __, _n, sprintf } from '@wordpress/i18n';
import { Button, Notice, TextControl } from '@wordpress/components';
import { trashShot } from './api';
import { useLibrary } from './library-context';

/**
 * Whether deleting this many shots needs the typed confirmation.
 *
 * @param {number} count How many shots are being deleted.
 * @return {boolean} True for more than one.
 */
export const needsTypedConfirm = ( count ) => count > 1;

/**
 * Whether the person typed the confirmation word: exactly DELETE, surrounding spaces ignored.
 *
 * @param {string} text What they typed.
 * @return {boolean} True when it matches.
 */
export const isTypedConfirm = ( text ) =>
	typeof text === 'string' && text.trim() === 'DELETE';

/**
 * The api-fetch error's message, or a fallback.
 *
 * @param {Object} error    The thrown error.
 * @param {string} fallback Message when the error has none.
 * @return {string} Message to show.
 */
export const errorMessage = ( error, fallback ) =>
	( error && error.message ) || fallback;

/**
 * The "Type DELETE to confirm" field.
 *
 * @param {Object}   props
 * @param {string}   props.value    Current text.
 * @param {Function} props.onChange Change handler.
 */
export function TypedConfirmField( { value, onChange } ) {
	return (
		<TextControl
			label={ __( 'Type DELETE to confirm', 'pressshot' ) }
			value={ value }
			onChange={ onChange }
			autoComplete="off"
			__next40pxDefaultSize
			__nextHasNoMarginBottom
		/>
	);
}

export const deleteModalHeader = ( items ) =>
	items.length > 1
		? sprintf(
				/* translators: %d: number of shots. */
				__( 'Delete %d shots?', 'pressshot' ),
				items.length
			)
		: __( 'Delete this shot?', 'pressshot' );

export default function DeleteShotsDialog( {
	items,
	closeModal,
	onActionPerformed,
} ) {
	const { onChange } = useLibrary();
	const [ typed, setTyped ] = useState( '' );
	const [ isBusy, setIsBusy ] = useState( false );
	const [ error, setError ] = useState( null );
	const typedNeeded = needsTypedConfirm( items.length );

	const remove = async () => {
		setIsBusy( true );
		setError( null );
		const removedIds = [];
		let failure = null;
		for ( const item of items ) {
			try {
				await trashShot( item.id );
				removedIds.push( item.id );
			} catch ( err ) {
				failure = err;
				break;
			}
		}
		setIsBusy( false );
		if ( removedIds.length ) {
			onChange( {
				message: failure ? '' : __( 'Moved to Trash', 'pressshot' ),
				removedIds,
			} );
			onActionPerformed?.(
				items.filter( ( item ) => removedIds.includes( item.id ) )
			);
		}
		if ( failure ) {
			const fallback = __(
				'The shot couldn’t be moved to Trash.',
				'pressshot'
			);
			setError(
				removedIds.length
					? sprintf(
							/* translators: 1: number of shots moved to Trash, 2: the error. */
							_n(
								'%1$d shot moved to Trash, then this happened: %2$s',
								'%1$d shots moved to Trash, then this happened: %2$s',
								removedIds.length,
								'pressshot'
							),
							removedIds.length,
							errorMessage( failure, fallback )
						)
					: errorMessage( failure, fallback )
			);
			return;
		}
		closeModal?.();
	};

	return (
		<div className="pressshot-dialog">
			<p>
				{ _n(
					'It goes to Trash, where you can restore it.',
					'They go to Trash, where you can restore them.',
					items.length,
					'pressshot'
				) }
			</p>
			{ error && (
				<Notice status="error" isDismissible={ false }>
					{ error }
				</Notice>
			) }
			{ typedNeeded && (
				<TypedConfirmField value={ typed } onChange={ setTyped } />
			) }
			<div className="pressshot-dialog__buttons">
				<Button
					variant="tertiary"
					onClick={ closeModal }
					disabled={ isBusy }
					__next40pxDefaultSize
				>
					{ __( 'Cancel', 'pressshot' ) }
				</Button>
				<Button
					variant="primary"
					isDestructive
					onClick={ remove }
					isBusy={ isBusy }
					disabled={
						isBusy || ( typedNeeded && ! isTypedConfirm( typed ) )
					}
					accessibleWhenDisabled
					__next40pxDefaultSize
				>
					{ __( 'Delete', 'pressshot' ) }
				</Button>
			</div>
		</div>
	);
}
