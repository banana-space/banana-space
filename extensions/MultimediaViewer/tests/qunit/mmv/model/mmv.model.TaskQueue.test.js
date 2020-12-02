/*
 * This file is part of the MediaWiki extension MediaViewer.
 *
 * MediaViewer is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 2 of the License, or
 * (at your option) any later version.
 *
 * MediaViewer is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with MediaViewer.  If not, see <http://www.gnu.org/licenses/>.
 */

( function () {
	QUnit.module( 'mmv.model.TaskQueue', QUnit.newMwEnvironment() );

	QUnit.test( 'TaskQueue constructor sanity check', function ( assert ) {
		var taskQueue = new mw.mmv.model.TaskQueue();

		assert.ok( taskQueue, 'TaskQueue created successfully' );
	} );

	QUnit.test( 'Queue length check', function ( assert ) {
		var taskQueue = new mw.mmv.model.TaskQueue();

		assert.strictEqual( taskQueue.queue.length, 0, 'queue is initially empty' );

		taskQueue.push( function () {} );

		assert.strictEqual( taskQueue.queue.length, 1, 'queue length is incremented on push' );
	} );

	QUnit.test( 'State check', function ( assert ) {
		var taskQueue = new mw.mmv.model.TaskQueue(),
			task = $.Deferred(),
			promise;

		taskQueue.push( function () { return task; } );

		assert.strictEqual( taskQueue.state, mw.mmv.model.TaskQueue.State.NOT_STARTED,
			'state is initially NOT_STARTED' );

		promise = taskQueue.execute().then( function () {
			assert.strictEqual( taskQueue.state, mw.mmv.model.TaskQueue.State.FINISHED,
				'state is FINISHED after execution finished' );
		} );

		assert.strictEqual( taskQueue.state, mw.mmv.model.TaskQueue.State.RUNNING,
			'state is RUNNING after execution started' );

		task.resolve();

		return promise;
	} );

	QUnit.test( 'State check for cancellation', function ( assert ) {
		var taskQueue = new mw.mmv.model.TaskQueue(),
			task = $.Deferred();

		taskQueue.push( function () { return task; } );
		taskQueue.execute();
		taskQueue.cancel();

		assert.strictEqual( taskQueue.state, mw.mmv.model.TaskQueue.State.CANCELLED,
			'state is CANCELLED after cancellation' );
	} );

	QUnit.test( 'Test executing empty queue', function ( assert ) {
		var taskQueue = new mw.mmv.model.TaskQueue();

		return taskQueue.execute().done( function () {
			assert.ok( true, 'Queue promise resolved' );
		} );
	} );

	QUnit.test( 'Simple execution test', function ( assert ) {
		var taskQueue = new mw.mmv.model.TaskQueue(),
			called = false;

		taskQueue.push( function () {
			called = true;
		} );

		return taskQueue.execute().then( function () {
			assert.strictEqual( called, true, 'Task executed successfully' );
		} );
	} );

	QUnit.test( 'Task execution order test', function ( assert ) {
		var taskQueue = new mw.mmv.model.TaskQueue(),
			order = [];

		taskQueue.push( function () {
			order.push( 1 );
		} );

		taskQueue.push( function () {
			var deferred = $.Deferred();

			order.push( 2 );

			setTimeout( function () {
				deferred.resolve();
			}, 0 );

			return deferred;
		} );

		taskQueue.push( function () {
			order.push( 3 );
		} );

		return taskQueue.execute().then( function () {
			assert.deepEqual( order, [ 1, 2, 3 ], 'Tasks executed in order' );
		} );
	} );

	QUnit.test( 'Double execution test', function ( assert ) {
		var taskQueue = new mw.mmv.model.TaskQueue(),
			called = 0;

		taskQueue.push( function () {
			called++;
		} );

		return taskQueue.execute().then( function () {
			return taskQueue.execute();
		} ).then( function () {
			assert.strictEqual( called, 1, 'Task executed only once' );
		} );
	} );

	QUnit.test( 'Parallel execution test', function ( assert ) {
		var taskQueue = new mw.mmv.model.TaskQueue(),
			called = 0;

		taskQueue.push( function () {
			called++;
		} );

		return $.when(
			taskQueue.execute(),
			taskQueue.execute()
		).then( function () {
			assert.strictEqual( called, 1, 'Task executed only once' );
		} );
	} );

	QUnit.test( 'Test push after execute', function ( assert ) {
		var taskQueue = new mw.mmv.model.TaskQueue();

		taskQueue.execute();

		try {
			taskQueue.push( function () {} );
		} catch ( e ) {
			assert.ok( e, 'Exception thrown when trying to push to an already running queue' );
		}
	} );

	QUnit.test( 'Test failed task', function ( assert ) {
		var taskQueue = new mw.mmv.model.TaskQueue();

		taskQueue.push( function () {
			return $.Deferred().reject();
		} );

		return taskQueue.execute().done( function () {
			assert.ok( true, 'Queue promise resolved' );
		} );
	} );

	QUnit.test( 'Test that tasks wait for each other', function ( assert ) {
		var taskQueue = new mw.mmv.model.TaskQueue(),
			longRunningTaskFinished = false,
			seenFinished = false;

		taskQueue.push( function () {
			var deferred = $.Deferred();

			setTimeout( function () {
				longRunningTaskFinished = true;
				deferred.resolve();
			}, 0 );

			return deferred;
		} );

		taskQueue.push( function () {
			seenFinished = longRunningTaskFinished;
		} );

		return taskQueue.execute().then( function () {
			assert.ok( seenFinished, 'Task waits for previous task to finish' );
		} );
	} );

	QUnit.test( 'Test cancellation before start', function ( assert ) {
		var taskQueue = new mw.mmv.model.TaskQueue(),
			triggered = false,
			verificationTask = function () {
				triggered = true;
			};

		taskQueue.push( verificationTask );

		taskQueue.cancel();

		taskQueue.execute()
			.done( function () {
				assert.ok( false, 'Queue promise rejected' );
			} )
			.fail( function () {
				assert.ok( true, 'Queue promise rejected' );
				assert.strictEqual( triggered, false, 'Task was not triggered' );
			} )
			.always( assert.async() );
	} );

	QUnit.test( 'Test cancellation within callback', function ( assert ) {
		var taskQueue = new mw.mmv.model.TaskQueue(),
			triggered = false,
			verificationTask = function () {
				triggered = true;
			};

		taskQueue.push( function () {
			taskQueue.cancel();
		} );
		taskQueue.push( verificationTask );

		taskQueue.execute()
			.done( function () {
				assert.ok( false, 'Queue promise rejected' );
			} )
			.fail( function () {
				assert.ok( true, 'Queue promise rejected' );
				assert.strictEqual( triggered, false, 'Task was not triggered' );
			} )
			.always( assert.async() );
	} );

	QUnit.test( 'Test cancellation from task', function ( assert ) {
		var taskQueue = new mw.mmv.model.TaskQueue(),
			triggered = false,
			task1 = $.Deferred(),
			verificationTask = function () {
				triggered = true;
			};

		taskQueue.push( function () {
			return task1;
		} );
		taskQueue.push( verificationTask );

		setTimeout( function () {
			taskQueue.cancel();
			task1.resolve();
		}, 0 );

		taskQueue.execute()
			.done( function () {
				assert.ok( false, 'Queue promise rejected' );
			} )
			.fail( function () {
				assert.ok( true, 'Queue promise rejected' );
				assert.strictEqual( triggered, false, 'Task was not triggered' );
			} )
			.always( assert.async() );
	} );

}() );
