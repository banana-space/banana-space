<?php

namespace MediaWiki\Rest\Handler;

use MediaWiki\Permissions\PermissionManager;
use MediaWiki\Rest\Handler;
use MediaWiki\Rest\LocalizedHttpException;
use MediaWiki\Rest\StringStream;
use MediaWiki\Revision\RevisionAccessException;
use MediaWiki\Revision\RevisionLookup;
use MediaWiki\Revision\RevisionRecord;
use MediaWiki\Revision\SlotRecord;
use MediaWiki\Revision\SuppressedDataException;
use Parser;
use RequestContext;
use TextContent;
use User;
use Wikimedia\Message\MessageValue;
use Wikimedia\ParamValidator\ParamValidator;

class CompareHandler extends Handler {
	/** @var RevisionLookup */
	private $revisionLookup;

	/** @var PermissionManager */
	private $permissionManager;

	/** @var Parser */
	private $parser;

	/** @var User */
	private $user;

	/** @var RevisionRecord[] */
	private $revisions = [];

	/** @var string[] */
	private $textCache = [];

	public function __construct(
		RevisionLookup $revisionLookup,
		PermissionManager $permissionManager,
		Parser $parser
	) {
		$this->revisionLookup = $revisionLookup;
		$this->permissionManager = $permissionManager;
		$this->parser = $parser;

		// @todo Inject this, when there is a good way to do that
		$this->user = RequestContext::getMain()->getUser();
	}

	public function execute() {
		$fromRev = $this->getRevisionOrThrow( 'from' );
		$toRev = $this->getRevisionOrThrow( 'to' );

		if ( $fromRev->getPageId() !== $toRev->getPageId() ) {
			throw new LocalizedHttpException(
				new MessageValue( 'rest-compare-page-mismatch' ), 400 );
		}

		if ( !$this->permissionManager->userCan( 'read', $this->user,
			$toRev->getPageAsLinkTarget() )
		) {
			throw new LocalizedHttpException(
				new MessageValue( 'rest-compare-permission-denied' ), 403 );
		}

		$data = [
			'from' => [
				'id' => $fromRev->getId(),
				'slot_role' => $this->getRole(),
				'sections' => $this->getSectionInfo( 'from' )
			],
			'to' => [
				'id' => $toRev->getId(),
				'slot_role' => $this->getRole(),
				'sections' => $this->getSectionInfo( 'to' )
			],
			'diff' => [ 'PLACEHOLDER' => null ]
		];
		$rf = $this->getResponseFactory();
		$wrapperJson = $rf->encodeJson( $data );
		$diff = $this->getJsonDiff();
		$response = $rf->create();
		$response->setHeader( 'Content-Type', 'application/json' );
		// A hack until getJsonDiff() is moved to SlotDiffRenderer and only nested inner diff is returned
		$innerDiff = substr( $diff, 1, -1 );
		$response->setBody( new StringStream(
			str_replace( '"diff":{"PLACEHOLDER":null}', $innerDiff, $wrapperJson ) ) );
		return $response;
	}

	/**
	 * @param string $paramName
	 * @return RevisionRecord|null
	 */
	private function getRevision( $paramName ) {
		if ( !isset( $this->revisions[$paramName] ) ) {
			$this->revisions[$paramName] =
				$this->revisionLookup->getRevisionById( $this->getValidatedParams()[$paramName] );
		}
		return $this->revisions[$paramName];
	}

	/**
	 * @param string $paramName
	 * @return RevisionRecord
	 * @throws LocalizedHttpException
	 */
	private function getRevisionOrThrow( $paramName ) {
		$rev = $this->getRevision( $paramName );
		if ( !$rev ) {
			throw new LocalizedHttpException(
				new MessageValue( 'rest-compare-nonexistent', [ $paramName ] ), 404 );
		}

		if ( !$this->isAccessible( $rev ) ) {
			throw new LocalizedHttpException(
				new MessageValue( 'rest-compare-inaccessible', [ $paramName ] ), 403 );
		}
		return $rev;
	}

	/**
	 * @param RevisionRecord $rev
	 * @return bool
	 */
	private function isAccessible( $rev ) {
		return $rev->audienceCan(
			RevisionRecord::DELETED_TEXT,
			RevisionRecord::FOR_THIS_USER,
			$this->user
		);
	}

	private function getRole() {
		return SlotRecord::MAIN;
	}

	private function getRevisionText( $paramName ) {
		if ( !isset( $this->textCache[$paramName] ) ) {
			$revision = $this->getRevision( $paramName );
			try {
				$content = $revision
					->getSlot( $this->getRole(), RevisionRecord::FOR_THIS_USER, $this->user )
					->getContent()
					->convert( CONTENT_MODEL_TEXT );
				if ( $content instanceof TextContent ) {
					$this->textCache[$paramName] = $content->getText();
				} else {
					throw new LocalizedHttpException(
						new MessageValue(
							'rest-compare-wrong-content',
							[ $this->getRole(), $paramName ]
						),
						400 );
				}
			} catch ( SuppressedDataException $e ) {
				throw new LocalizedHttpException(
					new MessageValue( 'rest-compare-inaccessible', [ $paramName ] ), 403 );
			} catch ( RevisionAccessException $e ) {
				throw new LocalizedHttpException(
					new MessageValue( 'rest-compare-nonexistent', [ $paramName ] ), 404 );
			}
		}
		return $this->textCache[$paramName];
	}

	/**
	 * @return string
	 */
	private function getJsonDiff() {
		// TODO: properly implement
		// This is a prototype only. SlotDiffRenderer should be extended to support this use case.
		$fromText = $this->getRevisionText( 'from' );
		$toText = $this->getRevisionText( 'to' );
		if ( !function_exists( 'wikidiff2_inline_json_diff' ) ) {
			throw new LocalizedHttpException(
				new MessageValue( 'rest-compare-wikidiff2' ), 500 );
		}
		return wikidiff2_inline_json_diff( $fromText, $toText, 2 );
	}

	/**
	 * @param string $paramName
	 * @return array
	 */
	private function getSectionInfo( $paramName ) {
		$text = $this->getRevisionText( $paramName );
		$parserSections = $this->parser->getFlatSectionInfo( $text );
		$sections = [];
		foreach ( $parserSections as $i => $parserSection ) {
			// Skip section zero, which comes before the first heading, since
			// its offset is always zero, so the client can assume its location.
			if ( $i !== 0 ) {
				$sections[] = [
					'level' => $parserSection['level'],
					'heading' => $parserSection['heading'],
					'offset' => $parserSection['offset'],
				];
			}
		}
		return $sections;
	}

	public function getParamSettings() {
		return [
			'from' => [
				ParamValidator::PARAM_TYPE => 'integer',
				ParamValidator::PARAM_REQUIRED => true,
				Handler::PARAM_SOURCE => 'path',
			],
			'to' => [
				ParamValidator::PARAM_TYPE => 'integer',
				ParamValidator::PARAM_REQUIRED => true,
				Handler::PARAM_SOURCE => 'path',
			],
		];
	}
}
