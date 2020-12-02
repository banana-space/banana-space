<?php

declare( strict_types = 1 );

namespace Wikimedia\Parsoid\Config\Api;

use Wikimedia\Assert\Assert;
use Wikimedia\Parsoid\Config\PageConfig as IPageConfig;
use Wikimedia\Parsoid\Config\PageContent;
use Wikimedia\Parsoid\Mocks\MockPageContent;

/**
 * PageConfig via MediaWiki's Action API
 *
 * Note this is intended for testing, not performance.
 */
class PageConfig extends IPageConfig {

	/** @var ApiHelper */
	private $api;

	/** @var string */
	private $title;

	/** @var string|null */
	private $revid;

	/** @phan-var array<string,mixed>|null */
	private $page;

	/** @phan-var array<string,mixed>|null */
	private $rev;

	/** @var PageContent|null */
	private $content;

	/** @var string|null */
	private $pagelanguage;

	/** @var string|null */
	private $pagelanguageDir;

	/**
	 * @param ApiHelper|null $api (only needed if $opts doesn't provide page info)
	 * @param array $opts
	 */
	public function __construct( ?ApiHelper $api, array $opts ) {
		$this->api = $api;

		if ( !isset( $opts['title'] ) ) {
			throw new \InvalidArgumentException( '$opts[\'title\'] must be set' );
		}
		$this->title = $opts['title'];
		$this->revid = $opts['revid'] ?? null;
		$this->pagelanguage = $opts['pageLanguage'] ?? null;
		$this->pagelanguageDir = $opts['pageLanguageDir'] ?? null;

		// This option is primarily used to mock the page content.
		if ( isset( $opts['pageContent'] ) && empty( $opts['loadData'] ) ) {
			$this->mockPageContent( $opts );
		} else {
			Assert::invariant( $api !== null, 'Cannot load page info without an API' );
			# Lazily load later
			$this->page = null;
			$this->rev = null;

			if ( isset( $opts['pageContent'] ) ) {
				$this->loadData();
				$this->rev = [
					'slots' => [ 'main' => $opts['pageContent'] ],
				];
			}
		}
	}

	/**
	 * @param array $opts
	 */
	private function mockPageContent( array $opts ) {
		$this->page = [
			'title' => $this->title,
			'ns' => $opts['pagens'] ?? 0,
			'pageid' => -1,
			'pagelanguage' => $opts['pageLanguage'] ?? 'en',
			'pagelanguagedir' => $opts['pageLanguageDir'] ?? 'ltr',
		];
		$this->rev = [
			'slots' => [ 'main' => $opts['pageContent'] ],
		];
	}

	private function loadData() {
		if ( $this->page !== null ) {
			return;
		}

		$params = [
			'action' => 'query',
			'prop' => 'info|revisions',
			'rvprop' => 'ids|timestamp|user|userid|sha1|size|content',
			'rvslots' => '*',
		];

		if ( !empty( $this->revid ) ) {
			$params['revids'] = $this->revid;
		} else {
			$params['titles'] = $this->title;
			$params['rvlimit'] = 1;
		}

		$content = $this->api->makeRequest( $params );
		if ( !isset( $content['query']['pages'][0] ) ) {
			throw new \RuntimeException( 'Request for page failed' );
		}
		$this->page = $content['query']['pages'][0];

		$this->rev = $this->page['revisions'][0] ?? [];
		unset( $this->page['revisions'] );

		if ( isset( $this->rev['timestamp'] ) ) {
			$this->rev['timestamp'] = preg_replace( '/\D/', '', $this->rev['timestamp'] );
		}

		// Well, we tried but the page probably doesn't exist
		if ( !$this->rev ) {
			$this->mockPageContent( [
				'pageContent' => '',  // FIXME: T234549
			] );
		}
	}

	/** @inheritDoc */
	public function getContentModel(): string {
		$this->loadData();
		return $this->rev['slots']['main']['contentmodel'] ?? 'wikitext';
	}

	public function hasLintableContentModel(): bool {
		$contentmodel = $this->getContentModel();
		return $contentmodel === 'wikitext' ||
			$contentmodel === 'proofread-page';
	}

	/** @inheritDoc */
	public function getTitle(): string {
		$this->loadData();
		return $this->page['title']; // normalized
	}

	/** @inheritDoc */
	public function getNs(): int {
		$this->loadData();
		return $this->page['ns'];
	}

	/** @inheritDoc */
	public function getPageId(): int {
		$this->loadData();
		return $this->page['pageid'] ?? 0;
	}

	/** @inheritDoc */
	public function getPageLanguage(): string {
		$this->loadData();
		return $this->pagelanguage ?? $this->page['pagelanguage'];
	}

	/** @inheritDoc */
	public function getPageLanguageDir(): string {
		$this->loadData();
		return $this->pagelanguageDir ?? $this->page['pagelanguagedir'];
	}

	/** @inheritDoc */
	public function getRevisionId(): ?int {
		$this->loadData();
		return $this->rev['revid'] ?? null;
	}

	/** @inheritDoc */
	public function getParentRevisionId(): ?int {
		$this->loadData();
		return $this->rev['parentid'] ?? null;
	}

	/** @inheritDoc */
	public function getRevisionTimestamp(): ?string {
		$this->loadData();
		return $this->rev['timestamp'] ?? null;
	}

	/** @inheritDoc */
	public function getRevisionUser(): ?string {
		$this->loadData();
		return $this->rev['user'] ?? null;
	}

	/** @inheritDoc */
	public function getRevisionUserId(): ?int {
		$this->loadData();
		return $this->rev['userid'] ?? null;
	}

	/** @inheritDoc */
	public function getRevisionSha1(): ?string {
		$this->loadData();
		return $this->rev['sha1'] ?? null;
	}

	/** @inheritDoc */
	public function getRevisionSize(): ?int {
		$this->loadData();
		return $this->rev['size'] ?? null;
	}

	/** @inheritDoc */
	public function getRevisionContent(): ?PageContent {
		$this->loadData();
		if ( !$this->content ) {
			$this->content = new MockPageContent( $this->rev['slots'] );
		}
		return $this->content;
	}

	/**
	 * @param array $parsoidSettings
	 * @param array $opts
	 * @return PageConfig
	 */
	public static function fromSettings(
		array $parsoidSettings, array $opts
	): PageConfig {
		$api = ApiHelper::fromSettings( $parsoidSettings );
		return new PageConfig( $api, $opts );
	}

}
