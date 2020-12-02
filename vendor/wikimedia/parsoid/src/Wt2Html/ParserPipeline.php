<?php
declare( strict_types = 1 );

namespace Wikimedia\Parsoid\Wt2Html;

use DOMDocument;
use Wikimedia\Assert\Assert;
use Wikimedia\Parsoid\Config\Env;
use Wikimedia\Parsoid\Tokens\SourceRange;
use Wikimedia\Parsoid\Utils\PHPUtils;
use Wikimedia\Parsoid\Utils\Title;

/**
 * Wrap some stages into a pipeline.
 */

class ParserPipeline {
	/** @var int */
	private $id;

	/** @var string */
	private $outputType;

	/** @var string */
	private $pipelineType;

	/** @var array */
	private $stages;

	/** @var Env */
	private $env;

	/** @var String */
	private $cacheKey;

	/**
	 * @param string $type
	 * @param string $outType
	 * @param string $cacheKey
	 * @param array $stages
	 * @param Env $env
	 */
	public function __construct(
		string $type, string $outType, string $cacheKey, array $stages, Env $env
	) {
		$this->id = -1;
		$this->cacheKey = $cacheKey;
		$this->pipelineType = $type;
		$this->outputType = $outType;
		$this->stages = $stages;
		$this->env = $env;
	}

	/**
	 * @return string
	 */
	public function getCacheKey(): string {
		return $this->cacheKey;
	}

	/**
	 * Applies the function across all stages and transformers registered at
	 * each stage.
	 *
	 * @param string $fn
	 * @param mixed ...$args
	 */
	private function applyToStage( string $fn, ...$args ): void {
		// Apply to each stage
		foreach ( $this->stages as $stage ) {
			$stage->$fn( ...$args );
		}
	}

	/**
	 * This is useful for debugging.
	 *
	 * @param int $id
	 */
	public function setPipelineId( int $id ): void {
		$this->id = $id;
		$this->applyToStage( 'setPipelineId', $id );
	}

	/**
	 * Reset any local state in the pipeline stage
	 * @param array $opts
	 */
	public function resetState( array $opts = [] ): void {
		$this->applyToStage( 'resetState', $opts );
	}

	/**
	 * Set source offsets for the source that this pipeline will process.
	 *
	 * This lets us use different pipelines to parse fragments of the same page
	 * Ex: extension content (found on the same page) is parsed with a different
	 * pipeline than the top-level page.
	 *
	 * Because of this, the source offsets are not [0, page.length) always
	 * and needs to be explicitly initialized
	 *
	 * @param SourceRange $so
	 */
	public function setSourceOffsets( SourceRange $so ): void {
		$this->applyToStage( 'setSourceOffsets', $so );
	}

	/**
	 * @inheritDoc
	 */
	public function setFrame( ?Frame $frame, ?Title $title, array $args, string $srcText ): void {
		$this->applyToStage( 'setFrame', $frame, $title, $args, $srcText );
	}

	/**
	 * Process input through the pipeline (potentially skipping the first stage
	 * in case that first stage is the source of input chunks we are processing
	 * in the rest of the pipeline)
	 *
	 * @param array|string|DOMDocument $input wikitext string or array of tokens or DOMDocument
	 * @param array $opts
	 *  - sol (bool) Whether tokens should be processed in start-of-line context.
	 *  - chunky (bool) Whether we are processing the input chunkily.
	 *                  If so, the first stage will be skipped
	 * @return array|DOMDocument
	 */
	public function parse( $input, array $opts ) {
		$output = $input;
		foreach ( $this->stages as $stage ) {
			$output = $stage->process( $output, $opts );
			if ( $output === null ) {
				throw new \Exception( 'Stage ' . get_class( $stage ) . ' generated null output.' );
			}
		}

		$this->env->getPipelineFactory()->returnPipeline( $this );

		return $output;
	}

	/**
	 * Parse input in chunks
	 *
	 * @param string $input Input wikitext
	 * @param array $opts
	 * @return DOMDocument|array final DOM or array of token chnks
	 */
	public function parseChunkily( string $input, array $opts ) {
		$ret = [];
		$lastStage = PHPUtils::lastItem( $this->stages );
		foreach ( $lastStage->processChunkily( $input, $opts ) as $output ) {
			$ret[] = $output;
		}

		$this->env->getPipelineFactory()->returnPipeline( $this );

		// Return either the DOM or the array of chunks
		return $this->outputType === "DOM" ? $ret[0] : $ret;
	}

	/**
	 * Feed input to the first pipeline stage.
	 * The input is expected to be the wikitext string for the doc.
	 *
	 * @param string $input
	 * @param array|null $opts
	 * @return DOMDocument
	 */
	public function parseToplevelDoc( string $input, array $opts = null ) {
		Assert::invariant( $this->pipelineType === 'text/x-mediawiki/full',
			'You cannot process top-level document from wikitext to DOM with a pipeline of type ' .
			$this->pipelineType );

		// Disable the garbage collector in PHP 7.2 (T230861)
		if ( gc_enabled() && version_compare( PHP_VERSION, '7.3.0', '<' ) ) {
			$gcDisabled = true;
			gc_collect_cycles();
			gc_disable();
		} else {
			$gcDisabled = false;
		}

		// Reset pipeline state once per top-level doc.
		// This clears state from any per-doc global state
		// maintained across all pipelines used by the document.
		// (Ex: Cite state)
		$this->resetState( [ 'toplevel' => true ] );
		if ( empty( $this->env->startTime ) ) {
			$this->env->startTime = PHPUtils::getStartHRTime();
		}
		$this->env->log( 'trace/time', 'Starting parse at ', $this->env->startTime );

		if ( !$opts ) {
			$opts = [];
		}

		// Top-level doc parsing always start in SOL state
		$opts['sol'] = true;

		if ( !empty( $opts['chunky'] ) ) {
			$result = $this->parseChunkily( $input, $opts );
		} else {
			$result = $this->parse( $input, $opts );
		}

		if ( $gcDisabled ) {
			gc_enable();
			// There's no point running gc_collect_cycles() here, since objects
			// are not marked for collection while the GC is disabled. The root
			// buffer will be empty.
		}
		return $result;
	}
}
