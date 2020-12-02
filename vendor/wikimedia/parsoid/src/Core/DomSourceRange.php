<?php
declare( strict_types = 1 );

namespace Wikimedia\Parsoid\Core;

use Wikimedia\Assert\Assert;
use Wikimedia\Parsoid\Tokens\SourceRange;
use Wikimedia\Parsoid\Utils\PHPUtils;

/**
 * Represents a DOM source range.  That is, for a given DOM tree, gives
 * the source offset range in the original wikitext for this DOM tree,
 * as well as the opening and closing tag widths if appropriate.
 */
class DomSourceRange extends SourceRange {
	/**
	 * Opening tag width.
	 * @var int|null
	 */
	public $openWidth;

	/**
	 * Closing tag width.
	 * @var int|null
	 */
	public $closeWidth;

	/**
	 * Create a new DOM source offset.
	 * @param int|null $start The starting index (UTF-8 byte count, inclusive)
	 * @param int|null $end The ending index (UTF-8 byte count, exclusive)
	 * @param int|null $openWidth The width of the open container tag
	 * @param int|null $closeWidth The width of the close container tag
	 */
	public function __construct( ?int $start, ?int $end, ?int $openWidth, ?int $closeWidth ) {
		parent::__construct( $start, $end );
		$this->openWidth = $openWidth;
		$this->closeWidth = $closeWidth;
	}

	/**
	 * Return the substring of the given string corresponding to the
	 * inner portion of this range (that is, not including the opening
	 * and closing tag widths).
	 * @param string $str The source text string
	 * @return string
	 */
	public function innerSubstr( string $str ): string {
		return PHPUtils::safeSubstr( $str, $this->innerStart(), $this->innerLength() );
	}

	/**
	 * Return the "inner start", that is, the start offset plus the open width.
	 * @return int
	 */
	public function innerStart(): int {
		return $this->start + ( $this->openWidth ?? 0 );
	}

	/**
	 * Return the "inner end", that is, the end offset minus the close width.
	 * @return int
	 */
	public function innerEnd(): int {
		return $this->end - ( $this->closeWidth ?? 0 );
	}

	/**
	 * Return the length of this source range, excluding the open and close
	 * tag widths.
	 * @return int
	 */
	public function innerLength(): int {
		return $this->innerEnd() - $this->innerStart();
	}

	/**
	 * Return the substring of the given string corresponding to the
	 * open portion of this range.
	 * @param string $str The source text string
	 * @return string
	 */
	public function openSubstr( string $str ): string {
		return PHPUtils::safeSubstr( $str, $this->start, $this->openWidth );
	}

	/**
	 * Return the substring of the given string corresponding to the
	 * close portion of this range.
	 * @param string $str The source text string
	 * @return string
	 */
	public function closeSubstr( string $str ): string {
		return PHPUtils::safeSubstr( $str, $this->innerEnd(), $this->closeWidth );
	}

	/**
	 * Strip the tag open and close from the beginning and end of the
	 * provided string.  This is similar to `DomSourceRange::innerSubstr()`
	 * but we assume that the string before `$this->start` and after
	 * `$this->end` has already been removed. (That is, that the input
	 * is `$this->substr( $originalWikitextSource )`.)
	 *
	 * @param string $src The source text string from `$this->start`
	 *   (inclusive) to `$this->end` (exclusive).
	 * @return string
	 */
	public function stripTags( string $src ): string {
		Assert::invariant(
			strlen( $src ) === $this->length(),
			"Input string not the expected length"
		);
		return PHPUtils::safeSubstr(
			$src,
			$this->openWidth,
			-$this->closeWidth
		);
	}

	/**
	 * Return a new DOM source range shifted by $amount.
	 * @param int $amount The amount to shift by
	 * @return DomSourceRange
	 */
	public function offset( int $amount ): DomSourceRange {
		return new DomSourceRange(
			$this->start + $amount,
			$this->end + $amount,
			$this->openWidth,
			$this->closeWidth
		);
	}

	/**
	 * @return bool True if the tag widths are valid.
	 */
	public function hasValidTagWidths(): bool {
		return $this->openWidth !== null && $this->closeWidth !== null &&
			$this->openWidth >= 0 && $this->closeWidth >= 0;
	}

	/**
	 * Convert a TSR to a DSR with zero-width container open/close tags.
	 * @param SourceRange $tsr
	 * @return DomSourceRange
	 */
	public static function fromTsr( SourceRange $tsr ): DomSourceRange {
		return new DomSourceRange( $tsr->start, $tsr->end, null, null );
	}

	/**
	 * Create a new DomSourceRange from an array of integers/null (such as
	 * created during JSON serialization).
	 * @param array<int|null> $dsr
	 * @return DomSourceRange
	 */
	public static function fromArray( array $dsr ): DomSourceRange {
		Assert::invariant(
			count( $dsr ) === 2 || count( $dsr ) === 4,
			'Not enough elements in DSR array'
		);
		return new DomSourceRange(
			$dsr[0], $dsr[1], $dsr[2] ?? null, $dsr[3] ?? null
		);
	}

	/**
	 * @inheritDoc
	 */
	public function jsonSerialize(): array {
		return [ $this->start, $this->end, $this->openWidth, $this->closeWidth ];
	}
}
