<?php
declare( strict_types = 1 );

namespace Wikimedia\Parsoid\Ext;

use DOMElement;

/**
 * A Parsoid extension module may contain one or more DOMProcessors,
 * which allow Parsoid to post-process the DOM in the wt2html direction,
 * or pre-process the DOM in the html2wt direction.
 */
abstract class DOMProcessor {

	/**
	 * Post-process DOM in the wt2html direction.
	 *
	 * @param ParsoidExtensionAPI $extApi
	 * @param DOMElement $root The root of the tree to process
	 * @param array $options
	 * @param bool $atTopLevel Is this processor invoked on the top level page?
	 *   If false, this is being invoked in a sub-pipeline (ex: extensions)
	 */
	public function wtPostprocess(
		ParsoidExtensionAPI $extApi,
		DOMElement $root,
		array $options,
		bool $atTopLevel
	): void {
		/* do nothing by default */
	}

	/**
	 * Pre-process DOM in the html2wt direction.
	 *
	 * @param ParsoidExtensionAPI $extApi
	 * @param DOMElement $root
	 * @suppress PhanEmptyPublicMethod
	 */
	public function htmlPreprocess(
		ParsoidExtensionAPI $extApi,
		DOMElement $root
	): void {
		/* do nothing by default */
	}
}
