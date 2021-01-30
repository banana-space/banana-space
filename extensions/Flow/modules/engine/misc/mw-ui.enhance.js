/*!
 * Enhances mediawiki-ui style elements with JavaScript.
 */

// Expose for the sake of tests
mw.flow.ui.enhance = {};

/** @class mw.ui.enhance */
( function () {
	/*
	* Reduce eye-wandering due to adjacent colorful buttons
	* This will make unhovered and unfocused sibling buttons become faded and blurred
	* Usage: Buttons must be in a form, or in a parent with mw-ui-button-container, or they must be siblings
	*/
	$( function () {
		function onMwUiButtonFocus( event ) {
			var $el, $form, $siblings;

			if ( event.target.className.indexOf( 'mw-ui-button' ) === -1 ) {
				// Not a button event
				return;
			}

			$el = $( event.target );

			if ( event.type !== 'keyup' || $el.is( ':focus' ) ) {
				// Reset style
				$el.removeClass( 'mw-ui-button-althover' );

				$form = $el.closest( 'form, .mw-ui-button-container' );
				if ( $form.length ) {
					// If this button is in a form, apply this to all the form's buttons.
					$siblings = $form.find( '.mw-ui-button' );
				} else {
					// Otherwise, try to find neighboring buttons
					$siblings = $el.siblings( '.mw-ui-button' );
				}

				// Add fade/blur to unfocused sibling buttons
				$siblings.not( $el ).filter( ':not(:focus)' )
					.addClass( 'mw-ui-button-althover' );
			}
		}

		function onMwUiButtonBlur( event ) {
			var $el, $form, $siblings, $focused;

			if ( event.target.className.indexOf( 'mw-ui-button' ) === -1 ) {
				// Not a button event
				return;
			}

			$el = $( event.target );
			$form = $el.closest( 'form, .mw-ui-button-container' );
			if ( $form.length ) {
				// If this button is in a form, apply this to all the form's buttons.
				$siblings = $form.find( '.mw-ui-button' );
			} else {
				// Otherwise, try to find neighboring buttons
				$siblings = $el.siblings( '.mw-ui-button' );
			}

			// Add fade/blur to unfocused sibling buttons
			$focused = $siblings.not( $el ).filter( ':focus' );

			if ( event.type === 'mouseleave' && $el.is( ':focus' ) ) {
				// If this button is still focused, but the mouse left it, keep siblings faded
				return;
			} else if ( $focused.length ) {
				// A sibling has focus; have it trigger the restyling
				$focused.trigger( 'mouseenter.mw-ui-enhance' );
			} else {
				// No other siblings are focused; removing button fading
				$siblings.removeClass( 'mw-ui-button-althover' );
			}
		}

		// Attach the mouseenter and mouseleave handlers on document
		$( document )
			.on( 'mouseenter.mw-ui-enhance', '.mw-ui-button', onMwUiButtonFocus )
			.on( 'mouseleave.mw-ui-enhance', '.mw-ui-button', onMwUiButtonBlur );

		// Attach these independently, because jQuery doesn't support useCapture mode (focus propagation)
		if ( document.attachEvent ) {
			document.attachEvent( 'focusin', onMwUiButtonFocus );
			document.attachEvent( 'focusout', onMwUiButtonBlur );
		} else {
			document.body.addEventListener( 'focus', onMwUiButtonFocus, true );
			document.body.addEventListener( 'blur', onMwUiButtonBlur, true );
		}
	} );

	/**
	 * Disables action and submit buttons when a form has required fields
	 *
	 * @param {jQuery} $form jQuery object corresponding to a form element.
	 */
	function enableFormWithRequiredFields( $form ) {
		var
			$fields = $form.find( 'input, textarea' ).filter( '[required]' ),
			ready = true;

		$fields.each( function () {
			if ( this.value === '' ) {
				ready = false;
			}
		} );

		// @todo scrap data-role? use submit types? or a single role=action?
		$form.find( '.mw-ui-button' ).filter( '[data-role=action], [data-role=submit]' )
			.prop( 'disabled', !ready );
	}
	// Expose for the sake of tests. This will be unnecessary and removed when all
	// instances of the editor are migrated to ooui
	mw.flow.ui.enhance.enableFormWithRequiredFields = enableFormWithRequiredFields;

	/*
	 * Disable / enable submit buttons without/with text in field.
	 * Usage: field needs required attribute
	 */
	$( function () {
		// We should probably not use this change detection method for VE
		//
		// Also, consider using the input event (which I think can replace all of these
		// and paste) when we drop IE 8.
		// https://developer.mozilla.org/en-US/docs/Web/Events/input
		// setTimeout works around paste firing before the field is modified.
		$( document ).on(
			// @todo change this to listen to emitted change event once/if everything is OOUI thingies
			'keyup.flow-actions-disabler cut.flow-actions-disabler paste.flow-actions-disabler',
			'.mw-ui-input, .oo-ui-textInputWidget input, .oo-ui-textInputWidget textarea',
			function () {
				var $el = $( this );

				setTimeout( function () {
					enableFormWithRequiredFields( $el.closest( 'form' ) );
				} );
			}
		);
	} );

	/*
	 * mw-ui-tooltip
	 * Renders tooltips on over, and also via mw.tooltip.
	 */
	$( function () {
		var $tooltipTemplate = $( '<span>' )
				.addClass( 'flow-ui-tooltip flow-ui-tooltip-left' )
				.append(
					$( '<span>' ).addClass( 'flow-ui-tooltip-content' ),
					$( '<span>' ).addClass( 'flow-ui-tooltip-triangle' ),
					$( '<span>' ).addClass( 'flow-ui-tooltip-close' )
				),
			$activeTooltips = $(),
			_mwUiTooltipExpireTimer;

		/**
		 * Renders a tooltip at target.
		 * Options (either given as param, or fetched from target as data-tooltip-x params):
		 *  tooltipSize=String (small,large,block)
		 *  tooltipContext=String (progressive,destructive)
		 *  tooltipPointing=String (up,down,left,right)
		 *  tooltipClosable=Boolean
		 *  tooltipContentCallback=Function
		 *
		 * @param {jQuery|HTMLElement} target
		 * @param {jQuery|HTMLElement|string} [content] A jQuery set, an element, or a string of
		 *  HTML.  If omitted, first tries tooltipContentCallback, then target.title
		 * @param {Object} [options]
		 * @return {jQuery}
		 */
		function mwUiTooltipShow( target, content, options ) {
			var $target = $( target ),
				// Find previous tooltip for this el
				$tooltip = $target.data( '$tooltip' ),

				// Get window size and scroll details
				windowWidth = $( window ).width(),
				windowHeight = $( window ).height(),
				scrollX = Math.max( window.pageXOffset, document.documentElement.scrollLeft, document.body.scrollLeft ),
				scrollY = Math.max( window.pageYOffset, document.documentElement.scrollTop, document.body.scrollTop ),

				// Store target and tooltip details
				tooltipWidth, tooltipHeight,
				targetPosition,
				locationOrder, tooltipLocation = {},
				insertFn = 'append',

				// Options, no longer by objet reference
				optionsUnreferenced = {},

				i = 0;

			options = options || {};
			// Do this so that we don't alter the data object by reference
			optionsUnreferenced.tooltipSize = options.tooltipSize || $target.data( 'tooltipSize' );
			optionsUnreferenced.tooltipContext = options.tooltipContext || $target.data( 'tooltipContext' );
			optionsUnreferenced.tooltipPointing = options.tooltipPointing || $target.data( 'tooltipPointing' );
			optionsUnreferenced.tooltipContentCallback = options.tooltipContentCallback || $target.data( 'tooltipContentCallback' );
			// @todo closable
			optionsUnreferenced.tooltipClosable = options.tooltipClosable || $target.data( 'tooltipClosable' );

			// Support passing jQuery as argument
			target = $target[ 0 ];

			if ( !content ) {
				if ( optionsUnreferenced.tooltipContentCallback ) {
					// Use content callback to get the content for this element
					content = optionsUnreferenced.tooltipContentCallback( target, optionsUnreferenced );

					if ( !content ) {
						return false;
					}
				} else {
					// Check to see if we're simply using target.title as the content
					if ( !target.title ) {
						return false;
					}

					content = target.title;
					$target.data( 'tooltipTitle', content ); // store title
					target.title = ''; // and hide it so it doesn't appear
					insertFn = 'text';

					if ( !optionsUnreferenced.tooltipSize ) {
						// Default size for title tooltip is small
						optionsUnreferenced.tooltipSize = 'small';
					}
				}
			}

			// No previous tooltip
			if ( !$tooltip ) {
				// See if content itself is a tooltip
				try {
					if ( typeof content === 'string' ) {
						$tooltip = $( $.parseHTML( content ) );
					} else {
						$tooltip = $( content );
					}
				} catch ( e ) {}
				if ( !$tooltip || !$tooltip.is( '.flow-ui-tooltip' ) && !$tooltip.find( '.flow-ui-tooltip' ).length ) {
					// Content is not and does not contain a tooltip, so instead, put content inside a new tooltip wrapper
					$tooltip = $tooltipTemplate.clone();
				}
			}

			// Try to inherit tooltipContext from the target's classes
			if ( !optionsUnreferenced.tooltipContext ) {
				// eslint-disable-next-line no-jquery/no-class-state
				if ( $target.hasClass( 'mw-ui-progressive' ) ) {
					optionsUnreferenced.tooltipContext = 'progressive';
				// eslint-disable-next-line no-jquery/no-class-state
				} else if ( $target.hasClass( 'mw-ui-destructive' ) ) {
					optionsUnreferenced.tooltipContext = 'destructive';
				}
			}

			$tooltip
				// Add the content to it
				.find( '.flow-ui-tooltip-content' )
				.empty()[ insertFn ]( content );
			$tooltip
				// Move this off-page before rendering it, so that we can calculate its real dimensions
				// @todo use .parent() loop to check for z-index and + that to this if needed
				.css( { position: 'absolute', zIndex: 1000, top: 0, left: '-999em' } )
				// Render
				// @todo inject at #bodyContent to inherit (font-)styling
				.appendTo( document.body );

			// Tooltip style context
			if ( optionsUnreferenced.tooltipContext ) {
				$tooltip.removeClass( 'mw-ui-progressive mw-ui-destructive' );
				// Classes documented above
				// eslint-disable-next-line mediawiki/class-doc
				$tooltip.addClass( 'mw-ui-' + optionsUnreferenced.tooltipContext );
			}

			// Tooltip size (small, large)
			if ( optionsUnreferenced.tooltipSize ) {
				$tooltip.removeClass( 'flow-ui-tooltip-sm flow-ui-tooltip-lg' );
				// Classes documented above
				// eslint-disable-next-line mediawiki/class-doc
				$tooltip.addClass( 'flow-ui-tooltip-' + optionsUnreferenced.tooltipSize );
			}

			// Remove the old pointing direction
			$tooltip.removeClass( 'flow-ui-tooltip-up flow-ui-tooltip-down flow-ui-tooltip-left flow-ui-tooltip-right' );

			// tooltip width and height with the new content
			tooltipWidth = $tooltip.outerWidth( true );
			tooltipHeight = $tooltip.outerHeight( true );

			// target positioning info
			targetPosition = $target.offset();
			targetPosition.width = $target.outerWidth( true );
			targetPosition.height = $target.outerHeight( true );
			targetPosition.leftEnd = targetPosition.left + targetPosition.width;
			targetPosition.topEnd = targetPosition.top + targetPosition.height;
			targetPosition.leftMiddle = targetPosition.left + targetPosition.width / 2;
			targetPosition.topMiddle = targetPosition.top + targetPosition.height / 2;

			// Use the preferred pointing direction first
			switch ( optionsUnreferenced.tooltipPointing ) {
				case 'left':
					locationOrder = [ 'left', 'right', 'left' ];
					break;
				case 'right':
					locationOrder = [ 'right', 'left', 'right' ];
					break;
				case 'down':
					locationOrder = [ 'down', 'up', 'down' ];
					break;
				default:
					locationOrder = [ 'up', 'down', 'up' ];
					break;
			}

			do {
				// Position of the POINTER, not the tooltip itself
				switch ( locationOrder[ i ] ) {
					case 'left':
						tooltipLocation.left = targetPosition.leftEnd;
						tooltipLocation.top = targetPosition.topMiddle - tooltipHeight / 2;
						break;
					case 'right':
						tooltipLocation.left = targetPosition.left - tooltipWidth;
						tooltipLocation.top = targetPosition.topMiddle - tooltipHeight / 2;
						break;
					case 'down':
						tooltipLocation.left = targetPosition.leftMiddle - tooltipWidth / 2;
						tooltipLocation.top = targetPosition.top - tooltipHeight;
						break;
					case 'up':
						tooltipLocation.left = targetPosition.leftMiddle - tooltipWidth / 2;
						tooltipLocation.top = targetPosition.topEnd;
						break;
				}

				// Verify tooltip will be mostly visible in viewport
				if (
					tooltipLocation.left > scrollX - 5 &&
					tooltipLocation.top > scrollY - 5 &&
					tooltipLocation.left + tooltipWidth < windowWidth + scrollX + 5 &&
					tooltipLocation.top + tooltipHeight < windowHeight + scrollY + 5
				) {
					break;
				}
				if ( i + 1 === locationOrder.length ) {
					break;
				}
			} while ( ++i <= locationOrder.length );

			// Add the pointing direction class from the loop
			// Classes documented above
			// eslint-disable-next-line mediawiki/class-doc
			$tooltip.addClass( 'flow-ui-tooltip-' + locationOrder[ i ] );

			// Apply the new location CSS
			$tooltip.css( tooltipLocation );

			// Store this tooltip onto target
			$target.data( '$tooltip', $tooltip );
			// Store this target onto tooltip
			$tooltip.data( '$target', $target );
			// Add this tooltip to our set of active tooltips
			$activeTooltips = $activeTooltips.add( $tooltip );

			// Start the expiry timer
			_mwUiTooltipExpire();

			return $tooltip;
		}

		/**
		 * Hides the tooltip associated with target instantly.
		 *
		 * @param {HTMLElement|jQuery} target
		 */
		function mwUiTooltipHide( target ) {
			var $target = $( target ),
				$tooltip = $target.data( '$tooltip' ),
				tooltipTitle = $target.data( 'tooltipTitle' );

			// Remove tooltip from DOM
			if ( $tooltip ) {
				$target.removeData( '$tooltip' );
				$activeTooltips = $activeTooltips.not( $tooltip );
				$tooltip.remove();
			}

			// Restore old title; was used for tooltip
			if ( tooltipTitle ) {
				$target[ 0 ].title = tooltipTitle;
				$target.removeData( 'tooltipTitle' );
			}
		}

		/**
		 * Runs on a timer to expire tooltips. This is useful in scenarios where a tooltip's target
		 * node has disappeared (removed from page), and didn't trigger a mouseout event. We detect
		 * the target disappearing, and as such remove the tooltip node.
		 */
		function _mwUiTooltipExpire() {
			clearTimeout( _mwUiTooltipExpireTimer );

			$activeTooltips.each( function () {
				var $this = $( this ),
					$target = $this.data( '$target' );

				// Remove the tooltip if this tooltip has been removed,
				// or if target is not visible (hidden or removed from DOM)
				// eslint-disable-next-line no-jquery/no-sizzle
				if ( !this.parentNode || !$target.is( ':visible' ) ) {
					// Remove the tooltip from the DOM
					$this.remove();
					// Unset tooltip from target
					$target.removeData( '$tooltip' );
					// Remove the tooltip from our active tooltips list
					$activeTooltips = $activeTooltips.not( $this );
				}
			} );

			if ( $activeTooltips.length ) {
				// Check again in 500ms if we still have active tooltips
				_mwUiTooltipExpireTimer = setTimeout( _mwUiTooltipExpire, 500 );
			}
		}

		/**
		 * MW UI Tooltip access through JS API.
		 */
		mw.tooltip = {
			show: mwUiTooltipShow,
			hide: mwUiTooltipHide
		};

		/**
		 * Event handler for mouse entering on a .flow-ui-tooltip-target
		 *
		 * @param {jQuery.Event} event
		 */
		function onMwUiTooltipFocus() {
			mw.tooltip.show( this );
		}

		/**
		 * Event handler for mouse leaving a .flow-ui-tooltip-target
		 *
		 * @param {jQuery.Event} event
		 */
		function onMwUiTooltipBlur() {
			mw.tooltip.hide( this );
		}

		// Attach the mouseenter and mouseleave handlers on document
		$( document )
			.on( 'mouseenter.mw-ui-enhance focus.mw-ui-enhance', '.flow-ui-tooltip-target', onMwUiTooltipFocus )
			.on( 'mouseleave.mw-ui-enhance blur.mw-ui-enhance click.mw-ui-enhance', '.flow-ui-tooltip-target', onMwUiTooltipBlur );
	} );
}() );
