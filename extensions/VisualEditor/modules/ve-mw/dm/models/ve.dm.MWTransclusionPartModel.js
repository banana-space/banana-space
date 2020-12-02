/*!
 * VisualEditor DataModel MWTransclusionPartModel class.
 *
 * @copyright 2011-2020 VisualEditor Team and others; see AUTHORS.txt
 * @license The MIT License (MIT); see LICENSE.txt
 */

/**
 * MediaWiki transclusion part model.
 *
 * @class
 * @mixins OO.EventEmitter
 *
 * @constructor
 * @param {ve.dm.MWTransclusionModel} transclusion Transclusion
 */
ve.dm.MWTransclusionPartModel = function VeDmMWTransclusionPartModel( transclusion ) {
	// Mixin constructors
	OO.EventEmitter.call( this );

	// Properties
	this.transclusion = transclusion;
	this.id = 'part_' + this.transclusion.getUniquePartId();
};

/* Inheritance */

OO.mixinClass( ve.dm.MWTransclusionPartModel, OO.EventEmitter );

/* Events */

/**
 * @event change
 */

/* Methods */

/**
 * Get transclusion part is in.
 *
 * @return {ve.dm.MWTransclusionModel} Transclusion
 */
ve.dm.MWTransclusionPartModel.prototype.getTransclusion = function () {
	return this.transclusion;
};

/**
 * Get a unique part ID within the transclusion.
 *
 * @return {string} Unique ID
 */
ve.dm.MWTransclusionPartModel.prototype.getId = function () {
	return this.id;
};

/**
 * Remove part from transclusion.
 */
ve.dm.MWTransclusionPartModel.prototype.remove = function () {
	this.transclusion.removePart( this );
};

/**
 * Get serialized representation of transclusion part.
 *
 * @return {Mixed} Serialized representation, or undefined if empty
 */
ve.dm.MWTransclusionPartModel.prototype.serialize = function () {
	return undefined;
};

/**
 * Get the wikitext for this part.
 *
 * @return {string} Wikitext
 */
ve.dm.MWTransclusionPartModel.prototype.getWikitext = function () {
	return '';
};

/**
 * Add all non-existing required and suggested parameters, if any.
 *
 * @return {number} Number of parameters added
 */
ve.dm.MWTransclusionPartModel.prototype.addPromptedParameters = function () {
	return 0;
};
