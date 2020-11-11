/**
 * Collapsible Tabs for the Vector skin.
 *
 * @class jQuery.plugin.collapsibleTabs
 */
( function ( $ ) {
	var isRTL = document.documentElement.dir === 'rtl',
		boundEvent = false,
		rAF = window.requestAnimationFrame || setTimeout;

	/**
	 * @event beforeTabCollapse
	 */

	/**
	 * @event afterTabCollapse
	 */

	/**
	 * @param {Object} [options]
	 * @param {string} [options.expandedContainer="#p-views ul"] List of tabs
	 * @param {string} [options.collapsedContainer="#p-cactions ul"] List of menu items
	 * @param {string} [options.collapsible="li.collapsible"] Match tabs that are collapsible
	 * @param {Function} [options.expandCondition]
	 * @param {Function} [options.collapseCondition]
	 * @return {jQuery}
	 * @chainable
	 */
	$.fn.collapsibleTabs = function ( options ) {
		// Merge options into the defaults
		var settings = $.extend( {}, $.collapsibleTabs.defaults, options );

		// return if the function is called on an empty jquery object
		if ( !this.length ) {
			return this;
		}

		this.each( function () {
			var $el = $( this );
			// add the element to our array of collapsible managers
			$.collapsibleTabs.instances.push( $el );
			// attach the settings to the elements
			$el.data( 'collapsibleTabsSettings', settings );
			// attach data to our collapsible elements
			$el.children( settings.collapsible ).each( function () {
				$.collapsibleTabs.addData( $( this ) );
			} );
		} );

		// if we haven't already bound our resize handler, bind it now
		if ( !boundEvent ) {
			boundEvent = true;
			$( window ).on( 'resize', $.debounce( 100, function () {
				rAF( $.collapsibleTabs.handleResize );
			} ) );
		}

		// call our resize handler to setup the page
		rAF( $.collapsibleTabs.handleResize );
		return this;
	};
	$.collapsibleTabs = {
		instances: [],
		defaults: {
			expandedContainer: '#p-views ul',
			collapsedContainer: '#p-cactions ul',
			collapsible: 'li.collapsible',
			shifting: false,
			expandCondition: function ( eleWidth ) {
				// If there are at least eleWidth + 1 pixels of free space, expand.
				// We add 1 because .width() will truncate fractional values but .offset() will not.
				return $.collapsibleTabs.calculateTabDistance() >= eleWidth + 1;
			},
			collapseCondition: function () {
				// If there's an overlap, collapse.
				return $.collapsibleTabs.calculateTabDistance() < 0;
			}
		},
		addData: function ( $collapsible ) {
			var settings = $collapsible.parent().data( 'collapsibleTabsSettings' );
			if ( settings ) {
				$collapsible.data( 'collapsibleTabsSettings', {
					expandedContainer: settings.expandedContainer,
					collapsedContainer: settings.collapsedContainer,
					expandedWidth: $collapsible.width()
				} );
			}
		},
		getSettings: function ( $collapsible ) {
			var settings = $collapsible.data( 'collapsibleTabsSettings' );
			if ( !settings ) {
				$.collapsibleTabs.addData( $collapsible );
				settings = $collapsible.data( 'collapsibleTabsSettings' );
			}
			return settings;
		},
		handleResize: function () {
			$.each( $.collapsibleTabs.instances, function ( i, $el ) {
				var data = $.collapsibleTabs.getSettings( $el );
				if ( data.shifting ) {
					return;
				}

				// if the two navigations are colliding
				if ( $el.children( data.collapsible ).length && data.collapseCondition() ) {
					$el.trigger( 'beforeTabCollapse' );
					// move the element to the dropdown menu
					$.collapsibleTabs.moveToCollapsed( $el.children( data.collapsible + ':last' ) );
				}

				// if there are still moveable items in the dropdown menu,
				// and there is sufficient space to place them in the tab container
				if ( $( data.collapsedContainer + ' ' + data.collapsible ).length &&
						data.expandCondition( $.collapsibleTabs.getSettings( $( data.collapsedContainer ).children(
							data.collapsible + ':first' ) ).expandedWidth ) ) {
					// move the element from the dropdown to the tab
					$el.trigger( 'beforeTabExpand' );
					$.collapsibleTabs
						.moveToExpanded( data.collapsedContainer + ' ' + data.collapsible + ':first' );
				}
			} );
		},
		moveToCollapsed: function ( $moving ) {
			var outerData, expContainerSettings, target;

			outerData = $.collapsibleTabs.getSettings( $moving );
			if ( !outerData ) {
				return;
			}
			expContainerSettings = $.collapsibleTabs.getSettings( $( outerData.expandedContainer ) );
			if ( !expContainerSettings ) {
				return;
			}
			expContainerSettings.shifting = true;

			// Remove the element from where it's at and put it in the dropdown menu
			target = outerData.collapsedContainer;
			$moving.css( 'position', 'relative' )
				.css( ( isRTL ? 'left' : 'right' ), 0 )
				.animate( { width: '1px' }, 'normal', function () {
					$( this ).hide();
					// add the placeholder
					$( '<span class="placeholder" style="display: none;"></span>' ).insertAfter( this );
					$( this ).detach().prependTo( target ).data( 'collapsibleTabsSettings', outerData );
					$( this ).attr( 'style', 'display: list-item;' );
					expContainerSettings.shifting = false;
					rAF( $.collapsibleTabs.handleResize );
				} );
		},
		moveToExpanded: function ( ele ) {
			var data, expContainerSettings, $target, expandedWidth,
				$moving = $( ele );

			data = $.collapsibleTabs.getSettings( $moving );
			if ( !data ) {
				return;
			}
			expContainerSettings = $.collapsibleTabs.getSettings( $( data.expandedContainer ) );
			if ( !expContainerSettings ) {
				return;
			}
			expContainerSettings.shifting = true;

			// grab the next appearing placeholder so we can use it for replacing
			$target = $( data.expandedContainer ).find( 'span.placeholder:first' );
			expandedWidth = data.expandedWidth;
			$moving.css( 'position', 'relative' ).css( ( isRTL ? 'right' : 'left' ), 0 ).css( 'width', '1px' );
			$target.replaceWith(
				$moving
					.detach()
					.css( 'width', '1px' )
					.data( 'collapsibleTabsSettings', data )
					.animate( { width: expandedWidth + 'px' }, 'normal', function () {
						$( this ).attr( 'style', 'display: block;' );
						rAF( function () {
							// Update the 'expandedWidth' in case someone was brazen enough to change the tab's
							// contents after the page load *gasp* (T71729). This doesn't prevent a tab from
							// collapsing back and forth once, but at least it won't continue to do that forever.
							data.expandedWidth = $moving.width();
							$moving.data( 'collapsibleTabsSettings', data );
							expContainerSettings.shifting = false;
							$.collapsibleTabs.handleResize();
						} );
					} )
			);
		},
		/**
		 * Get the amount of horizontal distance between the two tabs groups in pixels.
		 *
		 * Uses `#left-navigation` and `#right-navigation`. If negative, this
		 * means that the tabs overlap, and the value is the width of overlapping
		 * parts.
		 *
		 * Used in default `expandCondition` and `collapseCondition` options.
		 *
		 * @return {number} distance/overlap in pixels
		 */
		calculateTabDistance: function () {
			var leftTab, rightTab, leftEnd, rightStart;

			// In RTL, #right-navigation is actually on the left and vice versa.
			// Hooray for descriptive naming.
			if ( !isRTL ) {
				leftTab = document.getElementById( 'left-navigation' );
				rightTab = document.getElementById( 'right-navigation' );
			} else {
				leftTab = document.getElementById( 'right-navigation' );
				rightTab = document.getElementById( 'left-navigation' );
			}

			leftEnd = leftTab.getBoundingClientRect().right;
			rightStart = rightTab.getBoundingClientRect().left;
			return rightStart - leftEnd;
		}
	};

	/**
	 * @class jQuery
	 * @mixins jQuery.plugin.collapsibleTabs
	 */

}( jQuery ) );
