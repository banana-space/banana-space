<?php
/**
 * ResourceLoader module to set some LESS variables for the skin
 */
class TimelessVariablesModule extends ResourceLoaderSkinModule {
	/**
	 * Add our LESS variables
	 *
	 * @param ResourceLoaderContext $context
	 * @return array LESS variables
	 */
	protected function getLessVars( ResourceLoaderContext $context ) {
		$vars = parent::getLessVars( $context );
		$config = $this->getConfig();

		// Backdrop image
		$backdrop = $config->get( 'TimelessBackdropImage' );

		if ( $backdrop === 'cat.svg' ) {
			// expand default
			$backdrop = 'images/cat.svg';
		}

		$vars = array_merge(
			$vars,
			[
				'backdrop-image' => "url($backdrop)",
				// 'logo-image' => ''
				// 'wordmark-image' => ''
				// +width cutoffs ...
			]
		);

		return $vars;
	}

	/**
	 * Register the config var with the caching stuff so it properly updates the cache
	 *
	 * @param ResourceLoaderContext $context
	 * @return array
	 */
	public function getDefinitionSummary( ResourceLoaderContext $context ) {
		$summary = parent::getDefinitionSummary( $context );
		$summary[] = [
			'TimelessBackdropImage' => $this->getConfig()->get( 'TimelessBackdropImage' )
		];
		return $summary;
	}
}
