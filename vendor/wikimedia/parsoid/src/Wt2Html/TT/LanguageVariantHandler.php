<?php
declare( strict_types = 1 );

namespace Wikimedia\Parsoid\Wt2Html\TT;

use Wikimedia\Parsoid\Config\WikitextConstants;
use Wikimedia\Parsoid\Tokens\EndTagTk;
use Wikimedia\Parsoid\Tokens\EOFTk;
use Wikimedia\Parsoid\Tokens\KV;
use Wikimedia\Parsoid\Tokens\SourceRange;
use Wikimedia\Parsoid\Tokens\TagTk;
use Wikimedia\Parsoid\Tokens\Token;
use Wikimedia\Parsoid\Utils\ContentUtils;
use Wikimedia\Parsoid\Utils\DOMCompat;
use Wikimedia\Parsoid\Utils\DOMUtils;
use Wikimedia\Parsoid\Utils\PHPUtils;
use Wikimedia\Parsoid\Utils\PipelineUtils;
use Wikimedia\Parsoid\Wt2Html\TokenTransformManager;

/**
 * Handler for language conversion markup, which looks like `-{ ... }-`.
 */
class LanguageVariantHandler extends TokenHandler {
	/** @inheritDoc */
	public function __construct( TokenTransformManager $manager, array $options ) {
		parent::__construct( $manager, $options );
	}

	/**
	 * convert one variant text to dom.
	 * @param TokenTransformManager $manager
	 * @param array $options
	 * @param string $t
	 * @param array $attribs
	 * @return array
	 */
	private function convertOne( TokenTransformManager $manager, array $options, string $t,
		array $attribs ): array {
		// we're going to fetch the actual token list from attribs
		// (this ensures that it has gone through the earlier stages
		// of the pipeline already to be expanded)
		$t = preg_replace( '/^mw:lv/', '', $t, 1 );
		$srcOffsets = $attribs[$t]->srcOffsets;
		$doc = PipelineUtils::processContentInPipeline(
			$manager->env, $manager->getFrame(), array_merge( $attribs[$t]->v, [ new EOFTk() ] ),
			[
				'pipelineType' => 'tokens/x-mediawiki/expanded',
				'pipelineOpts' => [
					'inlineContext' => true,
					'expandTemplates' => $options['expandTemplates'],
					'inTemplate' => $options['inTemplate']
				],
				'srcOffsets' => $srcOffsets->value ?? null,
				'sol' => true
			]
		);
		return [
			'xmlstr' => ContentUtils::ppToXML( DOMCompat::getBody( $doc ), [ 'innerXML' => true ] ),
			'isBlock' => DOMUtils::hasBlockElementDescendant( DOMCompat::getBody( $doc ) )
		];
	}

	/**
	 * compress a whitespace sequence
	 * @param array|null $a
	 * @return array|null
	 */
	private function compressSpArray( ?array $a ): ?array {
		$result = [];
		$ctr = 0;
		if ( $a === null ) {
			return $a;
		}
		foreach ( $a as $sp ) {
			if ( $sp === '' ) {
				$ctr++;
			} else {
				if ( $ctr > 0 ) {
					$result[] = $ctr;
					$ctr = 0;
				}
				$result[] = $sp;
			}
		}
		if ( $ctr > 0 ) {
			$result[] = $ctr;
		}
		return $result;
	}

	/**
	 * Main handler.
	 * See {@link TokenTransformManager#addTransform}'s transformation parameter
	 * @param Token $token
	 * @return array
	 */
	private function onLanguageVariant( Token $token ): array {
		$manager = $this->manager;
		$options = $this->options;
		$attribs = $token->attribs;
		$dataAttribs = $token->dataAttribs;
		$tsr = $dataAttribs->tsr;
		$flags = $dataAttribs->flags;
		$flagSp = $dataAttribs->flagSp;
		$isMeta = false;
		$sawFlagA = false;

		// remove trailing semicolon marker, if present
		$trailingSemi = false;
		if ( count( $dataAttribs->texts ) &&
			( $dataAttribs->texts[count( $dataAttribs->texts ) - 1]['semi'] ?? null )
		) {
			$trailingSemi = array_pop( $dataAttribs->texts )['sp'] ?? null;
		}
		// convert all variant texts to DOM
		$isBlock = false;
		$texts = array_map( function ( array $t ) use ( $manager, $options, $attribs, &$isBlock ) {
			$text = null;
			$from = null;
			$to = null;
			if ( isset( $t['twoway'] ) ) {
				$text = $this->convertOne( $manager, $options, $t['text'], $attribs );
				$isBlock = $isBlock || !empty( $text['isBlock'] );
				return [ 'lang' => $t['lang'], 'text' => $text['xmlstr'], 'twoway' => true, 'sp' => $t['sp'] ];
			} elseif ( isset( $t['lang'] ) ) {
				$from = $this->convertOne( $manager, $options, $t['from'], $attribs );
				$to = $this->convertOne( $manager, $options, $t['to'], $attribs );
				$isBlock = $isBlock || !empty( $from['isBlock'] ) || !empty( $to['isBlock'] );
				return [ 'lang' => $t['lang'], 'from' => $from['xmlstr'], 'to' => $to['xmlstr'],
					'sp' => $t['sp'] ];
			} else {
				$text = $this->convertOne( $manager, $options, $t['text'], $attribs );
				$isBlock = $isBlock || !empty( $text['isBlock'] );
				return [ 'text' => $text['xmlstr'], 'sp' => [] ];
			}
		}, $dataAttribs->texts );
		// collect two-way/one-way conversion rules
		$oneway = [];
		$twoway = [];
		$sawTwoway = false;
		$sawOneway = false;
		$textSp = null;
		$twowaySp = [];
		$onewaySp = [];
		foreach ( $texts as $t ) {
			if ( isset( $t['twoway'] ) ) {
				$twoway[] = [ 'l' => $t['lang'], 't' => $t['text'] ];
				array_push( $twowaySp, $t['sp'][0], $t['sp'][1], $t['sp'][2] );
				$sawTwoway = true;
			} elseif ( isset( $t['lang'] ) ) {
				$oneway[] = [ 'l' => $t['lang'], 'f' => $t['from'], 't' => $t['to'] ];
				array_push( $onewaySp, $t['sp'][0], $t['sp'][1], $t['sp'][2], $t['sp'][3] );
				$sawOneway = true;
			}
		}

		// To avoid too much data-mw bloat, only the top level keys in
		// data-mw-variant are "human readable".  Nested keys are single-letter:
		// `l` for `language`, `t` for `text` or `to`, `f` for `from`.
		$dataMWV = null;
		if ( count( $flags ) === 0 && count( $dataAttribs->variants ) > 0 ) {
			// "Restrict possible variants to a limited set"
			$dataMWV = [
				'filter' => [ 'l' => $dataAttribs->variants, 't' => $texts[0]['text'] ],
				'show' => true
			];
		} else {
			$dataMWV = array_reduce( $flags, function ( array $dmwv, string $f ) use ( &$sawFlagA ) {
				if ( array_key_exists( $f, WikitextConstants::$LCFlagMap ) ) {
					if ( WikitextConstants::$LCFlagMap[$f] ) {
						$dmwv[WikitextConstants::$LCFlagMap[$f]] = true;
						if ( $f === 'A' ) {
							$sawFlagA = true;
						}
					}
				} else {
					$dmwv['error'] = true;
				}
				return $dmwv;
			}, [] );
			// (this test is done at the top of ConverterRule::getRuleConvertedStr)
			// (also partially in ConverterRule::parse)
			if ( count( $texts ) === 1 &&
				!isset( $texts[0]['lang'] ) && !isset( $dataMWV['name'] )
			) {
				if ( isset( $dataMWV['add'] ) || isset( $dataMWV['remove'] ) ) {
					$variants = [ '*' ];
					$twoway = array_map( function ( string $code ) use ( $texts, &$sawTwoway ) {
						return [ 'l' => $code, 't' => $texts[0]['text'] ];
					}, $variants );
					$sawTwoway = true;
				} else {
					$dataMWV['disabled'] = true;
					unset( $dataMWV['describe'] );
				}
			}
			if ( isset( $dataMWV['describe'] ) ) {
				if ( !$sawFlagA ) {
					$dataMWV['show'] = true;
				}
			}
			if ( isset( $dataMWV['disabled'] ) || isset( $dataMWV['name'] ) ) {
				if ( isset( $dataMWV['disabled'] ) ) {
					$dataMWV['disabled'] = [ 't' => $texts[0]['text'] ?? '' ];
				} else {
					$dataMWV['name'] = [ 't' => $texts[0]['text'] ?? '' ];
				}
				if ( isset( $dataMWV['title'] ) || isset( $dataMWV['add'] ) ) {
					unset( $dataMWV['show'] );
				} else {
					$dataMWV['show'] = true;
				}
			} elseif ( $sawTwoway ) {
				$dataMWV['twoway'] = $twoway;
				$textSp = $twowaySp;
				if ( $sawOneway ) {
					$dataMWV['error'] = true;
				}
			} else {
				$dataMWV['oneway'] = $oneway;
				$textSp = $onewaySp;
				if ( !$sawOneway ) {
					$dataMWV['error'] = true;
				}
			}
		}
		// Use meta/not meta instead of explicit 'show' flag.
		$isMeta = !isset( $dataMWV['show'] );
		unset( $dataMWV['show'] );
		// Trim some data from data-parsoid if it matches the defaults
		if ( count( $flagSp ) === 2 * count( $dataAttribs->original ) ) {
			$result = true;
			foreach ( $flagSp as $s ) {
				if ( $s !== '' ) {
					$result = false;
					break;
				}
			}
			if ( $result ) {
				$flagSp = null;
			}
		}
		if ( $trailingSemi !== false && $textSp ) {
			$textSp[] = $trailingSemi;
		}

		// Our markup is always the same, except for the contents of
		// the data-mw-variant attribute and whether it's a span, div, or a
		// meta, depending on (respectively) whether conversion output
		// contains only inline content, could contain block content,
		// or never contains any content.

		$das = [
			'fl' => $dataAttribs->original, // original "fl"ags
			'flSp' => $this->compressSpArray( $flagSp ), // spaces around flags
			'src' => $dataAttribs->src,
			'tSp' => $this->compressSpArray( $textSp ), // spaces around texts
			'tsr' => new SourceRange( $tsr->start, $isMeta ? $tsr->end : ( $tsr->end - 2 ) )
		];

		if ( $das['flSp'] === null ) {
			unset( $das['flSp'] );
		}

		if ( $das['tSp'] === null ) {
			unset( $das['tSp'] );
		}

		PHPUtils::sortArray( $dataMWV );
		$tokens = [
			new TagTk( $isMeta ? 'meta' : ( $isBlock ? 'div' : 'span' ), [
					new KV( 'typeof', 'mw:LanguageVariant' ),
					new KV( 'data-mw-variant', PHPUtils::jsonEncode( $dataMWV ) )
				], (object)$das
			)
		];
		if ( !$isMeta ) {
			$tokens[] = new EndTagTk( $isBlock ? 'div' : 'span', [],
				(object)[
					'tsr' => new SourceRange( $tsr->end - 2, $tsr->end )
				]
			);
		}

		return [ 'tokens' => $tokens ];
	}

	/**
	 * @inheritDoc
	 */
	public function onTag( Token $token ) {
		return $token->getName() === 'language-variant' ? $this->onLanguageVariant( $token ) : $token;
	}
}
