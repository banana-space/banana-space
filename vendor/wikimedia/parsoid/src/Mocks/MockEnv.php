<?php

namespace Wikimedia\Parsoid\Mocks;

use Wikimedia\Parsoid\Config\Env;
use Wikimedia\Parsoid\Config\PageContent;

class MockEnv extends Env {

	/**
	 * @param array $opts
	 *  - log: (bool) Whether the logger should log. Default false.
	 *  - wrapSections: (bool) Whether to wrap sections. Default false.
	 *  - tidyWhitespaceBugMaxLength: (int|null) Value to use for tidyWhitespaceBugMaxLength,
	 *    if non-null.
	 *  - pageConfig: (PageConfig) If given, supplies a custom PageConfig instance to use.
	 *  - siteConfig: (SiteConfig) If given, supplies a custom SiteConfig instance to use.
	 *  - dataAccess: (DataAccess) If given, supplies a custom DataAccess instance to use.
	 *  - pageContent: (PageContent|string) If given and 'pageConfig' is not, this is passed to the
	 *    MockPageConfig.
	 */
	public function __construct( array $opts ) {
		if ( isset( $opts['pageConfig'] ) ) {
			$pageConfig = $opts['pageConfig'];
		} else {
			$content = $opts['pageContent'] ?? 'Some dummy source wikitext for testing.';
			$pageContent = $content instanceof PageContent
				? $content
				: new MockPageContent( [ 'main' => $content ] );
			$pageConfig = new MockPageConfig( $opts, $pageContent );
		}
		$siteConfig = $opts['siteConfig'] ?? new MockSiteConfig( $opts );
		$dataAccess = $opts['dataAccess'] ?? new MockDataAccess( $opts );
		parent::__construct( $siteConfig, $pageConfig, $dataAccess, $opts );
	}

	/** @inheritDoc */
	public function bumpTimeUse( string $resource, $time, $cat ): void {
	}

	/** @inheritDoc */
	public function bumpCount( string $resource, int $n = 1 ): void {
	}

	/**
	 * @suppress PhanEmptyPublicMethod
	 * @param string $resource
	 * @param int $count
	 */
	public function bumpParserResourceUse( string $resource, int $count = 1 ): void {
	}
}
