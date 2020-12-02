<?php
declare( strict_types = 1 );

namespace Wikimedia\Parsoid\Config;

use DOMDocument;

/**
 * Page-level configuration interface for Parsoid
 */
abstract class PageConfig {

	/**
	 * The owner document of the page. Used to transfer context between WikitextSerializer and
	 * some extensions, see WikitextSerializer::serializeDOM.
	 * PORT-FIXME this should not be here.
	 * @var DOMDocument|null
	 */
	public $editedDoc;

	/**
	 * Non-null to record the fact that conversion has been done on
	 * this page (to the specified variant).
	 * @var ?string
	 */
	private $htmlVariant = null;

	/**
	 * Get content model
	 * @return string
	 */
	abstract public function getContentModel(): string;

	/**
	 * Whether the page has a lintable content model
	 * @return bool
	 */
	abstract public function hasLintableContentModel(): bool;

	/**
	 * The page's title, as a string.
	 * @return string With namespace, spaces not underscores
	 */
	abstract public function getTitle(): string;

	/**
	 * The page's namespace ID
	 * @return int
	 */
	abstract public function getNs(): int;

	/**
	 * The page's ID, if any
	 * @return int 0 if the page doesn't exist
	 */
	abstract public function getPageId(): int;

	/**
	 * The page's language code
	 * @return string
	 */
	abstract public function getPageLanguage(): string;

	/**
	 * The page's language direction
	 * @return string 'ltr' or 'rtl'
	 */
	abstract public function getPageLanguageDir(): string;

	/**
	 * The revision's ID, if any
	 * @return int|null
	 */
	abstract public function getRevisionId(): ?int;

	/**
	 * The revision's parent ID, if any
	 * @return int|null
	 */
	abstract public function getParentRevisionId(): ?int;

	/**
	 * The revision's timestamp, if any
	 * @return string|null "YYYYMMDDHHIISS" format
	 */
	abstract public function getRevisionTimestamp(): ?string;

	/**
	 * The revision's author's user name, if any
	 * @return string|null
	 */
	abstract public function getRevisionUser(): ?string;

	/**
	 * The revision's author's user ID, if any
	 * @return int|null 0 if the user is not registered
	 */
	abstract public function getRevisionUserId(): ?int;

	/**
	 * The revision's SHA1 checksum, if any
	 * @return string|null Hex encoded
	 */
	abstract public function getRevisionSha1(): ?string;

	/**
	 * The revision's length, if known
	 * @return int|null Bytes
	 */
	abstract public function getRevisionSize(): ?int;

	/**
	 * The revision's content
	 * @return PageContent|null
	 */
	abstract public function getRevisionContent(): ?PageContent;

	/**
	 * Get the page's language variant
	 * @return string|null
	 */
	public function getVariant(): ?string {
		return $this->htmlVariant;
	}

	/**
	 * Set the page's language variant.  (Records the fact that
	 * conversion has been done in the parser pipeline.)
	 * @param string $htmlVariant
	 */
	public function setVariant( $htmlVariant ): void {
		$this->htmlVariant = $htmlVariant;
	}

	/**
	 * FIXME: Once we remove the hardcoded slot name here,
	 * the name of this method could be updated, if necessary.
	 *
	 * Shortcut method to get page source
	 * @deprecated Use $this->topFrame->getSrcText()
	 * @return string
	 */
	public function getPageMainContent(): string {
		return $this->getRevisionContent()->getContent( 'main' );
	}

}
