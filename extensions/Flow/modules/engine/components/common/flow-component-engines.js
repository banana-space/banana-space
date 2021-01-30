/*!
 * Initializes TemplateEngine (Handlebars), and API (FlowApi).
 */

/**
 * @class FlowHandlebars
 * TODO: Use @-external in JSDoc
 */
/**
 * @class FlowApi
 * TODO: Use @-external in JSDoc
 */

( function () {
	/**
	 * Initializes Handlebars and FlowApi.
	 *
	 * @constructor
	 */
	function FlowComponentEnginesMixin() {}
	OO.initClass( FlowComponentEnginesMixin );

	/**
	 * Contains the Flow templating engine translation class (in case we change templating engines).
	 *
	 * @type {FlowHandlebars}
	 */
	mw.flow.TemplateEngine = FlowComponentEnginesMixin.static.TemplateEngine = new mw.flow.FlowHandlebars();

	/**
	 * Flow API singleton
	 *
	 * @type {FlowApi}
	 */
	mw.flow.Api = new mw.flow.FlowApi();

	// Copy static and prototype from mixin to main class
	mw.flow.mixinComponent( 'component', FlowComponentEnginesMixin );
}() );
