/* eslint-disable no-console */
/* globals Prism, javascriptStringify */
/**
 * @class
 * @extends OO.ui.Element
 *
 * @constructor
 */
window.Demo = function Demo() {
	var demo = this;

	// Parent constructor
	Demo.parent.call( this );

	// Mixin constructors
	OO.EventEmitter.call( this );

	// Normalization
	this.normalizeQuery();

	// Properties
	this.stylesheetLinks = this.getStylesheetLinks();
	this.mode = this.getCurrentMode();
	this.$menu = $( '<div>' );
	this.pageDropdown = new OO.ui.DropdownWidget( {
		menu: {
			items: [
				new OO.ui.MenuOptionWidget( { data: 'dialogs', label: 'Dialogs' } ),
				new OO.ui.MenuOptionWidget( { data: 'icons', label: 'Icons' } ),
				new OO.ui.MenuOptionWidget( { data: 'toolbars', label: 'Toolbars' } ),
				new OO.ui.MenuOptionWidget( { data: 'widgets', label: 'Widgets' } )
			],
			// Funny effect... This dropdown is considered to always be "out of viewport"
			// due to the getViewportSpacing() override below. Don't let it disappear.
			hideWhenOutOfView: false
		},
		classes: [ 'demo-pageDropdown' ]
	} );
	this.pageMenu = this.pageDropdown.getMenu();
	this.themeSelect = new OO.ui.ButtonSelectWidget();
	Object.keys( this.constructor.static.themes ).forEach( function ( theme ) {
		demo.themeSelect.addItems( [
			new OO.ui.ButtonOptionWidget( {
				data: theme,
				label: demo.constructor.static.themes[ theme ]
			} )
		] );
	} );
	this.directionSelect = new OO.ui.ButtonSelectWidget().addItems( [
		new OO.ui.ButtonOptionWidget( { data: 'ltr', label: 'LTR' } ),
		new OO.ui.ButtonOptionWidget( { data: 'rtl', label: 'RTL' } )
	] );
	this.jsPhpSelect = new OO.ui.ButtonGroupWidget().addItems( [
		new OO.ui.ButtonWidget( { label: 'JS' } ).setActive( true ),
		new OO.ui.ButtonWidget( {
			label: 'PHP',
			href: 'demos.php' + this.getUrlQuery( this.getCurrentFactorValues() )
		} )
	] );
	this.platformSelect = new OO.ui.ButtonSelectWidget().addItems( [
		new OO.ui.ButtonOptionWidget( { data: 'desktop', label: 'Desktop' } ),
		new OO.ui.ButtonOptionWidget( { data: 'mobile', label: 'Mobile' } )
	] );

	this.documentationLink = new OO.ui.ButtonWidget( {
		label: 'Docs',
		classes: [ 'demo-button-docs' ],
		icon: 'journal',
		href: '../js/',
		flags: [ 'progressive' ]
	} );

	// Events
	this.pageMenu.on( 'choose', OO.ui.bind( this.onModeChange, this ) );
	this.themeSelect.on( 'choose', OO.ui.bind( this.onModeChange, this ) );
	this.directionSelect.on( 'choose', OO.ui.bind( this.onModeChange, this ) );
	this.platformSelect.on( 'choose', OO.ui.bind( this.onModeChange, this ) );

	// Initialization
	this.pageMenu.selectItemByData( this.mode.page );
	this.themeSelect.selectItemByData( this.mode.theme );
	this.directionSelect.selectItemByData( this.mode.direction );
	this.platformSelect.selectItemByData( this.mode.platform );
	this.$menu
		.addClass( 'demo-menu' )
		.attr( 'role', 'navigation' )
		.append(
			this.pageDropdown.$element,
			this.themeSelect.$element,
			this.directionSelect.$element,
			this.jsPhpSelect.$element,
			this.platformSelect.$element,
			this.documentationLink.$element
		);
	this.$element
		.addClass( 'demo' )
		.append( this.$menu );
	$( 'html' ).attr( 'dir', this.mode.direction );
	$( 'head' ).append( this.stylesheetLinks );
	$( 'body' ).addClass( 'oo-ui-theme-' + this.mode.theme );
	// eslint-disable-next-line new-cap
	OO.ui.theme = new OO.ui[ this.constructor.static.themes[ this.mode.theme ] + 'Theme' ]();
	OO.ui.isMobile = function () {
		return demo.mode.platform === 'mobile';
	};
	OO.ui.getViewportSpacing = function () {
		return {
			// Contents of dialogs are shown on top of the fixed menu
			top: demo.mode.page === 'dialogs' ? 0 : demo.$menu.outerHeight(),
			right: 0,
			bottom: 0,
			left: 0
		};
	};
};

/* Setup */

OO.inheritClass( Demo, OO.ui.Element );
OO.mixinClass( Demo, OO.EventEmitter );

/* Static Properties */

/**
 * Available pages.
 *
 * Populated by each of the page scripts in the `pages` directory.
 *
 * @static
 * @property {Object.<string,Function>} pages List of functions that render a page, keyed by
 *   symbolic page name
 */
Demo.static.pages = {};

/**
 * Available themes.
 *
 * Map of lowercase name to proper name. Lowercase names are used for linking to the
 * correct stylesheet file. Proper names are used to find the theme class.
 *
 * @static
 * @property {Object.<string,string>}
 */
Demo.static.themes = {
	wikimediaui: 'WikimediaUI', // Do not change this line or you'll break `grunt add-theme`
	apex: 'Apex'
};

/**
 * Additional suffixes for which each theme defines image modules.
 *
 * @static
 * @property {Object.<string,string[]>
 */
Demo.static.additionalThemeImagesSuffixes = {
	wikimediaui: [
		'-icons-movement',
		'-icons-content',
		'-icons-alerts',
		'-icons-interactions',
		'-icons-moderation',
		'-icons-editing-core',
		'-icons-editing-styling',
		'-icons-editing-list',
		'-icons-editing-advanced',
		'-icons-media',
		'-icons-location',
		'-icons-user',
		'-icons-layout',
		'-icons-accessibility',
		'-icons-wikimedia'
	],
	apex: [
		'-icons-movement',
		'-icons-content',
		'-icons-alerts',
		'-icons-interactions',
		'-icons-moderation',
		'-icons-editing-core',
		'-icons-editing-styling',
		'-icons-editing-list',
		'-icons-editing-advanced',
		'-icons-media',
		'-icons-location',
		'-icons-user',
		'-icons-layout',
		'-icons-accessibility',
		'-icons-wikimedia'
	]
};

/**
 * Available text directions.
 *
 * List of text direction descriptions, each containing a `fileSuffix` property used for linking to
 * the correct stylesheet file.
 *
 * @static
 * @property {Object.<string,Object>}
 */
Demo.static.directions = {
	ltr: { fileSuffix: '' },
	rtl: { fileSuffix: '.rtl' }
};

/**
 * Available platforms.
 *
 * @static
 * @property {string[]}
 */
Demo.static.platforms = [ 'desktop', 'mobile' ];

/**
 * Default page.
 *
 * @static
 * @property {string}
 */
Demo.static.defaultPage = 'widgets';

/**
 * Default page.
 *
 * Set by one of the page scripts in the `pages` directory.
 *
 * @static
 * @property {string}
 */
Demo.static.defaultTheme = 'wikimediaui';

/**
 * Default page.
 *
 * Set by one of the page scripts in the `pages` directory.
 *
 * @static
 * @property {string}
 */
Demo.static.defaultDirection = 'ltr';

/**
 * Default platform.
 *
 * Set by one of the page scripts in the `pages` directory.
 *
 * @static
 * @property {string}
 */
Demo.static.defaultPlatform = 'desktop';

/* Static Methods */

/**
 * Scroll to current fragment identifier. We have to do this manually because of the fixed header.
 */
Demo.static.scrollToFragment = function () {
	var elem = document.getElementById( location.hash.slice( 1 ) );
	if ( elem ) {
		// The additional '10' is just because it looks nicer.
		$( window ).scrollTop( $( elem ).offset().top - $( '.demo-menu' ).outerHeight() - 10 );
	}
};

/* Methods */

/**
 * Load the demo page. Must be called after $element is attached.
 *
 * @return {jQuery.Promise} Resolved when demo is initialized
 */
Demo.prototype.initialize = function () {
	var demo = this,
		promises = this.stylesheetLinks.map( function ( el ) {
			return $( el ).data( 'load-promise' );
		} );

	// Helper function to get high resolution profiling data, where available.
	function now() {
		return ( window.performance && performance.now ) ? performance.now() :
			Date.now ? Date.now() : new Date().getTime();
	}

	return $.when.apply( $, promises )
		.done( function () {
			var start, end;
			start = now();
			demo.constructor.static.pages[ demo.mode.page ]( demo );
			end = now();
			window.console.log( 'Took ' + ( end - start ) + ' ms to build demo page.' );
		} )
		.fail( function () {
			demo.$element.append( $( '<p>' ).text( 'Demo styles failed to load.' ) );
		} );
};

/**
 * Handle mode change events.
 *
 * Will load a new page.
 */
Demo.prototype.onModeChange = function () {
	var page = this.pageMenu.findSelectedItem().getData(),
		theme = this.themeSelect.findSelectedItem().getData(),
		direction = this.directionSelect.findSelectedItem().getData(),
		platform = this.platformSelect.findSelectedItem().getData();

	history.pushState( null, document.title, this.getUrlQuery( [ page, theme, direction, platform ] ) );
	$( window ).triggerHandler( 'popstate' );
};

/**
 * Get URL query for given factors describing the demo's mode.
 *
 * @param {string[]} factors Factors, as returned e.g. by #getCurrentFactorValues
 * @return {string} URL query part, starting with '?'
 */
Demo.prototype.getUrlQuery = function ( factors ) {
	return '?page=' + factors[ 0 ] +
		'&theme=' + factors[ 1 ] +
		'&direction=' + factors[ 2 ] +
		'&platform=' + factors[ 3 ] +
		// Preserve current URL 'fragment' part
		location.hash;
};

/**
 * Get a list of mode factors.
 *
 * Factors are a mapping between symbolic names used in the URL query and internal information used
 * to act on those symbolic names.
 *
 * Factor lists are in URL order: page, theme, direction, platform. Page contains the symbolic
 * page name, others contain file suffixes.
 *
 * @return {Object[]} List of mode factors, keyed by symbolic name
 */
Demo.prototype.getFactors = function () {
	var key,
		factors = [ {}, {}, {}, {} ];

	for ( key in this.constructor.static.pages ) {
		factors[ 0 ][ key ] = key;
	}
	for ( key in this.constructor.static.themes ) {
		factors[ 1 ][ key ] = '-' + key;
	}
	for ( key in this.constructor.static.directions ) {
		factors[ 2 ][ key ] = this.constructor.static.directions[ key ].fileSuffix;
	}
	this.constructor.static.platforms.forEach( function ( platform ) {
		factors[ 3 ][ platform ] = '';
	} );

	return factors;
};

/**
 * Get a list of default factors.
 *
 * Factor defaults are in URL order: page, theme, direction, platform. Each contains a symbolic
 * factor name which should be used as a fallback when the URL query is missing or invalid.
 *
 * @return {Object[]} List of default factors
 */
Demo.prototype.getDefaultFactorValues = function () {
	return [
		this.constructor.static.defaultPage,
		this.constructor.static.defaultTheme,
		this.constructor.static.defaultDirection,
		this.constructor.static.defaultPlatform
	];
};

/**
 * Parse the current URL query into factor values.
 *
 * @return {string[]} Factor values in URL order: page, theme, direction, platform
 */
Demo.prototype.getCurrentFactorValues = function () {
	var i, parts, index,
		factors = this.getDefaultFactorValues(),
		order = [ 'page', 'theme', 'direction', 'platform' ],
		query = location.search.slice( 1 ).split( '&' );
	for ( i = 0; i < query.length; i++ ) {
		parts = query[ i ].split( '=', 2 );
		index = order.indexOf( parts[ 0 ] );
		if ( index !== -1 ) {
			factors[ index ] = decodeURIComponent( parts[ 1 ] );
		}
	}
	return factors;
};

/**
 * Get the current mode.
 *
 * Generated from parsed URL query values.
 *
 * @return {Object} List of factor values keyed by factor name
 */
Demo.prototype.getCurrentMode = function () {
	var factorValues = this.getCurrentFactorValues();

	return {
		page: factorValues[ 0 ],
		theme: factorValues[ 1 ],
		direction: factorValues[ 2 ],
		platform: factorValues[ 3 ]
	};
};

/**
 * Get link elements for the current mode.
 *
 * @return {HTMLElement[]} List of link elements
 */
Demo.prototype.getStylesheetLinks = function () {
	var i, len, links, fragments,
		factors = this.getFactors(),
		theme = this.getCurrentFactorValues()[ 1 ],
		suffixes = this.constructor.static.additionalThemeImagesSuffixes[ theme ] || [],
		urls = [];

	// Translate modes to filename fragments
	fragments = this.getCurrentFactorValues().map( function ( val, index ) {
		return factors[ index ][ val ];
	} );

	// Theme styles
	urls.push( 'dist/oojs-ui' + fragments.slice( 1 ).join( '' ) + '.css' );
	for ( i = 0, len = suffixes.length; i < len; i++ ) {
		urls.push( 'dist/oojs-ui' + fragments[ 1 ] + suffixes[ i ] + fragments[ 2 ] + '.css' );
	}

	// Demo styles
	urls.push( 'styles/demo' + fragments[ 2 ] + '.css' );

	// Add link tags
	links = urls.map( function ( url ) {
		var
			link = document.createElement( 'link' ),
			$link = $( link ),
			deferred = $.Deferred();
		$link.data( 'load-promise', deferred.promise() );
		$link.on( {
			load: deferred.resolve,
			error: deferred.reject
		} );
		link.rel = 'stylesheet';
		link.href = url;
		return link;
	} );

	return links;
};

/**
 * Normalize the URL query.
 */
Demo.prototype.normalizeQuery = function () {
	var i, len, factorValues, match, valid, factorValue,
		modes = [],
		factors = this.getFactors(),
		defaults = this.getDefaultFactorValues();

	factorValues = this.getCurrentFactorValues();
	for ( i = 0, len = factors.length; i < len; i++ ) {
		factorValue = factorValues[ i ];
		modes[ i ] = factors[ i ][ factorValue ] !== undefined ? factorValue : defaults[ i ];
	}

	// Backwards-compatibility with old URLs that used the 'fragment' part to link to demo sections:
	// if a fragment is specified and it describes valid factors, turn the URL into the new style.
	match = location.hash.match( /^#(\w+)-(\w+)-(\w+)-(\w+)$/ );
	if ( match ) {
		factorValues = [];
		valid = true;
		for ( i = 0, len = factors.length; i < len; i++ ) {
			factorValue = match[ i + 1 ];
			if ( factors[ i ][ factorValue ] !== undefined ) {
				factorValues[ i ] = factorValue;
			} else {
				valid = false;
				break;
			}
		}
		if ( valid ) {
			location.hash = '';
			modes = factorValues;
		}
	}

	// Update query
	history.replaceState( null, document.title, this.getUrlQuery( modes ) );
};

/**
 * Destroy demo.
 */
Demo.prototype.destroy = function () {
	$( 'body' ).removeClass( 'oo-ui-ltr oo-ui-rtl' );
	$( 'body' ).removeClass( 'oo-ui-theme-' + this.mode.theme );
	$( this.stylesheetLinks ).remove();
	this.$element.remove();
	this.emit( 'destroy' );
};

/**
 * Build a console for interacting with an element.
 *
 * @param {OO.ui.Layout} item
 * @param {string} layout Variable name for layout
 * @param {string} widget Variable name for layout's field widget
 * @return {jQuery} Console interface element
 */
Demo.prototype.buildConsole = function ( item, layout, widget, showLayoutCode ) {
	var $toggle, $log, $label, $input, $submit, $console, $form, $pre, $code,
		console = window.console;

	function exec( str ) {
		var func, ret;
		if ( str.indexOf( 'return' ) !== 0 ) {
			str = 'return ' + str;
		}
		try {
			// eslint-disable-next-line no-new-func
			func = new Function( layout, widget, 'item', str );
			ret = { value: func( item, item.fieldWidget, item.fieldWidget ) };
		} catch ( error ) {
			ret = {
				value: undefined,
				error: error
			};
		}
		return ret;
	}

	function submit() {
		var val, result, logval;

		val = $input.val();
		$input.val( '' );
		$input[ 0 ].focus();
		result = exec( val );

		logval = String( result.value );
		if ( logval === '' ) {
			logval = '""';
		}

		$log.append(
			$( '<div>' )
				.addClass( 'demo-console-log-line demo-console-log-line-input' )
				.text( val ),
			$( '<div>' )
				.addClass( 'demo-console-log-line demo-console-log-line-return' )
				.text( logval || result.value )
		);

		if ( result.error ) {
			$log.append( $( '<div>' ).addClass( 'demo-console-log-line demo-console-log-line-error' ).text( result.error ) );
		}

		if ( console && console.log ) {
			console.log( '[demo]', result.value );
			if ( result.error ) {
				if ( console.error ) {
					console.error( '[demo]', String( result.error ), result.error );
				} else {
					console.log( '[demo] Error: ', result.error );
				}
			}
		}

		// Scrol to end
		$log.prop( 'scrollTop', $log.prop( 'scrollHeight' ) );
	}

	function getCode( item, toplevel ) {
		var config, defaultConfig, url, params, out, i,
			items = [],
			demoLinks = [],
			docLinks = [];

		function getConstructorName( item ) {
			var isDemoWidget = item.constructor.name.indexOf( 'Demo' ) === 0;
			return ( isDemoWidget ? 'Demo.' : 'OO.ui.' ) + item.constructor.name.slice( 4 );
		}

		// If no item was passed we shouldn't show a code block
		if ( item === undefined ) {
			return false;
		}

		config = item.initialConfig;

		// Prevent the default config from being part of the code
		if ( item instanceof OO.ui.ActionFieldLayout ) {
			defaultConfig = ( new item.constructor( new OO.ui.TextInputWidget(), new OO.ui.ButtonWidget() ) ).initialConfig;
		} else if ( item instanceof OO.ui.FieldLayout ) {
			defaultConfig = ( new item.constructor( new OO.ui.ButtonWidget() ) ).initialConfig;
		} else {
			defaultConfig = ( new item.constructor() ).initialConfig;
		}
		Object.keys( defaultConfig ).forEach( function ( key ) {
			if ( config[ key ] === defaultConfig[ key ] ) {
				delete config[ key ];
			} else if (
				typeof config[ key ] === 'object' && typeof defaultConfig[ key ] === 'object' &&
				OO.compare( config[ key ], defaultConfig[ key ] )
			) {
				delete config[ key ];
			}
		} );

		config = javascriptStringify( config, function ( obj, indent, stringify ) {
			if ( obj instanceof Function ) {
				// Get function's source code, with extraneous indentation removed
				return obj.toString().replace( /^\t\t\t\t\t\t/gm, '' );
			} else if ( obj instanceof jQuery ) {
				if ( $.contains( item.$element[ 0 ], obj[ 0 ] ) ) {
					// If this element appears inside the generated widget,
					// assume this was something like `$label: $( '<p>Text</p>' )`
					return '$( ' + javascriptStringify( obj.prop( 'outerHTML' ) ) + ' )';
				} else {
					// Otherwise assume this was something like `$overlay: $( '#overlay' )`
					return '$( ' + javascriptStringify( '#' + obj.attr( 'id' ) ) + ' )';
				}
			} else if ( obj instanceof OO.ui.HtmlSnippet ) {
				return 'new OO.ui.HtmlSnippet( ' + javascriptStringify( obj.toString() ) + ' )';
			} else if ( obj instanceof OO.ui.Element ) {
				return getCode( obj );
			} else {
				return stringify( obj );
			}
		}, '\t' );

		// The generated code needs to include different arguments, based on the object type
		items.push( item );
		if ( item instanceof OO.ui.ActionFieldLayout ) {
			params = getCode( item.fieldWidget ) + ', ' + getCode( item.buttonWidget );
			items.push( item.fieldWidget );
			items.push( item.buttonWidget );
		} else if ( item instanceof OO.ui.FieldLayout ) {
			params = getCode( item.fieldWidget );
			items.push( item.fieldWidget );
		} else {
			params = '';
		}
		if ( config !== '{}' ) {
			params += ( params ? ', ' : '' ) + config;
		}
		out = 'new ' + getConstructorName( item ) + '(' +
			( params ? ' ' : '' ) + params + ( params ? ' ' : '' ) +
			')';

		if ( toplevel ) {
			for ( i = 0; i < items.length; i++ ) {
				item = items[ i ];
				// The code generated for Demo widgets cannot be copied and used
				if ( item.constructor.name.indexOf( 'Demo' ) === 0 ) {
					url =
						'https://phabricator.wikimedia.org/diffusion/GOJU/browse/master/demos/classes/' +
						item.constructor.name.slice( 4 ) + '.js';
					demoLinks.push( url );
				} else {
					url = 'https://doc.wikimedia.org/oojs-ui/master/js/#!/api/' + getConstructorName( item );
					url = '[' + url + '](' + url + ')';
					docLinks.push( url );
				}
			}
		}

		return (
			( docLinks.length ? '// See documentation at: \n// ' : '' ) +
			docLinks.join( '\n// ' ) + ( docLinks.length ? '\n' : '' ) +
			( demoLinks.length ? '// See source code:\n// ' : '' ) +
			demoLinks.join( '\n// ' ) + ( demoLinks.length ? '\n' : '' ) +
			out
		);
	}

	$toggle = $( '<span>' )
		.addClass( 'demo-console-toggle' )
		.attr( 'title', 'Toggle console' )
		.on( 'click', function ( e ) {
			var code;
			e.preventDefault();
			$console.toggleClass( 'demo-console-collapsed demo-console-expanded' );
			if ( $input.is( ':visible' ) ) {
				$input[ 0 ].focus();
				if ( console && console.log ) {
					window[ layout ] = item;
					window[ widget ] = item.fieldWidget;
					console.log( '[demo]', 'Globals ' + layout + ', ' + widget + ' have been set' );
					console.log( '[demo]', item );

					if ( showLayoutCode === true ) {
						code = getCode( item, true );
					} else {
						code = getCode( item.fieldWidget, true );
					}

					if ( code ) {
						$code.text( code );
						Prism.highlightElement( $code[ 0 ] );
					} else {
						$code.remove();
					}
				}
			}
		} );

	$log = $( '<div>' )
		.addClass( 'demo-console-log' );

	$label = $( '<label>' )
		.addClass( 'demo-console-label' );

	$input = $( '<input>' )
		.addClass( 'demo-console-input' )
		.prop( 'placeholder', '... (predefined: ' + layout + ', ' + widget + ')' );

	$submit = $( '<div>' )
		.addClass( 'demo-console-submit' )
		.text( '↵' )
		.on( 'click', submit );

	$form = $( '<form>' ).on( 'submit', function ( e ) {
		e.preventDefault();
		submit();
	} );

	$code = $( '<code>' ).addClass( 'language-javascript' );

	$pre = $( '<pre>' )
		.addClass( 'demo-sample-code' )
		.append( $code );

	$console = $( '<div>' )
		.addClass( 'demo-console demo-console-collapsed' )
		.append(
			$toggle,
			$log,
			$form.append(
				$label.append(
					$input
				),
				$submit
			),
			$pre
		);

	return $console;
};

/**
 * Build a link to this example.
 *
 * @param {OO.ui.Layout} item
 * @param {OO.ui.FieldsetLayout} parentItem
 * @return {jQuery} Link interface element
 */
Demo.prototype.buildLinkExample = function ( item, parentItem ) {
	var $linkExample, label, fragment;

	if ( item.$label.text() === '' ) {
		item = parentItem;
	}
	fragment = item.elementId;
	if ( !fragment ) {
		label = item.$label.text();
		fragment = label.replace( /[^\w]+/g, '-' ).replace( /^-|-$/g, '' );
		item.setElementId( fragment );
	}

	$linkExample = $( '<a>' )
		.addClass( 'demo-link-example' )
		.attr( 'title', 'Link to this example' )
		.attr( 'href', '#' + fragment )
		.on( 'click', function ( e ) {
			// We have to handle this manually in order to call .scrollToFragment() even if it's the same
			// fragment. Normally, the browser will scroll but not fire a 'hashchange' event in this
			// situation, and the scroll position will be off because of our fixed header.
			if ( e.which === OO.ui.MouseButtons.LEFT ) {
				location.hash = $( this ).attr( 'href' );
				Demo.static.scrollToFragment();
				e.preventDefault();
			}
		} );

	return $linkExample;
};
