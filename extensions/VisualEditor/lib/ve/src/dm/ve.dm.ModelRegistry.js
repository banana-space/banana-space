/*!
 * VisualEditor ModelRegistry class.
 *
 * @copyright 2011-2020 VisualEditor Team and others; see http://ve.mit-license.org
 */
( function ( ve ) {

	/**
	 * Registry for models.
	 *
	 * To register a new model type, call #register.
	 *
	 * @extends OO.Registry
	 * @constructor
	 */
	ve.dm.ModelRegistry = function VeDmModelRegistry() {
		// Parent constructor
		OO.Registry.call( this );
		// Map of func presence and tag names to model names
		// [ { tagName: [modelNamesWithoutFunc] }, { tagName: [modelNamesWithFunc] } ]
		this.modelsByTag = [ {}, {} ];
		// Map of func presence and rdfaTypes to model names; only rdfaTypes specified as strings are in here
		// { matchFunctionPresence: { rdfaType: { tagName: [modelNames] } } }
		// [ { rdfaType: { tagName: [modelNamesWithoutFunc] } }, { rdfaType: { tagName: [modelNamesWithFunc] } ]
		this.modelsByTypeAndTag = [];
		// Map of func presence to array of model names with rdfaType regexps
		// [ [modelNamesWithoutFunc], [modelNamesWithFunc] ]
		this.modelsWithTypeRegExps = [ [], [] ];
		// Map tracking registration order
		// { nameA: 0, nameB: 1, … }
		this.registrationOrder = {};
		this.nextNumber = 0;
		this.extSpecificTypes = [];
	};

	/* Inheritance */

	OO.inheritClass( ve.dm.ModelRegistry, OO.Registry );

	/* Private helper functions */

	/**
	 * Helper function for register(). Adds a value to the front of an array in a nested object.
	 * Objects and arrays are created if needed. You can specify one or more keys and a value.
	 *
	 * Specifically:
	 *
	 * - `addType( obj, keyA, value )` does `obj[keyA].unshift( value );`
	 * - `addType( obj, keyA, keyB, value )` does `obj[keyA][keyB].unshift( value )`;
	 * - etc.
	 *
	 * @private
	 * @param {Object} obj Object the array resides in
	 * @param {...string} keys
	 * @param {Mixed} value
	 */
	function addType( obj ) {
		var i, len,
			keys = Array.prototype.slice.call( arguments, 1, -1 ),
			value = arguments[ arguments.length - 1 ],
			o = obj;

		for ( i = 0, len = keys.length - 1; i < len; i++ ) {
			if ( o[ keys[ i ] ] === undefined ) {
				o[ keys[ i ] ] = {};
			}
			o = o[ keys[ i ] ];
		}
		o[ keys[ i ] ] = o[ keys[ i ] ] || [];
		o[ keys[ i ] ].unshift( value );
	}

	/**
	 * Helper function for unregister().
	 *
	 * Same arguments as addType, except removes the type from the list.
	 *
	 * @private
	 * @param {Object} obj Object the array resides in
	 * @param {...string} keys
	 * @param {Mixed} value to remove
	 */
	function removeType( obj ) {
		var index,
			keys = Array.prototype.slice.call( arguments, 1, -1 ),
			value = arguments[ arguments.length - 1 ],
			arr = ve.getProp.apply( obj, [ obj ].concat( keys ) );

		if ( arr ) {
			index = arr.indexOf( value );
			if ( index !== -1 ) {
				arr.splice( index, 1 );
			}
			// TODO: Prune empty array and empty containing objects
		}
	}

	/* Public methods */

	/**
	 * Register a model type.
	 *
	 * @param {ve.dm.Model} constructor Subclass of ve.dm.Model
	 * @throws Model names must be strings and must not be empty
	 * @throws Models must be subclasses of ve.dm.Model
	 * @throws No factory associated with this ve.dm.Model subclass
	 */
	ve.dm.ModelRegistry.prototype.register = function ( constructor ) {
		var i, j, tags, types,
			name = constructor.static && constructor.static.name;

		if ( typeof name !== 'string' || name === '' ) {
			throw new Error( 'Model names must be strings and must not be empty' );
		}
		if ( !( constructor.prototype instanceof ve.dm.Model ) ) {
			throw new Error( 'Models must be subclasses of ve.dm.Model' );
		}
		if ( this.lookup( name ) === constructor ) {
			// Don't allow double registration as it would create duplicate
			// entries in various caches.
			return;
		}

		// Register the model with the right factory
		if ( constructor.prototype instanceof ve.dm.Annotation ) {
			ve.dm.annotationFactory.register( constructor );
		} else if ( constructor.prototype instanceof ve.dm.Node ) {
			ve.dm.nodeFactory.register( constructor );
		} else {
			throw new Error( 'No factory associated with this ve.dm.Model subclass' );
		}

		// Parent method
		ve.dm.ModelRegistry.super.prototype.register.call( this, name, constructor );

		tags = constructor.static.matchTagNames === null ?
			[ '' ] :
			constructor.static.matchTagNames;
		types = constructor.static.getMatchRdfaTypes() === null ?
			[ '' ] :
			constructor.static.getMatchRdfaTypes();

		for ( i = 0; i < tags.length; i++ ) {
			// +!!foo is a shorter equivalent of Number( Boolean( foo ) ) or foo ? 1 : 0
			addType( this.modelsByTag, +!!constructor.static.matchFunction,
				tags[ i ], name
			);
		}
		for ( i = 0; i < types.length; i++ ) {
			if ( types[ i ] instanceof RegExp ) {
				// TODO: Guard against running this again during subsequent
				// iterations of the for loop
				addType( this.modelsWithTypeRegExps, +!!constructor.static.matchFunction, name );
			} else {
				for ( j = 0; j < tags.length; j++ ) {
					addType( this.modelsByTypeAndTag,
						+!!constructor.static.matchFunction, types[ i ], tags[ j ], name
					);
				}
			}
		}

		this.registrationOrder[ name ] = this.nextNumber++;
	};

	/**
	 * Unregister a model type.
	 *
	 * @param {ve.dm.Model} constructor Subclass of ve.dm.Model
	 * @throws Model names must be strings and must not be empty
	 * @throws Models must be subclasses of ve.dm.Model
	 * @throws No factory associated with this ve.dm.Model subclass
	 */
	ve.dm.ModelRegistry.prototype.unregister = function ( constructor ) {
		var i, j, tags, types,
			name = constructor.static && constructor.static.name;

		if ( typeof name !== 'string' || name === '' ) {
			throw new Error( 'Model names must be strings and must not be empty' );
		}
		if ( !( constructor.prototype instanceof ve.dm.Model ) ) {
			throw new Error( 'Models must be subclasses of ve.dm.Model' );
		}

		// Unregister the model from the right factory
		if ( constructor.prototype instanceof ve.dm.Annotation ) {
			ve.dm.annotationFactory.unregister( constructor );
		} else if ( constructor.prototype instanceof ve.dm.Node ) {
			ve.dm.nodeFactory.unregister( constructor );
		} else {
			throw new Error( 'No factory associated with this ve.dm.Model subclass' );
		}

		// Parent method
		ve.dm.ModelRegistry.super.prototype.unregister.call( this, name );

		tags = constructor.static.matchTagNames === null ?
			[ '' ] :
			constructor.static.matchTagNames;
		types = constructor.static.getMatchRdfaTypes() === null ?
			[ '' ] :
			constructor.static.getMatchRdfaTypes();

		for ( i = 0; i < tags.length; i++ ) {
			// +!!foo is a shorter equivalent of Number( Boolean( foo ) ) or foo ? 1 : 0
			removeType( this.modelsByTag, +!!constructor.static.matchFunction,
				tags[ i ], name
			);
		}
		for ( i = 0; i < types.length; i++ ) {
			if ( types[ i ] instanceof RegExp ) {
				// TODO: Guard against running this again during subsequent
				// iterations of the for loop
				removeType( this.modelsWithTypeRegExps, +!!constructor.static.matchFunction, name );
			} else {
				for ( j = 0; j < tags.length; j++ ) {
					removeType( this.modelsByTypeAndTag,
						+!!constructor.static.matchFunction, types[ i ], tags[ j ], name
					);
				}
			}
		}

		delete this.registrationOrder[ name ];
	};

	/**
	 * Determine which model best matches the given node
	 *
	 * Model matching works as follows:
	 *
	 * Get all models whose tag and rdfaType rules match
	 *
	 * Rank them in order of specificity:
	 *
	 * - tag, rdfaType and func specified
	 * - rdfaType and func specified
	 * - tag and func specified
	 * - func specified
	 * - tag and rdfaType specified
	 * - rdfaType specified
	 * - tag specified
	 * - nothing specified
	 *
	 * If there are multiple candidates with the same specificity, they are ranked in reverse order of
	 * registration (i.e. if A was registered before B, B will rank above A).
	 * The highest-ranking model whose test function does not return false, wins.
	 *
	 * @param {Node} node Node to match (usually an HTMLElement but can also be a Comment node)
	 * @param {boolean} [forceAboutGrouping] If true, only match models with about grouping enabled
	 * @param {string[]} [excludeTypes] Model names to exclude when matching
	 * @return {string|null} Model type, or null if none found
	 */
	ve.dm.ModelRegistry.prototype.matchElement = function ( node, forceAboutGrouping, excludeTypes ) {
		var i, name, model, matches, winner, types,
			tag = node.nodeName.toLowerCase(),
			reg = this;

		function byRegistrationOrderDesc( a, b ) {
			return reg.registrationOrder[ b ] - reg.registrationOrder[ a ];
		}

		function matchTypeRegExps( type, tag, withFunc ) {
			var i, j, types,
				matches = [],
				models = reg.modelsWithTypeRegExps[ +withFunc ];
			for ( i = 0; i < models.length; i++ ) {
				if ( excludeTypes && excludeTypes.indexOf( models[ i ] ) !== -1 ) {
					continue;
				}
				types = reg.registry[ models[ i ] ].static.getMatchRdfaTypes();
				for ( j = 0; j < types.length; j++ ) {
					if (
						types[ j ] instanceof RegExp &&
						type.match( types[ j ] ) &&
						(
							( tag === '' && reg.registry[ models[ i ] ].static.matchTagNames === null ) ||
							( reg.registry[ models[ i ] ].static.matchTagNames || [] ).indexOf( tag ) !== -1
						)
					) {
						matches.push( models[ i ] );
					}
				}
			}
			return matches;
		}

		function allTypesAllowed( types, name ) {
			var i, j, typeAllowed,
				model = reg.lookup( name ),
				allowedTypes = model.static.getAllowedRdfaTypes(),
				matchTypes = model.static.getMatchRdfaTypes();

			// All types allowed
			if ( allowedTypes === null || matchTypes === null ) {
				return true;
			}

			allowedTypes = allowedTypes.concat( matchTypes );

			function checkType( rule, type ) {
				return rule instanceof RegExp ? !!type.match( rule ) : rule === type;
			}

			for ( i = 0; i < types.length; i++ ) {
				typeAllowed = false;
				for ( j = 0; j < allowedTypes.length; j++ ) {
					if ( checkType( allowedTypes[ j ], types[ i ] ) ) {
						typeAllowed = true;
						break;
					}
				}
				if ( !typeAllowed ) {
					return false;
				}
			}
			return true;
		}

		function matchWithFunc( types, tag ) {
			var i,
				queue = [],
				queue2 = [];
			for ( i = 0; i < types.length; i++ ) {
				// Queue string matches and regexp matches separately
				queue = queue.concat( ve.getProp( reg.modelsByTypeAndTag, 1, types[ i ], tag ) || [] );
				if ( excludeTypes ) {
					queue = OO.simpleArrayDifference( queue, excludeTypes );
				}
				queue2 = queue2.concat( matchTypeRegExps( types[ i ], tag, true ) );
			}
			// Filter out matches which contain types which aren't allowed
			queue = queue.filter( function ( name ) {
				return allTypesAllowed( types, name );
			} );
			queue2 = queue2.filter( function ( name ) {
				return allTypesAllowed( types, name );
			} );
			if ( forceAboutGrouping ) {
				// Filter out matches that don't support about grouping
				queue = queue.filter( function ( name ) {
					return reg.registry[ name ].static.enableAboutGrouping;
				} );
				queue2 = queue2.filter( function ( name ) {
					return reg.registry[ name ].static.enableAboutGrouping;
				} );
			}
			// Try string matches first, then regexp matches
			queue.sort( byRegistrationOrderDesc );
			queue2.sort( byRegistrationOrderDesc );
			queue = queue.concat( queue2 );
			for ( i = 0; i < queue.length; i++ ) {
				if ( reg.registry[ queue[ i ] ].static.matchFunction( node ) ) {
					return queue[ i ];
				}
			}
			return null;
		}

		function matchWithoutFunc( types, tag ) {
			var i,
				queue = [],
				queue2 = [],
				winningName = null;
			for ( i = 0; i < types.length; i++ ) {
				// Queue string and regexp matches separately
				queue = queue.concat( ve.getProp( reg.modelsByTypeAndTag, 0, types[ i ], tag ) || [] );
				if ( excludeTypes ) {
					queue = OO.simpleArrayDifference( queue, excludeTypes );
				}
				queue2 = queue2.concat( matchTypeRegExps( types[ i ], tag, false ) );
			}
			// Filter out matches which contain types which aren't allowed
			queue = queue.filter( function ( name ) {
				return allTypesAllowed( types, name );
			} );
			queue2 = queue2.filter( function ( name ) {
				return allTypesAllowed( types, name );
			} );
			if ( forceAboutGrouping ) {
				// Filter out matches that don't support about grouping
				queue = queue.filter( function ( name ) {
					return reg.registry[ name ].static.enableAboutGrouping;
				} );
				queue2 = queue2.filter( function ( name ) {
					return reg.registry[ name ].static.enableAboutGrouping;
				} );
			}
			// Only try regexp matches if there are no string matches
			queue = queue.length > 0 ? queue : queue2;
			// Find most recently registered
			for ( i = 0; i < queue.length; i++ ) {
				if (
					winningName === null ||
					reg.registrationOrder[ winningName ] < reg.registrationOrder[ queue[ i ] ]
				) {
					winningName = queue[ i ];
				}
			}
			return winningName;
		}

		types = [];
		if ( node.getAttribute ) {
			if ( node.getAttribute( 'rel' ) ) {
				types = types.concat( node.getAttribute( 'rel' ).trim().split( /\s+/ ) );
			}
			if ( node.getAttribute( 'typeof' ) ) {
				types = types.concat( node.getAttribute( 'typeof' ).trim().split( /\s+/ ) );
			}
			if ( node.getAttribute( 'property' ) ) {
				types = types.concat( node.getAttribute( 'property' ).trim().split( /\s+/ ) );
			}
		}

		if ( types.length ) {
			// func+tag+type match
			winner = matchWithFunc( types, tag );
			if ( winner !== null ) {
				return winner;
			}

			// func+type match
			// Only look at rules with no tag specified; if a rule does specify a tag, we've
			// either already processed it above, or the tag doesn't match
			winner = matchWithFunc( types, '' );
			if ( winner !== null ) {
				return winner;
			}
		}

		// func+tag match
		matches = ve.getProp( this.modelsByTag, 1, tag ) || [];
		// No need to sort because individual arrays in modelsByTag are already sorted
		// correctly
		for ( i = 0; i < matches.length; i++ ) {
			name = matches[ i ];
			model = this.registry[ name ];
			// Only process this one if it doesn't specify types
			// If it does specify types, then we've either already processed it in the
			// func+tag+type step above, or its type rule doesn't match
			if ( model.static.getMatchRdfaTypes() === null && model.static.matchFunction( node ) ) {
				return matches[ i ];
			}
		}

		// func only
		// We only need to get the [''][''] array because the other arrays were either
		// already processed during the steps above, or have a type or tag rule that doesn't
		// match this node.
		// No need to sort because individual arrays in modelsByTypeAndTag are already sorted
		// correctly
		matches = ve.getProp( this.modelsByTypeAndTag, 1, '', '' ) || [];
		for ( i = 0; i < matches.length; i++ ) {
			if ( this.registry[ matches[ i ] ].static.matchFunction( node ) ) {
				return matches[ i ];
			}
		}

		// tag+type
		winner = matchWithoutFunc( types, tag );
		if ( winner !== null ) {
			return winner;
		}

		// type only
		// Only look at rules with no tag specified; if a rule does specify a tag, we've
		// either already processed it above, or the tag doesn't match
		winner = matchWithoutFunc( types, '' );
		if ( winner !== null ) {
			return winner;
		}

		// tag only
		matches = ve.getProp( this.modelsByTag, 0, tag ) || [];
		// No need to track winningName because the individual arrays in modelsByTag are
		// already sorted correctly
		for ( i = 0; i < matches.length; i++ ) {
			name = matches[ i ];
			model = this.registry[ name ];
			// Only process this one if it doesn't specify types
			// If it does specify types, then we've either already processed it in the
			// tag+type step above, or its type rule doesn't match
			if ( model.static.getMatchRdfaTypes() === null ) {
				return matches[ i ];
			}
		}

		// Rules with no type or tag specified
		// These are the only rules that can still qualify at this point, the others we've either
		// already processed or have a type or tag rule that disqualifies them
		matches = ve.getProp( this.modelsByTypeAndTag, 0, '', '' ) || [];
		if ( matches.length > 0 ) {
			return matches[ 0 ];
		}

		// We didn't find anything, give up
		return null;
	};

	/**
	 * Tests whether a node will be modelled as an annotation
	 *
	 * @param {Node} node The node
	 * @return {boolean} Whether the element will be modelled as an annotation
	 */
	ve.dm.ModelRegistry.prototype.isAnnotation = function ( node ) {
		var modelClass = this.lookup( this.matchElement( node ) );
		return ( modelClass && modelClass.prototype ) instanceof ve.dm.Annotation;
	};

	/* Initialization */

	ve.dm.modelRegistry = new ve.dm.ModelRegistry();

}( ve ) );
