import { registerBlockType } from '@wordpress/blocks';
import { InnerBlocks, InspectorControls, useBlockProps } from '@wordpress/block-editor';
import { PanelBody, Placeholder, ToggleControl } from '@wordpress/components';
import { __ } from '@wordpress/i18n';
import { gallery } from '@wordpress/icons';
import metadata from '../../blocks/library/block.json';

function Edit( { attributes, setAttributes } ) {
	return (
		<div { ...useBlockProps() }>
			<InspectorControls>
				<PanelBody title={ __( 'Layout', 'pressshot' ) }>
					<ToggleControl
						label={ __( 'Fill the window', 'pressshot' ) }
						help={ __(
							'Makes the Library app-like: it fills the window below the WordPress bar, with the site name and your account in the sidebar. Best on a template with no header.',
							'pressshot'
						) }
						checked={ !! attributes.appLayout }
						onChange={ ( appLayout ) =>
							setAttributes( { appLayout } )
						}
					/>
				</PanelBody>
			</InspectorControls>
			<Placeholder
				icon={ gallery }
				label={ __( 'PressShot Library', 'pressshot' ) }
				instructions={ __(
					'Signed-in people who can upload see the Library here.',
					'pressshot'
				) }
			>
				<div className="pressshot-library__extras-editor">
					<p>{ __( 'Sidebar extras', 'pressshot' ) }</p>
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
