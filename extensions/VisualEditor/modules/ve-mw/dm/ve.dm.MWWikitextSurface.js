/*!
 * VisualEditor DataModel Surface class.
 *
 * @copyright 2011-2020 VisualEditor Team and others; see http://ve.mit-license.org
 */

/**
 * DataModel surface.
 *
 * @class
 * @extends ve.dm.Surface
 *
 * @constructor
 * @param {ve.dm.Document} doc
 * @param {Object} [config]
 */
ve.dm.MWWikitextSurface = function VeDmMwWikitextSurface( doc, config ) {
	// Parent constructors
	ve.dm.MWWikitextSurface.super.call( this, doc, ve.extendObject( config, { sourceMode: true } ) );
};

/* Inheritance */

OO.inheritClass( ve.dm.MWWikitextSurface, ve.dm.Surface );

/**
 * @inheritdoc
 */
ve.dm.MWWikitextSurface.prototype.getFragment = function ( selection, noAutoSelect, excludeInsertions ) {
	return new ve.dm.MWWikitextSurfaceFragment( this, selection || this.selection, noAutoSelect, excludeInsertions );
};
