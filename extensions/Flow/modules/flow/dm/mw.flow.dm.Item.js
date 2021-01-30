( function () {
	/**
	 * Flow Item
	 *
	 * @abstract
	 * @mixins OO.EventEmitter
	 *
	 * @constructor
	 */
	mw.flow.dm.Item = function mwFlowDmItem() {
		// Mixin constructor
		OO.EventEmitter.call( this );

		this.id = null;
		this.comparableHash = {};
	};

	/* Inheritance */

	OO.mixinClass( mw.flow.dm.Item, OO.EventEmitter );

	/**
	 * Get a hash object representing the current state
	 * of the item
	 *
	 * @return {Object} Hash object
	 */
	mw.flow.dm.Item.prototype.getHashObject = function () {
		return {
			id: this.getId()
		};
	};

	/**
	 * Get item id
	 *
	 * @return {string} Item Id
	 */
	mw.flow.dm.Item.prototype.getId = function () {
		return this.id;
	};

	/**
	 * Set item id
	 *
	 * @param {string} id Item Id
	 */
	mw.flow.dm.Item.prototype.setId = function ( id ) {
		this.id = id;
	};

	/**
	 * Get the comparable hash
	 *
	 * @return {Object} Hash
	 */
	mw.flow.dm.Item.prototype.getComparableHash = function () {
		return this.comparableHash;
	};

	/**
	 * Store a new comparable hash. This is similar to setting comparable
	 * breakpoints to the state of the object. The comparable hash will be
	 * compared to the current state of the object to determine whether
	 * the object has changes pending.
	 *
	 * @param {Object} [hash] New hash. If none given, the current hash will
	 * be stored
	 */
	mw.flow.dm.Item.prototype.storeComparableHash = function ( hash ) {
		this.comparableHash = hash || $.extend( {}, this.getHashObject() );
	};

	/**
	 * Check whether the topic changed since we last saved a comparable hash
	 *
	 * @return {boolean} Item has changed
	 */
	mw.flow.dm.Item.prototype.hasBeenChanged = function () {
		return !OO.compare( this.comparableHash, this.getHashObject() );
	};
}() );
