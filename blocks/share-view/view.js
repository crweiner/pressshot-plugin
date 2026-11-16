import { store, getContext } from '@wordpress/interactivity';

store( 'pressshot/share-view', {
	actions: {
		*copy() {
			const context = getContext();
			try {
				yield navigator.clipboard.writeText( context.url );
			} catch ( error ) {
				// Clipboard access can be blocked; select the URL in a prompt as a fallback.
				window.prompt( '', context.url );
				return;
			}
			context.copied = true;
			setTimeout( () => {
				context.copied = false;
			}, 2000 );
		},
	},
} );
