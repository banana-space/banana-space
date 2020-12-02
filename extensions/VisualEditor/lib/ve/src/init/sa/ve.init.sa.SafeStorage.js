/**
 * Implementation of ve.init.SafeStorage
 *
 * Duplicate of mediawiki.storage.
 *
 * @class ve.init.sa.SafeStorage
 * @extends ve.init.SafeStorage
 *
 * @constructor
 * @param {Storage|undefined} store The Storage instance to wrap around
 */
ve.init.sa.SafeStorage = function ( store ) {
	this.store = store;
};

/**
 * @inheritdoc
 */
ve.init.sa.SafeStorage.prototype.get = function ( key ) {
	try {
		return this.store.getItem( key );
	} catch ( e ) {}
	return false;
};

/**
 * @inheritdoc
 */
ve.init.sa.SafeStorage.prototype.set = function ( key, value ) {
	try {
		this.store.setItem( key, value );
		return true;
	} catch ( e ) {}
	return false;
};

/**
 * @inheritdoc
 */
ve.init.sa.SafeStorage.prototype.remove = function ( key ) {
	try {
		this.store.removeItem( key );
		return true;
	} catch ( e ) {}
	return false;
};

/**
 * @inheritdoc
 */
ve.init.sa.SafeStorage.prototype.getObject = function ( key ) {
	var json = this.get( key );

	if ( json === false ) {
		return false;
	}

	try {
		return JSON.parse( json );
	} catch ( e ) {}

	return null;
};

/**
 * @inheritdoc
 */
ve.init.sa.SafeStorage.prototype.setObject = function ( key, value ) {
	var json;
	try {
		json = JSON.stringify( value );
		return this.set( key, json );
	} catch ( e ) {}
	return false;
};
