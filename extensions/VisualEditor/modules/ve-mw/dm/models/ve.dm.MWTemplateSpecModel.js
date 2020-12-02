/*!
 * VisualEditor DataModel MWTemplateSpecModel class.
 *
 * @copyright 2011-2020 VisualEditor Team and others; see AUTHORS.txt
 * @license The MIT License (MIT); see LICENSE.txt
 */

/**
 * MediaWiki template specification.
 *
 * See https://raw.github.com/wikimedia/mediawiki-extensions-TemplateData/master/spec.templatedata.json
 * for the latest version of the TemplateData specification.
 *
 * @class
 *
 * @constructor
 * @param {ve.dm.MWTemplateModel} template Template
 */
ve.dm.MWTemplateSpecModel = function VeDmMWTemplateSpecModel( template ) {
	// Properties
	this.template = template;
	this.description = null;
	this.params = {};
	this.paramOrder = [];
	this.sets = [];
	this.maps = {};

	// Initialization
	this.fillFromTemplate();
};

OO.initClass( ve.dm.MWTemplateSpecModel );

/* Static methods */

ve.dm.MWTemplateSpecModel.static.getLocalValue = function ( stringOrObject, lang ) {
	return stringOrObject && typeof stringOrObject === 'object' ?
		OO.ui.getLocalValue( stringOrObject, lang ) :
		stringOrObject;
};

/* Methods */

/**
 * Extend with template spec data.
 *
 * Template spec data is available from the TemplateData extension's API. Extension is passive so
 * any filled in values are not overwritten unless new values are available. This prevents changes
 * in the API or fill methods from causing issues.
 *
 * @param {Object} data Template spec data
 * @param {string} [data.description] Template description
 * @param {string[]} [data.paramOrder] Canonically ordered parameter names
 * @param {Object} [data.params] Template param specs keyed by param name
 * @param {Array} [data.sets] Lists of param sets
 */
ve.dm.MWTemplateSpecModel.prototype.extend = function ( data ) {
	var key, param, i, len;

	if ( data.description !== null ) {
		this.description = data.description;
	}
	if ( Array.isArray( data.paramOrder ) ) {
		this.paramOrder = data.paramOrder.slice();
	}
	if ( data.params ) {
		for ( key in data.params ) {
			// Pre-fill spec
			if ( !this.params[ key ] ) {
				this.params[ key ] = this.getDefaultParameterSpec( key );
			}
			param = this.params[ key ];
			// Extend existing spec
			ve.extendObject( true, this.params[ key ], data.params[ key ] );
			// Add aliased references
			if ( param.aliases.length ) {
				for ( i = 0, len = param.aliases.length; i < len; i++ ) {
					this.params[ param.aliases[ i ] ] = param;
				}
			}
		}
	}
	this.sets = data.sets;
	if ( data.maps ) {
		this.maps = data.maps;
	}
};

/**
 * Fill from template.
 *
 * Filling is passive, so existing information is never overwritten. The spec should be re-filled
 * after a parameter is added to ensure it's still complete, and this is safe because existing data
 * is never overwritten.
 */
ve.dm.MWTemplateSpecModel.prototype.fillFromTemplate = function () {
	var key;

	for ( key in this.template.getParameters() ) {
		if ( key && !this.params[ key ] ) {
			this.params[ key ] = this.getDefaultParameterSpec( key );
		}
	}
};

/**
 * Get the default spec for a parameter.
 *
 * @param {string} name Parameter name
 * @return {Object} Parameter spec
 */
ve.dm.MWTemplateSpecModel.prototype.getDefaultParameterSpec = function ( name ) {
	return {
		label: name,
		description: null,
		default: '',
		type: 'string',
		aliases: [],
		name: name,
		required: false,
		suggested: false,
		deprecated: false
	};
};

/**
 * Get template label.
 *
 * @return {string} Template label
 */
ve.dm.MWTemplateSpecModel.prototype.getLabel = function () {
	var titleObj,
		title = this.template.getTitle(),
		target = this.template.getTarget();

	if ( title ) {
		try {
			// Normalize and remove namespace prefix if in the Template: namespace
			titleObj = new mw.Title( title );
			title = titleObj.getRelativeText( mw.config.get( 'wgNamespaceIds' ).template );
		} catch ( e ) { }
	}

	return title || target.wt;
};

/**
 * Get template description.
 *
 * @param {string} [lang] Language to get description in
 * @return {string|null} Template description or null if not available
 */
ve.dm.MWTemplateSpecModel.prototype.getDescription = function ( lang ) {
	return this.constructor.static.getLocalValue( this.description, lang );
};

/**
 * Get parameter order.
 *
 * @return {string[]} Canonically ordered parameter names
 */
ve.dm.MWTemplateSpecModel.prototype.getParameterOrder = function () {
	return this.paramOrder.slice();
};

/**
 * Check if a parameter name is known.
 *
 * Could be a primary name or alias.
 *
 * @param {string} name Parameter name
 * @return {boolean} Parameter name is known
 */
ve.dm.MWTemplateSpecModel.prototype.isParameterKnown = function ( name ) {
	return this.params[ name ] !== undefined;
};

/**
 * Check if a parameter name is an alias.
 *
 * @param {string} name Parameter name
 * @return {boolean} Parameter name is an alias
 */
ve.dm.MWTemplateSpecModel.prototype.isParameterAlias = function ( name ) {
	return this.params[ name ] !== undefined && this.params[ name ].name !== name;
};

/**
 * Get a parameter label.
 *
 * @param {string} name Parameter name
 * @param {string} [lang] Language to get label in
 * @return {string} Parameter label
 */
ve.dm.MWTemplateSpecModel.prototype.getParameterLabel = function ( name, lang ) {
	return this.constructor.static.getLocalValue( this.params[ name ].label || name, lang );
};

/**
 * Get a parameter description.
 *
 * @param {string} name Parameter name
 * @param {string} [lang] Language to get description
 * @return {string|null} Parameter description
 */
ve.dm.MWTemplateSpecModel.prototype.getParameterDescription = function ( name, lang ) {
	return this.constructor.static.getLocalValue( this.params[ name ].description, lang );
};

/**
 * Get a parameter value.
 *
 * @param {string} name Parameter name
 * @return {string} Default parameter value
 */
ve.dm.MWTemplateSpecModel.prototype.getParameterDefaultValue = function ( name ) {
	return this.params[ name ].default;
};

/**
 * Get a parameter example value.
 *
 * @param {string} name Parameter name
 * @param {string} [lang] Language to get description
 * @return {string} Example parameter value
 */
ve.dm.MWTemplateSpecModel.prototype.getParameterExampleValue = function ( name, lang ) {
	return this.constructor.static.getLocalValue( this.params[ name ].example, lang );
};

/**
 * Get a parameter auto value.
 *
 * @param {string} name Parameter name
 * @return {string} Auto-value for the parameter
 */
ve.dm.MWTemplateSpecModel.prototype.getParameterAutoValue = function ( name ) {
	return this.params[ name ].autovalue;
};

/**
 * Get a parameter type.
 *
 * @param {string} name Parameter name
 * @return {string} Parameter type
 */
ve.dm.MWTemplateSpecModel.prototype.getParameterType = function ( name ) {
	return this.params[ name ].type;
};

/**
 * Get parameter aliases.
 *
 * @param {string} name Parameter name
 * @return {string[]} Alternate parameter names
 */
ve.dm.MWTemplateSpecModel.prototype.getParameterAliases = function ( name ) {
	return this.params[ name ].aliases;
};

/**
 * Get the parameter name, resolving an alias.
 *
 * If a parameter is not an alias of another, the output will be the same as the input.
 *
 * @param {string} name Parameter alias
 * @return {string} Parameter name
 */
ve.dm.MWTemplateSpecModel.prototype.getParameterName = function ( name ) {
	return this.params[ name ].name;
};

/**
 * Check if parameter is required.
 *
 * @param {string} name Parameter name
 * @return {boolean} Parameter is required
 */
ve.dm.MWTemplateSpecModel.prototype.isParameterRequired = function ( name ) {
	return !!this.params[ name ].required;
};

/**
 * Check if parameter is suggested.
 *
 * @param {string} name Parameter name
 * @return {boolean} Parameter is suggested
 */
ve.dm.MWTemplateSpecModel.prototype.isParameterSuggested = function ( name ) {
	return !!this.params[ name ].suggested;
};

/**
 * Check if parameter is deprecated.
 *
 * @param {string} name Parameter name
 * @return {boolean} Parameter is deprecated
 */
ve.dm.MWTemplateSpecModel.prototype.isParameterDeprecated = function ( name ) {
	return this.params[ name ].deprecated !== false;
};

/**
 * Get parameter deprecation description.
 *
 * @param {string} name Parameter name
 * @return {string} Explaining of why parameter is deprecated, empty if parameter is either not
 *   deprecated or no description has been specified
 */
ve.dm.MWTemplateSpecModel.prototype.getParameterDeprecationDescription = function ( name ) {
	return typeof this.params[ name ].deprecated === 'string' ?
		this.params[ name ].deprecated : '';
};

/**
 * Get all primary parameter names.
 *
 * @return {string[]} Parameter names
 */
ve.dm.MWTemplateSpecModel.prototype.getParameterNames = function () {
	var name,
		names = [];

	for ( name in this.params ) {
		if ( this.params[ name ].name === name ) {
			names.push( name );
		}
	}

	return names;
};

/**
 * Get parameter sets.
 *
 * @return {Object[]} Lists of parameter set descriptors
 */
ve.dm.MWTemplateSpecModel.prototype.getParameterSets = function () {
	return this.sets;
};

/**
 * Get map describing relationship between another content type and the parameters.
 *
 * @return {Object} Object with application property maps to parameters keyed to application name.
 */
ve.dm.MWTemplateSpecModel.prototype.getMaps = function () {
	return this.maps;
};
