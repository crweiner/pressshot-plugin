/**
 * The Library app: sidebar scope + DataViews view state, the shots request, the viewer, the
 * shot and folder actions, and snackbars.
 */
import apiFetch from '@wordpress/api-fetch';
import {
	useCallback,
	useEffect,
	useMemo,
	useRef,
	useState,
} from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import { Notice, SnackbarList } from '@wordpress/components';
import { ShotActionButtons, shotActions } from './actions';
import { fetchShots, scopeQuery, viewToQuery } from './api';
import { buildFolderTree, decodeName, decodeShot, defaultView } from './fields';
import { FolderMenu } from './folder-dialogs';
import {
	SidebarButton,
	useSidebarCollapse,
	useSidebarToggle,
} from './app-layout';
import { LibraryContext } from './library-context';
import Sidebar from './sidebar';
import ShotsView from './shots-view';
import { LibrarySearch } from './toolbar';
import Viewer from './viewer';

/**
 * Every term in a taxonomy, following X-WP-TotalPages, with its shotCount: the shots in it that
 * aren't in Trash (the plugin adds that field; core's count includes trashed media).
 *
 * @param {string} restBase The taxonomy's REST base, e.g. pressshot-folders.
 * @return {Promise<Array<{id: number, name: string, parent: number, shotCount: number}>>} All terms.
 */
async function fetchAllTerms( restBase ) {
	const terms = [];
	let page = 1;
	let totalPages = 1;
	do {
		const response = await apiFetch( {
			path: `/wp/v2/${ restBase }?per_page=100&page=${ page }&_fields=id,name,parent,shotCount`,
			parse: false,
		} );
		terms.push( ...( await response.json() ) );
		totalPages =
			parseInt( response.headers.get( 'X-WP-TotalPages' ), 10 ) || 1;
		page++;
	} while ( page <= totalPages );
	return terms.map( ( term ) =>
		decodeName( {
			id: term.id,
			name: term.name,
			parent: term.parent || 0,
			shotCount: Number( term.shotCount ) || 0,
		} )
	);
}

const isMineFilter = ( userId ) => ( filter ) =>
	filter.field === 'author' && Number( filter.value ) === userId;

function emptyMessage( scope, view ) {
	if ( view.search || ( view.filters || [] ).length ) {
		return __( 'No shots match.', 'pressshot' );
	}
	switch ( scope.kind ) {
		case 'type':
			return scope.id === 'recording'
				? __( 'No recordings yet', 'pressshot' )
				: __( 'No screenshots yet', 'pressshot' );
		case 'folder':
			return __( 'This folder is empty', 'pressshot' );
		case 'tag':
			return __( 'No shots with this tag', 'pressshot' );
		case 'trash':
			return __( 'Trash is empty', 'pressshot' );
		default:
			return __( 'No shots yet', 'pressshot' );
	}
}

export default function App( {
	userId,
	avatarUrl,
	authors,
	nonce,
	canShareFolders,
	appLayout = { on: false },
	extras = null,
} ) {
	const sidebar = useSidebarToggle();
	const headingRef = useRef( null );
	const collapse = useSidebarCollapse();
	const [ scope, setScope ] = useState( { kind: 'all' } );
	const [ view, setView ] = useState( defaultView );
	const [ selection, setSelection ] = useState( [] );
	// The open shot is kept here, not looked up in the current page, so it stays open while the
	// list refetches or after it leaves the page (moved to another folder, say).
	const [ openShot, setOpenShot ] = useState( null );
	const [ snackbars, setSnackbars ] = useState( [] );

	const [ shots, setShots ] = useState( [] );
	const [ total, setTotal ] = useState( 0 );
	const [ totalPages, setTotalPages ] = useState( 0 );
	const [ isLoading, setIsLoading ] = useState( true );
	const [ error, setError ] = useState( null );
	const [ attempt, setAttempt ] = useState( 0 );

	const [ folders, setFolders ] = useState( [] );
	const [ tags, setTags ] = useState( [] );
	const [ isLoadingTerms, setIsLoadingTerms ] = useState( true );
	const [ termsError, setTermsError ] = useState( null );
	const [ termsAttempt, setTermsAttempt ] = useState( 0 );

	useEffect( () => {
		let current = true;
		setIsLoadingTerms( true );
		setTermsError( null );
		Promise.all( [
			fetchAllTerms( 'pressshot-folders' ),
			fetchAllTerms( 'pressshot-tags' ),
		] )
			.then( ( [ folderList, tagList ] ) => {
				if ( current ) {
					setFolders( folderList );
					setTags(
						tagList.sort( ( a, b ) =>
							a.name.localeCompare( b.name )
						)
					);
				}
			} )
			.catch( ( err ) => current && setTermsError( err ) )
			.finally( () => current && setIsLoadingTerms( false ) );
		return () => {
			current = false;
		};
	}, [ termsAttempt ] );

	useEffect( () => {
		let current = true;
		setIsLoading( true );
		setError( null );
		fetchShots( viewToQuery( view, scopeQuery( scope ) ) )
			.then( ( response ) => {
				if ( current ) {
					const items = response.items.map( decodeShot );
					setShots( items );
					setTotal( response.total );
					setTotalPages( response.totalPages );
					// Keep the selection to shots still on this page, and refresh the open shot.
					setSelection( ( prev ) =>
						prev.filter( ( id ) =>
							items.some( ( item ) => String( item.id ) === id )
						)
					);
					setOpenShot(
						( prev ) =>
							( prev &&
								items.find(
									( item ) => item.id === prev.id
								) ) ||
							prev
					);
				}
			} )
			.catch( ( err ) => {
				if ( current ) {
					setShots( [] );
					setError( err );
				}
			} )
			.finally( () => current && setIsLoading( false ) );
		return () => {
			current = false;
		};
	}, [ view, scope, attempt ] );

	const folderTree = useMemo( () => buildFolderTree( folders ), [ folders ] );
	const isMine = ( view.filters || [] ).some( isMineFilter( userId ) );

	const selectScope = ( next ) => {
		sidebar.closeTo( headingRef );
		setScope( next );
		setSelection( [] );
		setView( ( prev ) => ( { ...prev, page: 1 } ) );
	};

	const toggleMine = () => {
		setView( ( prev ) => {
			const others = ( prev.filters || [] ).filter(
				( filter ) => filter.field !== 'author'
			);
			const filters = isMine
				? others
				: [
						...others,
						{ field: 'author', operator: 'is', value: userId },
					];
			return { ...prev, filters, page: 1 };
		} );
	};

	const closeViewer = () => setOpenShot( null );

	const notify = useCallback( ( message ) => {
		if ( ! message ) {
			return;
		}
		setSnackbars( ( prev ) => [
			...prev.slice( -2 ),
			{ id: `${ Date.now() }-${ Math.random() }`, content: message },
		] );
	}, [] );

	const onChange = useCallback(
		( { message, removedIds = [] } = {} ) => {
			notify( message );
			setAttempt( ( n ) => n + 1 );
			// Folder and tag counts change whenever shots move, get trashed, or come back, so the
			// terms reload after every change (callers' termsChanged hint is no longer needed).
			setTermsAttempt( ( n ) => n + 1 );
			if ( removedIds.length ) {
				setOpenShot( ( prev ) =>
					prev && removedIds.includes( prev.id ) ? null : prev
				);
			}
		},
		[ notify ]
	);

	const selectedShots = useMemo(
		() =>
			shots.filter( ( shot ) => selection.includes( String( shot.id ) ) ),
		[ shots, selection ]
	);
	const actions = useMemo(
		() => shotActions( { onChange, notify, nonce, selectedShots } ),
		[ onChange, notify, nonce, selectedShots ]
	);
	const viewerActions = useMemo(
		() => shotActions( { onChange, notify, nonce } ),
		[ onChange, notify, nonce ]
	);
	const context = useMemo(
		() => ( {
			folders,
			folderTree,
			tags,
			nonce,
			canShareFolders,
			onChange,
			notify,
		} ),
		[ folders, folderTree, tags, nonce, canShareFolders, onChange, notify ]
	);

	const folderName = ( id ) =>
		folders.find( ( folder ) => folder.id === id )?.name || '';
	const tagNames = ( ids ) =>
		ids
			.map( ( id ) => tags.find( ( item ) => item.id === id )?.name )
			.filter( Boolean );

	const openFolder =
		scope.kind === 'folder'
			? folders.find( ( folder ) => folder.id === scope.id )
			: null;
	let heading = __( 'All shots', 'pressshot' );
	if ( scope.kind === 'type' ) {
		heading =
			scope.id === 'recording'
				? __( 'Recordings', 'pressshot' )
				: __( 'Screenshots', 'pressshot' );
	} else if ( scope.kind === 'folder' ) {
		heading = openFolder?.name || __( 'Folder', 'pressshot' );
	} else if ( scope.kind === 'tag' ) {
		heading =
			tags.find( ( item ) => item.id === scope.id )?.name ||
			__( 'Tag', 'pressshot' );
	} else if ( scope.kind === 'trash' ) {
		heading = __( 'Trash', 'pressshot' );
	}

	return (
		<LibraryContext.Provider value={ context }>
			<div
				className={ `pressshot-library${
					collapse.isCollapsed ? ' is-sidebar-collapsed' : ''
				}` }
			>
				<Sidebar
					scope={ scope }
					onSelectScope={ selectScope }
					folderTree={ folderTree }
					tags={ tags }
					isLoadingTerms={ isLoadingTerms }
					isMine={ isMine }
					avatarUrl={ avatarUrl }
					onToggleMine={ toggleMine }
					appLayout={ appLayout }
					extras={ extras }
					isOpen={ appLayout.on && sidebar.isOpen }
					onClose={ sidebar.dismiss }
				/>
				<div
					className={ `pressshot-library__main${
						selection.length ? ' has-selection' : ''
					}` }
				>
					<div className="pressshot-library__header">
						<SidebarButton
							toggle={ sidebar }
							collapse={ collapse }
							appLayoutOn={ appLayout.on }
						/>
						<h1
							className="pressshot-library__heading"
							ref={ headingRef }
							tabIndex={ -1 }
						>
							{ heading }
						</h1>
						{ openFolder && (
							<FolderMenu
								folder={ openFolder }
								onDeleted={ () =>
									selectScope( { kind: 'all' } )
								}
								onOpenFolder={ ( id ) =>
									selectScope( { kind: 'folder', id } )
								}
							/>
						) }
						<LibrarySearch view={ view } onChangeView={ setView } />
					</div>
					{ termsError && (
						<Notice
							status="error"
							isDismissible={ false }
							actions={ [
								{
									label: __( 'Retry', 'pressshot' ),
									onClick: () =>
										setTermsAttempt( ( n ) => n + 1 ),
								},
							] }
						>
							{ __(
								'Folders and tags couldn’t be loaded.',
								'pressshot'
							) }
						</Notice>
					) }
					{ error && (
						<Notice
							status="error"
							isDismissible={ false }
							actions={ [
								{
									label: __( 'Retry', 'pressshot' ),
									onClick: () => setAttempt( ( n ) => n + 1 ),
								},
							] }
						>
							{ error.message ||
								__( 'Shots couldn’t be loaded.', 'pressshot' ) }
						</Notice>
					) }
					<ShotsView
						shots={ shots }
						total={ total }
						totalPages={ totalPages }
						isLoading={ isLoading }
						view={ view }
						onChangeView={ setView }
						authors={ authors }
						folders={ folders }
						tags={ tags }
						nonce={ nonce }
						emptyMessage={
							error ? '' : emptyMessage( scope, view )
						}
						actions={ actions }
						selection={ selection }
						onChangeSelection={ setSelection }
						onOpenShot={ setOpenShot }
					/>
				</div>
				{ openShot && (
					<Viewer
						shot={ openShot }
						folderName={ folderName( openShot.folderId ) }
						tagNames={ tagNames( openShot.tagIds ) }
						nonce={ nonce }
						onClose={ closeViewer }
					>
						<ShotActionButtons
							shot={ openShot }
							actions={ viewerActions }
						/>
					</Viewer>
				) }
				<SnackbarList
					className="pressshot-snackbars"
					notices={ snackbars }
					onRemove={ ( id ) =>
						setSnackbars( ( prev ) =>
							prev.filter( ( notice ) => notice.id !== id )
						)
					}
				/>
			</div>
		</LibraryContext.Provider>
	);
}
