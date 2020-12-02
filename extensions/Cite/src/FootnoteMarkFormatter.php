<?php

namespace Cite;

use Parser;
use Sanitizer;

/**
 * @license GPL-2.0-or-later
 */
class FootnoteMarkFormatter {

	/**
	 * @var string[][]
	 */
	private $linkLabels = [];

	/**
	 * @var AnchorFormatter
	 */
	private $anchorFormatter;

	/**
	 * @var ErrorReporter
	 */
	private $errorReporter;

	/**
	 * @var ReferenceMessageLocalizer
	 */
	private $messageLocalizer;

	/**
	 * @param ErrorReporter $errorReporter
	 * @param AnchorFormatter $anchorFormatter
	 * @param ReferenceMessageLocalizer $messageLocalizer
	 */
	public function __construct(
		ErrorReporter $errorReporter,
		AnchorFormatter $anchorFormatter,
		ReferenceMessageLocalizer $messageLocalizer
	) {
		$this->anchorFormatter = $anchorFormatter;
		$this->errorReporter = $errorReporter;
		$this->messageLocalizer = $messageLocalizer;
	}

	/**
	 * Generate a link (<sup ...) for the <ref> element from a key
	 * and return XHTML ready for output
	 *
	 * @suppress SecurityCheck-DoubleEscaped
	 * @param Parser $parser
	 * @param string $group
	 * @param array $ref Dictionary with ReferenceStack ref format
	 *
	 * @return string
	 */
	public function linkRef( Parser $parser, string $group, array $ref ) : string {
		$label = $this->getLinkLabel( $parser, $group, $ref['number'] );
		if ( $label === null ) {
			$label = $this->messageLocalizer->localizeDigits( $ref['number'] );
			if ( $group !== Cite::DEFAULT_GROUP ) {
				$label = "$group $label";
			}
		}
		if ( isset( $ref['extendsIndex'] ) ) {
			$label .= '.' . $this->messageLocalizer->localizeDigits( $ref['extendsIndex'] );
		}

		$key = $ref['name'] ?? $ref['key'];
		$count = $ref['name'] ? $ref['key'] . '-' . $ref['count'] : null;
		$subkey = $ref['name'] ? '-' . $ref['key'] : null;

		return $parser->recursiveTagParse(
			$this->messageLocalizer->msg(
				'cite_reference_link',
				$this->anchorFormatter->refKey( $key, $count ),
				$this->anchorFormatter->getReferencesKey( $key . $subkey ),
				Sanitizer::safeEncodeAttribute( $label )
			)->plain()
		);
	}

	/**
	 * Generate a custom format link for a group given an offset, e.g.
	 * the second <ref group="foo"> is b if $this->mLinkLabels["foo"] =
	 * [ 'a', 'b', 'c', ...].
	 * Return an error if the offset > the # of array items
	 *
	 * @param Parser $parser
	 * @param string $group The group name
	 * @param int $number Expected to start at 1
	 *
	 * @return string|null Returns null if no custom labels for this group exist
	 */
	private function getLinkLabel( Parser $parser, string $group, int $number ) : ?string {
		$message = "cite_link_label_group-$group";
		if ( !isset( $this->linkLabels[$group] ) ) {
			$msg = $this->messageLocalizer->msg( $message );
			$this->linkLabels[$group] = $msg->isDisabled() ? [] : preg_split( '/\s+/', $msg->plain() );
		}

		if ( !$this->linkLabels[$group] ) {
			return null;
		}

		return $this->linkLabels[$group][$number - 1] ?? $this->errorReporter->plain(
			$parser,
			'cite_error_no_link_label_group',
			$group,
			$message
		);
	}

}
