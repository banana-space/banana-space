( function () {
	/**
	 * Flow List mixin
	 * Must be mixed into an mw.flow.dm.Item element
	 *
	 * @mixin
	 * @abstract
	 * @constructor
	 */
	mw.flow.dm.List = function mwFlowDmList() {
		this.items = [];

		// Store references to items by their ids
		this.itemsById = {};

		this.aggregateItemEvents = {};
	};

	/* Events */

	/**
	 * @event add Items have been added
	 * @param {mw.flow.dm.Item[]} items Added items
	 * @param {number} index Index items were added at
	 */

	/**
	 * @event remove Items have been removed
	 * @param {mw.flow.dm.Item[]} items Removed items
	 */

	/* Methods */

	/**
	 * Get all items
	 *
	 * @return {mw.flow.dm.Item[]} Items in the list
	 */
	mw.flow.dm.List.prototype.getItems = function () {
		return this.items.slice( 0 );
	};

	/**
	 * Get an item by its id
	 *
	 * @param {string} id Item id
	 * @return {mw.flow.dm.Item} Item
	 */
	mw.flow.dm.List.prototype.getItemById = function ( id ) {
		return this.itemsById[ id ];
	};

	/**
	 * Get the index of a specific item
	 *
	 * @param {mw.flow.dm.Item} item Requested item
	 * @return {number} Index of the item
	 */
	mw.flow.dm.List.prototype.getItemIndex = function ( item ) {
		return this.items.indexOf( item );
	};

	/**
	 * Get number of items
	 *
	 * @return {number} Number of items in the list
	 */
	mw.flow.dm.List.prototype.getItemCount = function () {
		return this.items.length;
	};

	/**
	 * Check if a list contains no items.
	 *
	 * @return {boolean} Group is empty
	 */
	mw.flow.dm.List.prototype.isEmpty = function () {
		return !this.items.length;
	};

	/**
	 * Aggregate the events emitted by the group.
	 * Taken from OOUI's OO.ui.GroupElement#aggregate
	 *
	 * When events are aggregated, the group will listen to all contained items for the event,
	 * and then emit the event under a new name. The new event will contain an additional leading
	 * parameter containing the item that emitted the original event. Other arguments emitted from
	 * the original event are passed through.
	 *
	 * @param {Object.<string,string|null>} events An object keyed by the name of the event that should be
	 *  aggregated  (e.g., ‘click’) and the value of the new name to use (e.g., ‘groupClick’).
	 *  A `null` value will remove aggregated events.
	 * @throws {Error} An error is thrown if aggregation already exists.
	 */
	mw.flow.dm.List.prototype.aggregate = function ( events ) {
		var i, len, item, add, remove, itemEvent, groupEvent;

		for ( itemEvent in events ) {
			groupEvent = events[ itemEvent ];

			// Remove existing aggregated event
			if ( Object.prototype.hasOwnProperty.call( this.aggregateItemEvents, itemEvent ) ) {
				// Don't allow duplicate aggregations
				if ( groupEvent ) {
					throw new Error( 'Duplicate item event aggregation for ' + itemEvent );
				}
				// Remove event aggregation from existing items
				for ( i = 0, len = this.items.length; i < len; i++ ) {
					item = this.items[ i ];
					if ( item.connect && item.disconnect ) {
						remove = {};
						remove[ itemEvent ] = [ 'emit', this.aggregateItemEvents[ itemEvent ], item ];
						item.disconnect( this, remove );
					}
				}
				// Prevent future items from aggregating event
				delete this.aggregateItemEvents[ itemEvent ];
			}

			// Add new aggregate event
			if ( groupEvent ) {
				// Make future items aggregate event
				this.aggregateItemEvents[ itemEvent ] = groupEvent;
				// Add event aggregation to existing items
				for ( i = 0, len = this.items.length; i < len; i++ ) {
					item = this.items[ i ];
					if ( item.connect && item.disconnect ) {
						add = {};
						add[ itemEvent ] = [ 'emit', groupEvent, item ];
						item.connect( this, add );
					}
				}
			}
		}
	};

	/**
	 * Add items
	 *
	 * @param {mw.flow.dm.Item[]} items Items to add
	 * @param {number} index Index to add items at
	 * @chainable
	 * @fires add
	 */
	mw.flow.dm.List.prototype.addItems = function ( items, index ) {
		var i, len, item, event, events, currentIndex, existingItem, at;

		if ( items.length === 0 ) {
			return this;
		}

		// Support adding existing items at new locations
		for ( i = 0, len = items.length; i < len; i++ ) {
			item = items[ i ];
			existingItem = this.getItemById( item.getId() );

			// Check if item exists then remove it first, effectively "moving" it
			currentIndex = this.items.indexOf( existingItem );
			if ( currentIndex >= 0 ) {
				this.removeItems( [ existingItem ] );
				// Adjust index to compensate for removal
				if ( currentIndex < index ) {
					index--;
				}
			}

			// Add the item
			if ( item.connect && item.disconnect && !$.isEmptyObject( this.aggregateItemEvents ) ) {
				events = {};
				for ( event in this.aggregateItemEvents ) {
					events[ event ] = [ 'emit', this.aggregateItemEvents[ event ], item ];
				}
				item.connect( this, events );
			}

			// Add by reference
			this.itemsById[ item.getId() ] = items[ i ];
		}

		if ( index === undefined || index < 0 || index >= this.items.length ) {
			at = this.items.length;
			this.items.push.apply( this.items, items );
		} else if ( index === 0 ) {
			at = 0;
			this.items.unshift.apply( this.items, items );
		} else {
			at = index;
			this.items.splice.apply( this.items, [ index, 0 ].concat( items ) );
		}
		this.emit( 'add', items, at );

		return this;
	};

	/**
	 * Remove items
	 *
	 * @param {mw.flow.dm.Item[]} items Items to remove
	 * @chainable
	 * @fires remove
	 */
	mw.flow.dm.List.prototype.removeItems = function ( items ) {
		var i, len, item, index, remove, itemEvent,
			removed = [];

		if ( items.length === 0 ) {
			return this;
		}

		// Remove specific items
		for ( i = 0, len = items.length; i < len; i++ ) {
			item = items[ i ];
			index = this.items.indexOf( item );
			if ( index !== -1 ) {
				if (
					item.connect && item.disconnect &&
					!$.isEmptyObject( this.aggregateItemEvents )
				) {
					remove = {};
					if ( Object.prototype.hasOwnProperty.call( this.aggregateItemEvents, itemEvent ) ) {
						remove[ itemEvent ] = [ 'emit', this.aggregateItemEvents[ itemEvent ], item ];
					}
					item.disconnect( this, remove );
				}
				this.items.splice( index, 1 );
				// Remove reference by Id
				delete this.itemsById[ item.getId() ];
			}
		}
		this.emit( 'remove', removed );

		return this;
	};

	/**
	 * Clear all items
	 *
	 * @chainable
	 * @fires clear
	 */
	mw.flow.dm.List.prototype.clearItems = function () {
		var i, len, item, remove, itemEvent;

		// Remove all items
		for ( i = 0, len = this.items.length; i < len; i++ ) {
			item = this.items[ i ];
			if (
				item.connect && item.disconnect &&
				!$.isEmptyObject( this.aggregateItemEvents )
			) {
				remove = {};
				if ( Object.prototype.hasOwnProperty.call( this.aggregateItemEvents, itemEvent ) ) {
					remove[ itemEvent ] = [ 'emit', this.aggregateItemEvents[ itemEvent ], item ];
				}
				item.disconnect( this, remove );
			}
		}

		this.items = [];
		this.itemsById = {};

		this.emit( 'clear' );

		return this;
	};
}() );
