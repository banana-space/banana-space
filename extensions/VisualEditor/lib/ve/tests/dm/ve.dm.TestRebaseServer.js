/*!
 * VisualEditor DataModel TestRebaseClient class
 *
 * @copyright 2011-2020 VisualEditor Team and others; see http://ve.mit-license.org
 */

/**
 * Rebase client used for testing
 *
 * @class
 * @extends ve.dm.RebaseServer
 *
 * @constructor
 */
ve.dm.TestRebaseServer = function VeDmRebaseServer() {
	ve.dm.RebaseServer.apply( this );

	this.incoming = [];
};

OO.inheritClass( ve.dm.TestRebaseServer, ve.dm.RebaseServer );

ve.dm.TestRebaseServer.static.fakeDocName = 'foo';

ve.dm.TestRebaseServer.prototype.getHistorySummary = function historySummary() {
	return ve.dm.TestRebaseClient.static.historySummary(
		this.getDocState( this.constructor.static.fakeDocName ).history
	);
};
