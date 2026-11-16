/**
 * @jest-environment jsdom
 */
const { answerPassRequests } = require( './videopress-pass' );

function setup( getPass = () => Promise.resolve( 'pass-123' ) ) {
	const sent = [];
	const frameWindow = { postMessage: ( message, origin ) => sent.push( { message, origin } ) };
	const frame = { contentWindow: frameWindow };
	const stop = answerPassRequests( { frame, guid: 'GuIdAaa1', getPass } );
	return { sent, frameWindow, stop };
}

function send( data, origin, source ) {
	const event = new MessageEvent( 'message', { data, origin } );
	Object.defineProperty( event, 'source', { value: source } );
	window.dispatchEvent( event );
}

const flush = () => new Promise( ( resolve ) => setTimeout( resolve, 0 ) );
const request = { event: 'videopress_token_request', guid: 'GuIdAaa1', requestId: 'r1' };

test( 'answers the player with an ack and the pass', async () => {
	const { sent, frameWindow, stop } = setup();
	send( request, 'https://videopress.com', frameWindow );
	await flush();
	expect( sent ).toEqual( [
		{ message: { event: 'videopress_token_request_ack', guid: 'GuIdAaa1', requestId: 'r1' }, origin: 'https://videopress.com' },
		{ message: { event: 'videopress_token_received', guid: 'GuIdAaa1', jwt: 'pass-123', requestId: 'r1' }, origin: 'https://videopress.com' },
	] );
	stop();
} );

test( 'ignores other origins, other frames, and other videos', async () => {
	const getPass = jest.fn( () => Promise.resolve( 'pass-123' ) );
	const { sent, frameWindow, stop } = setup( getPass );
	send( request, 'https://evil.example', frameWindow );
	send( request, 'https://videopress.com', { postMessage: () => {} } );
	send( { ...request, guid: 'OtHer123' }, 'https://videopress.com', frameWindow );
	send( { event: 'something_else' }, 'https://videopress.com', frameWindow );
	await flush();
	expect( sent ).toEqual( [] );
	expect( getPass ).not.toHaveBeenCalled();
	stop();
} );

test( 'reports an error when there is no pass', async () => {
	const { sent, frameWindow, stop } = setup( () => Promise.resolve( null ) );
	send( request, 'https://videopress.com', frameWindow );
	await flush();
	expect( sent.map( ( s ) => s.message.event ) ).toEqual( [ 'videopress_token_request_ack', 'videopress_token_error' ] );
	stop();
} );

test( 'stops answering once stopped', async () => {
	const { sent, frameWindow, stop } = setup();
	stop();
	send( request, 'https://videopress.com', frameWindow );
	await flush();
	expect( sent ).toEqual( [] );
} );
