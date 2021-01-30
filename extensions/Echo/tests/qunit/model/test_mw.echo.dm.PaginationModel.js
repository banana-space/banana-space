( function () {
	var defaultValues = {
		getPageContinue: undefined,
		getCurrPageIndex: 0,
		getPrevPageContinue: '',
		getCurrPageContinue: '',
		getNextPageContinue: '',
		hasPrevPage: false,
		hasNextPage: false,
		getCurrentPageItemCount: 25,
		getItemsPerPage: 25
	};

	QUnit.module( 'ext.echo.dm - mw.echo.dm.PaginationModel' );

	QUnit.test( 'Constructing the model', function ( assert ) {
		var i, model, method,
			cases = [
				{
					msg: 'Empty config',
					config: {},
					expected: defaultValues
				},
				{
					msg: 'Overridng defaults',
					config: {
						pageNext: 'continueValNext|123',
						itemsPerPage: 10
					},
					expected: $.extend( true, {}, defaultValues, {
						getNextPageContinue: 'continueValNext|123',
						hasNextPage: true,
						getItemsPerPage: 10,
						getCurrentPageItemCount: 10
					} )
				}
			];

		for ( i = 0; i < cases.length; i++ ) {
			model = new mw.echo.dm.PaginationModel( cases[ i ].config );

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

	QUnit.test( 'Emitting update event', function ( assert ) {
		var results = [],
			model = new mw.echo.dm.PaginationModel();

		// Listen to update event
		model.on( 'update', function () {
			results.push( [
				model.getCurrPageIndex(),
				model.hasNextPage()
			] );
		} );

		// Trigger events

		// Set up initial pages (first page is 0)
		model.setPageContinue( 1, 'page2|2' ); // [ [ 0, true ] ]
		model.setPageContinue( 2, 'page3|3' ); // [ [ 0, true ], [ 0, true ] ]
		model.setPageContinue( 3, 'page4|4' ); // [ [ 0, true ], [ 0, true ], [ 0, true ] ]

		model.forwards(); // [ [ 0, true ], [ 0, true ], [ 0, true ], [ 1, true ] ]
		model.forwards(); // [ [ 0, true ], [ 0, true ], [ 0, true ], [ 1, true ], [ 2, true ] ]
		model.forwards(); // [ [ 0, true ], [ 0, true ], [ 0, true ], [ 1, true ], [ 2, true ], [ 3, false ] ]
		model.backwards(); // [ [ 0, true ], [ 0, true ], [ 0, true ], [ 1, true ], [ 2, true ], [ 3, false ], [ 2, true ] ]
		model.setCurrentPageItemCount(); // [ [ 0, true ], [ 0, true ], [ 0, true ], [ 1, true ], [ 2, true ], [ 3, false ], [ 2, true ], [ 2, true ] ]
		model.reset(); // [ [ 0, true ], [ 0, true ], [ 0, true ], [ 1, true ], [ 2, true ], [ 3, false ], [ 2, true ], [ 2, true ], [ 0, false ] ]

		assert.deepEqual(
			// Actual
			results,
			// Expected:
			[ [ 0, true ], [ 0, true ], [ 0, true ], [ 1, true ], [ 2, true ], [ 3, false ], [ 2, true ], [ 2, true ], [ 0, false ] ],
			// Message
			'Update events emitted'
		);
	} );

}() );
