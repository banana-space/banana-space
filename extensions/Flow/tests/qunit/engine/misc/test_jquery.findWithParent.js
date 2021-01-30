$( function () {
	QUnit.module( 'jquery: findWithParent' );

	QUnit.test( 'jQueryFindWithParent', function ( assert ) {
		var $html = $( '<main id="wrapper"><pre class="no-margin"><div><p></p></div></pre></main>' );

		assert.strictEqual(
			$html.findWithParent( 'p < div' )[ 0 ].tagName, 'DIV',
			'finds outer node div from p'
		);
		assert.strictEqual(
			$html.findWithParent( 'div > p' )[ 0 ].tagName, 'P',
			'finds inner node p from div'
		);
		assert.strictEqual(
			$html.findWithParent( '< #wrapper > pre' )[ 0 ].className, 'no-margin',
			'finds element using a class of parent'
		);

		assert.strictEqual(
			$html.findWithParent( '.no-margin < div' ).length, 0,
			'doesn\'t finds a element when using wrong arrow'
		);
		assert.notStrictEqual(
			$html.findWithParent( '.no-margin > div' ).length, 0,
			'finds a element when using correct arrow'
		);

		assert.strictEqual(
			$html.findWithParent( 'div > p, p < div' ).length, 2,
			'finds multiple elements when using selectors seperated by comma'
		);
		assert.strictEqual(
			$html.findWithParent( 'ul > p' ).length, 0,
			'doesn\'t find element when using non existing node'
		);
	} );
} );
