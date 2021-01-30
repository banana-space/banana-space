( function () {
	/**
	 * Flow board categories widget
	 *
	 * @class
	 * @extends OO.ui.Widget
	 * @mixin OO.ui.mixin.GroupElement
	 *
	 * @constructor
	 * @param {mw.flow.dm.CategoryItem} categoryModel Category item model
	 * @param {Object} [config]
	 * @cfg {boolean} [exists] Category page exists on this wiki
	 */
	mw.flow.ui.CategoryItemWidget = function mwFlowUiCategoryItemWidget( categoryModel, config ) {
		var prefixedCleanName, $link;

		// Parent constructor
		mw.flow.ui.CategoryItemWidget.super.call( this, config );

		this.model = categoryModel;
		this.name = this.model.getId();
		this.title = mw.Title.newFromText( this.name, mw.config.get( 'wgNamespaceIds' ).category );
		this.exists = this.model.exists();
		prefixedCleanName = this.title && this.title.getPrefixedText() || this.name;

		$link = $( '<a>' )
			.attr( 'href', mw.util.getUrl( this.title && this.title.getPrefixedDb() || this.name ) )
			.attr( 'title', this.exists ? prefixedCleanName : mw.msg( 'red-link-title', prefixedCleanName ) )
			.text( prefixedCleanName )
			.toggleClass( 'new', !this.exists );

		this.$element
			.addClass( 'flow-ui-categoryItemWidget flow-board-header-category-item' )
			.append( $link );
	};

	OO.inheritClass( mw.flow.ui.CategoryItemWidget, OO.ui.Widget );

	/* Static Properties */

	mw.flow.ui.CategoryItemWidget.static.tagName = 'li';

	/**
	 * Get the category data
	 *
	 * @return {string} Category name
	 */
	mw.flow.ui.CategoryItemWidget.prototype.getData = function () {
		return this.name;
	};
}() );
