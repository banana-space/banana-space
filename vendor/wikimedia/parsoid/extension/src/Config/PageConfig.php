<?php
/**
 * Copyright (C) 2011-2020 Wikimedia Foundation and others.
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 2 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License along
 * with this program; if not, write to the Free Software Foundation, Inc.,
 * 51 Franklin Street, Fifth Floor, Boston, MA 02110-1301 USA.
 */

namespace MWParsoid\Config;

use MediaWiki\Revision\RevisionRecord;
use MediaWiki\Revision\SlotRecord;
use MediaWiki\Revision\SlotRoleHandler;
use Parser;
use ParserOptions;
use Title;
use Wikimedia\Parsoid\Config\PageConfig as IPageConfig;
use Wikimedia\Parsoid\Config\PageContent as IPageContent;

/**
 * Page-level configuration interface for Parsoid
 *
 * @todo This belongs in MediaWiki, not Parsoid. We'll move it there when we
 *  get to the point of integrating the two.
 * @todo We should probably deprecate ParserOptions somehow, using a version of
 *  this directly instead.
 */
class PageConfig extends IPageConfig {

	/** @var Parser */
	private $parser;

	/** @var ParserOptions */
	private $parserOptions;

	/** @var SlotRoleHandler */
	private $slotRoleHandler;

	/** @var Title */
	private $title;

	/** @var RevisionRecord|null */
	private $revision;

	/** @var string|null */
	private $pagelanguage;

	/** @var string|null */
	private $pagelanguageDir;

	/**
	 * @param Parser $parser
	 * @param ParserOptions $parserOptions
	 * @param SlotRoleHandler $slotRoleHandler
	 * @param Title $title Title being parsed
	 * @param RevisionRecord|null $revision
	 * @param string|null $pagelanguage
	 * @param string|null $pagelanguageDir
	 */
	public function __construct(
		Parser $parser, ParserOptions $parserOptions, SlotRoleHandler $slotRoleHandler,
		Title $title, RevisionRecord $revision = null,
		string $pagelanguage = null, string $pagelanguageDir = null
	) {
		$this->parser = $parser;
		$this->parserOptions = $parserOptions;
		$this->slotRoleHandler = $slotRoleHandler;
		$this->title = $title;
		$this->revision = $revision;
		$this->pagelanguage = $pagelanguage;
		$this->pagelanguageDir = $pagelanguageDir;
	}

	/**
	 * Get content model
	 * @return string
	 */
	public function getContentModel(): string {
		// @todo Check just the main slot, or all slots, or what?
		$rev = $this->getRevision();
		if ( $rev ) {
			$content = $rev->getContent( SlotRecord::MAIN );
			if ( $content ) {
				return $content->getModel();
			} else {
				// The page does have a content model but we can't see it. Returning the
				// default model is not really correct. But we can't see the content either
				// so it won't matter much what we do here.
				return $this->slotRoleHandler->getDefaultModel( $this->title );
			}
		} else {
			return $this->slotRoleHandler->getDefaultModel( $this->title );
		}
	}

	public function hasLintableContentModel(): bool {
		// @todo Check just the main slot, or all slots, or what?
		$content = $this->getRevisionContent();
		$model = $content ? $content->getModel( SlotRecord::MAIN ) : null;
		return $content && ( $model === CONTENT_MODEL_WIKITEXT || $model === 'proofread-page' );
	}

	/** @inheritDoc */
	public function getTitle(): string {
		return $this->title->getPrefixedText();
	}

	/** @inheritDoc */
	public function getNs(): int {
		return $this->title->getNamespace();
	}

	/** @inheritDoc */
	public function getPageId(): int {
		return $this->title->getArticleID();
	}

	/** @inheritDoc */
	public function getPageLanguage(): string {
		return $this->pagelanguage ??
			$this->title->getPageLanguage()->getCode();
	}

	/** @inheritDoc */
	public function getPageLanguageDir(): string {
		return $this->pagelanguageDir ??
			$this->title->getPageLanguage()->getDir();
	}

	/**
	 * @return ParserOptions
	 */
	public function getParserOptions(): ParserOptions {
		return $this->parserOptions;
	}

	/**
	 * @return Parser
	 */
	public function getParser(): Parser {
		return $this->parser;
	}

	/**
	 * @return ?RevisionRecord
	 */
	private function getRevision(): ?RevisionRecord {
		if ( $this->revision === null ) {
			$this->revision = call_user_func(
				$this->parserOptions->getCurrentRevisionRecordCallback(),
				$this->title, $this->parser
			);
		}
		return $this->revision ?: null;
	}

	/** @inheritDoc */
	public function getRevisionId(): ?int {
		$rev = $this->getRevision();
		return $rev ? $rev->getId() : null;
	}

	/** @inheritDoc */
	public function getParentRevisionId(): ?int {
		$rev = $this->getRevision();
		return $rev ? $rev->getParentId() : null;
	}

	/** @inheritDoc */
	public function getRevisionTimestamp(): ?string {
		$rev = $this->getRevision();
		return $rev ? $rev->getTimestamp() : null;
	}

	/** @inheritDoc */
	public function getRevisionUser(): ?string {
		$rev = $this->getRevision();
		$user = $rev ? $rev->getUser() : null;
		return $user ? $user->getName() : null;
	}

	/** @inheritDoc */
	public function getRevisionUserId(): ?int {
		$rev = $this->getRevision();
		$user = $rev ? $rev->getUser() : null;
		return $user ? $user->getId() : null;
	}

	/** @inheritDoc */
	public function getRevisionSha1(): ?string {
		$rev = $this->getRevision();
		if ( $rev ) {
			// This matches what the Parsoid/JS gets from the API
			// FIXME: Maybe we don't need to do this in the future?
			return \Wikimedia\base_convert( $rev->getSha1(), 36, 16, 40 );
		} else {
			return null;
		}
	}

	/** @inheritDoc */
	public function getRevisionSize(): ?int {
		$rev = $this->getRevision();
		return $rev ? $rev->getSize() : null;
	}

	/** @inheritDoc */
	public function getRevisionContent(): ?IPageContent {
		$rev = $this->getRevision();
		return $rev ? new PageContent( $rev ) : null;
	}

}
