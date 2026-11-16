import { createRoot } from '@wordpress/element';
// A relative path keeps this stylesheet in the bundle (a package path would be treated as a WordPress script handle).
import '../../node_modules/@wordpress/dataviews/build-style/style.css';
import './style.scss';
import App from './app';
import { decodeName } from './fields';
import { readAppLayout } from './app-layout';

function readAuthors( raw ) {
	try {
		const list = JSON.parse( raw || '[]' );
		return Array.isArray( list ) ? list.map( decodeName ) : [];
	} catch {
		return [];
	}
}

const mount = document.getElementById( 'pressshot-library' );
if ( mount ) {
	const appLayout = readAppLayout( mount );
	// The block's inner blocks, rendered hidden right after the mount. The sidebar shows them
	// only in the app layout.
	const next = mount.nextElementSibling;
	const extras =
		next && next.classList.contains( 'pressshot-library__extras' )
			? next
			: null;
	createRoot( mount ).render(
		<App
			userId={ Number( mount.dataset.userId ) }
			avatarUrl={ mount.dataset.avatarUrl || '' }
			authors={ readAuthors( mount.dataset.authors ) }
			nonce={ mount.dataset.restNonce || '' }
			canShareFolders={ mount.dataset.canShareFolders === '1' }
			appLayout={ appLayout }
			extras={ extras }
		/>
	);
}
