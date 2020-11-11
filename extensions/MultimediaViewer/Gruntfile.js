/* eslint-env node */

module.exports = function ( grunt ) {
	var conf = grunt.file.readJSON( 'extension.json' );

	grunt.loadNpmTasks( 'grunt-banana-checker' );
	grunt.loadNpmTasks( 'grunt-eslint' );
	grunt.loadNpmTasks( 'grunt-jsonlint' );
	grunt.loadNpmTasks( 'grunt-stylelint' );
	grunt.loadNpmTasks( 'grunt-svgmin' );

	grunt.initConfig( {
		banana: conf.MessagesDirs,
		jsonlint: {
			all: [
				'**/*.json',
				'!node_modules/**',
				'!vendor/**'
			]
		},
		eslint: {
			all: [
				'*.js',
				'resources/mmv/**/*.js',
				'tests/**/*.js'
			]
		},
		stylelint: {
			options: {
				syntax: 'less'
			},
			src: 'resources/mmv/**/*.{css,less}'
		},
		// Image Optimization
		svgmin: {
			options: {
				js2svg: {
					pretty: true
				},
				plugins: [ {
					cleanupIDs: false
				}, {
					removeDesc: false
				}, {
					removeRasterImages: true
				}, {
					removeTitle: false
				}, {
					removeViewBox: false
				}, {
					removeXMLProcInst: false
				}, {
					sortAttrs: true
				} ]
			},
			all: {
				files: [ {
					expand: true,
					cwd: 'resources',
					src: [
						'**/*.svg'
					],
					dest: 'resources/',
					ext: '.svg'
				} ]
			}
		}
	} );

	grunt.registerTask( 'test', [ 'eslint', 'stylelint', 'svgmin', 'jsonlint', 'banana' ] );
	grunt.registerTask( 'default', 'test' );
};
