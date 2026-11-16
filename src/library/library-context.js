/**
 * What the Library's dialogs need from the app. DataViews renders an action's modal with only
 * the items and closeModal, so the rest comes from here.
 *
 * - folders, folderTree, tags: the loaded terms.
 * - nonce: the REST nonce for media URLs.
 * - canShareFolders: whether this person can make folder links (Editors and Admins).
 * - onChange( { message?, removedIds?, termsChanged? } ): something changed; refetch, show the
 *   message as a snackbar, close the viewer if its shot was removed, reload folders and tags (and
 *   their counts). Terms reload on every change; termsChanged is kept only as a hint.
 * - notify( message ): show a snackbar without refetching.
 */
import { createContext, useContext } from '@wordpress/element';

export const LibraryContext = createContext( {
	folders: [],
	folderTree: [],
	tags: [],
	nonce: '',
	canShareFolders: false,
	onChange: () => {},
	notify: () => {},
} );

export const useLibrary = () => useContext( LibraryContext );
