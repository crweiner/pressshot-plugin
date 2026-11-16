/**
 * Private VideoPress players ask their parent page for a viewing pass with a
 * `videopress_token_request` message. This answers requests from one player frame, for one video,
 * with a pass from `getPass` (the Library asks the site, which checks Library access).
 * Returns a function that stops listening.
 */
const ORIGINS = [ 'https://videopress.com', 'https://video.wordpress.com' ];

export function answerPassRequests( { frame, guid, getPass } ) {
	async function onMessage( event ) {
		const data = event.data;
		if ( ! data || data.event !== 'videopress_token_request' || data.guid !== guid || ! data.requestId ) {
			return;
		}
		if ( ! ORIGINS.includes( event.origin ) || ! frame || ! event.source || event.source !== frame.contentWindow ) {
			return;
		}
		const reply = ( message ) => event.source.postMessage( { guid, requestId: data.requestId, ...message }, event.origin );
		reply( { event: 'videopress_token_request_ack' } );
		let pass = null;
		try {
			pass = await getPass();
		} catch ( error ) {
			pass = null;
		}
		reply( pass ? { event: 'videopress_token_received', jwt: pass } : { event: 'videopress_token_error' } );
	}
	window.addEventListener( 'message', onMessage );
	return () => window.removeEventListener( 'message', onMessage );
}
