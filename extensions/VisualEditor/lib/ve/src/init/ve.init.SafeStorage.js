/**
 * A wrapper for an HTML5 Storage interface (`localStorage` or `sessionStorage`)
 * that is safe to call on all browsers.
 *
 * @abstract
 * @class ve.init.SafeStorage
 *
 * @constructor
 * @param {Storage|undefined} store The Storage instance to wrap around
 */
ve.init.SafeStorage = function ( store ) {
	this.store = store;
};

/**
 * Retrieve value from device storage.
 *
 * @abstract
 * @param {string} key Key of item to retrieve
 * @return {string|null|boolean} String value, null if no value exists, or false
 *  if storage is not available.
 */
ve.init.SafeStorage.prototype.get = null;

/**
 * Set a value in device storage.
 *
 * @abstract
 * @param {string} key Key name to store under
 * @param {string} value Value to be stored
 * @return {boolean} The value was set
 */
ve.init.SafeStorage.prototype.set = null;

/**
 * Remove a value from device storage.
 *
 * @abstract
 * @param {string} key Key of item to remove
 * @return {boolean} Whether the save succeeded or not
 */
ve.init.SafeStorage.prototype.remove = null;

/**
 * Retrieve JSON object from device storage.
 *
 * @abstract
 * @param {string} key Key of item to retrieve
 * @return {Object|null|boolean} Object, null if no value exists or value
 *  is not JSON-parseable, or false if storage is not available.
 */
ve.init.SafeStorage.prototype.getObject = null;

/**
 * Set an object value in device storage by JSON encoding
 *
 * @abstract
 * @param {string} key Key name to store under
 * @param {Object} value Object value to be stored
 * @return {boolean} The value was set
 */
ve.init.SafeStorage.prototype.setObject = null;
