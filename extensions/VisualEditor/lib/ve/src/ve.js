/*!
 * VisualEditor namespace.
 *
 * @copyright 2011-2020 VisualEditor Team and others; see http://ve.mit-license.org
 */

/**
 * Namespace for all VisualEditor classes, static methods and static properties.
 *
 * @class ve
 * @singleton
 */
window.ve = {};

/**
 * Get the current time, measured in milliseconds since January 1, 1970 (UTC).
 *
 * On browsers that implement the Navigation Timing API, this function will produce floating-point
 * values with microsecond precision that are guaranteed to be monotonic. On all other browsers,
 * it will fall back to using `Date.now`.
 *
 * @return {number} Current time
 */
ve.now = ( function () {
	var perf = window.performance,
		navStart = perf && perf.timing && perf.timing.navigationStart;
	return navStart && typeof perf.now === 'function' ?
		function () { return navStart + perf.now(); } : Date.now;
}() );
