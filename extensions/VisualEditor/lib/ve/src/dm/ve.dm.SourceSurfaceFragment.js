/*!
 * VisualEditor DataModel SourceSurfaceFragment class.
 *
 * @copyright 2011-2020 VisualEditor Team and others; see http://ve.mit-license.org
 */

/**
 * Surface fragment for editing surfaces in source mode.
 *
 * @class
 * @extends ve.dm.SurfaceFragment
 *
 * @constructor
 * @param {ve.dm.Document} doc
 */
ve.dm.SourceSurfaceFragment = function VeDmSourceSurfaceFragment() {
	// Parent constructors
	ve.dm.SourceSurfaceFragment.super.apply( this, arguments );
};

/* Inheritance */

OO.inheritClass( ve.dm.SourceSurfaceFragment, ve.dm.SurfaceFragment );

/**
 * @inheritdoc
 */
ve.dm.SourceSurfaceFragment.prototype.annotateContent = function () {
	var tempFragment, tempSurfaceModel,
		args = arguments,
		fragment = this,
		text = this.getText( true );

	this.pushPending( this.convertFromSource( text ).then( function ( selectionDocument ) {
		tempSurfaceModel = new ve.dm.Surface( selectionDocument );
		tempFragment = tempSurfaceModel.getLinearFragment(
			// TODO: Find content offsets
			selectionDocument.getDocumentRange()
		);
		tempFragment.annotateContent.apply( tempFragment, args );

		fragment.clearPending();
		fragment.insertDocument( tempFragment.getDocument() );
		return fragment.getPending();
	} ) );

	return this;
};

/**
 * @inheritdoc
 */
ve.dm.SourceSurfaceFragment.prototype.getAnnotations = function () {
	// Source surface contains no annotations
	return new ve.dm.AnnotationSet( this.getDocument().getStore() );
};

/**
 * @inheritdoc
 */
ve.dm.SourceSurfaceFragment.prototype.convertNodes = function () {
	var tempFragment, tempSurfaceModel,
		args = arguments,
		fragment = this,
		text = this.getText( true );

	this.pushPending( this.convertFromSource( text ).then( function ( selectionDocument ) {
		tempSurfaceModel = new ve.dm.Surface( selectionDocument );
		tempFragment = tempSurfaceModel.getLinearFragment(
			// TODO: Find content offsets
			selectionDocument.getDocumentRange()
		);
		tempFragment.convertNodes.apply( tempFragment, args );

		fragment.clearPending();
		fragment.insertDocument( tempFragment.getDocument() );
		return fragment.getPending();
	} ) );

	return this;
};

/**
 * @inheritdoc
 */
ve.dm.SourceSurfaceFragment.prototype.insertContent = function ( content, annotate ) {
	var data;

	if ( typeof content !== 'string' ) {
		data = new ve.dm.ElementLinearData( new ve.dm.HashValueStore(), content );
		// Pass `annotate` as `ignoreCoveringAnnotations`. If matching the target annotation (plain text) strip covering annotations.
		if ( !data.isPlainText( null, false, [ 'paragraph' ], annotate ) ) {
			this.insertDocument( new ve.dm.Document( content.concat( [ { type: 'internalList' }, { type: '/internalList' } ] ) ) );
			return this;
		}
	} else {
		content = ve.dm.sourceConverter.getDataFromSourceText( content, true );
	}

	// Parent method
	return ve.dm.SourceSurfaceFragment.super.prototype.insertContent.call( this, content );
};

/**
 * @inheritdoc
 */
ve.dm.SourceSurfaceFragment.prototype.insertDocument = function ( doc, newDocRange, annotate ) {
	var data, i, l,
		range = this.getSelection().getCoveringRange(),
		fragment = this;

	if ( !range ) {
		return this;
	}

	if ( newDocRange ) {
		doc = doc.shallowCloneFromRange( newDocRange );
		newDocRange = doc.originalRange;
	}

	// Pass `annotate` as `ignoreCoveringAnnotations`. If matching the target annotation (plain text) strip covering annotations.
	if ( doc.data.isPlainText( newDocRange, false, [ 'paragraph' ], annotate ) ) {
		data = doc.data.getDataSlice( newDocRange );
		for ( i = 0, l = data.length; i < l; i++ ) {
			// Remove any text annotations, as we have determined them to be covering
			if ( Array.isArray( data[ i ] ) ) {
				data[ i ] = data[ i ][ 0 ];
			}
			// Convert newlines to paragraph breaks (T156498)
			if ( data[ i ] === '\r' ) {
				data.splice( i, 1 );
				i--;
				l--;
			} else if ( data[ i ] === '\n' ) {
				data.splice( i, 1, { type: '/paragraph' }, { type: 'paragraph' } );
				i++;
				l++;
			} else if ( data[ i ] === '\u00a0' ) {
				// Replace non-breaking spaces, they were probably created during HTML conversion
				// and are invisible to the user so likely to just cause problems.
				// If you want a non-breaking space in source you should probably use &nbsp; T154382
				data[ i ] = ' ';
			}
		}
		return ve.dm.SourceSurfaceFragment.super.prototype.insertContent.call( this, data );
	}

	this.pushPending(
		this.convertToSource( doc ).then(
			function ( source ) {
				fragment.insertContent( source.trim() );
			},
			function () {
				ve.error( 'Failed to convert document', arguments );
				return ve.createDeferred().reject().promise();
			}
		)
	);

	return this;
};

/**
 * @inheritdoc
 */
ve.dm.SourceSurfaceFragment.prototype.wrapAllNodes = function ( wrapOuter, wrapEach ) {
	var i, node, nodes,
		content,
		range = this.getSelection().getCoveringRange();

	if ( !range ) {
		return this;
	}

	function getOpening( element ) {
		element.internal = {
			whitespace: [ '\n', '\n', '\n', '\n' ]
		};
		return element;
	}

	function getClosing( element ) {
		return { type: '/' + element.type };
	}

	if ( !Array.isArray( wrapOuter ) ) {
		wrapOuter = [ wrapOuter ];
	}

	wrapEach = wrapEach || [];

	if ( !Array.isArray( wrapEach ) ) {
		wrapEach = [ wrapEach ];
	}

	nodes = this.getSelectedLeafNodes();

	content = wrapOuter.map( getOpening );
	for ( i = 0; i < nodes.length; i++ ) {
		node = nodes[ i ];
		content = content
			.concat( wrapEach.map( getOpening ) )
			.concat( this.getSurface().getLinearFragment( node.getRange() ).getText().split( '' ) )
			.concat( wrapEach.reverse().map( getClosing ) );
	}
	content = content.concat( wrapOuter.reverse().map( getClosing ) );

	this.insertContent( content );

	return this;
};

/**
 * Convert model slice to source text
 *
 * The default implementation converts to HTML synchronously.
 *
 * If the conversion is asynchronous it should lock the surface
 * until complete.
 *
 * @param {ve.dm.Document} doc Document
 * @return {jQuery.Promise} Promise which resolves with source, or rejects
 */
ve.dm.SourceSurfaceFragment.prototype.convertToSource = function ( doc ) {
	if ( !doc.data.hasContent() ) {
		return ve.createDeferred().resolve( '' ).promise();
	} else {
		return ve.createDeferred().resolve(
			ve.properInnerHtml(
				ve.dm.converter.getDomFromModel( doc ).body
			)
		).promise();
	}
};

/**
 * Convert source text to model slice
 *
 * The default implementation converts from HTML synchronously.
 *
 * If the conversion is asynchronous it should lock the surface
 * until complete.
 *
 * @param {string} source Source text
 * @return {jQuery.Promise} Promise which resolves with document model
 */
ve.dm.SourceSurfaceFragment.prototype.convertFromSource = function ( source ) {
	var lang = this.getDocument().getLang(),
		dir = this.getDocument().getDir();

	if ( !source ) {
		return ve.createDeferred().resolve(
			new ve.dm.Document(
				[
					{ type: 'paragraph', internal: { generated: 'wrapper' } }, { type: '/paragraph' },
					{ type: 'internalList' }, { type: '/internalList' }
				],
				null, null, null, null,
				lang, dir
			)
		).promise();
	} else {
		return ve.createDeferred().resolve(
			ve.dm.converter.getModelFromDom(
				ve.createDocumentFromHtml( source, { lang: lang, dir: dir } )
			)
		).promise();
	}
};
