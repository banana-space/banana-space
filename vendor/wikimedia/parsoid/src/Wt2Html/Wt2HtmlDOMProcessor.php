<?php
declare( strict_types = 1 );

namespace Wikimedia\Parsoid\Wt2Html;

use DOMElement;
use Wikimedia\Parsoid\Config\Env;

/**
 * wt2html DOM processor used to implement some DOM functionality
 * (ex: DSR computation, template wrapping, etc.)
 */
interface Wt2HtmlDOMProcessor {
	/**
	 * @param Env $env
	 * @param DOMElement $root The root of the tree to process
	 * @param array $options
	 * @param bool $atTopLevel Is this processor invoked on the top level page?
	 *   If false, this is being invoked in a sub-pipeline (ex: extensions)
	 */
	public function run(
		Env $env, DOMElement $root, array $options = [], bool $atTopLevel = false
	): void;
}
