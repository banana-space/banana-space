<?php

namespace Wikimedia\Parsoid\Language;

use DOMElement;
use DOMNode;
use stdClass;
use Wikimedia\LangConv\ReplacementMachine;
use Wikimedia\Parsoid\Utils\DOMDataUtils;
use Wikimedia\Parsoid\Utils\DOMPostOrder;
use Wikimedia\Parsoid\Utils\DOMUtils;

/**
 * Use a {@Link ReplacementMachine} to predict the best "source language" for every node in a DOM.
 * Appropriate for wikis which are written in a mix of variants.
 */
class MachineLanguageGuesser extends LanguageGuesser {

	/**
	 * MachineLanguageGuesser constructor.
	 * @param ReplacementMachine $machine
	 * @param DOMNode $root
	 * @param string $destCode
	 */
	public function __construct( ReplacementMachine $machine, DOMNode $root, $destCode ) {
		$codes = [];
		foreach ( $machine->getCodes() as $invertCode => $ignore ) {
			if ( $machine->isValidCodePair( $destCode, $invertCode ) ) {
				$codes[] = $invertCode;
			}
		}
		$zeroCounts = [];
		foreach ( $codes as $invertCode ) {
			$zeroCounts[$invertCode] = 0;
		}

		DOMPostOrder::traverse(
			$root, function ( DOMNode &$node ) use (
				$machine, $codes, $destCode, $zeroCounts
			) {
				if ( !( $node instanceof DOMElement ) ) {
					// Elements only!
					return;
				}
				// XXX look at `lang` attribute and use it to inform guess?
				$nodeData = self::getNodeData( $node );
				$first = true;
				// Iterate over child *nodes* (not just elements)
				for ( $child = $node->firstChild;
					  $child;
					  $child = $child->nextSibling
				) {
					if ( DOMUtils::isText( $child ) ) {
						$countMap = [];
						foreach ( $codes as $invertCode ) {
							$countMap[$invertCode] = $machine->countBrackets(
								$child->textContent,
								$destCode,
								$invertCode
							)->safe;
						}
					} elseif ( $child instanceof DOMElement ) {
						$countMap = self::getNodeData( $child )->countMap;
					} else {
						continue; // skip this non-element non-text node
					}
					if ( $first ) {
						$nodeData->countMap = $countMap;
						$first = false;
					} else {
						// accumulate child counts!
						foreach ( $codes as $c ) {
							$nodeData->countMap[$c] += $countMap[$c];
						}
					}
				}
				if ( $first ) {
					$nodeData->countMap = $zeroCounts;
				}
				// Compute best guess for language
				$safe = [];
				foreach ( $codes as $code ) {
					$safe[$code] = $nodeData->countMap[$code];
				}
				arsort( $safe );
				$nodeData->guessLang = array_keys( $safe )[0];
			} );
	}

	/**
	 * Helper function that namespaces all of our node data used in
	 * this class into the top-level `mw_variant` key.
	 *
	 * @param DOMElement $node
	 * @return stdClass
	 */
	private static function getNodeData( DOMElement $node ): stdClass {
		$nodeData = DOMDataUtils::getNodeData( $node );
		if ( !isset( $nodeData->mw_variant ) ) {
			$nodeData->mw_variant = new stdClass;
		}
		return $nodeData->mw_variant;
	}

	/** @inheritDoc */
	public function guessLang( DOMElement $node ): string {
		return self::getNodeData( $node )->guessLang;
	}
}
