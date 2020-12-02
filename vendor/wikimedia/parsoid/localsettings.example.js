/*
 * This old / unusual way to configure Parsoid.
 *
 * You'll probably want to start in config.example.yaml
 * and only end up here if you need some sort of backwards compatibility
 * or to support non-static configuration.
 */

'use strict';

exports.setup = function(parsoidConfig) {
	// Do something dynamic with `parsoidConfig` like,
	// parsoidConfig.setMwApi({
	//	uri: 'http://localhost/w/api.php',
	// });
};
