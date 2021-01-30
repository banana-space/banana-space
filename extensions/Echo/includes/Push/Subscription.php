<?php

namespace EchoPush;

use Wikimedia\Timestamp\ConvertibleTimestamp;

class Subscription {

	/** @var string */
	private $provider;

	/** @var string */
	private $token;

	/** @var ConvertibleTimestamp */
	private $updated;

	/**
	 * Construct a subscription from a DB result row.
	 * @param object $row echo_push_subscription row from IResultWrapper::fetchRow
	 * @return Subscription
	 */
	public static function newFromRow( object $row ) {
		return new self(
			$row->epp_name,
			$row->eps_token,
			new ConvertibleTimestamp( $row->eps_updated )
		);
	}

	/**
	 * @param string $provider
	 * @param string $token
	 * @param ConvertibleTimestamp $updated
	 */
	public function __construct( string $provider, string $token, ConvertibleTimestamp $updated ) {
		$this->provider = $provider;
		$this->token = $token;
		$this->updated = $updated;
	}

	/** @return string provider */
	public function getProvider(): string {
		return $this->provider;
	}

	/** @return string token */
	public function getToken(): string {
		return $this->token;
	}

	/** @return ConvertibleTimestamp last updated timestamp */
	public function getUpdated(): ConvertibleTimestamp {
		return $this->updated;
	}

}
