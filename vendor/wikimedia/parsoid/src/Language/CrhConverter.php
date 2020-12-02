<?php

namespace Wikimedia\Parsoid\Language;

use Wikimedia\LangConv\ReplacementMachine;

class CrhConverter extends LanguageConverter {

	public function loadDefaultTables() {
		$this->setMachine( new ReplacementMachine( 'crh', [ 'crh-latn', 'crh-cyrl' ] ) );
	}

	// phpcs:ignore MediaWiki.Commenting.FunctionComment.MissingDocumentationPublic
	public function findVariantLink( $link, $nt, $ignoreOtherCond ) {
		$ns = $nt->getNamespace();
		// do not try to find variants for usernames
		if ( $ns->isUser() || $ns->isUserTalk ) {
			return [ 'nt' => $nt, 'link' => $link ];
		}
		// FIXME check whether selected language is 'crh'
		return parent::findVariantLink( $link, $nt, $ignoreOtherCond );
	}

}
