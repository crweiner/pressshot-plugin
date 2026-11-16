/**
 * Hands a private VideoPress player its viewing pass. The player asks its parent page with a
 * `videopress_token_request` message; this answers only VideoPress's own frames, and only with the
 * pass the server put on that frame (the share page mints one only while its link is live).
 */
( function () {
	var ORIGINS = [ 'https://videopress.com', 'https://video.wordpress.com' ];
	window.addEventListener( 'message', function ( event ) {
		var data = event.data;
		if ( ! data || data.event !== 'videopress_token_request' || ! data.guid || ! data.requestId ) {
			return;
		}
		if ( ORIGINS.indexOf( event.origin ) === -1 || ! event.source ) {
			return;
		}
		var frames = document.querySelectorAll( 'iframe[data-pressshot-pass]' );
		for ( var i = 0; i < frames.length; i++ ) {
			var frame = frames[ i ];
			if ( frame.contentWindow !== event.source || frame.getAttribute( 'data-pressshot-guid' ) !== data.guid ) {
				continue;
			}
			event.source.postMessage( { event: 'videopress_token_request_ack', guid: data.guid, requestId: data.requestId }, event.origin );
			event.source.postMessage( { event: 'videopress_token_received', guid: data.guid, jwt: frame.getAttribute( 'data-pressshot-pass' ), requestId: data.requestId }, event.origin );
			return;
		}
	} );
} )();
