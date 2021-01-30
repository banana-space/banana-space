/*!
 * Creates and manages the component registry.
 * We expand upon OOjs in several ways here:
 * 1. Allow mixinClasses to have their constructor functions to be called (at initComponent).
 * 2. Automatically call all parent constructors from inheritClasses (at initComponent).
 * 3. Create a global instance registry of components on a page, and also create a registry for each component type.
 * 4. Have the ability to fetch individual prototype methods from classes in the registry, as they are out of scope.
 */

/**
 * @class FlowComponent
 * TODO: Use @-external in JSDoc
 */

( function () {
	var _componentRegistry = new OO.Registry();

	/** @class mw.flow */
	mw.flow = mw.flow || {}; // create mw.flow globally

	/**
	 * Instantiate one or more new FlowComponents.
	 * Uses data-flow-component to find the right class, and returns that new instance.
	 * Accepts one or more container elements in $container. If multiple, returns an array of FlowBoardComponents.
	 *
	 * @param {jQuery} $container
	 * @return {FlowComponent|boolean|Array} The created FlowComponent instance, or an
	 *  array of FlowComponent instances, or boolean false in case of an error.
	 */
	function initFlowComponent( $container ) {
		var a, i, componentName, componentBase;

		/**
		 * @private
		 * Deep magic: This crazy little function becomes the "real" top-level constructor
		 * It recursively calls every parent so that we don't have to do it manually in a Component constructor
		 * @return {FlowComponent}
		 */
		function _RecursiveConstructor() {
			var constructors = [],
				parent = this.constructor.super,
				i, j, parentReturn;

			// Find each parent class
			while ( parent ) {
				constructors.push( parent );
				parent = parent.super;
			}

			// Call each parent in reverse (starting with the base class and moving up the chain)
			for ( i = constructors.length; i--; ) {
				// Call each mixin constructor
				for ( j = 0; j < constructors[ i ].static.mixinConstructors.length; j++ ) {
					constructors[ i ].static.mixinConstructors[ j ].apply( this, arguments );
				}

				// Call this class constructor
				parentReturn = constructors[ i ].apply( this, arguments );

				if ( parentReturn && parentReturn.constructor ) {
					// If the parent returned an instantiated class (cached), return that instead
					return parentReturn;
				}
			}

			// Run any post-instantiation handlers
			this.emitWithReturn( 'instantiationComplete', this );
		}

		if ( !$container || !$container.length ) {
			// No containers found
			mw.flow.debug( 'Will not instantiate: no $container.length', arguments );
			return false;
		} else if ( $container.length > 1 ) {
			// Too many elements; instantiate them all
			for ( a = [], i = $container.length; i--; ) {
				a.push( initFlowComponent( $( $container[ i ] ) ) );
			}
			return a;
		}

		// Find out which component this is
		componentName = $container.data( 'flow-component' );
		// Get that component
		componentBase = _componentRegistry.lookup( componentName );
		if ( componentBase ) {
			// Return the new instance of that FlowComponent, via our _RecursiveConstructor method
			OO.inheritClass( _RecursiveConstructor, componentBase );
			return new _RecursiveConstructor( $container );
		}

		// Don't know what kind of component this is.
		mw.flow.debug( 'Unknown FlowComponent: ', componentName, arguments );
		return false;
	}
	mw.flow.initComponent = initFlowComponent;

	/**
	 * Registers a given FlowComponent into the component registry, and also has it inherit another class using the
	 * prototypeName argument (defaults to 'component', which returns FlowComponent).
	 *
	 * @param {string} name Name of component to register
	 * @param {Function} constructorClass Actual class to link to that name
	 * @param {string} [prototypeName='component'] A base class which this one will inherit
	 */
	function registerFlowComponent( name, constructorClass, prototypeName ) {
		if ( name !== 'component' ) {
			// Inherit a base class; defaults to FlowComponent
			OO.inheritClass( constructorClass, _componentRegistry.lookup( prototypeName || 'component' ) );
		}

		// Create the instance registry for this component
		constructorClass._instanceRegistry = [];
		constructorClass._instanceRegistryById = {};

		// Assign the OOjs static name to this class
		constructorClass.static.name = name;

		// Allow mixins to use their constructor
		constructorClass.static.mixinConstructors = [];

		// Register the component class
		_componentRegistry.register( name, constructorClass );
	}
	mw.flow.registerComponent = registerFlowComponent;

	/**
	 * For when you want to call a specific function from a class's prototype.
	 *
	 *     mw.flow.getPrototypeMethod( 'board', 'getInstanceByElement' )( $el );
	 *
	 * @param {string} className
	 * @param {string} methodName
	 * @param {*} [context]
	 * @return {Function}
	 */
	function getFlowPrototypeMethod( className, methodName, context ) {
		var registeredClass = _componentRegistry.lookup( className ),
			method;

		if ( !registeredClass ) {
			mw.flow.debug( 'Failed to find FlowComponent.', arguments );
			return function () {};
		}

		method = registeredClass.prototype[ methodName ];
		if ( !method ) {
			mw.flow.debug( 'Failed to find FlowComponent method.', arguments );
			return function () {};
		}

		return method.bind( context || registeredClass );
	}
	mw.flow.getPrototypeMethod = getFlowPrototypeMethod;

	/**
	 * Mixes in the given mixinClass to be copied to an existing class, by name.
	 *
	 * @param {string} targetName Target component
	 * @param {Function} mixinClass Class with extension to add to target
	 */
	function mixinFlowComponent( targetName, mixinClass ) {
		var registeredClass = _componentRegistry.lookup( targetName );

		if ( !registeredClass ) {
			mw.flow.debug( 'Failed to find FlowComponent to extend.', arguments );
			return;
		}

		OO.mixinClass( registeredClass, mixinClass );

		// Allow mixins to use their constructors (in init)
		if ( typeof mixinClass === 'function' ) {
			registeredClass.static.mixinConstructors.push( mixinClass );
		}
	}
	mw.flow.mixinComponent = mixinFlowComponent;
}() );
