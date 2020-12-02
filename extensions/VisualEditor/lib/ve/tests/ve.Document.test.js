/*!
 * VisualEditor Document tests.
 *
 * @copyright 2011-2020 VisualEditor Team and others; see http://ve.mit-license.org
 */

QUnit.module( 've.Document' );

/* Stubs */

ve.DocumentStub = function VeDocumentStub() {
	// Parent constructor
	ve.DocumentStub.super.apply( this, arguments );
};

OO.inheritClass( ve.DocumentStub, ve.Document );

/* Tests */

QUnit.test( 'getDocumentNode', function ( assert ) {
	var node = new ve.NodeStub(),
		doc = new ve.DocumentStub( node );
	assert.strictEqual( doc.getDocumentNode(), node );
	assert.strictEqual( node.getDocument(), doc );
} );
