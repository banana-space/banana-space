<?php

namespace CirrusSearch;

use MediaWiki\Logger\LoggerFactory;

/**
 * Extending from SearchRequestLog doesn't quite feel right, but there
 * is a good amount of shared code. think about best way.
 */
class MultiSearchRequestLog extends SearchRequestLog {

	/**
	 * Not sure what's best to return here, primarily we need whatever
	 * would be interesting when looking at error logging. For now this
	 * just generates a context for the first request and ignores the
	 * existence of the rest.
	 *
	 * Basically this is known to be wrong, but not sure what to do instead.
	 *
	 * @return array
	 */
	public function getLogVariables() {
		$vars = [
			'queryType' => $this->queryType,
			'tookMs' => $this->getTookMs(),
		] + $this->extra;

		if ( !$this->request || !$this->response ) {
			return $vars;
		}

		// In a multi-search instance the plain string, as sent to elasticsearch,
		// is returned by Request::getData(). Each single request is represented
		// by two lines, first a metadata line about the request and second the
		// actual query.
		/** @phan-suppress-next-line PhanTypeMismatchArgumentInternal getData() actually returns array|string */
		$lines = explode( "\n", trim( $this->request->getData(), "\n" ) );
		if ( !empty( $lines ) ) {
			$vars += $this->extractRequestVariables(
				array_slice( $lines, 0, 2 )
			);
		}

		$responseData = $this->response->getData();
		if ( !empty( $responseData['responses'] ) ) {
			// Have to use + $vars, rather than +=, to
			// allow 'suggestion' returned from here to
			// override 'suggestion' provided by $this->extra
			$vars = $this->extractResponseVariables(
				reset( $responseData['responses'] )
			) + $vars;
		}

		// in case of failures from Elastica
		if ( isset( $responseData['message'] ) ) {
			$vars['error_message'] = $responseData['message'];
		}

		return $vars;
	}

	/**
	 * @return array[]
	 */
	public function getRequests() {
		if ( !$this->request || !$this->response ) {
			// we don't actually know at this point how many searches there were,
			// or how many results to return...so just bail and return nothing
			return [];
		}

		$responseData = $this->response->getData();
		if ( !$responseData || !isset( $responseData['responses'] ) ) {
			$message = $responseData['message'] ?? 'no message';
			LoggerFactory::getInstance( 'CirrusSearch' )->warning(
				'Elasticsearch response does not have any data. {response_message}',
				[ 'response_message' => $message ]
			);
			return [];
		}

		// In a multi-search instance the plain string, as sent to elasticsearch,
		// is returned by Request::getData(). Each single request is represented
		// by two lines, first a metadata line about the request and second the
		// actual query.
		/** @phan-suppress-next-line PhanTypeMismatchArgumentInternal getData() actually returns array|string */
		$lines = explode( "\n", trim( $this->request->getData(), "\n" ) );
		$requestData = array_chunk( $lines, 2 );

		if ( count( $requestData ) !== count( $responseData['responses'] ) ) {
			// The world has ended...:(
			// @todo add more context.
			throw new \RuntimeException( 'Request and response data does not match' );
		}

		$meta = [
			'queryType' => $this->queryType,
			'tookMs' => $this->getTookMs(),
		] + $this->extra;
		$requests = [];
		foreach ( $responseData['responses'] as $singleResponseData ) {
			$vars = $this->extractRequestVariables( array_shift( $requestData ) ) +
				$this->extractResponseVariables( $singleResponseData );
			$vars['hits'] = $this->extractHits( $singleResponseData );
			// + $meta must come *after* extractResponseVariables, because
			// items like 'suggestion' override data provided in $this->extra
			$requests[] = $vars + $meta;
		}

		return $requests;
	}

	/**
	 * @param array $requestData
	 * @return array
	 */
	protected function extractRequestVariables( $requestData ) {
		// @todo error check decode
		$meta = json_decode( $requestData[0], true );
		$query = json_decode( $requestData[1], true );

		return [
			// @phan-suppress-next-line PhanTypeArraySuspiciousNullable
			'index' => implode( ',', $meta['index'] ),
		] + parent::extractRequestVariables( $query );
	}
}
