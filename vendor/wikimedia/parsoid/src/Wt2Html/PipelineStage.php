<?php
declare( strict_types = 1 );

namespace Wikimedia\Parsoid\Wt2Html;

use DOMDocument;
use Generator;

use Wikimedia\Parsoid\Config\Env;
use Wikimedia\Parsoid\Tokens\SourceRange;
use Wikimedia\Parsoid\Utils\Title;
use Wikimedia\Parsoid\Wt2Html\TT\TokenHandler;

/**
 * This represents the abstract interface for a wt2html parsing pipeline stage
 * Currently there are 4 known pipeline stages:
 * - PEG Tokenizer
 * - Token Transform Manager
 * - HTML5 Tree Builder
 * - DOM Post Processor
 *
 * The Token Transform Manager could eventually go away and be directly replaced by
 * the very many token transformers that are represented by the abstract TokenHandler class.
 */

abstract class PipelineStage {
	/**
	 * Previous pipeline stage that generates input for this stage.
	 * Will be null for the first pipeline stage.
	 * @var PipelineStage
	 */
	protected $prevStage;

	/**
	 * This is primarily a debugging aid.
	 * @var int
	 */
	protected $pipelineId = -1;

	/** @var Env */
	protected $env = null;

	/**
	 * @param Env $env
	 * @param PipelineStage|null $prevStage
	 */
	public function __construct( Env $env, PipelineStage $prevStage = null ) {
		$this->env = $env;
		$this->prevStage = $prevStage;
	}

	/**
	 * @param int $id
	 */
	public function setPipelineId( int $id ): void {
		$this->pipelineId = $id;
	}

	/**
	 * @return int
	 */
	public function getPipelineId(): int {
		return $this->pipelineId;
	}

	/**
	 * @return Env
	 */
	public function getEnv(): Env {
		return $this->env;
	}

	/**
	 * Register a token transformer
	 * @param TokenHandler $t
	 */
	public function addTransformer( TokenHandler $t ): void {
		throw new \BadMethodCallException( "This pipeline stage doesn't accept token transformers." );
	}

	/**
	 * Resets any internal state for this pipeline stage.
	 * This is usually called so a cached pipeline can be reused.
	 *
	 * @param array $options
	 */
	public function resetState( array $options ): void {
		/* Default implementation: Do nothing */
	}

	/**
	 * Pass parent-frame, title and args of the new pipeline (for template expansions)
	 * to the new pipeline's stages.
	 *
	 * FIXME: This can be refactored to deal directly with the pipeline's constructor
	 * and TTM instead of exposing this on the pipeline.
	 *
	 * @param Frame|null $frame Parent pipeline frame
	 * @param Title|null $title Title (template) being processed in this (nested) pipeline
	 * @param array $args Template args for the title (template)
	 * @param string $srcText The wikitext source for this frame
	 */
	public function setFrame( ?Frame $frame, ?Title $title, array $args, string $srcText ): void {
		/* Default implementation: Do nothing */
	}

	/**
	 * Set the source offsets for the content being processing by this pipeline
	 * This matters for when a substring of the top-level page is being processed
	 * in its own pipeline. This ensures that all source offsets assigned to tokens
	 * and DOM nodes in this stage are relative to the top-level page.
	 *
	 * @param SourceRange $so
	 */
	public function setSourceOffsets( SourceRange $so ): void {
		/* Default implementation: Do nothing */
	}

	/**
	 * Process wikitext, an array of tokens, or a DOM document depending on
	 * what pipeline stage this is. This will be entirety of the input that
	 * will be processed by this pipeline stage and no further input or an EOF
	 * signal will follow.
	 *
	 * @param string|array|DOMDocument $input
	 * @param array|null $options
	 * @return array|DOMDocument
	 */
	abstract public function process( $input, array $options = null );

	/**
	 * Process wikitext, an array of tokens, or a DOM document depending on
	 * what pipeline stage this is. This method will either directly or indirectly
	 * implement a generator that parses the input in chunks and yields output
	 * in chunks as well.
	 *
	 * Implementations that don't consume tokens (ex: Tokenizer, DOMPostProcessor)
	 * will provide specialized implementations that handle their input type.
	 *
	 * @param string|array|DOMDocument $input
	 * @param array|null $options
	 */
	abstract public function processChunkily( $input, ?array $options ): Generator;
}
