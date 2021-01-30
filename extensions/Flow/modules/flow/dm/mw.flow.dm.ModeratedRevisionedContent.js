( function () {
	/**
	 * Flow ModeratedRevisionedContent class
	 *
	 * @class
	 * @abstract
	 * @extends mw.flow.dm.RevisionedContent
	 *
	 * @constructor
	 * @param {Object} [config] Configuration options
	 */
	mw.flow.dm.ModeratedRevisionedContent = function mwFlowRevisionedContent() {
		// Parent constructor
		mw.flow.dm.ModeratedRevisionedContent.super.apply( this, arguments );
	};

	/* Inheritance */
	OO.inheritClass( mw.flow.dm.ModeratedRevisionedContent, mw.flow.dm.RevisionedContent );

	/* Events */

	/**
	 * Moderation state has changed.
	 * The content is either moderated, changed its moderation
	 * status or reason, or is no longer moderated.
	 *
	 * @event moderated
	 * @param {boolean} moderated Content is moderated
	 * @param {string} moderationState Moderation state
	 * @param {string} moderationReason Moderation reason
	 * @param {Object} moderator Moderator
	 */

	/* Methods */

	/**
	 * @inheritdoc
	 */
	mw.flow.dm.ModeratedRevisionedContent.prototype.getHashObject = function () {
		return $.extend( {
			moderated: this.isModerated(),
			moderationReason: this.getModerationReason(),
			moderationState: this.getModerationState(),
			moderator: this.getModerator()
		}, mw.flow.dm.ModeratedRevisionedContent.super.prototype.getHashObject.apply( this, arguments ) );
	};

	/**
	 * @inheritdoc
	 */
	mw.flow.dm.ModeratedRevisionedContent.prototype.populate = function ( data ) {
		this.setModerated( !!data.isModerated, data.moderateState, data.moderateReason && data.moderateReason.content, data.moderator );

		// Parent method
		mw.flow.dm.ModeratedRevisionedContent.super.prototype.populate.apply( this, arguments );
	};

	/**
	 * Check if content is moderated
	 *
	 * @return {boolean} Topic is moderated
	 */
	mw.flow.dm.ModeratedRevisionedContent.prototype.isModerated = function () {
		return this.moderated;
	};

	/**
	 * Toggle the moderated state of the content
	 *
	 * @param {boolean} moderated Content is moderated
	 * @param {string} moderationState Moderation state
	 * @param {string} moderationReason Moderation reason
	 * @param {Object} moderator Moderator
	 * @fires moderated
	 */
	mw.flow.dm.ModeratedRevisionedContent.prototype.setModerated = function ( moderated, moderationState, moderationReason, moderator ) {
		if ( this.moderated !== moderated ) {
			this.moderated = moderated;
			this.setModerationReason( moderationReason );
			this.setModerationState( moderationState );
			this.setModerator( moderator );

			// Emit event
			this.emit( 'moderated', this.isModerated(), this.getModerationState(), this.getModerationReason(), this.getModerator() );
		}
	};

	/**
	 * Get content moderation reason
	 *
	 * @return {string} Moderation reason
	 */
	mw.flow.dm.ModeratedRevisionedContent.prototype.getModerationReason = function () {
		return this.moderationReason;
	};

	/**
	 * Set content moderation reason
	 *
	 * @private
	 * @param {string} reason Moderation reason
	 */
	mw.flow.dm.ModeratedRevisionedContent.prototype.setModerationReason = function ( reason ) {
		this.moderationReason = reason;
	};

	/**
	 * Get content moderation state
	 *
	 * @return {string} Moderation state
	 */
	mw.flow.dm.ModeratedRevisionedContent.prototype.getModerationState = function () {
		return this.moderationState;
	};

	/**
	 * Set content moderation state
	 *
	 * @private
	 * @param {string} state Moderation state
	 */
	mw.flow.dm.ModeratedRevisionedContent.prototype.setModerationState = function ( state ) {
		this.moderationState = state;
	};

	/**
	 * Get content moderator
	 *
	 * @return {Object} Moderator
	 */
	mw.flow.dm.ModeratedRevisionedContent.prototype.getModerator = function () {
		return this.moderator;
	};

	/**
	 * Set content moderator
	 *
	 * @private
	 * @param {Object} mod Moderator
	 */
	mw.flow.dm.ModeratedRevisionedContent.prototype.setModerator = function ( mod ) {
		this.moderator = mod;
	};

}() );
