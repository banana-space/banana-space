<?php

namespace CirrusSearch\Job;

use Elastica\Document;

/**
 * Updates to be sent to elasticsearch need to be represented as a Document
 * object, but we can't directly serialize those into the job queue which only
 * supports json.
 *
 * Implements a simple serialize / deserialize routine that round trips
 * documents to plain json types and back.
 */
class ElasticaDocumentsJsonSerde {
	/**
	 * @param Document[] $docs
	 * @return array[] Document represented with json compatible types
	 */
	public function serialize( array $docs ) {
		$res = [];
		foreach ( $docs as $doc ) {
			$res[] = [
				'data' => $doc->getData(),
				'params' => $doc->getParams(),
				'upsert' => $doc->getDocAsUpsert(),
			];
		}
		return $res;
	}

	/**
	 * @param array[] $serialized Data returned by self::serialize
	 * @return Document[]
	 */
	public function deserialize( array $serialized ) {
		// TODO: Because json_encode/decode is involved the round trip
		// is imperfect. Almost everything here is an array regardless
		// of what it was before serialization.  That shouldn't matter
		// for documents, but elastica does occasionally use `new stdClass`
		// instead of an empty array to force `{}` in the json output
		// and that has been lost here.

		$res = [];
		foreach ( $serialized as $x ) {
			// document _source
			$doc = Document::create( $x['data'] );
			// id, version, etc.
			$doc->setParams( $x['params'] );
			$doc->setDocAsUpsert( $x['upsert'] );
			$res[] = $doc;
		}
		return $res;
	}
}
