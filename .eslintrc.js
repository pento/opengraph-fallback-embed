module.exports = {
	root: true,
	extends: [ 'plugin:@wordpress/eslint-plugin/recommended' ],
	settings: {
		// WordPress packages are externals provided at runtime,
		// not installed as npm dependencies.
		'import/core-modules': [
			'@wordpress/api-fetch',
			'@wordpress/block-editor',
			'@wordpress/blocks',
			'@wordpress/components',
			'@wordpress/element',
			'@wordpress/i18n',
			'@wordpress/server-side-render',
		],
	},
	parserOptions: {
		requireConfigFile: false,
		babelOptions: {
			presets: [ require.resolve( '@wordpress/babel-preset-default' ) ],
		},
	},
	overrides: [
		{
			files: [ '**/@(test|__tests__)/**/*.js', '**/?(*.)test.js' ],
			extends: [ 'plugin:@wordpress/eslint-plugin/test-unit' ],
		},
	],
};
