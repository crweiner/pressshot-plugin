/**
 * The shot viewer: a large modal with the image or video and the shot's details.
 * `children` renders under the details, in the side column, for action buttons.
 */
import { __, sprintf } from '@wordpress/i18n';
import { Modal } from '@wordpress/components';
import { useEffect, useRef } from '@wordpress/element';
import apiFetch from '@wordpress/api-fetch';
import { dateI18n, getSettings } from '@wordpress/date';
import { mediaSrc } from './fields';
import { answerPassRequests } from './videopress-pass';

/** A VideoPress player that gets its viewing pass from the site when the video is private. */
function VideoPressPlayer( { shot } ) {
	const frame = useRef();
	useEffect(
		() =>
			answerPassRequests( {
				frame: frame.current,
				guid: shot.videopressGuid,
				getPass: () =>
					apiFetch( { path: `/pressshot/v1/shots/${ shot.id }/videopress-pass` } ).then( ( r ) => r.pass ),
			} ),
		[ shot.id, shot.videopressGuid ]
	);
	return (
		<iframe
			ref={ frame }
			className="pressshot-viewer__frame"
			title={ shot.title || __( 'Recording', 'pressshot' ) }
			src={ `https://videopress.com/embed/${ encodeURIComponent( shot.videopressGuid ) }` }
			allow="fullscreen; picture-in-picture"
			allowFullScreen
		/>
	);
}

function ShotMedia( { shot, nonce } ) {
	if ( shot.videopressGuid ) {
		return <VideoPressPlayer shot={ shot } />;
	}
	if ( ! shot.mediaUrl ) {
		return (
			<p className="pressshot-viewer__missing">
				{ __( 'This shot’s file isn’t available.', 'pressshot' ) }
			</p>
		);
	}
	const src = mediaSrc( shot.mediaUrl, { _wpnonce: nonce } );
	if ( shot.kind === 'recording' ) {
		return (
			<video
				className="pressshot-viewer__video"
				controls
				preload="metadata"
				src={ src }
			/>
		);
	}
	return (
		<img
			className="pressshot-viewer__image"
			src={ src }
			alt={ shot.title }
		/>
	);
}

function linkState( link ) {
	if ( ! link ) {
		return __( 'Not shared', 'pressshot' );
	}
	if ( link.expiresAt ) {
		return sprintf(
			/* translators: %s: the date the share link stops working. */
			__( 'Shared with a link until %s', 'pressshot' ),
			dateI18n( getSettings().formats.date, link.expiresAt )
		);
	}
	return __( 'Shared with a link', 'pressshot' );
}

export default function Viewer( {
	shot,
	folderName,
	tagNames,
	nonce,
	onClose,
	children,
} ) {
	const none = __( 'None', 'pressshot' );
	return (
		<Modal
			className="pressshot-viewer"
			title={ shot.title || __( '(no title)', 'pressshot' ) }
			size="large"
			onRequestClose={ onClose }
		>
			<div className="pressshot-viewer__body">
				<div className="pressshot-viewer__stage">
					<ShotMedia shot={ shot } nonce={ nonce } />
				</div>
				<div className="pressshot-viewer__side">
					<dl className="pressshot-viewer__details">
						<dt>{ __( 'Uploaded by', 'pressshot' ) }</dt>
						<dd>{ shot.author.name }</dd>
						<dt>{ __( 'Date', 'pressshot' ) }</dt>
						<dd>
							{ dateI18n(
								getSettings().formats.datetime,
								shot.createdAt
							) }
						</dd>
						<dt>{ __( 'Folder', 'pressshot' ) }</dt>
						<dd>{ folderName || none }</dd>
						<dt>{ __( 'Tags', 'pressshot' ) }</dt>
						<dd>
							{ tagNames.length ? tagNames.join( ', ' ) : none }
						</dd>
						<dt>{ __( 'Link', 'pressshot' ) }</dt>
						<dd>{ linkState( shot.link ) }</dd>
					</dl>
					{ children }
				</div>
			</div>
		</Modal>
	);
}
