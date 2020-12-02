/*!
 * VisualEditor tracking methods.
 *
 * @copyright 2011-2020 VisualEditor Team and others; see http://ve.mit-license.org
 */

( function () {
	var callbacks = $.Callbacks( 'memory' ),
		queue = [];

	/**
	 * Track an analytic event.
	 *
	 * VisualEditor uses this method internally to track internal changes of state that are of analytic
	 * interest, either because they provide data about how users interact with the editor, or because
	 * they contain exception info, latency measurements, or other metrics that help gauge performance
	 * and reliability. VisualEditor does not transmit these events by default, but it provides a
	 * generic interface for routing these events to an analytics framework.
	 *
	 * @member ve
	 * @param {string} topic Event name
	 * @param {Object} [data] Additional data describing the event, encoded as an object
	 */
	ve.track = function ( topic, data ) {
		queue.push( { topic: topic, timeStamp: ve.now(), data: data } );
		callbacks.fire( queue );
	};

	/**
	 * Register a handler for subset of analytic events, specified by topic
	 *
	 * Handlers will be called once for each tracked event, including any events that fired before the
	 * handler was registered, with the topic, event data payload, and event timestamp as the first,
	 * second, and third arguments, respectively.
	 *
	 * @member ve
	 * @param {string} topic Handle events whose name starts with this string prefix
	 * @param {Function} callback Handler to call for each matching tracked event
	 */
	ve.trackSubscribe = function ( topic, callback ) {
		var seen = 0;

		callbacks.add( function ( queue ) {
			var event;
			for ( ; seen < queue.length; seen++ ) {
				event = queue[ seen ];
				if ( event.topic.indexOf( topic ) === 0 ) {
					callback( event.topic, event.data, event.timeStamp );
				}
			}
		} );
	};

	/**
	 * Register a handler for all analytic events
	 *
	 * Like ve#trackSubscribe, but binds the callback to all events, regardless of topic.
	 *
	 * @member ve
	 * @param {Function} callback
	 */
	ve.trackSubscribeAll = function ( callback ) {
		ve.trackSubscribe( '', callback );
	};
}() );
