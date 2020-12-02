/*!
 * VisualEditor DataModel MWTemplatePlaceholderModel class.
 *
 * @copyright 2011-2020 VisualEditor Team and others; see AUTHORS.txt
 * @license The MIT License (MIT); see LICENSE.txt
 */

/**
 * MediaWiki template placeholder model.
 *
 * @class
 * @extends ve.dm.MWTransclusionPartModel
 *
 * @constructor
 * @param {ve.dm.MWTransclusionModel} transclusion Transclusion
 */
ve.dm.MWTemplatePlaceholderModel = function VeDmMWTemplatePlaceholderModel() {
	// Parent constructor
	ve.dm.MWTemplatePlaceholderModel.super.apply( this, arguments );
};

/* Inheritance */

OO.inheritClass( ve.dm.MWTemplatePlaceholderModel, ve.dm.MWTransclusionPartModel );
