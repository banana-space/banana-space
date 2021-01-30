/*!
 * mw-ui-modal
 * Implements mw.Modal functionality.
 */

( function () {
	// Make it easier to remove this later on, should it be implemented in Core
	if ( mw.Modal ) {
		return;
	}

	/**
	 * Accepts an element or HTML string as contents. If none given,
	 * modal will start in hidden state.
	 * Settings keys:
	 * - open (same arguments as open method)
	 * - title string
	 * - disableCloseOnOutsideClick boolean (if true, ESC and background clicks do not close it)
	 *
	 * Simple modal:
	 *
	 *     @example
	 *     modal1 = mw.Modal();
	 *
	 * Modal with contents and title:
	 *
	 *     @example
	 *     modal2 = mw.Modal( { open: 'Contents!!', title: 'Title!!' } );
	 *
	 * Named modal:
	 *
	 *     @example
	 *     modal3 = mw.Modal( 'special_modal' );
	 *
	 * @todo Implement multi-step
	 * @todo Implement data-mwui handlers
	 * @todo Implement OOjs & events
	 * @class MwUiModal
	 * @constructor
	 * @param {string} [name] Name of modal (may be omitted)
	 * @param {Object} [settings]
	 */
	function MwUiModal( name, settings ) {
		// allow calling this method with or without "new" keyword
		if ( this.constructor !== MwUiModal ) {
			return new MwUiModal( name, settings );
		}

		// Defaults and ordering
		if ( !settings && typeof name === 'object' ) {
			settings = name;
			name = null;
		}
		settings = settings || {};

		// Set name
		this.name = name;

		// Set title
		this.setTitle( settings.title );

		// Set disableCloseOnOutsideClick
		this.disableCloseOnOutsideClick = !!settings.disableCloseOnOutsideClick;

		// Auto-open
		if ( settings.open ) {
			this.open( settings.open );
		}

		return this;
	}

	/** Stores template
	 *
	 * @todo use data-mwui attributes instead of data-flow **/
	MwUiModal.prototype.template =
		'<div class="flow-ui-modal">' +
		'<div class="flow-ui-modal-layout">' +
		'<div class="flow-ui-modal-heading">' +
		'<a href="#" class="mw-ui-anchor mw-ui-quiet mw-ui-destructive flow-ui-modal-heading-prev" data-flow-interactive-handler="modalPrevOrClose"><span class="mw-ui-icon mw-ui-icon-before mw-ui-icon-close"></span></a>' +
		'<a href="#" class="mw-ui-anchor mw-ui-quiet mw-ui-progressive flow-ui-modal-heading-next" data-flow-interactive-handler="modalNextOrSubmit"><span class="mw-ui-icon mw-ui-icon-before mw-ui-icon-check"></span></a>' +
		// title
		'</div>' +

		'<div class="flow-ui-modal-content">' +
		// content
		'</div>' +
		'</div>' +
		'</div>';

	/** Stores modal wrapper selector **/
	MwUiModal.prototype.wrapperSelector = '.flow-ui-modal';
	/** Stores content wrapper selector **/
	MwUiModal.prototype.contentSelector = '.flow-ui-modal-content';
	/** Stores heading wrapper selector, which contains prev/next links **/
	MwUiModal.prototype.headingSelector = '.flow-ui-modal-heading';
	/** Stores prev link selector **/
	MwUiModal.prototype.prevSelector = '.flow-ui-modal-heading-prev';
	/** Stores next link selector **/
	MwUiModal.prototype.nextSelector = '.flow-ui-modal-heading-next';

	// Primary functions

	/**
	 * Closes and destroys the given instance of mw.Modal.
	 *
	 * @return {boolean} false on failure, true on success
	 */
	MwUiModal.prototype.close = function () {
		// Remove references
		this._contents = this._title = null;

		if ( this.$node ) {
			// Remove whole thing from page
			this.getNode().remove();

			return true;
		}

		return false;
	};

	/**
	 * You can visually render the modal using this method. Opens up by displaying it on the page.
	 *
	 * - Multi-step modals with an Array. You can pass [ Element, Element ] to have two steps.
	 * - Multi-step modals with an Object to have named step keys. Pass this for three steps:
	 *   { steps: [ 'first', 'second', 'foobar' ], first: Element, second: Element, foobar: Element }
	 *
	 * @todo Currently only supports string|jQuery|HTMLElement. Implement multi-step modals.
	 *
	 * @param {Object|HTMLElement|HTMLElement[]|jQuery|string} [contents]
	 * @return {MwUiModal}
	 */
	MwUiModal.prototype.open = function ( contents ) {
		var $node = this.getNode(),
			$contentNode = this.getContentNode(),
			$fields;

		// Only update content if it's new
		if ( contents && contents !== this._contents ) {
			this._contents = contents;

			$contentNode
				// Remove children (this way we can unbind events)
				.children()
				.remove()
				.end()
				// Remove any plain text left over
				.empty()
				// Add the new content
				.append( contents );
		}

		// Drop it into the page
		$node.appendTo( document.body );

		// Hide the tick box @todo implement multi-step and event handling / form binding
		$node.find( this.nextSelector ).hide();

		// If something in here did not auto-focus, let's focus something
		// eslint-disable-next-line no-jquery/no-sizzle
		$fields = $node.find( 'textarea, input, select' ).filter( ':visible' );
		if ( !$fields.filter( ':focus' ).length ) {
			// Try to focus on an autofocus field
			$fields = $fields.filter( '[autofocus]' );
			if ( $fields.length ) {
				$fields.trigger( 'focus' );
			} else {
				// Try to focus on ANY input
				$fields = $fields.end().first();
				if ( $fields.length ) {
					$fields.trigger( 'focus' );
				} else {
					// Give focus to the wrapper itself
					$node.trigger( 'focus' );
				}
			}
		}

		return this;
	};

	/**
	 * Changes the title of the modal.
	 *
	 * @param {string|null} title
	 * @return {MwUiModal}
	 */
	MwUiModal.prototype.setTitle = function ( title ) {
		var $heading = this.getNode().find( this.headingSelector ),
			$children;

		title = title || '';

		// Only update title if it's new
		if ( title !== this._title ) {
			this._title = title;

			// Remove any element children temporarily, so we can set the title here
			$children = $heading.children().detach();

			$heading
				// Set the new title
				.text( title )
				// Add the child nodes back
				.prepend( $children );
		}

		// Show the heading if there's a title; hide otherwise
		$heading[ title ? 'show' : 'hide' ]();

		return this;
	};

	/**
	 * @todo Implement data-mwui handlers, currently using data-flow
	 * @return {boolean}
	 */
	MwUiModal.prototype.setInteractiveHandler = function () {
		return false;
	};

	/**
	 * Returns modal name.
	 *
	 * @return {string}
	 */
	MwUiModal.prototype.getName = function () {
		return this.name;
	};

	// Nodes

	/**
	 * Returns the modal's wrapper Element, which contains the header node and content node.
	 *
	 * @return {jQuery}
	 */
	MwUiModal.prototype.getNode = function () {
		var self = this,
			$node = this.$node;

		// Create our template instance
		if ( !$node ) {
			$node = this.$node = $( this.template );

			// Store a self-reference
			$node.data( 'MwUiModal', this );

			// Bind close handlers
			$node.on( 'click', function ( event ) {
				// If we are clicking on the modal itself, it's the outside area, so close it;
				// make sure we aren't clicking INSIDE the modal content!
				if ( !self.disableCloseOnOutsideClick && this === $node[ 0 ] && event.target === $node[ 0 ] ) {
					self.close();
				}
			} );
		}

		return $node;
	};

	/**
	 * Returns the wrapping Element on which you can bind bubbling events for your content.
	 *
	 * @return {jQuery}
	 */
	MwUiModal.prototype.getContentNode = function () {
		return this.getNode().find( this.contentSelector );
	};

	// Step creation

	/**
	 * Adds one or more steps, using the same arguments as modal.open.
	 * May overwrite steps if any exist with the same key in Object mode.
	 *
	 * @todo Implement multi-step.
	 *
	 * @param {Object|HTMLElement|HTMLElement[]|jQuery|string} contents
	 * @return {MwUiModal}
	 */
	MwUiModal.prototype.addSteps = function () {
		return false;
	};

	/**
	 * Changes a given step. If String to does not exist in the list of steps, throws an exception;
	 * int to always succeeds. If the given step is the currently-active one, rerenders the modal contents.
	 * Theoretically, you could use setStep to keep changing step 1 to create a pseudo-multi-step modal.
	 *
	 * @todo Implement multi-step.
	 *
	 * @param {number|string} to
	 * @param {HTMLElement|jQuery|string} contents
	 * @return {MwUiModal}
	 */
	MwUiModal.prototype.setStep = function () {
		return false;
	};

	/**
	 * Returns an Object with steps, and their contents.
	 *
	 * @todo Implement multi-step.
	 *
	 * @return {Object}
	 */
	MwUiModal.prototype.getSteps = function () {
		return {};
	};

	// Step interaction

	/**
	 * For a multi-step modal, goes to the previous step, otherwise, closes the modal.
	 *
	 * @return {MwUiModal|boolean} false if none, MwUiModal on prev, true on close
	 */
	MwUiModal.prototype.prevOrClose = function () {
		if ( this.prev() === false ) {
			return this.close();
		}
	};

	/**
	 * For a multi-step modal, goes to the next step (if any), otherwise, submits the form.
	 *
	 * @return {MwUiModal|boolean} false if no next step and no button to click, MwUiModal on success
	 */
	MwUiModal.prototype.nextOrSubmit = function () {
		var $button;

		if ( this.next() === false && this.$node ) {
			// Find an anchor or button with role=primary
			// eslint-disable-next-line no-jquery/no-sizzle
			$button = this.$node.find( this.contentSelector ).find( 'a, input, button' ).filter( ':visible' ).filter( '[type=submit], [data-role=submit]' );

			if ( !$button.length ) {
				return false;
			}

			$button.trigger( 'click' );
		}
	};

	/**
	 * For a multi-step modal, goes to the previous step, if any are left.
	 *
	 * @todo Implement multi-step.
	 *
	 * @return {MwUiModal|boolean} false if invalid step, MwUiModal on success
	 */
	MwUiModal.prototype.prev = function () {
		return false;
	};

	/**
	 * For a multi-step modal, goes to the next step, if any are left.
	 *
	 * @todo Implement multi-step.
	 *
	 * @return {MwUiModal|boolean} false if invalid step, MwUiModal on success
	 */
	MwUiModal.prototype.next = function () {
		return false;
	};

	/**
	 * For a multi-step modal, goes to a specific step by number or name.
	 *
	 * @todo Implement multi-step.
	 *
	 * @param {number|string} to
	 * @return {MwUiModal|boolean} false if invalid step, MwUiModal on success
	 */
	MwUiModal.prototype.go = function () {
		return false;
	};

	/**
	 * MW UI Modal access through JS API.
	 *
	 *    mw.Modal( "<p>lorem</p>" );
	 */
	mw.Modal = MwUiModal;

	/**
	 * Returns an instance of mw.Modal if one is currently being displayed on the page.
	 * If node is given, tries to find which modal (if any) that node is within.
	 * Returns false if none found.
	 *
	 * @param {HTMLElement|jQuery} [node]
	 * @return {boolean|MwUiModal}
	 */
	mw.Modal.getModal = function ( node ) {
		if ( node ) {
			// Node was given; try to find a parent modal
			return $( node ).closest( MwUiModal.prototype.wrapperSelector ).data( 'MwUiModal' ) || false;
		}

		// No node given; return the last-opened modal on the page
		return $( document.body ).children( MwUiModal.prototype.wrapperSelector ).last().data( 'MwUiModal' ) || false;
	};

	// Transforms: automatically map these functions to call their mw.Modal methods globally, on any active instance
	[ 'close', 'getName', 'prev', 'next', 'prevOrClose', 'nextOrSubmit', 'go' ].forEach( function ( fn ) {
		mw.Modal[ fn ] = function () {
			var args = Array.prototype.splice.call( arguments, 0, arguments.length - 1 ),
				node = arguments[ arguments.length - 1 ],
				modal;

			// Find the node, if any was given
			if ( !node || ( typeof node.is === 'function' && !node.is( '*' ) ) || node.nodeType !== 1 ) {
				// The last argument to this function was not a node, assume none was intended to be given
				node = null;
				args = arguments;
			}

			// Try to find that modal
			modal = mw.Modal.getModal( node );

			// Call the intended function locally
			if ( modal ) {
				modal[ fn ].apply( modal, args );
			}
		};
	} );
}() );
