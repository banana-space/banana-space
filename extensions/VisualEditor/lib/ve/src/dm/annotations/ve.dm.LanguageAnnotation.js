/*!
 * VisualEditor DataModel LanguageAnnotation class.
 *
 * @copyright 2011-2020 VisualEditor Team and others; see http://ve.mit-license.org
 */

/**
 * DataModel language annotation.
 *
 * Represents `<span>` tags with 'lang' and 'dir' properties.
 *
 * @class
 * @extends ve.dm.Annotation
 * @constructor
 * @param {Object} element
 */
ve.dm.LanguageAnnotation = function VeDmLanguageAnnotation() {
	// Parent constructor
	ve.dm.LanguageAnnotation.super.apply( this, arguments );
};

/* Inheritance */

OO.inheritClass( ve.dm.LanguageAnnotation, ve.dm.Annotation );

/* Static Properties */

ve.dm.LanguageAnnotation.static.name = 'meta/language';

ve.dm.LanguageAnnotation.static.matchTagNames = [ 'span' ];

ve.dm.LanguageAnnotation.static.matchFunction = function ( domElement ) {
	var lang = domElement.getAttribute( 'lang' ),
		dir = ( domElement.getAttribute( 'dir' ) || '' ).toLowerCase();
	return lang || dir === 'ltr' || dir === 'rtl';
};

ve.dm.LanguageAnnotation.static.applyToAppendedContent = true;

ve.dm.LanguageAnnotation.static.toDataElement = function ( domElements ) {
	return {
		type: this.name,
		attributes: {
			lang: domElements[ 0 ].getAttribute( 'lang' ),
			dir: domElements[ 0 ].getAttribute( 'dir' )
		}
	};
};

ve.dm.LanguageAnnotation.static.toDomElements = function ( dataElement, doc ) {
	var domElement = doc.createElement( 'span' );
	if ( dataElement.attributes.lang ) {
		domElement.setAttribute( 'lang', dataElement.attributes.lang );
	}
	if ( dataElement.attributes.dir ) {
		domElement.setAttribute( 'dir', dataElement.attributes.dir );
	}

	return [ domElement ];
};

ve.dm.LanguageAnnotation.static.describeChange = function ( key, change ) {
	if ( key === 'lang' ) {
		return ve.htmlMsg( 'visualeditor-changedesc-language',
			this.wrapText( 'del', ve.init.platform.getLanguageName( change.from.toLowerCase() ) ),
			this.wrapText( 'ins', ve.init.platform.getLanguageName( change.to.toLowerCase() ) )
		);
	}

	// TODO: Show something nicer than 'null', 'ltr', and 'rtl'.
	if ( key === 'dir' ) {
		return ve.htmlMsg( 'visualeditor-changedesc-direction',
			this.wrapText( 'del', change.from.toLowerCase() ),
			this.wrapText( 'ins', change.to.toLowerCase() )
		);
	}

	// Parent method
	return ve.dm.LanguageAnnotation.parent.static.describeChange.apply( this, arguments );
};

/* Methods */

/**
 * @return {Object}
 */
ve.dm.LanguageAnnotation.prototype.getComparableObject = function () {
	return {
		type: 'meta/language',
		lang: this.getAttribute( 'lang' ),
		dir: this.getAttribute( 'dir' )
	};
};

/* Registration */

ve.dm.modelRegistry.register( ve.dm.LanguageAnnotation );
