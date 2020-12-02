/*!
 * VisualEditor trigger demo
 *
 * @copyright 2011-2020 VisualEditor Team and others; see http://ve.mit-license.org
 */

( function () {
	var i, len, key,
		/* eslint-disable no-jquery/no-global-selector */
		$primary = $( '#primary' ),
		$modifiers = $( '#modifiers' ),
		$aliases = $( '#aliases' ),
		$trigger = $( '#trigger' ),
		/* eslint-enable no-jquery/no-global-selector */
		primaryKeys = ve.ui.Trigger.static.primaryKeys,
		modifierKeys = ve.ui.Trigger.static.modifierKeys,
		keyAliases = ve.ui.Trigger.static.keyAliases;

	function setTrigger( trigger ) {
		var i, len, key, parts;
		trigger = trigger.toString();
		parts = trigger.split( '+' );
		$trigger.text( trigger );
		for ( i = 0, len = parts.length; i < len; i++ ) {
			key = parts[ i ].replace( '\\', '\\\\' ).replace( '"', '\\"' );
			$( '.key[rel~="' + key + '"]' ).addClass( 'active' );
		}
	}

	// Initialization

	for ( i = 0, len = modifierKeys.length; i < len; i++ ) {
		$modifiers.append(
			$( '<li>' ).append(
				$( '<span>' )
					.addClass( 'key' )
					.attr( 'rel', modifierKeys[ i ] )
					.text( modifierKeys[ i ] )
			)
		);
	}
	for ( i = 0, len = primaryKeys.length; i < len; i++ ) {
		$primary.append(
			$( '<li>' ).append(
				$( '<span>' )
					.addClass( 'key' )
					.attr( 'rel', primaryKeys[ i ] )
					.text( primaryKeys[ i ] )
			)
		);
	}
	for ( key in keyAliases ) {
		$aliases.append(
			$( '<li>' )
				.append( $( '<span>' ).addClass( 'key alias' ).text( key ) )
				.append( '⇢' )
				.append( $( '<span>' ).addClass( 'key' ).text( keyAliases[ key ] ) )
		);
	}

	// Events

	$( document.body ).on( {
		keydown: function ( e ) {
			// eslint-disable-next-line no-jquery/no-global-selector
			$( '.active' ).removeClass( 'active' );
			setTrigger( new ve.ui.Trigger( e ) );
			e.preventDefault();
		}
	} );
	// eslint-disable-next-line no-jquery/no-global-selector
	$( '#primary .key, #modifiers .key' ).on( {
		mousedown: function ( e ) {
			var $target = $( e.target );
			if ( e.which === OO.ui.MouseButtons.LEFT ) {
				if ( $target.closest( '#primary' ).length ) {
					$primary.find( '.active' ).removeClass( 'active' );
				}
				if ( !$target.hasClass( 'active' ) ) {
					$target.addClass( 'active activating' );
				}
			}
		},
		mouseup: function ( e ) {
			var parts = [],
				$target = $( e.target );
			if ( e.which === OO.ui.MouseButtons.LEFT ) {
				if ( $target.hasClass( 'active' ) && !$target.hasClass( 'activating' ) ) {
					$target.removeClass( 'active' );
				}
				$target.removeClass( 'activating' );
				// eslint-disable-next-line no-jquery/no-global-selector
				$( '.active' ).each( function () {
					parts.push( $( this ).attr( 'rel' ) );
				} );
				setTrigger( new ve.ui.Trigger( parts.join( '+' ) ) );
			}
		}
	} );
}() );
