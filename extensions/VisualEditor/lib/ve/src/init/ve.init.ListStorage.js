/**
 * Efficient append-only list storage based on a ve.init.SafeStorage instance
 *
 * @class ve.init.ListStorage
 *
 * @constructor
 * @param {ve.init.SafeStorage} storage The SafeStorage instance to wrap around
 */
ve.init.ListStorage = function ( storage ) {
	this.storage = storage;
};

/**
 * @inheritdoc ve.init.SafeStorage
 */
ve.init.ListStorage.prototype.get = function ( key ) {
	return this.storage.get( key );
};

/**
 * @inheritdoc ve.init.SafeStorage
 */
ve.init.ListStorage.prototype.set = function ( key, value ) {
	return this.storage.set( key, value );
};

/**
 * @inheritdoc ve.init.SafeStorage
 */
ve.init.ListStorage.prototype.remove = function ( key ) {
	return this.storage.remove( key );
};

/**
 * @inheritdoc ve.init.SafeStorage
 */
ve.init.ListStorage.prototype.getObject = function ( key ) {
	return this.storage.getObject( key );
};

/**
 * @inheritdoc ve.init.SafeStorage
 */
ve.init.ListStorage.prototype.setObject = function ( key, value ) {
	return this.storage.setObject( key, value );
};

/**
 * Append a value to a list stored in storage
 *
 * @param {string} key Key of list to set value for
 * @param {string} value Value to set
 * @return {boolean} The value was set
 */
ve.init.ListStorage.prototype.appendToList = function ( key, value ) {
	var length = this.getListLength( key );

	if ( this.set( key + '__' + length, value ) ) {
		length++;
		return this.set( key + '__length', length.toString() );
	}
	return false;
};

/**
 * Get the length of a list in storage
 *
 * @param {string} key Key of list
 * @return {number} List length, 0 if the list doesn't exist
 */
ve.init.ListStorage.prototype.getListLength = function ( key ) {
	return +this.get( key + '__length' ) || 0;
};

/**
 * Get a list stored in storage
 *
 * Internally this will use items with the keys:
 *  - key__length
 *  - key__0 … key__N
 *
 * @param {string} key Key of list
 * @return {string[]} List
 */
ve.init.ListStorage.prototype.getList = function ( key ) {
	var i,
		list = [],
		length = this.getListLength( key );

	for ( i = 0; i < length; i++ ) {
		list.push( this.get( key + '__' + i ) );
	}
	return list;
};

/**
 * Remove a list stored in storage
 *
 * @param {string} key Key of list
 */
ve.init.ListStorage.prototype.removeList = function ( key ) {
	var i,
		length = this.getListLength( key );

	for ( i = 0; i < length; i++ ) {
		this.remove( key + '__' + i );
	}
	this.remove( key + '__length' );
};
