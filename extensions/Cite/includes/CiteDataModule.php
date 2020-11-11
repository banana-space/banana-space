<?php

/**
 * Resource loader module providing extra data from the server to Cite.
 *
 * Temporary hack for T93800
 *
 * @copyright 2011-2018 VisualEditor Team's Cite sub-team and others; see AUTHORS.txt
 * @license MIT
 */
class CiteDataModule extends ResourceLoaderModule {

	protected $origin = self::ORIGIN_USER_SITEWIDE;
	protected $targets = [ 'desktop', 'mobile' ];

	/** @inheritDoc */
	public function getScript( ResourceLoaderContext $context ) {
		$citationDefinition = json_decode(
			$context->msg( 'cite-tool-definition.json' )
				->inContentLanguage()
				->plain()
		);

		if ( $citationDefinition === null ) {
			$citationDefinition = json_decode(
				$context->msg( 'visualeditor-cite-tool-definition.json' )
					->inContentLanguage()
					->plain()
			);
		}

		$citationTools = [];
		if ( is_array( $citationDefinition ) ) {
			foreach ( $citationDefinition as $tool ) {
				if ( !isset( $tool->title ) ) {
					$tool->title = $context->msg( 'visualeditor-cite-tool-name-' . $tool->name )
						->text();
				}
				$citationTools[] = $tool;
			}
		}

		return 've.init.platform.addMessages(' . FormatJson::encode(
				[
					'cite-tool-definition.json' => json_encode( $citationTools )
				],
				ResourceLoader::inDebugMode()
			) . ');';
	}

	/** @inheritDoc */
	public function getDependencies( ResourceLoaderContext $context = null ) {
		return [
			'ext.visualEditor.base',
			'ext.visualEditor.mediawiki',
		];
	}

	/** @inheritDoc */
	public function getDefinitionSummary( ResourceLoaderContext $context ) {
		$summary = parent::getDefinitionSummary( $context );
		$summary[] = [
			'script' => $this->getScript( $context ),
		];
		return $summary;
	}

}
