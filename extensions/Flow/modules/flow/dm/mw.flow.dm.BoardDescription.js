/**
 * Flow board description
 *
 * @class
 * @extends mw.flow.dm.RevisionedContent
 *
 * @constructor
 * @param {Object} [data] API data to build topic header with
 * @param {Object} [config] Configuration options
 */
mw.flow.dm.BoardDescription = function mwFlowDmBoardDescription( data, config ) {
	// Parent constructor
	mw.flow.dm.BoardDescription.super.call( this, config );

	if ( data ) {
		this.populate( data );
	}
};

/* Initialization */

OO.inheritClass( mw.flow.dm.BoardDescription, mw.flow.dm.RevisionedContent );
