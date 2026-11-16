import { registerBlockType } from '@wordpress/blocks';
import { InnerBlocks, InspectorControls, useBlockProps } from '@wordpress/block-editor';
import { PanelBody, Placeholder, ToggleControl } from '@wordpress/components';
import { __ } from '@wordpress/i18n';
import { image } from '@wordpress/icons';
import metadata from '../../blocks/share-view/block.json';

function Edit( { attributes, setAttributes } ) {
	return (
		<div { ...useBlockProps() }>
			<InspectorControls>
				<PanelBody title={ __( 'Top bar', 'pressshot' ) }>
					<ToggleControl
						label={ __( 'Show the site name', 'pressshot' ) }
						help={ __(
							'Puts the site name, linked to the home page, at the left of the top bar. Best on a template with no header.',
							'pressshot'
						) }
						checked={ !! attributes.showSiteName }
						onChange={ ( showSiteName ) =>
							setAttributes( { showSiteName } )
						}
					/>
				</PanelBody>
			</InspectorControls>
			<Placeholder
				icon={ image }
				label={ __( 'PressShot shared view', 'pressshot' ) }
				instructions={ __(
					'Visitors to a share link see the shot or folder here, with its name and actions in a top bar.',
					'pressshot'
				) }
			>
				<div className="pressshot-share__extras-editor">
					<p>{ __( 'Top bar extras', 'pressshot' ) }</p>
					<InnerBlocks templateLock={ false } />
				</div>
			</Placeholder>
		</div>
	);
}

function Save() {
	return <InnerBlocks.Content />;
}

registerBlockType( metadata.name, { edit: Edit, save: Save } );
