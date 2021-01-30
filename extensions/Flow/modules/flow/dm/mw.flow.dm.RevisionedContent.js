( function () {
	/**
	 * Flow RevisionedContent class
	 *
	 * @class
	 * @abstract
	 * @extends mw.flow.dm.Item
	 *
	 * @constructor
	 * @param {Object} [config] Configuration options
	 */
	mw.flow.dm.RevisionedContent = function mwFlowRevisionedContent() {
		// Parent constructor
		mw.flow.dm.RevisionedContent.super.apply( this, arguments );

		// Initialize properties
		this.content = new mw.flow.dm.Content();
		this.author = null;
		this.creator = null;
		this.lastUpdate = null;
		this.timestamp = null;
		this.changeType = null;
		this.workflowId = null;
		this.revisionId = null;
		this.previousRevisionId = null;
		this.originalContent = true;
		this.watched = false;
		this.watchable = true;
		this.editable = true;
		this.lastEditId = null;
		this.lastEditUser = null;

		this.content.connect( this, {
			contentChange: [ 'emit', 'contentChange' ]
		} );
	};

	/* Inheritance */
	OO.inheritClass( mw.flow.dm.RevisionedContent, mw.flow.dm.Item );

	/* Events */

	/**
	 * Change of content in this revision
	 *
	 * @event contentChange
	 */

	/**
	 * Revision is being watched or unwatched by the current user
	 *
	 * @event watchChange
	 * @param {boolean} watched Revision is watched by the current user
	 */

	/**
	 * Change of the watchable state of the revision
	 *
	 * @event watchableChange
	 * @param {boolean} watchable Revision can be watched by the current user
	 */

	/**
	 * Change of original content status
	 *
	 * @event originalContentChange
	 * @param {boolean} originalContent Revision is original content, and was never edited
	 */

	/**
	 * Change of editable status
	 *
	 * @event editableChange
	 * @param {boolean} editable The revision is editable
	 */

	/* Methods */

	/**
	 * @inheritdoc
	 */
	mw.flow.dm.RevisionedContent.prototype.getHashObject = function () {
		return $.extend( {
			content: this.getContent(),
			author: this.getAuthor(),
			creator: this.getCreator(),
			lastUpdate: this.getLastUpdate(),
			timestamp: this.getTimestamp(),
			changeType: this.getChangeType(),

			workflowId: this.getWorkflowId(),
			revisionId: this.getRevisionId(),
			previousRevisionId: this.getPreviousRevisionId(),
			originalContent: this.isOriginalContent(),
			watched: this.isWatched(),
			watchable: this.isWatchable(),
			editable: this.isEditable()
		}, mw.flow.dm.RevisionedContent.super.prototype.getHashObject.apply( this, arguments ) );
	};

	/**
	 * Populate the revision object with available data.
	 * Any missing data property (one that is set to undefined) will be
	 * ignored. If the intent is to nullify a property, use explicit 'null'
	 * value.
	 *
	 * @param {Object} data API data
	 */
	mw.flow.dm.RevisionedContent.prototype.populate = function ( data ) {
		this.setContent( data.content );
		this.setAuthor( data.author );
		this.setCreator( data.creator );
		this.setLastUpdate( data.last_updated );
		this.setTimestamp( data.timestamp );

		this.setChangeType( data.changeType );
		this.setWorkflowId( data.workflowId );
		this.setRevisionId( data.revisionId );
		this.setPreviousRevisionId( data.previousRevisionId );

		this.toggleOriginalContent(
			data.isOriginalContent !== undefined ?
				data.isOriginalContent :
				// If 'isOriginalContent' isn't at all defined, we will
				// define it by whether there is a previous revision id
				// present
				!this.getPreviousRevisionId()
		);

		this.setLastEditId( data.lastEditId );
		this.setLastEditUser( data.lastEditUser );

		this.toggleWatched( !!data.isWatched );
		if ( data.watchable !== undefined ) {
			this.toggleWatchable( !!data.watchable );
		}

		this.toggleEditable( !!( data.actions && data.actions.edit ) );

		this.actions = data.actions;
	};

	/**
	 * Get revision author
	 *
	 * @return {Object} Revision author
	 */
	mw.flow.dm.RevisionedContent.prototype.getAuthor = function () {
		return this.author;
	};

	/**
	 * Set revision author
	 *
	 * @param {Object} author Revision author
	 */
	mw.flow.dm.RevisionedContent.prototype.setAuthor = function ( author ) {
		if ( author !== undefined && !OO.compare( this.author, author ) ) {
			this.author = author;
		}
	};

	/**
	 * Get revision creator
	 *
	 * @return {Object} Revision creator
	 */
	mw.flow.dm.RevisionedContent.prototype.getCreator = function () {
		return this.creator;
	};

	/**
	 * Set revision creator
	 *
	 * @param {Object} creator Revision creator
	 */
	mw.flow.dm.RevisionedContent.prototype.setCreator = function ( creator ) {
		if ( creator !== undefined && !OO.compare( this.creator, creator ) ) {
			this.creator = creator;
		}
	};

	/**
	 * @see mw.flow.dm.Content
	 * @param {string} format
	 * @return {string}
	 */
	mw.flow.dm.RevisionedContent.prototype.getContent = function ( format ) {
		return this.content.get( format );
	};

	/**
	 * @see mw.flow.dm.Content
	 * @param {Object} representations
	 */
	mw.flow.dm.RevisionedContent.prototype.setContent = function ( representations ) {
		this.content.set( representations );
	};

	/**
	 * Get topic last update
	 *
	 * @return {number} Topic last update
	 */
	mw.flow.dm.RevisionedContent.prototype.getLastUpdate = function () {
		return this.lastUpdate;
	};

	/**
	 * Set topic last update
	 *
	 * @param {number} lastUpdate Topic last update
	 */
	mw.flow.dm.RevisionedContent.prototype.setLastUpdate = function ( lastUpdate ) {
		if ( lastUpdate !== undefined && this.lastUpdate !== lastUpdate ) {
			this.lastUpdate = lastUpdate;
		}
	};

	/**
	 * Get revision timestamp
	 *
	 * @return {number} Topic timestamp
	 */
	mw.flow.dm.RevisionedContent.prototype.getTimestamp = function () {
		return this.timestamp;
	};

	/**
	 * Set revision timestamp
	 *
	 * @param {number} timestamp Topic timestamp
	 */
	mw.flow.dm.RevisionedContent.prototype.setTimestamp = function ( timestamp ) {
		if ( timestamp !== undefined && this.timestamp !== timestamp ) {
			this.timestamp = timestamp;
		}
	};

	/**
	 * Set the revision change type
	 *
	 * @param {string} type Revision change type
	 */
	mw.flow.dm.RevisionedContent.prototype.setChangeType = function ( type ) {
		if ( type !== undefined && this.changeType !== type ) {
			this.changeType = type;
		}
	};

	/**
	 * Get the revision change type
	 *
	 * @return {string} Revision change type
	 */
	mw.flow.dm.RevisionedContent.prototype.getChangeType = function () {
		return this.changeType;
	};

	/**
	 * Get the revision id
	 *
	 * @return {string} Revision Id
	 */
	mw.flow.dm.RevisionedContent.prototype.getRevisionId = function () {
		return this.revisionId;
	};

	/**
	 * Set the revision id
	 *
	 * @param {string} id Revision Id
	 */
	mw.flow.dm.RevisionedContent.prototype.setRevisionId = function ( id ) {
		if ( id !== undefined && this.revisionId !== id ) {
			this.revisionId = id;
		}
	};
	/**
	 * Get the previous revision id.
	 * If this content was ever modified, this stores the Id of the previous
	 * revisions. Empty if never modified.
	 *
	 * @return {string} Previous revision Id
	 */
	mw.flow.dm.RevisionedContent.prototype.getPreviousRevisionId = function () {
		return this.previousRevisionId;
	};

	/**
	 * Set the previous revision id
	 *
	 * @param {string} id Previous revision Id
	 */
	mw.flow.dm.RevisionedContent.prototype.setPreviousRevisionId = function ( id ) {
		if ( id !== undefined && this.previousRevisionId !== id ) {
			this.previousRevisionId = id;
		}
	};

	/**
	 * Get the workflow id
	 *
	 * @return {string} Workflow Id
	 */
	mw.flow.dm.RevisionedContent.prototype.getWorkflowId = function () {
		return this.workflowId;
	};

	/**
	 * Set the workflow id
	 *
	 * @param {string} id Workflow Id
	 */
	mw.flow.dm.RevisionedContent.prototype.setWorkflowId = function ( id ) {
		if ( id !== undefined && this.workflowId !== id ) {
			this.workflowId = id;
		}
	};

	/**
	 * Get the last edit id
	 *
	 * @return {string} Last edit id
	 */
	mw.flow.dm.RevisionedContent.prototype.getLastEditId = function () {
		return this.lastEditId;
	};

	/**
	 * Set the last edit id
	 *
	 * @param {string} id Last edit id
	 */
	mw.flow.dm.RevisionedContent.prototype.setLastEditId = function ( id ) {
		if ( id !== undefined && this.lastEditId !== id ) {
			this.lastEditId = id;
		}
	};

	/**
	 * Get the last edit user
	 *
	 * @return {Object} Last edit user
	 */
	mw.flow.dm.RevisionedContent.prototype.getLastEditUser = function () {
		return this.lastEditUser;
	};

	/**
	 * Set the last edit user
	 *
	 * @param {Object} user Last edit user
	 */
	mw.flow.dm.RevisionedContent.prototype.setLastEditUser = function ( user ) {
		if ( user !== undefined && this.lastEditUser !== user ) {
			this.lastEditUser = user;
		}
	};

	/**
	 * Check whether the revision is watched by the current user
	 *
	 * @return {boolean} Revision is watched
	 */
	mw.flow.dm.RevisionedContent.prototype.isWatched = function () {
		return this.watched;
	};

	/**
	 * Toggle the watched state of a revision
	 *
	 * @param {boolean} [watch] Revision is watched
	 * @fires watched
	 */
	mw.flow.dm.RevisionedContent.prototype.toggleWatched = function ( watch ) {
		this.watched = watch !== undefined ? watch : !this.watched;

		this.emit( 'watchChange', this.watched );
	};

	/**
	 * Check topic originalContent status. A revision is original if it was
	 * never edited, and is the only revision for the current content.
	 *
	 * @return {boolean} Revision is original
	 */
	mw.flow.dm.RevisionedContent.prototype.isOriginalContent = function () {
		return this.originalContent;
	};

	/**
	 * Toggle the original content state of a revision.
	 * This should be false if a revision was edited.
	 *
	 * @param {boolean} [originalContent] Revision is original
	 * @fires originalContent
	 */
	mw.flow.dm.RevisionedContent.prototype.toggleOriginalContent = function ( originalContent ) {
		this.originalContent = originalContent !== undefined ? originalContent : !this.originalContent;

		this.emit( 'originalContentChange', this.originalContent );
	};

	/**
	 * Check topic watchable status
	 *
	 * @return {boolean} Topic is watchable
	 */
	mw.flow.dm.RevisionedContent.prototype.isWatchable = function () {
		return this.watchable;
	};

	/**
	 * Toggle the watchable state of a topic
	 *
	 * @param {boolean} [watchable] Topic is watchable
	 * @fires watchable
	 */
	mw.flow.dm.RevisionedContent.prototype.toggleWatchable = function ( watchable ) {
		this.watchable = watchable !== undefined ? watchable : !this.watchable;

		this.emit( 'watchableChange', this.watchable );
	};

	/**
	 * Toggle the editability state of this revision
	 *
	 * @param {boolean} [editable] The revision is editable
	 * @fires editableChange
	 */
	mw.flow.dm.RevisionedContent.prototype.toggleEditable = function ( editable ) {
		editable = editable !== undefined ? !!editable : !this.editable;

		if ( this.editable !== editable ) {
			this.editable = editable;
			this.emit( 'editableChange', this.editable );
		}
	};

	/**
	 * Check topic editable status
	 *
	 * @return {boolean} Revision is editable
	 */
	mw.flow.dm.RevisionedContent.prototype.isEditable = function () {
		return this.editable;
	};
}() );
