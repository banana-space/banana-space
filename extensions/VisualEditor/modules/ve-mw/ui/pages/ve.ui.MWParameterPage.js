/*!
 * VisualEditor user interface MWParameterPage class.
 *
 * @copyright 2011-2020 VisualEditor Team and others; see AUTHORS.txt
 * @license The MIT License (MIT); see LICENSE.txt
 */

/**
 * MediaWiki transclusion dialog template page.
 *
 * @class
 * @extends OO.ui.PageLayout
 *
 * @constructor
 * @param {ve.dm.MWParameterModel} parameter Template parameter
 * @param {string} name Unique symbolic name of page
 * @param {Object} [config] Configuration options
 * @cfg {jQuery} [$overlay] Overlay to render dropdowns in
 * @cfg {boolean} [readOnly] Parameter is read-only
 */
ve.ui.MWParameterPage = function VeUiMWParameterPage( parameter, name, config ) {
	var paramName = parameter.getName();

	// Configuration initialization
	config = ve.extendObject( {
		scrollable: false
	}, config );

	// Parent constructor
	ve.ui.MWParameterPage.super.call( this, name, config );

	// Properties
	this.edited = false;
	this.parameter = parameter;
	this.originalValue = parameter.getValue();
	this.spec = parameter.getTemplate().getSpec();
	this.defaultValue = parameter.getDefaultValue();
	this.exampleValue = parameter.getExampleValue();

	this.$info = $( '<div>' );
	this.$actions = $( '<div>' );
	this.$labelElement = $( '<div>' );
	this.$field = $( '<div>' );
	this.$more = $( '<div>' );
	this.$description = $( '<div>' );

	// Note: Calling createValueInput() sets some properties we rely on later in this function
	this.valueInput = this.createValueInput()
		.setValue( this.parameter.getValue() )
		.connect( this, { change: 'onValueInputChange' } );

	if ( config.readOnly && this.valueInput.setReadOnly ) {
		this.valueInput.setReadOnly( true );
	}

	this.statusIndicator = new OO.ui.IndicatorWidget( {
		classes: [ 've-ui-mwParameterPage-statusIndicator' ]
	} );

	// Construct the description popup

	this.$description
		.addClass( 've-ui-mwParameterPage-description' )
		.append( $( '<p>' ).text( this.spec.getParameterDescription( paramName ) || '' ) );

	if ( this.parameter.isRequired() ) {
		this.statusIndicator
			.setIndicator( 'required' )
			.setTitle(
				ve.msg( 'visualeditor-dialog-transclusion-required-parameter' )
			);
		this.$description.append(
			$( '<p>' )
				.addClass( 've-ui-mwParameterPage-description-required' )
				.text(
					ve.msg( 'visualeditor-dialog-transclusion-required-parameter-description' )
				)
		);
	} else if ( this.parameter.isDeprecated() ) {
		this.statusIndicator
			.setIndicator( 'alert' )
			.setTitle(
				ve.msg( 'visualeditor-dialog-transclusion-deprecated-parameter' )
			);
		this.$description.append(
			$( '<p>' )
				.addClass( 've-ui-mwParameterPage-description-deprecated' )
				.text(
					ve.msg(
						'visualeditor-dialog-transclusion-deprecated-parameter-description',
						this.spec.getParameterDeprecationDescription( paramName )
					)
				)
		);
	}

	if ( this.defaultValue ) {
		this.$description.append(
			$( '<p>' )
				.addClass( 've-ui-mwParameterPage-description-default' )
				.text(
					ve.msg( 'visualeditor-dialog-transclusion-param-default', this.defaultValue )
				)
		);
	}

	if ( this.exampleValue ) {
		this.$description.append(
			$( '<p>' )
				.addClass( 've-ui-mwParameterPage-description-example' )
				.text(
					ve.msg( 'visualeditor-dialog-transclusion-param-example', this.exampleValue )
				)
		);
	}

	// Construct the action buttons

	if ( !this.rawValueInput ) {
		this.rawFallbackButton = new OO.ui.ButtonWidget( {
			framed: false,
			icon: 'wikiText',
			title: ve.msg( 'visualeditor-dialog-transclusion-raw-fallback' )
		} )
			.connect( this, { click: 'onRawFallbackButtonClick' } );

		this.$actions.append( this.rawFallbackButton.$element );
	}

	if ( this.$description.text().trim() === '' ) {
		this.infoButton = new OO.ui.ButtonWidget( {
			disabled: true,
			title: ve.msg( 'visualeditor-dialog-transclusion-param-info-missing' ),
			framed: false,
			icon: 'info',
			classes: [ 've-ui-mwParameterPage-infoButton' ]
		} );
	} else {
		this.infoButton = new OO.ui.PopupButtonWidget( {
			$overlay: config.$overlay,
			popup: {
				$content: this.$description
			},
			title: ve.msg( 'visualeditor-dialog-transclusion-param-info' ),
			framed: false,
			icon: 'info',
			classes: [ 've-ui-mwParameterPage-infoButton' ]
		} );
	}

	this.$actions.append( this.infoButton.$element );

	if ( !this.parameter.isRequired() && !config.readOnly ) {
		this.removeButton = new OO.ui.ButtonWidget( {
			framed: false,
			icon: 'trash',
			title: ve.msg( 'visualeditor-dialog-transclusion-remove-param' ),
			flags: [ 'destructive' ],
			classes: [ 've-ui-mwParameterPage-removeButton' ]
		} )
			.connect( this, { click: 'onRemoveButtonClick' } );

		this.$actions.append( this.removeButton.$element );
	}

	this.addButton = new OO.ui.ButtonWidget( {
		framed: false,
		icon: 'parameter',
		label: ve.msg( 'visualeditor-dialog-transclusion-add-param' ),
		tabIndex: -1
	} )
		.connect( this, { click: 'onAddButtonFocus' } );

	// Events
	this.$labelElement.on( 'click', this.onLabelClick.bind( this ) );

	// Initialization
	this.$info
		.addClass( 've-ui-mwParameterPage-info' )
		.append( this.$labelElement, this.statusIndicator.$element );
	this.$actions
		.addClass( 've-ui-mwParameterPage-actions' );
	this.$labelElement
		.addClass( 've-ui-mwParameterPage-label' )
		.text( this.spec.getParameterLabel( paramName ) );
	this.$field
		.addClass( 've-ui-mwParameterPage-field' )
		.append(
			this.valueInput.$element
		);
	this.$more
		.addClass( 've-ui-mwParameterPage-more' )
		.append( this.addButton.$element );
	this.$element
		.addClass( 've-ui-mwParameterPage' )
		.append( this.$info, this.$field, this.$actions );
	if ( !config.readOnly ) {
		this.$element.append( this.$more );
	}
};

/* Inheritance */

OO.inheritClass( ve.ui.MWParameterPage, OO.ui.PageLayout );

/* Methods */

/**
 * Get default configuration for an input widget.
 *
 * @private
 * @return {Object}
 */
ve.ui.MWParameterPage.prototype.getDefaultInputConfig = function () {
	var required = this.parameter.isRequired(),
		valueInputConfig = {
			autosize: true,
			required: required,
			validate: required ? 'non-empty' : null
		};

	if ( this.defaultValue ) {
		valueInputConfig.placeholder = ve.msg(
			'visualeditor-dialog-transclusion-param-default',
			this.defaultValue
		);
	} else if ( this.exampleValue ) {
		valueInputConfig.placeholder = ve.msg(
			'visualeditor-dialog-transclusion-param-example',
			this.exampleValue
		);
	}

	return valueInputConfig;
};

/**
 * Create a value input widget based on the parameter type and whether it is
 * required or not.
 *
 * @return {OO.ui.InputWidget}
 */
ve.ui.MWParameterPage.prototype.createValueInput = function () {
	var type = this.parameter.getType(),
		value = this.parameter.getValue(),
		valueInputConfig = this.getDefaultInputConfig();

	this.rawValueInput = false;
	delete valueInputConfig.validate;

	// TODO:
	// * wiki-file-name
	// * date - T100206
	// * number - T124850
	// * unbalanced-wikitext/content - T106242
	// * string? - T124917
	if (
		type === 'wiki-page-name' &&
		( value === '' || mw.Title.newFromText( value ) )
	) {
		return new mw.widgets.TitleInputWidget( valueInputConfig, { api: ve.init.target.getContentApi() } );
	} else if (
		type === 'wiki-user-name' &&
		( value === '' || mw.Title.newFromText( value ) )
	) {
		valueInputConfig.validate = function ( value ) {
			// TODO: Check against wgMaxNameChars
			// TODO: Check against unicode validation regex from MW core's User::isValidUserName
			return !!mw.Title.newFromText( value );
		};
		return new mw.widgets.UserInputWidget( valueInputConfig, { api: ve.init.target.getContentApi() } );
	} else if (
		type === 'wiki-template-name' &&
		( value === '' || mw.Title.newFromText( value ) )
	) {
		return new mw.widgets.TitleInputWidget( $.extend( {}, valueInputConfig, {
			namespace: mw.config.get( 'wgNamespaceIds' ).template,
			api: ve.init.target.getContentApi()
		} ) );
	} else if ( type === 'boolean' && ( value === '1' || value === '0' ) ) {
		return new ve.ui.MWParameterCheckboxInputWidget( valueInputConfig );
	} else if (
		type === 'url' &&
		(
			value === '' ||
			ve.init.platform.getExternalLinkUrlProtocolsRegExp().exec( value.trim() )
		)
	) {
		return ve.ui.MWExternalLinkAnnotationWidget.static.createExternalLinkInputWidget( valueInputConfig );
	} else if ( type !== 'line' || value.indexOf( '\n' ) !== -1 ) {
		// If the type is line, but there are already newlines in the provided
		// value, don't break the existing content by only providing a single-
		// line field. (This implies that the TemplateData for the field isn't
		// complying with its use in practice...)
		this.rawValueInput = true;
		return new ve.ui.MWLazyMultilineTextInputWidget( valueInputConfig );
	}

	return new OO.ui.TextInputWidget( valueInputConfig );
};

/**
 * Check if the parameter is empty
 *
 * @return {boolean} The parameter is empty
 */
ve.ui.MWParameterPage.prototype.isEmpty = function () {
	return this.valueInput.getValue() === '' && this.defaultValue === '';
};

/**
 * Handle change events from the value input
 *
 * @param {string} value Value
 */
ve.ui.MWParameterPage.prototype.onValueInputChange = function () {
	var value = this.valueInput.getValue();

	this.edited = true;
	this.parameter.setValue( value );

	if ( this.outlineItem ) {
		this.outlineItem.setFlags( { empty: this.isEmpty() } );
	}
};

/**
 * Handle click events from the remove button
 */
ve.ui.MWParameterPage.prototype.onRemoveButtonClick = function () {
	this.parameter.remove();
};

/**
 * Handle click events from the raw fallback button
 */
ve.ui.MWParameterPage.prototype.onRawFallbackButtonClick = function () {
	this.valueInput.$element.detach();
	if ( this.rawValueInput ) {
		this.valueInput = this.createValueInput()
			.setValue( this.valueInput.getValue() );
	} else {
		this.valueInput = new OO.ui.TextInputWidget( this.getDefaultInputConfig() )
			.setValue( this.edited ? this.valueInput.getValue() : this.originalValue );
		this.valueInput.$input.addClass( 've-ui-mwParameter-wikitextFallbackInput' );
		this.rawValueInput = true;
	}
	this.valueInput.connect( this, { change: 'onValueInputChange' } );
	this.$field.append( this.valueInput.$element );
};

/**
 * Handle click events from the add button
 */
ve.ui.MWParameterPage.prototype.onAddButtonFocus = function () {
	var template = this.parameter.getTemplate();
	template.addParameter( new ve.dm.MWParameterModel( template ) );
};

/**
 * Handle click events from the label element
 *
 * @param {jQuery.Event} e Click event
 */
ve.ui.MWParameterPage.prototype.onLabelClick = function () {
	this.valueInput.simulateLabelClick();
};

/**
 * @inheritdoc
 */
ve.ui.MWParameterPage.prototype.setOutlineItem = function () {
	// Parent method
	ve.ui.MWParameterPage.super.prototype.setOutlineItem.apply( this, arguments );

	if ( this.outlineItem ) {
		this.outlineItem
			.setIcon( 'parameter' )
			.setMovable( false )
			.setRemovable( true )
			.setLevel( 1 )
			.setFlags( { empty: this.isEmpty() } )
			.setLabel( this.spec.getParameterLabel( this.parameter.getName() ) );

		if ( this.parameter.isRequired() ) {
			this.outlineItem
				.setIndicator( 'required' )
				.setTitle(
					ve.msg( 'visualeditor-dialog-transclusion-required-parameter' )
				);
		}
		if ( this.parameter.isDeprecated() ) {
			this.outlineItem
				.setIndicator( 'alert' )
				.setTitle(
					ve.msg( 'visualeditor-dialog-transclusion-deprecated-parameter' )
				);
		}
	}
};

/**
 * @inheritdoc
 */
ve.ui.MWParameterPage.prototype.focus = function () {
	this.valueInput.focus();
};
