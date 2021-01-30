<?php

use MediaWiki\MediaWikiServices;

class ApiEchoMute extends ApiBase {

	private $centralIdLookup = null;

	private static $muteLists = [
		'user' => [
			'pref' => 'echo-notifications-blacklist',
			'type' => 'user',
		],
		'page-linked-title' => [
			'pref' => 'echo-notifications-page-linked-title-muted-list',
			'type' => 'title'
		],
	];

	public function execute() {
		$user = $this->getUser()->getInstanceForUpdate();
		if ( !$user || $user->isAnon() ) {
			$this->dieWithError(
				[ 'apierror-mustbeloggedin', $this->msg( 'action-editmyoptions' ) ],
				'notloggedin'
			);
		}

		$this->checkUserRightsAny( 'editmyoptions' );

		$params = $this->extractRequestParams();
		$mutelistInfo = self::$muteLists[ $params['type'] ];
		$prefValue = $user->getOption( $mutelistInfo['pref'] );
		$ids = $this->parsePref( $prefValue, $mutelistInfo['type'] );
		$targetsToMute = $params['mute'] ?? [];
		$targetsToUnmute = $params['unmute'] ?? [];

		$changed = false;
		$addIds = $this->lookupIds( $targetsToMute, $mutelistInfo['type'] );
		foreach ( $addIds as $id ) {
			if ( !in_array( $id, $ids ) ) {
				$ids[] = $id;
				$changed = true;
			}
		}
		$removeIds = $this->lookupIds( $targetsToUnmute, $mutelistInfo['type'] );
		foreach ( $removeIds as $id ) {
			$index = array_search( $id, $ids );
			if ( $index !== false ) {
				array_splice( $ids, $index, 1 );
				$changed = true;
			}
		}

		if ( $changed ) {
			$user->setOption( $mutelistInfo['pref'], $this->serializePref( $ids, $mutelistInfo['type'] ) );
			$user->saveSettings();
		}

		$this->getResult()->addValue( null, $this->getModuleName(), 'success' );
	}

	private function getCentralIdLookup() {
		if ( $this->centralIdLookup === null ) {
			$this->centralIdLookup = CentralIdLookup::factory();
		}
		return $this->centralIdLookup;
	}

	private function lookupIds( $names, $type ) {
		if ( $type === 'title' ) {
			$linkBatch = MediaWikiServices::getInstance()->getLinkBatchFactory()->newLinkBatch();
			foreach ( $names as $name ) {
				$linkBatch->addObj( Title::newFromText( $name ) );
			}
			$linkBatch->execute();

			$ids = [];
			foreach ( $names as $name ) {
				$title = Title::newFromText( $name );
				if ( $title instanceof Title && $title->getArticleID() > 0 ) {
					$ids[] = $title->getArticleID();
				}
			}
			return $ids;
		} elseif ( $type === 'user' ) {
			return $this->getCentralIdLookup()->centralIdsFromNames( $names, CentralIdLookup::AUDIENCE_PUBLIC );
		}
	}

	private function parsePref( $prefValue, $type ) {
		return preg_split( '/\n/', $prefValue, -1, PREG_SPLIT_NO_EMPTY );
	}

	private function serializePref( $ids, $type ) {
		return implode( "\n", $ids );
	}

	public function getAllowedParams( $flags = 0 ) {
		return [
			'type' => [
				ApiBase::PARAM_REQUIRED => true,
				ApiBase::PARAM_TYPE => array_keys( self::$muteLists ),
			],
			'mute' => [
				ApiBase::PARAM_ISMULTI => true,
			],
			'unmute' => [
				ApiBase::PARAM_ISMULTI => true,
			]
		];
	}

	public function needsToken() {
		return 'csrf';
	}

	public function getTokenSalt() {
		return '';
	}

	public function mustBePosted() {
		return true;
	}

	public function isWriteMode() {
		return true;
	}

}
