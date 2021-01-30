( function () {
	/**
	 * Flow Board
	 *
	 * @class
	 * @extends mw.flow.dm.Item
	 *
	 * @constructor
	 * @param {string} name Category name
	 * @param {Object} [config] Configuration options
	 */
	mw.flow.dm.CategoryItem = function mwFlowDmCategoryItem( name, config ) {
		// Parent constructor
		mw.flow.dm.CategoryItem.super.call( this, config );

		this.setId( name );
		this.setExists( !!config.exists );
	};

	/* Initialization */

	OO.inheritClass( mw.flow.dm.CategoryItem, mw.flow.dm.Item );

	/**
	 * Set exist status for this category in this wiki.
	 *
	 * @param {boolean} exists Category page exists in this wiki
	 */
	mw.flow.dm.CategoryItem.prototype.setExists = function ( exists ) {
		this.categoryExists = exists;
	};

	/**
	 * Set exist status for this category in this wiki.
	 *
	 * @return {boolean} Category page exists in this wiki
	 */
	mw.flow.dm.CategoryItem.prototype.exists = function () {
		return this.categoryExists;
	};
}() );
