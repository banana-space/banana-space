<?php

namespace Flow\Import\LiquidThreadsApi;

use AppendIterator;
use ArrayIterator;
use Flow\Import\IImportPost;
use Flow\Import\IObjectRevision;
use Iterator;
use Title;

class ImportPost extends PageRevisionedObject implements IImportPost {

	/**
	 * @var array
	 */
	protected $apiResponse;

	/**
	 * @param ImportSource $source
	 * @param array $apiResponse
	 */
	public function __construct( ImportSource $source, array $apiResponse ) {
		parent::__construct( $source, $apiResponse['rootid'] );
		$this->apiResponse = $apiResponse;
	}

	/**
	 * @return string
	 */
	public function getAuthor() {
		return $this->apiResponse['author']['name'];
	}

	/**
	 * Gets the username (or IP) from the signature.
	 *
	 * @return string|null Returns username, IP, or null if none could be detected
	 */
	public function getSignatureUser() {
		$signatureText = $this->apiResponse['signature'];

		return self::extractUserFromSignature( $signatureText );
	}

	/**
	 * Gets the username (or IP) from the provided signature.
	 *
	 * @param string $signatureText
	 * @return string|null Returns username, IP, or null if none could be detected
	 */
	public static function extractUserFromSignature( $signatureText ) {
		$users = \EchoDiscussionParser::extractUsersFromLine( $signatureText );

		if ( count( $users ) > 0 ) {
			return $users[0];
		} else {
			return null;
		}
	}

	/**
	 * @return string|false
	 */
	public function getCreatedTimestamp() {
		return wfTimestamp( TS_MW, $this->apiResponse['created'] );
	}

	/**
	 * @return string|false
	 */
	public function getModifiedTimestamp() {
		return wfTimestamp( TS_MW, $this->apiResponse['modified'] );
	}

	public function getTitle() {
		$pageData = $this->importSource->getPageData( $this->apiResponse['rootid'] );

		return Title::newFromText( $pageData['title'] );
	}

	/**
	 * @return Iterator<IImportPost>
	 */
	public function getReplies() {
		return new ReplyIterator( $this );
	}

	/**
	 * @return array
	 */
	public function getApiResponse() {
		return $this->apiResponse;
	}

	/**
	 * @return ImportSource
	 */
	public function getSource() {
		return $this->importSource;
	}

	public function getRevisions() {
		$authorUsername = $this->getAuthor();
		$signatureUsername = $this->getSignatureUser();
		if ( $signatureUsername !== null && $signatureUsername !== $authorUsername ) {
			$originalRevisionData = $this->getRevisionData();

			// This is not the same object as the last one in the original iterator,
			// but it should be fungible.
			$lastLqtRevision = new ImportRevision(
				end( $originalRevisionData['revisions'] ),
				$this,
				$this->importSource->getScriptUser()
			);
			$signatureRevision = $this->createSignatureClarificationRevision(
				$lastLqtRevision,
				$authorUsername,
				$signatureUsername
			);

			$originalRevisions = parent::getRevisions();
			$iterator = new AppendIterator();
			$iterator->append( $originalRevisions );
			$iterator->append( new ArrayIterator( [ $signatureRevision ] ) );

			return $iterator;
		} else {
			return parent::getRevisions();
		}
	}

	/**
	 * Creates revision clarifying signature difference
	 *
	 * @param IObjectRevision $lastRevision Last revision prior to the clarification revision
	 * @param string $authorUsername Author username
	 * @param string $signatureUsername Username extracted from signature
	 * @return ScriptedImportRevision Generated top import revision
	 */
	protected function createSignatureClarificationRevision( IObjectRevision $lastRevision, $authorUsername, $signatureUsername ) {
		$wikitextForLastRevision = $lastRevision->getText();
		$newWikitext = $wikitextForLastRevision;

		$templateName = wfMessage(
			'flow-importer-lqt-different-author-signature-template'
		)->inContentLanguage()->plain();
		$arguments = implode(
			'|',
			[
				"authorUser=$authorUsername",
				"signatureUser=$signatureUsername",
			]
		);

		$newWikitext .= "\n\n{{{$templateName}|$arguments}}";
		$clarificationRevision = new ScriptedImportRevision(
			$this, $this->importSource->getScriptUser(), $newWikitext, $lastRevision
		);

		return $clarificationRevision;
	}

	public function getObjectKey() {
		return $this->importSource->getObjectKey( 'thread_id', $this->apiResponse['id'] );
	}
}
