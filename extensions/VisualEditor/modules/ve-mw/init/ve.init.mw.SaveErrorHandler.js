/*!
 * VisualEditor Initialization save error handler class
 *
 * @copyright 2011-2020 VisualEditor Team and others; see http://ve.mit-license.org
 */

/**
 * Save error handler.
 *
 * @class
 * @abstract
 *
 * @constructor
 */
ve.init.mw.SaveErrorHandler = function () {};

/* Inheritance */

OO.initClass( ve.init.mw.SaveErrorHandler );

/* Static methods */

/**
 * Test if this handler should handle a specific API response
 *
 * @static
 * @inheritable
 * @param {Object} data API response from action=visualeditoredit
 * @return {boolean}
 */
ve.init.mw.SaveErrorHandler.static.matchFunction = null;

/**
 * Process the save error
 *
 * @static
 * @inheritable
 * @param {Object} data API response from action=visualeditoredit
 * @param {ve.init.mw.ArticleTarget} target Target
 */
ve.init.mw.SaveErrorHandler.static.process = null;

/* Save error registry */

/*
 * Extensions can add SaveErrorHandler sub-classes to this registry.
 */
ve.init.mw.saveErrorHandlerFactory = new OO.Factory();
