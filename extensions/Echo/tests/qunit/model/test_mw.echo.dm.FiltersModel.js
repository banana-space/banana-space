( function () {
	var defaultValues = {
		getReadState: 'all'
	};

	QUnit.module( 'ext.echo.dm - mw.echo.dm.FiltersModel' );

	QUnit.test( 'Constructing the model', function ( assert ) {
		var i, model, method,
			cases = [
				{
					msg: 'Empty config',
					config: {},
					expected: defaultValues
				},
				{
					msg: 'Readstate: unread',
					config: {
						readState: 'unread'
					},
					expected: $.extend( true, {}, defaultValues, {
						getReadState: 'unread'
					} )
				},
				{
					msg: 'Readstate: read',
					config: {
						readState: 'read'
					},
					expected: $.extend( true, {}, defaultValues, {
						getReadState: 'read'
					} )
				}
			];

		for ( i = 0; i < cases.length; i++ ) {
			model = new mw.echo.dm.FiltersModel( cases[ i ].config );

			for ( method in cases[ i ].expected ) {
				assert.deepEqual(
					// Run the method
					model[ method ](),
					// Expected value
					cases[ i ].expected[ method ],
					// Message
					cases[ i ].msg + ' (' + method + ')'
				);
			}
		}
	} );

	QUnit.test( 'Changing filters', function ( assert ) {
		var model = new mw.echo.dm.FiltersModel();

		assert.strictEqual(
			model.getReadState(),
			'all',
			'Initial value: all'
		);

		model.setReadState( 'unread' );
		assert.strictEqual(
			model.getReadState(),
			'unread',
			'Changing state (unread)'
		);

		model.setReadState( 'read' );
		assert.strictEqual(
			model.getReadState(),
			'read',
			'Changing state (read)'
		);

		model.setReadState( 'foo' );
		assert.strictEqual(
			model.getReadState(),
			'read',
			'Ignoring invalid state (foo)'
		);
	} );

	QUnit.test( 'Emitting update event', function ( assert ) {
		var results = [],
			model = new mw.echo.dm.FiltersModel();

		// Listen to update event
		model.on( 'update', function () {
			results.push( model.getReadState() );
		} );

		// Trigger events
		model.setReadState( 'read' ); // [ 'read' ]
		model.setReadState( 'unread' ); // [ 'read', 'unread' ]
		model.setReadState( 'unread' ); // (no change, no event) [ 'read', 'unread' ]
		model.setReadState( 'all' ); // [ 'read', 'unread', 'all' ]
		model.setReadState( 'foo' ); // (invalid value, no event) [ 'read', 'unread', 'all' ]
		model.setReadState( 'unread' ); // [ 'read', 'unread', 'all', 'unread' ]

		assert.deepEqual(
			// Actual
			results,
			// Expected:
			[ 'read', 'unread', 'all', 'unread' ],
			// Message
			'Update events emitted'
		);
	} );

}() );
