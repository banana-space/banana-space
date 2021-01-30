/*!
 * Contains base FlowComponent class.
 */

( function () {
	var _totalInstanceCount = 0;

	/**
	 * Inherited base class. Stores the instance in the class's instance registry.
	 *
	 * @class FlowComponent
	 * @param {jQuery} $container
	 * @mixins FlowComponentEventsMixin
	 * @mixins FlowComponentEnginesMixin
	 * @mixins FlowComponentMenusFeatureMixin
	 * @constructor
	 */
	function FlowComponent( $container ) {
		var parent = this.constructor.super;

		// Run progressive enhancements if any are needed by this container
		mw.flow.TemplateEngine.processProgressiveEnhancement( $container );

		// Store the container for later use
		this.$container = $container;

		// Get this component's ID
		this.id = $container.data( 'flow-id' );
		if ( !this.id ) {
			// Generate an ID for this component
			this.id = 'flow-generated-' + _totalInstanceCount;
			$container.data( 'flow-id', this.id );
			// @todo throw an exception here instead of generating an id?
		} else if ( this.getInstanceByElement( $container ) ) {
			// Check if this board was already instantiated, and return that instead
			return this.getInstanceByElement( $container );
		}

		// Give this board its own API instance @todo do this with OOjs
		this.Api = new mw.flow.FlowApi( this.id );

		// Keep this in the registry to find it by other means
		while ( parent ) {
			parent._instanceRegistryById[ this.id ] = parent._instanceRegistry.push( this ) - 1;
			parent = parent.super; // and add it to every instance registry
		}
		_totalInstanceCount++;
	}
	OO.initClass( FlowComponent );

	//
	// PROTOTYPE METHODS
	//

	/**
	 * Takes any length of arguments, and passes it off to console.log.
	 *
	 * @param {boolean} [isError=true]
	 */
	mw.flow.debug = FlowComponent.prototype.debug = function ( isError ) {
		var args;
		/* eslint-disable no-console */
		if ( window.console ) {
			args = Array.prototype.slice.call( arguments, 0 );

			if ( typeof isError === 'boolean' ) {
				args.shift();
			} else {
				isError = true;
			}

			args.unshift( '[FLOW] ' );

			if ( isError && console.error ) {
				// If console.error is supported, send that, because it gives a stack trace
				console.error.apply( console, args );
			}

			// Otherwise, use console.log
			console.log.apply( console, args );
		}
		/* eslint-enable no-console */
	};

	/**
	 * Converts a Flow UUID to a UNIX timestamp.
	 *
	 * Example: sfhzxr5a00jkf405 -> 1429101316919
	 *
	 * @param {string} uuid
	 * @return {number} UNIX time
	 */
	mw.flow.uuidToTime = FlowComponent.prototype.uuidToTime = function ( uuid ) {

		var timestamp,
			_expandScientificNotation = function ( timestamp ) {
				var parts, first, zeroes;

				if ( timestamp.indexOf( 'e' ) !== -1 ) {
					parts = timestamp.split( '(e+' );
					first = parts[ 0 ].replace( '.', '' );
					zeroes = parseInt( parts[ 1 ], 10 ) - ( first.length - 1 );
					first += Array( zeroes + 1 ).join( '0' );

					return first;
				}

				return timestamp;
			};

		timestamp = parseInt( uuid, 36 ).toString( 2 ); // Parse from base-36, then serialize to base-2

		// IE's toString converts large numbers to scientific notation ( 1.1001110011(e+23) )
		timestamp = _expandScientificNotation( timestamp );

		timestamp = Array( 88 + 1 - timestamp.length ).join( '0' ) + timestamp; // left pad 0 to 88 chars
		timestamp = parseInt( timestamp.substr( 0, 46 ), 2 ); // first 46 chars base-2 to base-10

		return timestamp;
	};

	/**
	 * Returns all the registered instances of a given FlowComponent.
	 *
	 * @return {FlowComponent[]}
	 */
	FlowComponent.prototype.getInstances = function () {
		// Use the correct context (instance vs prototype)
		return ( this.constructor.super || this )._instanceRegistry;
	};

	/**
	 * Goes up the DOM tree to find which FlowComponent $el belongs to, via .flow-component[flow-id].
	 *
	 * @param {jQuery} $el
	 * @return {FlowComponent|boolean}
	 */
	FlowComponent.prototype.getInstanceByElement = function ( $el ) {
		var $container = $el.closest( '.flow-component' ),
			context = this.constructor.super || this, // Use the correct context (instance vs prototype)
			id;

		// This element isn't _within_ any actual component; was it spawned _by_ a component?
		if ( !$container.length ) {
			// Find any parents of this element with the flowSpawnedBy data attribute
			$container = $el.parents().addBack().filter( function () {
				return $( this ).data( 'flowSpawnedBy' );
			} ).last()
				// Get the flowSpawnedBy node
				.data( 'flowSpawnedBy' );
			// and then return the closest flow-component of it
			$container = $container ? $container.closest( '.flow-component' ) : $();
		}

		// Still no parent component. Crap out!
		if ( !$container.length ) {
			mw.flow.debug( 'Failed to getInstanceByElement: no $container.length', arguments );
			return false;
		}

		id = $container.data( 'flow-id' );

		return context._instanceRegistry[ context._instanceRegistryById[ id ] ] || false;
	};

	/**
	 * Sets the FlowComponent's $container element as the data-flow-spawned-by attribute on $el.
	 * Fires ALL events from within $el onto $eventTarget, albeit with the whole event intact.
	 * This allows us to listen for events from outside of FlowComponent's nodes, but still trigger them within.
	 *
	 * @param {jQuery} $el
	 * @param {jQuery} [$eventTarget]
	 */
	FlowComponent.prototype.assignSpawnedNode = function ( $el, $eventTarget ) {
		// Target defaults to .flow-component
		$eventTarget = $eventTarget || this.$container;

		// Assign flowSpawnedBy data attribute
		$el.data( 'flowSpawnedBy', $eventTarget );

		// Forward all events (except mouse movement) to $eventTarget
		$el.on(
			'blur change click dblclick error focus focusin focusout keydown keypress keyup load mousedown mouseenter mouseleave mouseup resize scroll select submit',
			'*',
			{ flowSpawnedBy: this.$container, flowSpawnedFrom: $el },
			function ( event ) {
				var i, $nodes;
				// Let's forward these events in an unusual way, similar to how jQuery propagates events...
				// First, only take the very first, top-level event, as the rest of the propagation is handled elsewhere
				if ( event.target === this ) {
					// Get all the parent nodes of our target,
					// but do not include any nodes we will already be bubbling up to (eg. body)
					$nodes = $eventTarget.parents().addBack().not( $( this ).parents().addBack() );
					i = $nodes.length;

					// For every node between $eventTarget and window that was not filtered out above...
					while ( i-- ) {
						// Trigger a bubbling event on each one, with the correct context
						_eventForwardDispatch.call( $nodes[ i ], event, $el[ 0 ] );
					}
				}
			}
		);
	};

	//
	// PRIVATE FUNCTIONS
	//

	/**
	 * This method is mostly cloned from jQuery.event.dispatch, except that it has been modified to use container
	 * as its base for finding event handlers (via jQuery.event.handlers). This allows us to trigger events on said
	 * container (and its parents, bubbling up), as if the event originated from within it.
	 * jQuery itself doesn't allow for this, as the context (this & event.currentTarget) become the actual element you
	 * are triggering an event on, instead of the element which matched the selector.
	 *
	 *     _eventForwardDispatch.call( Element, Event, Element );
	 *
	 * @param {jQuery.Event} event
	 * @param {HTMLElement} container
	 * @return {*}
	 * @private
	 */
	function _eventForwardDispatch( event, container ) {
		var i, ret, handleObj, matched, j,
			args, handlers, special,
			handlerQueue = [];

		// Make a writable jQuery.Event from the native event object
		event = $.event.fix( event );
		args = Array.prototype.slice.call( arguments, 0 );
		handlers = ( $._data( this, 'events' ) || {} )[ event.type ] || [];
		special = $.event.special[ event.type ] || {};

		// Use the fix-ed jQuery.Event rather than the (read-only) native event
		args[ 0 ] = event;
		event.delegateTarget = this;

		// Call the preDispatch hook for the mapped type, and let it bail if desired
		if ( special.preDispatch && special.preDispatch.call( this, event ) === false ) {
			return;
		}

		// Determine handlers
		// The important modification: we use container instead of this as the context
		handlerQueue = $.event.handlers.call( container, event, handlers );

		// Run delegates first; they may want to stop propagation beneath us
		i = 0;
		while ( ( matched = handlerQueue[ i++ ] ) && !event.isPropagationStopped() ) {
			event.currentTarget = matched.elem;

			j = 0;
			while ( ( handleObj = matched.handlers[ j++ ] ) && !event.isImmediatePropagationStopped() ) {
				// Triggered event must either 1) have no namespace, or
				// 2) have namespace(s) a subset or equal to those in the bound event (both can have no namespace).
				if ( !event.namespace_re || event.namespace_re.test( handleObj.namespace ) ) {

					event.handleObj = handleObj;
					event.data = handleObj.data;

					ret = ( ( $.event.special[ handleObj.origType ] || {} ).handle || handleObj.handler )
						.apply( matched.elem, args );

					if ( ret !== undefined ) {
						if ( ( event.result = ret ) === false ) {
							event.preventDefault();
							event.stopPropagation();
						}
					}
				}
			}
		}

		// Call the postDispatch hook for the mapped type
		if ( special.postDispatch ) {
			special.postDispatch.call( this, event );
		}

		return event.result;
	}

	mw.flow.registerComponent( 'component', FlowComponent );
}() );
