<?php
declare( strict_types = 1 );

namespace Wikimedia\Parsoid\Utils;

use Wikimedia\Parsoid\Config\SiteConfig;

/**
 * @deprecated Use namespace IDs and SiteConfig methods instead.
 */
class TitleNamespace {

	/** @var int */
	private $id;

	/** @phan-var array<string,bool> */
	private $is;

	/**
	 * @param int $id
	 * @param SiteConfig $siteConfig
	 */
	public function __construct( int $id, SiteConfig $siteConfig ) {
		$this->id = $id;
		$this->is = [
			'a talk' => $siteConfig->namespaceIsTalk( $id ),
			'user' => $id === $siteConfig->canonicalNamespaceId( 'user' ),
			'user_talk' => $id === $siteConfig->canonicalNamespaceId( 'user_talk' ),
			'media' => $id === $siteConfig->canonicalNamespaceId( 'media' ),
			'file' => $id === $siteConfig->canonicalNamespaceId( 'file' ),
			'category' => $id === $siteConfig->canonicalNamespaceId( 'category' ),
		];
	}

	/**
	 * Get the ID
	 * @return int
	 */
	public function getId(): int {
		return $this->id;
	}

	public function isATalkNamespace(): bool {
		return $this->is['a talk'];
	}

	public function isUser(): bool {
		return $this->is['user'];
	}

	public function isUserTalk(): bool {
		return $this->is['user_talk'];
	}

	public function isMedia(): bool {
		return $this->is['media'];
	}

	public function isFile(): bool {
		return $this->is['file'];
	}

	public function isCategory(): bool {
		return $this->is['category'];
	}

}
