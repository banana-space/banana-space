/*!
 * Build a static loader file from a template
 */

'use strict';

module.exports = function ( grunt ) {

	grunt.registerMultiTask( 'buildloader', function () {
		const styles = [],
			scripts = [],
			loadedModules = [],
			targetFile = this.data.targetFile,
			pathPrefix = this.data.pathPrefix || '',
			i18n = this.data.i18n || [],
			demoPages = this.data.demoPages,
			indent = this.data.indent || '',
			modules = this.data.modules,
			load = this.data.load,
			run = this.data.run || [],
			env = this.data.env || {},
			placeholders = this.data.placeholders || {},
			dir = this.data.dir,
			langList = this.data.langList !== undefined ? this.data.langList : true,
			done = this.async(),
			moduleUtils = require( '../moduleUtils' ),
			stringifyObject = require( 'stringify-object' );
		let text = grunt.file.read( this.data.template );

		function scriptTag( src ) {
			return indent + '<script src="' + pathPrefix + src.file + '"></script>';
		}

		function styleTag( group, src ) {
			const rtlFilepath = src.file.replace( /\.css$/, '.rtl.css' );

			if ( grunt.file.exists( rtlFilepath ) ) {
				if ( !dir ) {
					return indent + '<link rel=stylesheet href="' + pathPrefix + src.file + '" class="stylesheet-ltr' +
						( group ? ' stylesheet-' + group : '' ) + '">\n' +
						indent + '<link rel=stylesheet href="' + pathPrefix + rtlFilepath + '" class="stylesheet-rtl' +
						( group ? ' stylesheet-' + group : '' ) + '">';
				} else if ( dir === 'rtl' ) {
					return indent + '<link rel=stylesheet href="' + pathPrefix + rtlFilepath + '"' +
						( group ? ' class="stylesheet-' + group + '"' : '' ) + '>';
				}
			}
			return indent + '<link rel=stylesheet href="' + pathPrefix + src.file + '"' +
				( group ? ' class="stylesheet-' + group + '"' : '' ) + '>';
		}

		function expand( src ) {
			return typeof src === 'string' ? { file: src } : src;
		}

		function filter( type, src ) {
			if ( src.debug && !env.debug ) {
				return false;
			}
			if ( type === 'styles' && env.test && !src.test ) {
				return false;
			}

			return true;
		}

		function placeholder( input, id, replacement, callback ) {
			const rComment = new RegExp( '<!-- ' + id + ' -->', 'm' );
			if ( typeof replacement === 'function' ) {
				replacement( function ( response ) {
					const output = input.replace( rComment, response );
					callback( output );
				} );
			} else {
				const output = input.replace( rComment, replacement );
				callback( output );
			}
		}

		function addModules( load ) {
			const dependencies = moduleUtils.buildDependencyList( modules, load );
			for ( const dependency in dependencies ) {
				const module = dependencies[ dependency ];
				if ( loadedModules.indexOf( module ) > -1 ) {
					continue;
				}
				loadedModules.push( module );
				if ( modules[ module ].scripts ) {
					const moduleScripts = modules[ module ].scripts
						.map( expand ).filter( filter.bind( this, 'scripts' ) ).map( scriptTag )
						.join( '\n' );
					if ( moduleScripts ) {
						scripts.push( indent + '<!-- ' + module + ' -->\n' + moduleScripts );
					}
				}
				if ( modules[ module ].styles ) {
					const moduleStyles = modules[ module ].styles
						.map( expand ).filter( filter.bind( this, 'styles' ) ).map( styleTag.bind( styleTag, modules[ module ].styleGroup ) )
						.join( '\n' );
					if ( moduleStyles ) {
						styles.push( indent + '<!-- ' + module + ' -->\n' + moduleStyles );
					}
				}
			}
		}

		addModules( load );

		if ( i18n.length || demoPages ) {
			let configScript = indent + '<script>\n';

			if ( i18n.length ) {
				configScript +=
					indent + '\tve.messagePaths = ' +
					stringifyObject(
						i18n.map( function ( path ) { return pathPrefix + path; } )
					).replace( /\n/g, '\n\t' + indent ) + ';\n';

				if ( langList ) {
					configScript += indent + '\tve.availableLanguages = ' +
						stringifyObject(
							Array.from( new Set(
								grunt.file.expand(
									i18n.map( function ( path ) { return path + '*.json'; } )
								).map( function ( file ) {
									return file.split( '/' ).pop().slice( 0, -5 );
								} )
							) ).sort()
						).replace( /\n\t*/g, ' ' ) +
						';\n';
				}
			}
			if ( demoPages ) {
				configScript += indent + '\tve.demoPages = ' + stringifyObject( demoPages ).replace( /\n/g, '\n\t' + indent ) + ';\n';
			}

			configScript +=
				indent + '</script>';
			scripts.push( configScript );
		}

		addModules( run );

		placeholders.styles = styles.join( '\n\n' );
		placeholders.scripts = scripts.join( '\n\n' );
		placeholders.dir = dir;

		grunt.util.async.forEachSeries(
			Object.keys( placeholders ),
			function ( id, next ) {
				placeholder( text, id.toUpperCase(), placeholders[ id ], function ( newText ) {
					text = newText;
					next();
				} );
			},
			function () {
				grunt.file.write( targetFile, text );
				grunt.log.ok( 'File "' + targetFile + '" written.' );

				done();
			}
		);

	} );

};
