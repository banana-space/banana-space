<?php

namespace Wikimedia\Parsoid\Language;

use Wikimedia\LangConv\ZhReplacementMachine;

class ZhConverter extends LanguageConverter {

	public function loadDefaultTables() {
		$this->setMachine( new ZhReplacementMachine() );
	}

	// phpcs:ignore MediaWiki.Commenting.FunctionComment.MissingDocumentationPublic
	public function findVariantLink( $link, $nt, $ignoreOtherCond ) {
		$ns = $nt->getNamespace();
		// do not try to find variants for usernames
		if ( $ns->isUser() || $ns->isUserTalk ) {
			return [ 'nt' => $nt, 'link' => $link ];
		}
		// FIXME check whether selected language is 'zh'
		return parent::findVariantLink( $link, $nt, $ignoreOtherCond );
	}
}
