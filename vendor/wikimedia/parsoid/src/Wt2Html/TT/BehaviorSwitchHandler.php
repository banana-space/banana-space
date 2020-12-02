<?php
declare( strict_types = 1 );

namespace Wikimedia\Parsoid\Wt2Html\TT;

use Wikimedia\Parsoid\Tokens\KV;
use Wikimedia\Parsoid\Tokens\SelfclosingTagTk;
use Wikimedia\Parsoid\Tokens\Token;
use Wikimedia\Parsoid\Utils\Utils;
use Wikimedia\Parsoid\Wt2Html\TokenTransformManager;

/**
 * Handler for behavior switches, like '__TOC__' and similar.
 */
class BehaviorSwitchHandler extends TokenHandler {
	/**
	 * @param TokenTransformManager $manager
	 * @param array $options options
	 */
	public function __construct( TokenTransformManager $manager, array $options ) {
		parent::__construct( $manager, $options );
	}

	/**
	 * Main handler.
	 * See {@link TokenTransformManager#addTransform}'s transformation parameter.
	 *
	 * @param Token $token
	 * @return array
	 */
	public function onBehaviorSwitch( Token $token ): array {
		$env = $this->manager->env;
		$magicWord = $env->getSiteConfig()->magicWordCanonicalName( $token->attribs[0]->v );
		$env->setVariable( $magicWord, true );
		$metaToken = new SelfclosingTagTk(
			'meta',
			[ new KV( 'property', 'mw:PageProp/' . $magicWord ) ],
			Utils::clone( $token->dataAttribs )
		);

		return [ 'tokens' => [ $metaToken ] ];
	}

	/**
	 * @inheritDoc
	 */
	public function onTag( Token $token ) {
		return $token->getName() === 'behavior-switch' ? $this->onBehaviorSwitch( $token ) : $token;
	}
}
