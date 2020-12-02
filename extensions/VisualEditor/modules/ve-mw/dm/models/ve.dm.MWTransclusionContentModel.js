/*!
 * VisualEditor DataModel MWTransclusionContentModel class.
 *
 * @copyright 2011-2020 VisualEditor Team and others; see AUTHORS.txt
 * @license The MIT License (MIT); see LICENSE.txt
 */

/**
 * MediaWiki transclusion content model.
 *
 * @class
 * @extends ve.dm.MWTransclusionPartModel
 *
 * @constructor
 * @param {ve.dm.MWTransclusionModel} transclusion Transclusion
 * @param {string} [value] Content value
 */
ve.dm.MWTransclusionContentModel = function VeDmMWTransclusionContentModel( transclusion, value ) {
	// Parent constructor
	ve.dm.MWTransclusionContentModel.super.call( this, transclusion );

	// Properties
	this.value = value || '';
};

/* Inheritance */

OO.inheritClass( ve.dm.MWTransclusionContentModel, ve.dm.MWTransclusionPartModel );

/* Events */

/**
 * @event change
 */

/* Methods */

/**
 * Get content value.
 *
 * @return {string} Content value
 */
ve.dm.MWTransclusionContentModel.prototype.getValue = function () {
	return this.value;
};

/**
 * Set content value.
 *
 * @param {string} value Content value
 */
ve.dm.MWTransclusionContentModel.prototype.setValue = function ( value ) {
	this.value = value;
	this.emit( 'change' );
};

/**
 * @inheritdoc
 */
ve.dm.MWTransclusionContentModel.prototype.serialize = function () {
	return this.value;
};

/**
 * @inheritdoc
 */
ve.dm.MWTransclusionPartModel.prototype.getWikitext = function () {
	return this.value;
};
