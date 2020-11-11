/**
 * Demo for LookupElement.
 *
 * @class
 * @extends OO.ui.TextInputWidget
 * @mixins OO.ui.mixin.LookupElement
 *
 * @constructor
 * @param {Object} config Configuration options
 */
Demo.NumberLookupTextInputWidget = function DemoNumberLookupTextInputWidget( config ) {
	// Parent constructor
	OO.ui.TextInputWidget.call( this, $.extend( { validate: 'integer' }, config ) );
	// Mixin constructors
	OO.ui.mixin.LookupElement.call( this, config );
};
OO.inheritClass( Demo.NumberLookupTextInputWidget, OO.ui.TextInputWidget );
OO.mixinClass( Demo.NumberLookupTextInputWidget, OO.ui.mixin.LookupElement );

/**
 * @inheritdoc
 */
Demo.NumberLookupTextInputWidget.prototype.getLookupRequest = function () {
	var
		value = this.getValue(),
		deferred = $.Deferred(),
		delay = 500 + Math.floor( Math.random() * 500 );

	this.getValidity().then( function () {
		// Resolve with results after a faked delay
		setTimeout( function () {
			deferred.resolve( [
				value * 1, value * 2, value * 3, value * 4, value * 5,
				value * 6, value * 7, value * 8, value * 9, value * 10
			] );
		}, delay );
	}, function () {
		// No results when the input contains invalid content
		deferred.resolve( [] );
	} );

	return deferred.promise( { abort: function () {} } );
};

/**
 * @inheritdoc
 */
Demo.NumberLookupTextInputWidget.prototype.getLookupCacheDataFromResponse = function ( response ) {
	return response || [];
};

/**
 * @inheritdoc
 */
Demo.NumberLookupTextInputWidget.prototype.getLookupMenuOptionsFromData = function ( data ) {
	var
		items = [],
		i, number;
	for ( i = 0; i < data.length; i++ ) {
		number = String( data[ i ] );
		items.push( new OO.ui.MenuOptionWidget( {
			data: number,
			label: number
		} ) );
	}

	return items;
};
