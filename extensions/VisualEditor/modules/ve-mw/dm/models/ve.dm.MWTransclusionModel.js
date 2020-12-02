/*!
 * VisualEditor DataModel MWTransclusionModel class.
 *
 * @copyright 2011-2020 VisualEditor Team and others; see AUTHORS.txt
 * @license The MIT License (MIT); see LICENSE.txt
 */

( function () {
	var hasOwn = Object.hasOwnProperty,
		specCache = {};

	/**
	 * MediaWiki transclusion model.
	 *
	 * @class
	 * @mixins OO.EventEmitter
	 *
	 * @constructor
	 * @param {ve.dm.Document} doc Document to use associate with API requests
	 */
	ve.dm.MWTransclusionModel = function VeDmMWTransclusionModel( doc ) {
		// Mixin constructors
		OO.EventEmitter.call( this );

		// Properties
		this.doc = doc;
		this.parts = [];
		this.uid = 0;
		this.requests = [];
		this.queue = [];
		this.specCache = specCache;

	};

	/* Inheritance */

	OO.mixinClass( ve.dm.MWTransclusionModel, OO.EventEmitter );

	/* Events */

	/**
	 * @event replace
	 * @param {ve.dm.MWTransclusionPartModel|null} removed Removed part
	 * @param {ve.dm.MWTransclusionPartModel|null} added Added part
	 */

	/**
	 * @event change
	 */

	/* Methods */

	/**
	 * Insert transclusion at the end of a surface fragment.
	 *
	 * If forceType is not specified and this is used in async mode, users of this method
	 * should ensure the surface is not accessible while the type is being evaluated.
	 *
	 * @param {ve.dm.SurfaceFragment} surfaceFragment Surface fragment after which to insert.
	 * @param {boolean|undefined} [forceType] Force the type to 'inline' or 'block'. If not
	 *   specified it will be evaluated asynchronously.
	 * @return {jQuery.Promise} Promise which resolves when the node has been inserted. If
	 *   forceType was specified this will be instant.
	 */
	ve.dm.MWTransclusionModel.prototype.insertTransclusionNode = function ( surfaceFragment, forceType ) {
		var model = this,
			deferred = ve.createDeferred(),
			baseNodeClass = ve.dm.MWTransclusionNode;

		function insertNode( isInline, generatedContents ) {
			var hash, store, nodeClass,
				type = isInline ? baseNodeClass.static.inlineType : baseNodeClass.static.blockType,
				data = [
					{
						type: type,
						attributes: {
							mw: model.getPlainObject()
						}
					},
					{ type: '/' + type }
				];

			// If we just fetched the generated contents, put them in the store
			// so we don't do a duplicate API call later.
			if ( generatedContents ) {
				nodeClass = ve.dm.modelRegistry.lookup( type );
				store = surfaceFragment.getDocument().getStore();
				hash = OO.getHash( [ nodeClass.static.getHashObjectForRendering( data[ 0 ] ), undefined ] );
				store.hash( generatedContents, hash );
			}

			surfaceFragment.insertContent( data );

			deferred.resolve();
		}

		if ( forceType ) {
			insertNode( forceType === 'inline' );
		} else {
			ve.init.target.parseWikitextFragment(
				baseNodeClass.static.getWikitext( this.getPlainObject() ),
				true,
				surfaceFragment.getDocument()
			).then( function ( response ) {
				var contentNodes;

				if ( ve.getProp( response, 'visualeditor', 'result' ) === 'success' ) {
					// This method is only ever run by a client, so it is okay to use jQuery
					// eslint-disable-next-line no-undef
					contentNodes = $.parseHTML( response.visualeditor.content, surfaceFragment.getDocument().getHtmlDocument() ) || [];
					contentNodes = ve.ce.MWTransclusionNode.static.filterRendering( contentNodes );
					insertNode(
						baseNodeClass.static.isHybridInline( contentNodes, ve.dm.converter ),
						contentNodes
					);
				} else {
					// Request failed - just assume inline
					insertNode( true );
				}
			}, function () {
				insertNode( true );
			} );
		}
		return deferred.promise();
	};

	/**
	 * Update transclusion node in a document.
	 *
	 * @param {ve.dm.Surface} surfaceModel Surface model of main document
	 * @param {ve.dm.MWTransclusionNode} node Transclusion node to update
	 */
	ve.dm.MWTransclusionModel.prototype.updateTransclusionNode = function ( surfaceModel, node ) {
		var obj = this.getPlainObject();

		if ( obj !== null ) {
			surfaceModel.getLinearFragment( node.getOuterRange(), true )
				.changeAttributes( { mw: obj } );
		} else {
			surfaceModel.getLinearFragment( node.getOuterRange(), true )
				.removeContent();
		}
	};

	/**
	 * Load from transclusion data, and fetch spec from server.
	 *
	 * @param {Object} data Transclusion data
	 * @return {jQuery.Promise} Promise, resolved when spec is loaded
	 */
	ve.dm.MWTransclusionModel.prototype.load = function ( data ) {
		var i, len, part, deferred,
			promises = [];

		// Convert single part format to multi-part format
		// Parsoid doesn't use this format any more, but we accept it for backwards compatibility
		if ( data.params && data.target ) {
			data = { parts: [ { template: data } ] };
		}

		if ( Array.isArray( data.parts ) ) {
			for ( i = 0, len = data.parts.length; i < len; i++ ) {
				part = data.parts[ i ];
				if ( part.template ) {
					deferred = ve.createDeferred();
					promises.push( deferred.promise() );
					this.queue.push( {
						add: ve.dm.MWTemplateModel.newFromData( this, part.template ),
						deferred: deferred
					} );
				} else if ( typeof part === 'string' ) {
					deferred = ve.createDeferred();
					promises.push( deferred.promise() );
					this.queue.push( {
						add: new ve.dm.MWTransclusionContentModel( this, part ),
						deferred: deferred
					} );
				}
			}
			setTimeout( this.fetch.bind( this ) );
		}

		return ve.promiseAll( promises );
	};

	/**
	 * Process one or more queue items.
	 *
	 * @param {Object[]} queue List of objects containing parts to add and optionally indexes to add
	 *  them at, if no index is given parts will be added at the end
	 * @fires replace For each item added
	 * @fires change
	 */
	ve.dm.MWTransclusionModel.prototype.process = function ( queue ) {
		var i, len, item, title, index, remove, existing, resolveQueue = [];

		for ( i = 0, len = queue.length; i < len; i++ ) {
			remove = 0;
			item = queue[ i ];

			if ( item.add instanceof ve.dm.MWTemplateModel ) {
				title = item.add.getTitle();
				if ( hasOwn.call( specCache, title ) && specCache[ title ] ) {
					item.add.getSpec().extend( specCache[ title ] );
				}
			}

			// Use specified index
			index = item.index;
			// Auto-remove if already existing, preserving index
			existing = this.parts.indexOf( item.add );
			if ( existing !== -1 ) {
				this.removePart( item.add );
				if ( index && existing + 1 < index ) {
					index--;
				}
			}
			// Derive index from removal if given
			if ( index === undefined && item.remove ) {
				index = this.parts.indexOf( item.remove );
				if ( index !== -1 ) {
					remove = 1;
				}
			}
			// Use last index as a last resort
			if ( index === undefined || index === -1 ) {
				index = this.parts.length;
			}

			this.parts.splice( index, remove, item.add );
			if ( item.add ) {
				item.add.connect( this, { change: [ 'emit', 'change' ] } );
			}
			if ( item.remove ) {
				item.remove.disconnect( this );
			}
			this.emit( 'replace', item.remove || null, item.add );

			// Resolve promises
			if ( item.deferred ) {
				resolveQueue.push( item.deferred );
			}
		}
		this.emit( 'change' );

		// We need to go back and resolve the deferreds after emitting change.
		// Otherwise we get silly situations like a single change event being
		// guaranteed after the transclusion loaded promise gets resolved.
		for ( i = 0; i < resolveQueue.length; i++ ) {
			resolveQueue[ i ].resolve();
		}
	};

	/** */
	ve.dm.MWTransclusionModel.prototype.fetch = function () {
		var i, len, item, title, queue,
			templateNamespaceId = mw.config.get( 'wgNamespaceIds' ).template,
			titles = [],
			specs = {};

		if ( !this.queue.length ) {
			return;
		}

		queue = this.queue.slice();

		// Clear shared queue for future calls
		this.queue.length = 0;

		// Get unique list of template titles that aren't already loaded
		for ( i = 0, len = queue.length; i < len; i++ ) {
			item = queue[ i ];
			if ( item.add instanceof ve.dm.MWTemplateModel ) {
				title = item.add.getTitle();
				if (
					// Skip titles that don't have a resolvable href
					title &&
					// Skip titles outside the template namespace
					mw.Title.newFromText(
						title,
						templateNamespaceId
					).namespace === templateNamespaceId &&
					// Skip already cached data
					!hasOwn.call( specCache, title ) &&
					// Skip duplicate titles in the same batch
					titles.indexOf( title ) === -1
				) {
					titles.push( title );
				}
			}
		}

		// Bypass server for empty lists
		if ( !titles.length ) {
			setTimeout( this.process.bind( this, queue ) );
			return;
		}

		this.requests.push( this.fetchRequest( titles, specs, queue ) );
	};

	ve.dm.MWTransclusionModel.prototype.fetchRequest = function ( titles, specs, queue ) {
		var xhr = ve.init.target.getContentApi( this.doc ).get( {
			action: 'templatedata',
			titles: titles,
			lang: mw.config.get( 'wgUserLanguage' ),
			format: 'json',
			doNotIgnoreMissingTitles: '1',
			redirects: '1'
		} ).done( this.fetchRequestDone.bind( this, titles, specs ) );
		xhr.always( this.fetchRequestAlways.bind( this, queue, xhr ) );
		return xhr;
	};

	ve.dm.MWTransclusionModel.prototype.fetchRequestDone = function ( titles, specs, data ) {
		var i, len, id, title, missingTitle, aliasMap = [];

		if ( data && data.pages ) {
			// Keep spec data on hand for future use
			for ( id in data.pages ) {
				title = data.pages[ id ].title;

				if ( data.pages[ id ].missing ) {
					// Remmeber templates that don't exist in the link cache
					// { title: { missing: true|false }
					missingTitle = {};
					missingTitle[ title ] = { missing: true };
					ve.init.platform.linkCache.setMissing( missingTitle );
				} else if ( data.pages[ id ].notemplatedata && !OO.isPlainObject( data.pages[ id ].params ) ) {
					// (T243868) Prevent asking again for templates that have neither user-provided specs
					// nor automatically detected params
					specs[ title ] = null;
				} else {
					specs[ title ] = data.pages[ id ];
				}
			}
			// Follow redirects
			if ( data.redirects ) {
				aliasMap = data.redirects;
			}
			// Follow MW's normalisation
			if ( data.normalized ) {
				ve.batchPush( aliasMap, data.normalized );
			}
			// Cross-reference aliased titles.
			for ( i = 0, len = aliasMap.length; i < len; i++ ) {
				// Only define the alias if the target exists, otherwise
				// we create a new property with an invalid "undefined" value.
				if ( hasOwn.call( specs, aliasMap[ i ].to ) ) {
					specs[ aliasMap[ i ].from ] = specs[ aliasMap[ i ].to ];
				}
			}

			ve.extendObject( specCache, specs );
		}
	};

	ve.dm.MWTransclusionModel.prototype.fetchRequestAlways = function ( queue, apiPromise ) {
		// Prune completed request
		var index = this.requests.indexOf( apiPromise );
		if ( index !== -1 ) {
			this.requests.splice( index, 1 );
		}
		// Actually add queued items
		this.process( queue );
	};

	/**
	 * Abort any pending requests.
	 */
	ve.dm.MWTransclusionModel.prototype.abortRequests = function () {
		var i, len;

		for ( i = 0, len = this.requests.length; i < len; i++ ) {
			this.requests[ i ].abort();
		}
		this.requests.length = 0;
	};

	/**
	 * Get plain object representation of template transclusion.
	 *
	 * @return {Object|null} Plain object representation, or null if empty
	 */
	ve.dm.MWTransclusionModel.prototype.getPlainObject = function () {
		var i, len, part, serialization,
			obj = { parts: [] };

		for ( i = 0, len = this.parts.length; i < len; i++ ) {
			part = this.parts[ i ];
			serialization = part.serialize();
			if ( serialization !== undefined && serialization !== '' ) {
				obj.parts.push( serialization );
			}
		}

		if ( obj.parts.length === 0 ) {
			return null;
		}

		return obj;
	};

	/**
	 * Get the wikitext for this transclusion.
	 *
	 * @return {string} Wikitext like `{{foo|1=bar|baz=quux}}`
	 */
	ve.dm.MWTransclusionModel.prototype.getWikitext = function () {
		var i, len,
			wikitext = '';

		for ( i = 0, len = this.parts.length; i < len; i++ ) {
			wikitext += this.parts[ i ].getWikitext();
		}

		return wikitext;
	};

	/**
	 * Get a unique ID for a part in the transclusion.
	 *
	 * This is used to give parts unique IDs, and returns a different value each time it's called.
	 *
	 * @return {number} Unique ID
	 */
	ve.dm.MWTransclusionModel.prototype.getUniquePartId = function () {
		return this.uid++;
	};

	/**
	 * Replace part.
	 *
	 * Replace asynchronously.
	 *
	 * @param {ve.dm.MWTransclusionPartModel} remove Part to remove
	 * @param {ve.dm.MWTransclusionPartModel} add Part to add
	 * @throws {Error} If part to remove is not valid
	 * @throws {Error} If part to add is not valid
	 * @return {jQuery.Promise} Promise, resolved when part is added
	 */
	ve.dm.MWTransclusionModel.prototype.replacePart = function ( remove, add ) {
		var deferred = ve.createDeferred();
		if (
			!( remove instanceof ve.dm.MWTransclusionPartModel ) ||
			!( add instanceof ve.dm.MWTransclusionPartModel )
		) {
			throw new Error( 'Invalid transclusion part' );
		}
		this.queue.push( { remove: remove, add: add, deferred: deferred } );

		// Fetch on next yield to process items in the queue together, subsequent calls to fetch will
		// have no effect because the queue will be clear
		setTimeout( this.fetch.bind( this ) );

		return deferred.promise();
	};

	/**
	 * Add part.
	 *
	 * Added asynchronously, but order is preserved.
	 *
	 * @param {ve.dm.MWTransclusionPartModel} part Part to add
	 * @param {number} [index] Specific index to add content at, defaults to the end
	 * @throws {Error} If part is not valid
	 * @return {jQuery.Promise} Promise, resolved when part is added
	 */
	ve.dm.MWTransclusionModel.prototype.addPart = function ( part, index ) {
		var deferred = ve.createDeferred();
		if ( !( part instanceof ve.dm.MWTransclusionPartModel ) ) {
			throw new Error( 'Invalid transclusion part' );
		}
		this.queue.push( { add: part, index: index, deferred: deferred } );

		// Fetch on next yield to process items in the queue together, subsequent calls to fetch will
		// have no effect because the queue will be clear
		setTimeout( this.fetch.bind( this ) );

		return deferred.promise();
	};

	/**
	 * Remove a part.
	 *
	 * @param {ve.dm.MWTransclusionPartModel} part Part to remove
	 * @fires replace
	 */
	ve.dm.MWTransclusionModel.prototype.removePart = function ( part ) {
		var index = this.parts.indexOf( part );
		if ( index !== -1 ) {
			this.parts.splice( index, 1 );
			part.disconnect( this );
			this.emit( 'replace', part, null );
		}
	};

	/**
	 * Get all parts.
	 *
	 * @return {ve.dm.MWTransclusionPartModel[]} Parts in transclusion
	 */
	ve.dm.MWTransclusionModel.prototype.getParts = function () {
		return this.parts;
	};

	/**
	 * Get part by its ID.
	 *
	 * Matching is performed against the first section of the `id`, delimited by a '/'.
	 *
	 * @param {string} id Part ID
	 * @return {ve.dm.MWTransclusionPartModel|null} Part with matching ID, if found
	 */
	ve.dm.MWTransclusionModel.prototype.getPartFromId = function ( id ) {
		var i, len,
			// For ids from ve.dm.MWParameterModel, compare against the part id
			// of the parameter instead of the entire model id (e.g. "part_1" instead of "part_1/foo").
			partId = id.split( '/' )[ 0 ];

		for ( i = 0, len = this.parts.length; i < len; i++ ) {
			if ( this.parts[ i ].getId() === partId ) {
				return this.parts[ i ];
			}
		}
		return null;
	};

	/**
	 * Get the index of a part or parameter.
	 *
	 * Indexes are linear depth-first addresses in the transclusion tree.
	 *
	 * @param {ve.dm.MWTransclusionPartModel|ve.dm.MWParameterModel} model Part or parameter
	 * @return {number} Page index of model
	 */
	ve.dm.MWTransclusionModel.prototype.getIndex = function ( model ) {
		var i, iLen, j, jLen, part, names,
			parts = this.parts,
			index = 0;

		for ( i = 0, iLen = parts.length; i < iLen; i++ ) {
			part = parts[ i ];
			if ( part === model ) {
				return index;
			}
			index++;
			if ( part instanceof ve.dm.MWTemplateModel ) {
				names = part.getParameterNames();
				for ( j = 0, jLen = names.length; j < jLen; j++ ) {
					if ( part.getParameter( names[ j ] ) === model ) {
						return index;
					}
					index++;
				}
			}
		}
		return -1;
	};

	/*
	 * Add missing required and suggested parameters to each transclusion.
	 */
	ve.dm.MWTransclusionModel.prototype.addPromptedParameters = function () {
		var i;
		for ( i = 0; i < this.parts.length; i++ ) {
			this.parts[ i ].addPromptedParameters();
		}
	};

}() );
