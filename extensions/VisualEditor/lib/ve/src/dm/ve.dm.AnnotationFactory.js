/*!
 * VisualEditor DataModel AnnotationFactory class.
 *
 * @copyright 2011-2020 VisualEditor Team and others; see http://ve.mit-license.org
 */

/**
 * DataModel annotation factory.
 *
 * @class
 * @extends ve.dm.ModelFactory
 * @constructor
 */
ve.dm.AnnotationFactory = function VeDmAnnotationFactory() {
	// Parent constructor
	ve.dm.AnnotationFactory.super.call( this );
};

/* Inheritance */

OO.inheritClass( ve.dm.AnnotationFactory, ve.dm.ModelFactory );

/* Initialization */

ve.dm.annotationFactory = new ve.dm.AnnotationFactory();
