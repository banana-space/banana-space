/*!
 * VisualEditor DataModel MWParameterModel class.
 *
 * @copyright 2011-2020 VisualEditor Team and others; see AUTHORS.txt
 * @license The MIT License (MIT); see LICENSE.txt
 */

/**
 * MediaWiki template parameter.
 *
 * @class
 * @mixins OO.EventEmitter
 *
 * @constructor
 * @param {ve.dm.MWTemplateModel} template Template
 * @param {string} [name=''] Parameter name
 * @param {string} [value=''] Parameter value
 */
ve.dm.MWParameterModel = function VeDmMWParameterModel( template, name, value ) {
	// Mixin constructors
	OO.EventEmitter.call( this );

	// Properties
	this.template = template;
	this.originalName = name;
	this.name = typeof name === 'string' ? name.trim() : '';
	this.value = value || '';
	this.id = this.template.getId() + '/' + name;
};

/* Inheritance */

OO.mixinClass( ve.dm.MWParameterModel, OO.EventEmitter );

/* Events */

/**
 * @event change
 */

/* Methods */

/**
 * Check if parameter is required.
 *
 * @return {boolean} Parameter is required
 */
ve.dm.MWParameterModel.prototype.isRequired = function () {
	return this.template.getSpec().isParameterRequired( this.name );
};

/**
 * Check if parameter is suggested.
 *
 * @param {string} name Parameter name
 * @return {boolean} Parameter is suggested
 */
ve.dm.MWParameterModel.prototype.isSuggested = function () {
	return this.template.getSpec().isParameterSuggested( this.name );
};

/**
 * Check if parameter is deprecated.
 *
 * @return {boolean} Parameter is deprecated
 */
ve.dm.MWParameterModel.prototype.isDeprecated = function () {
	return this.template.getSpec().isParameterDeprecated( this.name );
};

/**
 * Get template of which this parameter is part.
 *
 * @return {ve.dm.MWTemplateModel} Template
 */
ve.dm.MWParameterModel.prototype.getTemplate = function () {
	return this.template;
};

/**
 * Get unique parameter ID within the transclusion.
 *
 * @return {string} Unique ID
 */
ve.dm.MWParameterModel.prototype.getId = function () {
	return this.id;
};

/**
 * Get parameter name.
 *
 * @return {string} Parameter name
 */
ve.dm.MWParameterModel.prototype.getName = function () {
	return this.name;
};

/**
 * Get parameter name.
 *
 * @return {string} Parameter name
 */
ve.dm.MWParameterModel.prototype.getOriginalName = function () {
	return this.originalName;
};

/**
 * Get parameter value.
 *
 * @return {string} Parameter value, or automatic value if there is none stored.
 *  Otherwise an empty string.
 */
ve.dm.MWParameterModel.prototype.getValue = function () {
	return this.value || this.getAutoValue() || '';
};

/**
 * Get default parameter value.
 *
 * @return {string} Default parameter value
 */
ve.dm.MWParameterModel.prototype.getDefaultValue = function () {
	return this.template.getSpec().getParameterDefaultValue( this.name );
};

/**
 * Get default parameter value.
 *
 * @return {string} Default parameter value
 */
ve.dm.MWParameterModel.prototype.getExampleValue = function () {
	return this.template.getSpec().getParameterExampleValue( this.name );
};

/**
 * Get automatic parameter value.
 *
 * @return {string} Automatic parameter name.
 */
ve.dm.MWParameterModel.prototype.getAutoValue = function () {
	return this.template.getSpec().getParameterAutoValue( this.name );
};

/**
 * Get parameter type.
 *
 * @return {string} Parameter type
 */
ve.dm.MWParameterModel.prototype.getType = function () {
	return this.template.getSpec().getParameterType( this.name );
};

/**
 * Set parameter value.
 *
 * @param {string} value Parameter value
 */
ve.dm.MWParameterModel.prototype.setValue = function ( value ) {
	this.value = value;
	this.emit( 'change' );
};

/**
 * Remove parameter from template.
 */
ve.dm.MWParameterModel.prototype.remove = function () {
	this.template.removeParameter( this );
};
