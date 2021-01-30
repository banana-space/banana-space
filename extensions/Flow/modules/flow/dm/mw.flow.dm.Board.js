( function () {
	/**
	 * Flow Board
	 *
	 * @class
	 * @extends mw.flow.dm.Item
	 * @mixins mw.flow.dm.List
	 *
	 * @constructor
	 * @param {Object} data API data to build board with
	 * @param {string} data.id Board Id
	 * @param {mw.Title} data.pageTitle Current page title
	 * @param {boolean} [data.isDeleted] Board is deleted
	 * @param {string} [data.defaultSort='newest'] The initial default topic sorting
	 * @param {Object} [config] Configuration options
	 */
	mw.flow.dm.Board = function mwFlowDmBoard( data, config ) {
		// Parent constructor
		mw.flow.dm.Board.super.call( this, config );

		// Mixin constructor
		mw.flow.dm.List.call( this );

		this.categories = new mw.flow.dm.Categories();

		// TODO: Fill this stuff in properly
		this.setId( data.id );
		this.pageTitle = data.pageTitle;
		this.deleted = !!data.isDeleted;
		this.sort = data.defaultSort || 'newest';
		this.description = new mw.flow.dm.BoardDescription();

		// Events
		this.aggregate( { contentChange: 'topicContentChange' } );
		this.categories.connect( this, {
			add: [ 'emit', 'addCategories' ],
			remove: [ 'emit', 'removeCategories' ],
			clear: [ 'emit', 'clearCategories' ]
		} );
	};

	/* Initialization */

	OO.inheritClass( mw.flow.dm.Board, mw.flow.dm.Item );
	OO.mixinClass( mw.flow.dm.Board, mw.flow.dm.List );

	/* Events */

	/**
	 * Board description changes
	 *
	 * @event descriptionChange
	 * @param {mw.flow.dm.BoardDescription} New description
	 */

	/**
	 * Board topics are reset
	 *
	 * @event reset
	 * @param {string} order The order of the topics; 'newest' or 'updated'
	 */

	/**
	 * One of the board's topics' content changed
	 *
	 * @event topicContentChange
	 * @param {string} topicId Topic UUID
	 * @param {string} content Topic content
	 * @param {string} format Content format
	 */

	/* Methods */

	/**
	 * @inheritdoc
	 */
	mw.flow.dm.Board.prototype.getHashObject = function () {
		return $.extend(
			{
				isDeleted: this.isDeleted(),
				pagePrefixedDb: this.getPageTitle().getPrefixedDb(),
				topicCount: this.getItemCount(),
				description: this.getDescription() && this.getDescription().getHashObject()
			},
			// Parent method
			mw.flow.dm.Board.super.prototype.getHashObject.apply( this, arguments )
		);
	};

	/**
	 * Add raw categories from the initial board api response
	 *
	 * @param {Object} categories Categories object
	 */
	mw.flow.dm.Board.prototype.setCategoriesFromObject = function ( categories ) {
		var cat,
			categoryDMs = [];

		// Add
		for ( cat in categories ) {
			categoryDMs.push( new mw.flow.dm.CategoryItem( cat, {
				exists: !!categories[ cat ].exists
			} ) );
		}
		this.addCategories( categoryDMs );
	};

	/**
	 * Add categories to the board
	 *
	 * @param {mw.flow.dm.CategoryItem[]} categories An array of category items
	 */
	mw.flow.dm.Board.prototype.addCategories = function ( categories ) {
		this.categories.addItems( categories );
	};

	/**
	 * Get board categories
	 *
	 * @return {mw.flow.dm.Categories} An array of category items
	 */
	mw.flow.dm.Board.prototype.getCategories = function () {
		return this.categories;
	};

	/**
	 * Remove board categories
	 *
	 * @param {mw.flow.dm.CategoryItem[]} categories An array of category items
	 */
	mw.flow.dm.Board.prototype.removeCategories = function ( categories ) {
		this.categories.removeItems( categories );
	};

	/**
	 * Clear the categories of this board
	 */
	mw.flow.dm.Board.prototype.clearCategories = function () {
		this.categories.clearItems();
	};

	/**
	 * Check whether the board has any categories
	 *
	 * @return {boolean} Board has categories
	 */
	mw.flow.dm.Board.prototype.hasCategories = function () {
		return !!this.categories.getItemCount();
	};

	/**
	 * Check if the board is in a deleted page
	 *
	 * @return {boolean} Board is in a deleted page
	 */
	mw.flow.dm.Board.prototype.isDeleted = function () {
		return this.deleted;
	};

	/**
	 * Get page title
	 *
	 * @return {mw.Title} Page title
	 */
	mw.flow.dm.Board.prototype.getPageTitle = function () {
		return this.pageTitle;
	};

	/**
	 * Get board description
	 *
	 * @return {mw.flow.dm.BoardDescription} Board description
	 */
	mw.flow.dm.Board.prototype.getDescription = function () {
		return this.description;
	};

	/**
	 * Set board description
	 *
	 * @param {mw.flow.dm.BoardDescription} desc Board description
	 * @fires descriptionChange
	 */
	mw.flow.dm.Board.prototype.setDescription = function ( desc ) {
		this.description = desc;
		this.emit( 'descriptionChange', this.description );
	};

	/**
	 * Update the description model
	 *
	 * @param {Object} headerRevision API response for view header revision
	 */
	mw.flow.dm.Board.prototype.updateDescription = function ( headerRevision ) {
		if ( this.description ) {
			this.description.populate( headerRevision );
		} else {
			this.setDescription(
				new mw.flow.dm.BoardDescription( headerRevision )
			);
		}
	};

	/**
	 * Get board sort order, 'newest' or 'updated'
	 *
	 * @return {string} Board sort order
	 */
	mw.flow.dm.Board.prototype.getSortOrder = function () {
		return this.sort;
	};

	/**
	 * Set board sort order, 'newest' or 'updated'
	 *
	 * @param {string} order Board sort order
	 * @fires sortOrderChange
	 */
	mw.flow.dm.Board.prototype.setSortOrder = function ( order ) {
		if ( this.sort !== order ) {
			this.sort = order;
			this.emit( 'sortOrderChange', order );
		}
	};

	/**
	 * Get the last offset for the API's offsetId
	 *
	 * @return {string}
	 */
	mw.flow.dm.Board.prototype.getOffsetId = function () {
		var topics = this.getItems();

		return topics.length > 0 ?
			topics[ topics.length - 1 ].getId() :
			null;
	};

	/**
	 * Get the last offset for the API's offset timestamp
	 *
	 * @return {number}
	 */
	mw.flow.dm.Board.prototype.getOffset = function () {
		var topics = this.getItems();

		return topics.length > 0 ?
			topics[ topics.length - 1 ].getLastUpdate() :
			null;
	};

	/**
	 * Reset the board
	 *
	 * @param {string} order The order of the topics; 'newest' or 'updated'
	 * @fires reset
	 */
	mw.flow.dm.Board.prototype.reset = function ( order ) {
		this.clearItems();
		this.emit( 'reset', order );
	};
}() );
