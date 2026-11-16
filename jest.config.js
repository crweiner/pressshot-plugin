/**
 * Jest settings. WordPress packages (and the few libraries they pull in) ship ES modules, so
 * they're compiled for Jest like our own code. That lets tests render the real components.
 */
module.exports = {
	transform: { '^.+\\.[mc]?[jt]sx?$': 'babel-jest' },
	transformIgnorePatterns: [
		'/node_modules/(?!(@wordpress|@ariakit|@base-ui|@base-ui-components|@floating-ui|uuid|clsx|colord|framer-motion|motion-dom|motion-utils|change-case|use-memo-one|memize|remove-accents)/)',
	],
	moduleFileExtensions: [ 'js', 'mjs', 'cjs', 'jsx', 'ts', 'tsx', 'json' ],
	testPathIgnorePatterns: [ '/node_modules/', '/build/' ],
};
