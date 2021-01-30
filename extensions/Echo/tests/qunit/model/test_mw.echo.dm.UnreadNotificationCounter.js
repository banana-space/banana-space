( function () {
	QUnit.module( 'ext.echo.dm - mw.echo.dm.UnreadNotificationCounter' );

	QUnit.test( 'Returning capped notifications count', function ( assert ) {
		var i,
			model = new mw.echo.dm.UnreadNotificationCounter(
				null,
				'all', // type
				10 // max
			),
			cases = [
				{ input: 5, output: 5 },
				{ input: 20, output: 11 },
				{ input: 10, output: 10 }
			];

		for ( i = 0; i < cases.length; i++ ) {
			assert.strictEqual(
				model.getCappedNotificationCount( cases[ i ].input ),
				cases[ i ].output,
				'Capped notifications count: ' +
					cases[ i ].input + ' -> ' +
					cases[ i ].output
			);
		}
	} );

	QUnit.test( 'Estimate change', function ( assert ) {
		var model = new mw.echo.dm.UnreadNotificationCounter(
			null,
			'all', // type
			99 // max
		);
		// Set initial
		model.setCount( 50 );

		model.estimateChange( -10 );
		assert.strictEqual(
			model.getCount(),
			40, // 50-10
			'Estimation within range'
		);

		model.estimateChange( 70 );
		assert.strictEqual(
			model.getCount(),
			100, // Estimation reached above cap - cap is set
			'Estimation brings count to cap'
		);

		model.estimateChange( -10 );
		assert.strictEqual(
			model.getCount(),
			100, // We are already above cap, count will not change
			'Estimation while counter is outside of cap - no change'
		);
	} );

	QUnit.test( 'Emitting countChange event', function ( assert ) {
		var results = [],
			model = new mw.echo.dm.UnreadNotificationCounter(
				null,
				'all', // type
				99 // max
			);

		// Listen to event
		model.on( 'countChange', function ( count ) {
			results.push( count );
		} );

		// Trigger events
		model.setCount( 50 ); // [ 50 ]
		model.setCount( 300, true ); // (estimate, above max, bring to cap) [ 50, 100 ]
		model.setCount( -1, true ); // (estimate while counter is above cap, no event) [ 50, 100 ]
		model.setCount( 200 ); // (setting above cap, value is capped, same as current, no event) [ 50,100 ]
		model.setCount( 10 ); // [ 50, 100, 10 ]

		assert.deepEqual(
			results,
			[ 50, 100, 10 ],
			'countChange events emitted.'
		);
	} );
}() );
