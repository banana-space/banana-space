<?php

namespace Wikimedia\Parsoid\Wt2Html;

use Generator;
use Wikimedia\Parsoid\Config\Env;
use Wikimedia\Parsoid\Tokens\SourceRange;
use Wikimedia\Parsoid\Utils\PHPUtils;
use Wikimedia\Parsoid\Utils\Title;
use Wikimedia\Parsoid\Utils\Utils;
use Wikimedia\Parsoid\Wt2Html\TT\TokenHandler;

/**
 * Token transformation manager. Individual transformations
 * implement the TokenHandler interface. The parser pipeline
 * registers individual transformers.
 *
 * See https://www.mediawiki.org/wiki/Parsoid/Token_stream_transformations
 * for more documentation.  This abstract class could eventually be
 * eliminated and the various token transforms just extend PipelineStage
 * directly.
 */
class TokenTransformManager extends PipelineStage {
	/** @var array */
	private $options = null;

	/** @var string */
	private $traceType = "";

	/** @var array */
	private $traceState = null;

	/** @var TokenHandler[] */
	private $transformers = [];

	/** @var Frame */
	private $frame;

	/**
	 * @param Env $env
	 * @param array $options
	 * @param string $stageId
	 * @param PipelineStage|null $prevStage
	 */
	public function __construct( Env $env, array $options, string $stageId, $prevStage = null ) {
		parent::__construct( $env, $prevStage );
		$this->options = $options;
		$this->traceType = 'trace/ttm:' . preg_replace( '/TokenTransform/', '', $stageId );
		$this->pipelineId = null;
		$this->frame = $env->topFrame;

		// Compute tracing state
		$this->traceState = null;
		if ( $env->hasTraceFlags() ) {
			$this->traceState = [
				'tokenTimes' => 0,
				'traceTime' => $env->hasTraceFlag( 'time' ),
				'tracer' => function ( $token, $transformer ) use ( $env ) {
					$cname = Utils::stripNamespace( get_class( $transformer ) );
					$cnameStr = $cname . str_repeat( ' ', 23 - strlen( $cname ) ) . "|";
					$env->log(
						$this->traceType, $this->pipelineId, $cnameStr,
						PHPUtils::jsonEncode( $token )
					);
				},
			];
		}
	}

	/**
	 * @return Frame
	 */
	public function getFrame(): Frame {
		return $this->frame;
	}

	/**
	 * @inheritDoc
	 */
	public function addTransformer( TokenHandler $t ): void {
		$this->transformers[] = $t;
	}

	/**
	 * Get this manager's tracing state object
	 * @return array|null
	 */
	public function getTraceState(): ?array {
		return $this->traceState;
	}

	/**
	 * Push the tokens through all the registered transformers.
	 * @inheritDoc
	 */
	public function processChunk( array $tokens ): ?array {
		// Trivial case
		if ( count( $tokens ) === 0 ) {
			return $tokens;
		}

		$startTime = null;
		if ( isset( $this->traceState['traceTime'] ) ) {
			$startTime = PHPUtils::getStartHRTime();
		}

		foreach ( $this->transformers as $transformer ) {
			if ( !$transformer->isDisabled() ) {
				if ( count( $tokens ) === 0 ) {
					break;
				}
				if ( $this->traceState ) {
					$this->traceState['transformer'] = get_class( $transformer );
				}

				$tokens = $transformer->process( $tokens );
			}
		}

		if ( isset( $this->traceState['traceTime'] ) ) {
			$this->env->bumpTimeUse( 'SyncTTM',
				( PHPUtils::getStartHRTime() - $startTime - $this->traceState['tokenTimes'] ),
				'TTM' );
		}

		return $tokens;
	}

	/**
	 * @inheritDoc
	 */
	public function resetState( array $opts ): void {
		foreach ( $this->transformers as $transformer ) {
			$transformer->resetState( $opts );
		}
	}

	/**
	 * @inheritDoc
	 */
	public function setSourceOffsets( SourceRange $so ): void {
		foreach ( $this->transformers as $transformer ) {
			$transformer->setSourceOffsets( $so );
		}
	}

	/**
	 * @inheritDoc
	 */
	public function setFrame(
		?Frame $parentFrame, ?Title $title, array $args, string $srcText
	): void {
		// now actually set up the frame
		if ( !$parentFrame ) {
			$this->frame = $this->env->topFrame->newChild(
				$title, $args, $srcText
			);
		} elseif ( !$title ) {
			$this->frame = $parentFrame->newChild(
				$parentFrame->getTitle(), $parentFrame->getArgs()->args, $srcText
			);
		} else {
			$this->frame = $parentFrame->newChild(
				$title, $args, $srcText
			);
		}
	}

	/**
	 * Process a chunk of tokens.
	 *
	 * @param array $tokens Array of tokens to process
	 * @param array|null $opts
	 * @return array Returns the array of processed tokens
	 */
	public function process( $tokens, array $opts = null ): array {
		'@phan-var array $tokens'; // @var array $tokens
		return $this->processChunk( $tokens );
	}

	/**
	 * @inheritDoc
	 */
	public function processChunkily( $input, array $opts = null ): Generator {
		if ( $this->prevStage ) {
			foreach ( $this->prevStage->processChunkily( $input, $opts ) as $chunk ) {
				'@phan-var array $chunk'; // @var array $chunk
				yield $this->processChunk( $chunk );
			}
		} else {
			yield $this->process( $input, $opts );
		}
	}
}
