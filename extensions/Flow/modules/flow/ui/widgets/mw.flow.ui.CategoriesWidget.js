( function () {
	/**
	 * Flow board categories widget
	 *
	 * @class
	 * @extends OO.ui.Widget
	 * @mixins OO.ui.mixin.GroupElement
	 *
	 * @constructor
	 * @param {mw.flow.dm.Board} model Board model
	 * @param {Object} [config]
	 */
	mw.flow.ui.CategoriesWidget = function mwFlowUiCategoriesWidget( model, config ) {
		var $categoryList = $( '<ul>' )
				.addClass( 'flow-board-header-category-list' ),
			categoriesTitle = mw.Title.newFromText( 'Special:Categories' );

		config = config || {};

		// Parent constructor
		mw.flow.ui.CategoriesWidget.super.call( this, config );

		// Mixin constructor
		OO.ui.mixin.GroupElement.call( this, $.extend( { $group: $categoryList }, config ) );

		this.model = model;
		this.model.connect( this, {
			addCategories: 'onModelAddCategories',
			removeCategories: 'onModelRemoveCategories',
			clearCategories: 'onModelClearCategories'
		} );

		this.$categoriesLabel = $( '<a>' )
			.prop( 'href', config.specialPageCategoryLink || categoriesTitle.getUrl() );
		this.updateCategoriesLabel();

		// Initialize
		this.$element
			// Mimic the same structure as mediawiki category
			// and the nojs version
			.addClass( 'catlinks flow-board-header-category-view-js flow-ui-categoriesWidget' )
			.prop( 'id', 'catlinks' )
			.append(
				$( '<div>' )
					.prop( 'id', 'mw-normal-catlinks' )
					.append(
						this.$categoriesLabel,
						mw.msg( 'colon-separator' ),
						this.$group
					)
					.addClass( 'mw-normal-catlinks flow-board-header-category-view' )
			);

		this.toggle( this.model.hasCategories() );
	};

	/* Initialization */

	OO.inheritClass( mw.flow.ui.CategoriesWidget, OO.ui.Widget );
	OO.mixinClass( mw.flow.ui.CategoriesWidget, OO.ui.mixin.GroupElement );

	/**
	 * Respond to a change of categories in the board model
	 *
	 * @param {mw.flow.dm.CategoryItem[]} categories Added categories
	 */
	mw.flow.ui.CategoriesWidget.prototype.onModelAddCategories = function ( categories ) {
		var i, len,
			widgets = [];

		for ( i = 0, len = categories.length; i < len; i++ ) {
			widgets.push( new mw.flow.ui.CategoryItemWidget( categories[ i ] ) );
		}

		this.addItems( widgets );
		this.updateCategoriesLabel();
		this.toggle( this.model.hasCategories() );
	};

	/**
	 * Respond to removing categories from the model
	 *
	 * @param {mw.flow.dm.CategoryItem[]} categories Removed categories
	 */
	mw.flow.ui.CategoriesWidget.prototype.onModelRemoveCategories = function ( categories ) {
		var i, len,
			widgets = [];

		for ( i = 0, len = categories.length; i < len; i++ ) {
			widgets.push( this.findItemFromData( categories[ i ].getId() ) );
		}

		this.removeItems( widgets );
		this.updateCategoriesLabel();
		this.toggle( this.model.hasCategories() );
	};

	/**
	 * Respond to clearing all categories from the model
	 */
	mw.flow.ui.CategoriesWidget.prototype.onModelClearCategories = function () {
		this.clearItems();
	};

	/**
	 * Update the category label according to the number of available items
	 */
	mw.flow.ui.CategoriesWidget.prototype.updateCategoriesLabel = function () {
		this.$categoriesLabel.text(
			// FIXME: this.model should be an instance of dm.Categories, not dm.Board
			mw.msg( 'pagecategories', this.model.getCategories().getItemCount() )
		);
	};
}() );
