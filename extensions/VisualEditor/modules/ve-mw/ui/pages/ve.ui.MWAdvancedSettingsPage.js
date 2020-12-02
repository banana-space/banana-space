/*!
 * VisualEditor user interface MWAdvancedSettingsPage class.
 *
 * @copyright 2011-2020 VisualEditor Team and others; see AUTHORS.txt
 * @license The MIT License (MIT); see LICENSE.txt
 */

/**
 * MediaWiki meta dialog advanced settings page.
 *
 * @class
 * @extends OO.ui.PageLayout
 *
 * @constructor
 * @param {string} name Unique symbolic name of page
 * @param {Object} [config] Configuration options
 * @cfg {jQuery} [$overlay] Overlay to render dropdowns in
 */
ve.ui.MWAdvancedSettingsPage = function VeUiMWAdvancedSettingsPage( name, config ) {
	var advancedSettingsPage = this;

	// Parent constructor
	ve.ui.MWAdvancedSettingsPage.super.apply( this, arguments );

	// Properties
	this.metaList = null;
	this.indexingOptionTouched = false;
	this.newSectionEditLinkOptionTouched = false;

	this.advancedSettingsFieldset = new OO.ui.FieldsetLayout( {
		label: ve.msg( 'visualeditor-dialog-meta-advancedsettings-label' ),
		icon: 'settings'
	} );

	// Initialization

	// Indexing items
	this.indexing = new OO.ui.FieldLayout(
		new OO.ui.ButtonSelectWidget()
			.addItems( [
				new OO.ui.ButtonOptionWidget( {
					data: 'mw:PageProp/index',
					label: ve.msg( 'visualeditor-dialog-meta-settings-index-force' )
				} ),
				new OO.ui.ButtonOptionWidget( {
					data: 'default',
					label: ve.msg( 'visualeditor-dialog-meta-settings-index-default' )
				} ),
				new OO.ui.ButtonOptionWidget( {
					data: 'mw:PageProp/noindex',
					label: ve.msg( 'visualeditor-dialog-meta-settings-index-disable' )
				} )
			] )
			.connect( this, { select: 'onIndexingOptionChange' } ),
		{
			$overlay: config.$overlay,
			align: 'top',
			label: ve.msg( 'visualeditor-dialog-meta-settings-index-label' ),
			help: ve.msg( 'visualeditor-dialog-meta-settings-index-help' )
		}
	);

	// New edit section link items
	this.newEditSectionLink = new OO.ui.FieldLayout(
		new OO.ui.ButtonSelectWidget()
			.addItems( [
				new OO.ui.ButtonOptionWidget( {
					data: 'mw:PageProp/newsectionlink',
					label: ve.msg( 'visualeditor-dialog-meta-settings-newsectioneditlink-force' )
				} ),
				new OO.ui.ButtonOptionWidget( {
					data: 'default',
					label: ve.msg( 'visualeditor-dialog-meta-settings-newsectioneditlink-default' )
				} ),
				new OO.ui.ButtonOptionWidget( {
					data: 'mw:PageProp/nonewsectionlink',
					label: ve.msg( 'visualeditor-dialog-meta-settings-newsectioneditlink-disable' )
				} )
			] )
			.connect( this, { select: 'onNewSectionEditLinkOptionChange' } ),
		{
			$overlay: config.$overlay,
			align: 'top',
			label: ve.msg( 'visualeditor-dialog-meta-settings-newsectioneditlink-label' ),
			// eslint-disable-next-line no-jquery/no-global-selector
			help: ve.msg( 'visualeditor-dialog-meta-settings-newsectioneditlink-help', $( '#ca-edit' ).text() )
		}
	);

	this.displayTitleTouched = false;
	this.displayTitleInput = new OO.ui.TextInputWidget();
	this.displayTitleInput.connect( this, { change: 'onDisplayTitleInputChange' } );
	this.displayTitleField = new OO.ui.FieldLayout(
		this.displayTitleInput,
		{
			$overlay: config.$overlay,
			align: 'top',
			label: ve.msg( 'visualeditor-dialog-meta-settings-displaytitle' ),
			help: ve.msg( 'visualeditor-dialog-meta-settings-displaytitle-help' )
		}
	);

	this.advancedSettingsFieldset.addItems( [ this.indexing, this.newEditSectionLink, this.displayTitleField ] );

	this.metaItemCheckboxes = [];
	if ( mw.config.get( 'wgVariantArticlePath' ) ) {
		this.metaItemCheckboxes.push(
			{
				metaName: 'mwNoContentConvert',
				label: ve.msg( 'visualeditor-dialog-meta-settings-nocontentconvert-label' ),
				help: ve.msg( 'visualeditor-dialog-meta-settings-nocontentconvert-help' )
			},
			{
				metaName: 'mwNoTitleConvert',
				label: ve.msg( 'visualeditor-dialog-meta-settings-notitleconvert-label' ),
				help: ve.msg( 'visualeditor-dialog-meta-settings-notitleconvert-help' )
			}
		);
	}

	this.metaItemCheckboxes.forEach( function ( metaItemCheckbox ) {
		metaItemCheckbox.fieldLayout = new OO.ui.FieldLayout(
			new OO.ui.CheckboxInputWidget(),
			{
				$overlay: config.$overlay,
				align: 'inline',
				label: metaItemCheckbox.label,
				help: metaItemCheckbox.help
			}
		);
		advancedSettingsPage.advancedSettingsFieldset.addItems( [ metaItemCheckbox.fieldLayout ] );
	} );

	this.$element.append( this.advancedSettingsFieldset.$element );
};

/* Inheritance */

OO.inheritClass( ve.ui.MWAdvancedSettingsPage, OO.ui.PageLayout );

/* Methods */

/**
 * Handle display title input change events.
 *
 * @param {string} value Current value of input widget
 */
ve.ui.MWAdvancedSettingsPage.prototype.onDisplayTitleInputChange = function () {
	this.displayTitleTouched = true;
};

/**
 * @inheritdoc
 */
ve.ui.MWAdvancedSettingsPage.prototype.setOutlineItem = function () {
	// Parent method
	ve.ui.MWAdvancedSettingsPage.super.prototype.setOutlineItem.apply( this, arguments );

	if ( this.outlineItem ) {
		this.outlineItem
			.setIcon( 'settings' )
			.setLabel( ve.msg( 'visualeditor-dialog-meta-advancedsettings-section' ) );
	}
};

/* Indexing option methods */

/**
 * Handle indexing option state change events.
 */
ve.ui.MWAdvancedSettingsPage.prototype.onIndexingOptionChange = function () {
	this.indexingOptionTouched = true;
};

/**
 * Get the first meta item of a given name
 *
 * @param {string} name Name of the meta item
 * @return {Object|null} Meta item, if any
 */
ve.ui.MWAdvancedSettingsPage.prototype.getMetaItem = function ( name ) {
	return this.metaList.getItemsInGroup( name )[ 0 ] || null;
};

/* New edit section link option methods */

/**
 * Handle new edit section link change events.
 */
ve.ui.MWAdvancedSettingsPage.prototype.onNewSectionEditLinkOptionChange = function () {
	this.newSectionEditLinkOptionTouched = true;
};

/**
 * Setup settings page.
 *
 * @param {ve.dm.MetaList} metaList Meta list
 * @param {Object} [config] Configuration options
 * @param {Object} [config.data] Dialog setup data
 * @param {boolean} [config.isReadOnly] Dialog is in read-only mode
 * @return {jQuery.Promise}
 */
ve.ui.MWAdvancedSettingsPage.prototype.setup = function ( metaList, config ) {
	var indexingField, indexingOption, indexingType,
		newSectionEditField, newSectionEditLinkOption, newSectionEditLinkType,
		displayTitleItem, displayTitle,
		advancedSettingsPage = this;

	this.metaList = metaList;

	// Indexing items
	indexingField = this.indexing.getField();
	indexingOption = this.getMetaItem( 'mwIndex' );
	indexingType = indexingOption && indexingOption.getAttribute( 'property' ) || 'default';
	indexingField
		.selectItemByData( indexingType )
		.setDisabled( config.isReadOnly );
	this.indexingOptionTouched = false;

	// New section edit link items
	newSectionEditField = this.newEditSectionLink.getField();
	newSectionEditLinkOption = this.getMetaItem( 'mwNewSectionEdit' );
	newSectionEditLinkType = newSectionEditLinkOption && newSectionEditLinkOption.getAttribute( 'property' ) || 'default';
	newSectionEditField
		.selectItemByData( newSectionEditLinkType )
		.setDisabled( config.isReadOnly );
	this.newSectionEditLinkOptionTouched = false;

	// Display title items
	displayTitleItem = this.getMetaItem( 'mwDisplayTitle' );
	displayTitle = displayTitleItem && displayTitleItem.getAttribute( 'content' ) || '';
	if ( !displayTitle ) {
		displayTitle = mw.Title.newFromText( ve.init.target.getPageName() ).getPrefixedText();
	}
	this.displayTitleInput
		.setValue( displayTitle )
		.setReadOnly( config.isReadOnly );
	this.displayTitleTouched = false;

	// Simple checkbox items
	this.metaItemCheckboxes.forEach( function ( metaItemCheckbox ) {
		var isSelected = !!advancedSettingsPage.getMetaItem( metaItemCheckbox.metaName );
		metaItemCheckbox.fieldLayout.getField()
			.setSelected( isSelected )
			.setDisabled( config.isReadOnly );
	} );

	return ve.createDeferred().resolve().promise();
};

/**
 * Tear down settings page.
 *
 * @param {Object} [data] Dialog tear down data
 */
ve.ui.MWAdvancedSettingsPage.prototype.teardown = function ( data ) {
	var currentIndexingItem, newIndexingData, newIndexingItem,
		currentNewSectionEditLinkItem, newNewSectionEditLinkData, newNewSectionEditLinkItem,
		currentDisplayTitleItem, newDisplayTitle, newDisplayTitleItem,
		advancedSettingsPage = this;

	// Data initialization
	data = data || {};
	if ( data.action !== 'done' ) {
		return;
	}

	// Indexing items
	currentIndexingItem = this.getMetaItem( 'mwIndex' );
	newIndexingData = this.indexing.getField().findSelectedItem();

	// Alter the indexing option flag iff it's been touched & is actually different
	if ( this.indexingOptionTouched ) {
		if ( newIndexingData.data === 'default' ) {
			if ( currentIndexingItem ) {
				currentIndexingItem.remove();
			}
		} else {
			newIndexingItem = { type: 'mwIndex', attributes: { property: newIndexingData.data } };

			if ( !currentIndexingItem ) {
				this.metaList.insertMeta( newIndexingItem );
			} else if ( currentIndexingItem.getAttribute( 'property' ) !== newIndexingData.data ) {
				currentIndexingItem.replaceWith(
					ve.extendObject( true, {}, currentIndexingItem.getElement(), newIndexingItem )
				);
			}
		}
	}

	// New section edit link items
	currentNewSectionEditLinkItem = this.getMetaItem( 'mwNewSectionEdit' );
	newNewSectionEditLinkData = this.newEditSectionLink.getField().findSelectedItem();

	// Alter the new section edit option flag iff it's been touched & is actually different
	if ( this.newSectionEditLinkOptionTouched ) {
		if ( newNewSectionEditLinkData.data === 'default' ) {
			if ( currentNewSectionEditLinkItem ) {
				currentNewSectionEditLinkItem.remove();
			}
		} else {
			newNewSectionEditLinkItem = { type: 'mwNewSectionEdit', attributes: { property: newNewSectionEditLinkData.data } };

			if ( !currentNewSectionEditLinkItem ) {
				this.metaList.insertMeta( newNewSectionEditLinkItem );
			} else if ( currentNewSectionEditLinkItem.getAttribute( 'property' ) !== newNewSectionEditLinkData.data ) {
				currentNewSectionEditLinkItem.replaceWith(
					ve.extendObject( true, {}, currentNewSectionEditLinkItem.getElement(), newNewSectionEditLinkItem )
				);
			}
		}
	}

	// Display title items
	currentDisplayTitleItem = this.getMetaItem( 'mwDisplayTitle' );
	newDisplayTitle = this.displayTitleInput.getValue();
	if ( newDisplayTitle === mw.Title.newFromText( ve.init.target.getPageName() ).getPrefixedText() ) {
		newDisplayTitle = '';
	}
	newDisplayTitleItem = { type: 'mwDisplayTitle', attributes: { content: newDisplayTitle } };

	// Alter the display title flag iff it's been touched & is actually different
	if ( this.displayTitleTouched ) {
		if ( currentDisplayTitleItem ) {
			if ( newDisplayTitle ) {
				if ( currentDisplayTitleItem.getAttribute( 'content' ) !== newDisplayTitle ) {
					// There was a display title and is a new one, but they differ, so replace
					currentDisplayTitleItem.replaceWith(
						ve.extendObject( true, {},
							currentDisplayTitleItem.getElement(),
							newDisplayTitleItem
						)
					);
				}
			} else {
				// There was a display title and is no new one, so remove
				currentDisplayTitleItem.remove();
			}
		} else {
			if ( newDisplayTitle ) {
				// There's no existing display title but there is a new one, so create
				// HACK: Putting this at position 0 so that it works – T63862
				this.metaList.insertMeta( newDisplayTitleItem, 0 );
			}
		}
	}

	this.metaItemCheckboxes.forEach( function ( metaItemCheckbox ) {
		var currentItem = advancedSettingsPage.getMetaItem( metaItemCheckbox.metaName ),
			isSelected = metaItemCheckbox.fieldLayout.getField().isSelected();

		if ( currentItem && !isSelected ) {
			currentItem.remove();
		} else if ( !currentItem && isSelected ) {
			advancedSettingsPage.metaList.insertMeta( { type: metaItemCheckbox.metaName } );
		}
	} );

	this.metaList = null;
};

ve.ui.MWAdvancedSettingsPage.prototype.getFieldsets = function () {
	return [
		this.advancedSettingsFieldset
	];
};
