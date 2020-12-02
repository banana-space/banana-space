/*!
 * VisualEditor Object freeze utilities.
 *
 * @copyright 2011-2020 VisualEditor Team and others; see http://ve.mit-license.org
 */

/* global Set */

( function () {
	var deepFreeze, freezeProxyHandler;
	freezeProxyHandler = {
		set: function ( obj, name ) {
			throw new Error( 'Object is frozen, can\'t set property: ' + name );
		},
		deleteProperty: function ( obj, name ) {
			throw new Error( 'Object is frozen, can\'t delete property: ' + name );
		}
	};

	if ( !window.Proxy || !window.Set ) {
		return;
	}
	/**
	 * Deep freeze an object, making it immutable
	 *
	 * Original object properties are overwritten with frozen versions.
	 *
	 * @param {Object} object Object to freeze
	 * @param {boolean} [onlyProperties] Only freeze properties (or array items)
	 * @param {Set} [seen] Set of already-seen objects (for internal, recursive use)
	 * @return {Object} Immutable deep copy of the original object
	 */
	ve.deepFreeze = deepFreeze = function ( object, onlyProperties, seen ) {
		var name, value;

		if ( !seen ) {
			seen = new Set();
			seen.add( object );
		}
		for ( name in object ) {
			if ( Object.prototype.hasOwnProperty.call( object, name ) ) {
				value = object[ name ];
				if (
					// Truth check so we don't try to freeze null
					value &&
					typeof value === 'object' &&
					!seen.has( value ) &&
					!Object.isFrozen( value )
				) {
					seen.add( value );
					// Recurse. Use local name (tests may alias ve.deepFreeze)
					object[ name ] = deepFreeze( value, false, seen );
				}
			}
		}

		if ( !onlyProperties ) {
			object = new window.Proxy( object, freezeProxyHandler );
			// Object#freeze isn't really necessary after proxying,
			// but use it so we can detect frozen objects with Object.isFrozen.
			Object.freeze( object );
		}
		return object;
	};
}() );
