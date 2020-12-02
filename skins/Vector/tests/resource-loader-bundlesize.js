/**
 * ResourceLoader module bandwidth test
 *
 * Fetches a series of ResourceLoader modules from a ResourceLoader URI and
 * pipes their content into a bundlesize command which validates their bytesize
 * against a configuration in in ../bundlesize.config.json.
 */

const
	MW_SERVER = process.env.MW_SERVER || 'http://127.0.0.1:8080',
	MW_SCRIPT_PATH = process.env.MW_SCRIPT_PATH || '/w',
	childProcess = require( 'child_process' ),
	fetch = require( 'node-fetch' ).default,
	bundles = require( '../bundlesize.config.json' ),
	// eslint-disable-next-line es/no-object-assign
	bundlesizeEnv = Object.assign( {}, process.env );

/**
 * Create a ResourceLoader URL based on $MW_SERVER and $MW_SCRIPT_PATH
 *
 * @param {string} rlModuleName
 * @return {string}
 */
function createRLUrl( rlModuleName ) {
	return `${MW_SERVER}${MW_SCRIPT_PATH}/load.php?lang=en&modules=${rlModuleName}`;
}

/**
 * Remove the "CI" environment variable so that it is not passed to
 * the bundlesize command, resulting in an unnecessary "Github token not found"
 * warning. Note: assigning to null or undefined doesn't work because Node casts
 * environment variable values to strings.
 */
delete bundlesizeEnv.CI;

/**
 * Fetch each ResourceLoader module defined in the bundlesize config
 * and pipe it's content into a bundlesize command.
 * The bundlesize stdout and stderr are passed to the parent process,
 * so any error in bundlesize will trigger a non-zero exit status for this script.
 */
bundles.forEach( async ( rlModule ) => {
	const rlModuleResponse = await fetch( createRLUrl( rlModule.resourceModule ) ),
		rlModuleContent = await rlModuleResponse.text();

	// Execute the bundlesize command.
	const cmd = childProcess.spawn(
		'node_modules/.bin/bundlesize-pipe',
		[ '--name', rlModule.resourceModule, '--max-size', rlModule.maxSize ],
		{
			// Stdin is writable stream, stdout and stderr are passed to the parent process.
			stdio: [ 'pipe', 'inherit', 'inherit' ],
			env: bundlesizeEnv
		}
	);

	cmd.stdin.write( rlModuleContent );
	cmd.stdin.end();

	cmd.on( 'exit', ( code ) => {
		if ( code !== 0 ) {
			process.exitCode = code || 1; // prevent assigning falsy values.
		}
	} );

} );
