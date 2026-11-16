/**
 * Library sidebar: All shots, Screenshots, Recordings, My shots, the folder tree (with New folder),
 * tags, then Trash. Folder and tag rows show how many shots (not in Trash) they hold. In the app
 * layout it also has the site's brand at the top and one account row at the bottom: avatar, name,
 * an account button (avatar, name, "Account", chevron) that opens a menu above it with Edit profile
 * and Sign out, and the block's extras (a light and dark toggle, say) at the right end. Open over the
 * shots on narrow screens, it has a close button and a backdrop that closes it when clicked.
 */
import { useEffect, useRef } from '@wordpress/element';
import { __, sprintf } from '@wordpress/i18n';
import { Button, MenuGroup, MenuItem, Spinner } from '@wordpress/components';
import {
	chevronUp,
	close as closeIcon,
	image,
	postAuthor,
	tag as tagIcon,
	trash,
	video,
} from '@wordpress/icons';
import { allShots, folder as folderIcon } from './icons';
import { NewFolderButton } from './folder-dialogs';
import { PressShotMark, useMoveExtras } from './app-layout';
import { LibraryMenu } from './menu-popover';

// Scopes are { kind: 'all' | 'type' | 'folder' | 'tag' | 'trash', id? }; api.js's scopeQuery()
// maps them to viewToQuery's scope. A type scope's id is 'screenshot' or 'recording'.
export const isSameScope = ( a, b ) => a.kind === b.kind && a.id === b.id;

function NavItem( {
	icon,
	label,
	count,
	isCurrent,
	isPressed,
	onClick,
	depth = 0,
} ) {
	return (
		<li>
			<Button
				className="pressshot-sidebar__item"
				icon={ icon }
				onClick={ onClick }
				aria-current={ isCurrent ? 'page' : undefined }
				aria-pressed={ isPressed }
				style={
					depth
						? { paddingInlineStart: `${ 8 + depth * 16 }px` }
						: undefined
				}
				__next40pxDefaultSize
			>
				<span className="pressshot-sidebar__label">{ label }</span>
				{ typeof count === 'number' && (
					<span className="pressshot-sidebar__count">{ count }</span>
				) }
			</Button>
		</li>
	);
}

function FolderBranch( { nodes, depth, scope, onSelectScope } ) {
	return nodes.map( ( node ) => (
		<FolderNode
			key={ node.id }
			node={ node }
			depth={ depth }
			scope={ scope }
			onSelectScope={ onSelectScope }
		/>
	) );
}

function FolderNode( { node, depth, scope, onSelectScope } ) {
	const own = { kind: 'folder', id: node.id };
	return (
		<>
			<NavItem
				icon={ folderIcon }
				label={ node.name }
				count={ node.shotCount }
				depth={ depth }
				isCurrent={ isSameScope( scope, own ) }
				onClick={ () => onSelectScope( own ) }
			/>
			{ node.children.length > 0 && (
				<FolderBranch
					nodes={ node.children }
					depth={ depth + 1 }
					scope={ scope }
					onSelectScope={ onSelectScope }
				/>
			) }
		</>
	);
}

function Brand( { siteName, siteIcon, homeUrl } ) {
	return (
		<a className="pressshot-sidebar__brand" href={ homeUrl }>
			{ siteIcon ? (
				<img
					className="pressshot-sidebar__site-icon"
					src={ siteIcon }
					alt=""
					width={ 24 }
					height={ 24 }
				/>
			) : (
				<PressShotMark />
			) }
			<span className="pressshot-sidebar__site-name">{ siteName }</span>
		</a>
	);
}

function Account( { avatarUrl, userName, logoutUrl, profileUrl, extrasRef } ) {
	return (
		<div className="pressshot-sidebar__account">
			<LibraryMenu
				above
				icon={ chevronUp }
				label={ sprintf(
					/* translators: %s: the signed-in person's name. */
					__( 'Account menu: %s', 'pressshot' ),
					userName
				) }
				toggleProps={ {
					className: 'pressshot-sidebar__account-button',
					iconPosition: 'right',
					showTooltip: false,
					children: (
						<>
							{ avatarUrl && (
								<img
									className="pressshot-sidebar__avatar"
									src={ avatarUrl }
									alt=""
									width={ 24 }
									height={ 24 }
								/>
							) }
							<span className="pressshot-sidebar__account-text">
								<span className="pressshot-sidebar__user-name">
									{ userName }
								</span>
								<span className="pressshot-sidebar__account-label">
									{ __( 'Account', 'pressshot' ) }
								</span>
							</span>
						</>
					),
				} }
			>
				{ () => (
					<MenuGroup>
						{ profileUrl && (
							<MenuItem href={ profileUrl }>
								{ __( 'Edit profile', 'pressshot' ) }
							</MenuItem>
						) }
						<MenuItem href={ logoutUrl }>
							{ __( 'Sign out', 'pressshot' ) }
						</MenuItem>
					</MenuGroup>
				) }
			</LibraryMenu>
			<div className="pressshot-sidebar__extras" ref={ extrasRef } />
		</div>
	);
}

const TYPE_VIEWS = [
	{ id: 'screenshot', icon: image, label: __( 'Screenshots', 'pressshot' ) },
	{ id: 'recording', icon: video, label: __( 'Recordings', 'pressshot' ) },
];

export default function Sidebar( {
	scope,
	onSelectScope,
	folderTree,
	tags,
	isLoadingTerms,
	isMine,
	onToggleMine,
	avatarUrl,
	appLayout,
	extras = null,
	isOpen = false,
	onClose,
} ) {
	const isApp = !! appLayout?.on;
	const extrasRef = useRef( null );
	const navRef = useRef( null );
	useMoveExtras( extrasRef, isApp ? extras : null );

	// Opening the sidebar over the shots moves focus into it, to its first item.
	useEffect( () => {
		if ( isOpen ) {
			navRef.current
				?.querySelector( '.pressshot-sidebar__item' )
				?.focus();
		}
	}, [ isOpen ] );

	return (
		<>
			<nav
				id="pressshot-sidebar"
				ref={ navRef }
				className={ `pressshot-sidebar${ isOpen ? ' is-open' : '' }` }
				aria-label={ __( 'Library', 'pressshot' ) }
			>
				{ isOpen && onClose && (
					<Button
						className="pressshot-sidebar__close"
						icon={ closeIcon }
						label={ __( 'Close sidebar', 'pressshot' ) }
						onClick={ onClose }
						size="compact"
					/>
				) }
				{ isApp && <Brand { ...appLayout } /> }

				<ul className="pressshot-sidebar__list">
					<NavItem
						icon={ allShots }
						label={ __( 'All shots', 'pressshot' ) }
						isCurrent={ scope.kind === 'all' }
						onClick={ () => onSelectScope( { kind: 'all' } ) }
					/>
					{ TYPE_VIEWS.map( ( type ) => (
						<NavItem
							key={ type.id }
							icon={ type.icon }
							label={ type.label }
							isCurrent={ isSameScope( scope, {
								kind: 'type',
								id: type.id,
							} ) }
							onClick={ () =>
								onSelectScope( { kind: 'type', id: type.id } )
							}
						/>
					) ) }
					<NavItem
						icon={
							avatarUrl ? (
								<img
									className="pressshot-sidebar__avatar"
									src={ avatarUrl }
									alt=""
									width={ 24 }
									height={ 24 }
								/>
							) : (
								postAuthor
							)
						}
						label={ __( 'My shots', 'pressshot' ) }
						isPressed={ isMine }
						onClick={ onToggleMine }
					/>
				</ul>

				{ isLoadingTerms && folderTree.length === 0 && <Spinner /> }

				{ /* Stays up while folders reload after a change, so the list doesn't flicker. */ }
				{ ( ! isLoadingTerms || folderTree.length > 0 ) && (
					<>
						<div className="pressshot-sidebar__heading-row">
							<h2 className="pressshot-sidebar__heading">
								{ __( 'Folders', 'pressshot' ) }
							</h2>
							<NewFolderButton />
						</div>
						{ folderTree.length > 0 && (
							<ul className="pressshot-sidebar__list">
								<FolderBranch
									nodes={ folderTree }
									depth={ 0 }
									scope={ scope }
									onSelectScope={ onSelectScope }
								/>
							</ul>
						) }
					</>
				) }

				{ tags.length > 0 && (
					<>
						<h2 className="pressshot-sidebar__heading">
							{ __( 'Tags', 'pressshot' ) }
						</h2>
						<ul className="pressshot-sidebar__list">
							{ tags.map( ( item ) => (
								<NavItem
									key={ item.id }
									icon={ tagIcon }
									label={ item.name }
									count={ item.shotCount }
									isCurrent={ isSameScope( scope, {
										kind: 'tag',
										id: item.id,
									} ) }
									onClick={ () =>
										onSelectScope( {
											kind: 'tag',
											id: item.id,
										} )
									}
								/>
							) ) }
						</ul>
					</>
				) }

				<ul className="pressshot-sidebar__list pressshot-sidebar__list--trash">
					<NavItem
						icon={ trash }
						label={ __( 'Trash', 'pressshot' ) }
						isCurrent={ scope.kind === 'trash' }
						onClick={ () => onSelectScope( { kind: 'trash' } ) }
					/>
				</ul>

				{ isApp && (
					<Account
						avatarUrl={ avatarUrl }
						extrasRef={ extrasRef }
						{ ...appLayout }
					/>
				) }
			</nav>
			{ isOpen && onClose && (
				// Clicking beside the open sidebar closes it. Keyboard users have Escape and the
				// close button, so the backdrop itself isn't focusable.
				// eslint-disable-next-line jsx-a11y/click-events-have-key-events, jsx-a11y/no-static-element-interactions
				<div
					className="pressshot-sidebar-backdrop"
					onClick={ onClose }
				/>
			) }
		</>
	);
}
