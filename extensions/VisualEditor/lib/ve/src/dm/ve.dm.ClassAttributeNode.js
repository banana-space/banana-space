/*!
 * VisualEditor DataModel ClassAttribute class.
 *
 * @copyright 2011-2020 VisualEditor Team and others; see http://ve.mit-license.org
 */

/**
 * DataModel class-attribute node.
 *
 * Used for nodes which use classes to store attributes.
 *
 * @class
 * @abstract
 *
 * @constructor
 */
ve.dm.ClassAttributeNode = function VeDmClassAttributeNode() {};

/* Inheritance */

OO.initClass( ve.dm.ClassAttributeNode );

/* Static methods */

/**
 * Mapping from class names to attributes
 *
 * e.g. { alignLeft: { align: 'left' } } sets the align attribute to 'left'
 * if the element has the class 'alignLeft'
 *
 * @type {Object}
 */
ve.dm.ClassAttributeNode.static.classAttributes = {};

ve.dm.ClassAttributeNode.static.preserveHtmlAttributes = function ( attribute ) {
	return attribute !== 'class';
};

/**
 * Set attributes from a class attribute
 *
 * Unrecognized classes are also preserved.
 *
 * @param {Object} attributes Attributes object to modify
 * @param {string|null} classAttr Class attribute from an element
 */
ve.dm.ClassAttributeNode.static.setClassAttributes = function ( attributes, classAttr ) {
	var className, i, l,
		unrecognizedClasses = [],
		classNames = classAttr ? classAttr.trim().split( /\s+/ ) : [];

	if ( !classNames.length ) {
		return;
	}

	for ( i = 0, l = classNames.length; i < l; i++ ) {
		className = classNames[ i ];
		if ( Object.prototype.hasOwnProperty.call( this.classAttributes, className ) ) {
			attributes = ve.extendObject( attributes, this.classAttributes[ className ] );
		} else {
			unrecognizedClasses.push( className );
		}
	}

	attributes.originalClasses = classAttr;
	attributes.unrecognizedClasses = unrecognizedClasses;
};

/**
 * Get class attribute from element attributes
 *
 * @param {Object|undefined} attributes Element attributes
 * @return {string|null} Class name, or null if no classes to set
 */
ve.dm.ClassAttributeNode.static.getClassAttrFromAttributes = function ( attributes ) {
	var className, key, classAttributeSet, hasClass,
		classNames = [];

	attributes = attributes || {};

	for ( className in this.classAttributes ) {
		classAttributeSet = this.classAttributes[ className ];
		hasClass = true;
		for ( key in classAttributeSet ) {
			if ( attributes[ key ] !== classAttributeSet[ key ] ) {
				hasClass = false;
				break;
			}
		}
		if ( hasClass ) {
			classNames.push( className );
		}
	}

	if ( attributes.unrecognizedClasses ) {
		classNames = OO.simpleArrayUnion( classNames, attributes.unrecognizedClasses );
	}

	// If no meaningful change in classes, preserve order
	if (
		attributes.originalClasses &&
		ve.compareClassLists( attributes.originalClasses, classNames )
	) {
		return attributes.originalClasses;
	} else if ( classNames.length > 0 ) {
		return classNames.join( ' ' );
	}

	return null;
};

/**
 * @inheritdoc ve.dm.Node
 */
ve.dm.ClassAttributeNode.static.sanitize = function ( dataElement ) {
	if ( dataElement.attributes ) {
		delete dataElement.attributes.unrecognizedClasses;
	}
};
