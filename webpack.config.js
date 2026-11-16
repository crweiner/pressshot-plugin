const defaultConfig = require( '@wordpress/scripts/config/webpack.config' );

// One classic script for the Library app. blocks/library/block.json points its viewScript at
// build/library.js and its viewStyle at build/library.css; wp-scripts writes
// build/library.asset.php with the WordPress script dependencies. @wordpress/dataviews is bundled
// (it is not a WordPress script handle).
module.exports = {
	...defaultConfig,
	entry: {
		library: './src/library/index.js',
		'library-editor': './src/library-editor/index.js',
		'share-view-editor': './src/share-view-editor/index.js',
	},
	module: {
		...defaultConfig.module,
		rules: [
			...defaultConfig.module.rules,
			// The dataviews package declares sideEffects: false, which would drop its stylesheet import.
			{
				test: /@wordpress[\\/]dataviews[\\/]build-style[\\/].*\.css$/,
				sideEffects: true,
			},
		],
	},
};
