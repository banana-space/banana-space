<?php

namespace Flow\Search\Maintenance;

/**
 * @phan-file-suppress PhanUndeclaredMethod,PhanUndeclaredConstantOfClass This script is outdated, T227561
 */
class MappingConfigBuilder extends \CirrusSearch\Maintenance\MappingConfigBuilder {
	/**
	 * Build the mapping config.
	 *
	 * The 2 arguments are unused in Flow, but are needed for PHP Strict
	 * standards compliance: declaration should be compatible with parent.
	 *
	 * @param null $prefixSearchStartsWithAnyWord Unused
	 * @param null $phraseSuggestUseText Unused
	 * @return array the mapping config
	 */
	public function buildConfig( $prefixSearchStartsWithAnyWord = null, $phraseSuggestUseText = null ) {
		$config = [
			'dynamic' => false,
			// '_all' => array( 'enabled' => false ),
			'properties' => [
				'namespace' => $this->buildLongField(),
				'namespace_text' => $this->buildKeywordField(),
				'pageid' => $this->buildLongField(),
				// no need to analyze title, we won't be searching it
				'title' => $this->buildKeywordField(),
				'timestamp' => [
					'type' => 'date',
					'format' => 'dateOptionalTime',
				],
				'update_timestamp' => [
					'type' => 'date',
					'format' => 'dateOptionalTime',
				],
				'revisions' => [
					// object can be flattened (probably doesn't have to be
					// "nested", which would allow them to be querried independently)
					'type' => 'object',
					'properties' => [
						'id' => $this->buildKeywordField(),
						// @todo: Cirrus' config for 'text' had some more - see if we need those?
						'text' => $this->buildStringField( static::ENABLE_NORMS | static::SPEED_UP_HIGHLIGHTING ),
						'source_text' => $this->buildStringField( static::MINIMAL ),
						'moderation_state' => $this->buildKeywordField(),
						'timestamp' => [
							'type' => 'date',
							'format' => 'dateOptionalTime',
						],
						'update_timestamp' => [
							'type' => 'date',
							'format' => 'dateOptionalTime',
						],
						'type' => $this->buildKeywordField(),
					]
				]
			],
		];

		// same config for both types (well, so far...)
		return [
			'topic' => $config,
			'header' => $config,
		];
	}
}
