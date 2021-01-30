( function () {
	var FlowEventLogRegistry = {
		funnels: {},
		generateFunnelId: mw.user.generateRandomSessionId
	};

	/**
	 * @class FlowEventLog
	 * @constructor
	 * @param {string} schemaName Canonical schema name.
	 * @param {Object} [eventInstance] Shared event instance data.
	 */
	function FlowEventLog( schemaName, eventInstance ) {
		this.schemaName = schemaName;
		this.eventInstance = eventInstance || {};

		/**
		 * @param {Object} eventInstance Additional event instance data for this
		 *   particular event.
		 */
		function logEvent( eventInstance ) {
			mw.track(
				'event.' + this.schemaName,
				$.extend( this.eventInstance, eventInstance )
			);
		}
		this.logEvent = logEvent;
	}

	// Export
	/**
	 * EventLogging wrapper
	 *
	 * @type {FlowEventLog}
	 */
	mw.flow.EventLog = FlowEventLog;

	mw.flow.EventLogRegistry = FlowEventLogRegistry;
}() );
