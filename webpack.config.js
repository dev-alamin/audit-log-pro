// webpack.config.js
const defaultConfig = require( '@wordpress/scripts/config/webpack.config' );
const path = require( 'path' );

module.exports = {
    ...defaultConfig,
    entry: {
        admin: path.resolve( process.cwd(), 'assets/js/src/index.js' ),
    },
    output: {
        ...defaultConfig.output,
        path: path.resolve( process.cwd(), 'assets/js/build' ),
    },
};