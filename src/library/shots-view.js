/**
 * The shots list: DataViews in grid (default) or table layout, with a preview field. Both
 * layouts have selection checkboxes for bulk actions (DataViews' list layout selects one row only).
 * Grid cards show the thumbnail, the title, and one muted byline; everything else lives in the
 * card's "…" menu. DataViews is composed by hand: our toolbar (Select all, count, sort, grid and
 * table) replaces its search and layout menu, and its own view options stay at the toolbar's end.
 */
import { useMemo, useState } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import { Icon } from '@wordpress/components';
import { DataViews } from '@wordpress/dataviews';
import { image } from '@wordpress/icons';
import {
	BYLINE_FIELD,
	PREVIEW_FIELD,
	mediaSrc,
	shotByline,
	shotFields,
} from './fields';
import { playMark } from './icons';
import { LibraryToolbar } from './toolbar';

// Applied when switching layouts. The table trades the byline for its own columns.
const DEFAULT_LAYOUTS = {
	grid: { fields: [], descriptionField: BYLINE_FIELD },
	table: {
		fields: [ 'author', 'date', 'folder' ],
		descriptionField: undefined,
		// The person column holds a name, though its value is a user id; keep it start-aligned.
		layout: { styles: { author: { align: 'start' } } },
	},
};

const CONFIG = { perPageSizes: [ 20, 40, 100 ] };

/**
 * A recording's preview: the still VideoPress made, when it has one, under the play badge. The
 * still comes through the poster route, which adds the viewing pass; if it doesn't load (the video
 * is still processing, or the site can't mint a pass) the plain tile stays.
 */
export function RecordingPreview( { item, nonce } ) {
	const [ failed, setFailed ] = useState( false );
	return (
		<div className="pressshot-preview pressshot-preview--recording">
			{ item.posterUrl && ! failed && (
				<img
					className="pressshot-preview__poster"
					src={ mediaSrc( item.posterUrl, { _wpnonce: nonce } ) }
					alt=""
					loading="lazy"
					onError={ () => setFailed( true ) }
				/>
			) }
			<span className="pressshot-play-badge">
				<Icon icon={ playMark } size={ 16 } />
			</span>
		</div>
	);
}

function ShotPreview( { item, nonce } ) {
	// Recordings never load video for a thumbnail, only a VideoPress still when there is one.
	if ( item.kind === 'recording' ) {
		return <RecordingPreview item={ item } nonce={ nonce } />;
	}
	if ( ! item.mediaUrl ) {
		return (
			<div className="pressshot-preview pressshot-preview--icon">
				<Icon icon={ image } size={ 48 } />
			</div>
		);
	}
	return (
		<img
			className="pressshot-preview"
			src={ mediaSrc( item.mediaUrl, {
				size: 'medium',
				_wpnonce: nonce,
			} ) }
			alt=""
			loading="lazy"
		/>
	);
}

export default function ShotsView( {
	shots,
	total,
	totalPages,
	isLoading,
	view,
	onChangeView,
	authors,
	folders,
	tags,
	nonce,
	emptyMessage,
	actions,
	selection,
	onChangeSelection,
	onOpenShot,
} ) {
	const fields = useMemo(
		() => [
			{
				id: PREVIEW_FIELD,
				type: 'media',
				label: __( 'Preview', 'pressshot' ),
				render: ( { item } ) => (
					<ShotPreview item={ item } nonce={ nonce } />
				),
				enableSorting: false,
				filterBy: false,
			},
			{
				id: BYLINE_FIELD,
				type: 'text',
				label: __( 'Byline', 'pressshot' ),
				getValue: ( { item } ) => shotByline( item ),
				render: ( { item } ) => (
					<span className="pressshot-byline">
						{ shotByline( item ) }
					</span>
				),
				enableSorting: false,
				filterBy: false,
			},
			...shotFields( authors, folders, tags ),
		],
		[ authors, folders, tags, nonce ]
	);

	return (
		<DataViews
			data={ shots }
			fields={ fields }
			view={ view }
			onChangeView={ onChangeView }
			paginationInfo={ { totalItems: total, totalPages } }
			defaultLayouts={ DEFAULT_LAYOUTS }
			config={ CONFIG }
			isLoading={ isLoading }
			getItemId={ ( item ) => String( item.id ) }
			isItemClickable={ () => true }
			onClickItem={ onOpenShot }
			actions={ actions }
			selection={ selection }
			onChangeSelection={ onChangeSelection }
			empty={ <p className="pressshot-empty">{ emptyMessage }</p> }
		>
			<LibraryToolbar
				view={ view }
				onChangeView={ onChangeView }
				defaultLayouts={ DEFAULT_LAYOUTS }
				shots={ shots }
				actions={ actions }
				total={ total }
				isLoading={ isLoading }
				selection={ selection }
				onChangeSelection={ onChangeSelection }
			>
				<DataViews.FiltersToggle />
				<DataViews.ViewConfig />
			</LibraryToolbar>
			<DataViews.FiltersToggled className="dataviews-filters__container" />
			<DataViews.Layout />
			<DataViews.Footer />
		</DataViews>
	);
}
