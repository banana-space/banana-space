<?php

namespace Wikimedia\Parsoid\Wt2Html;


	use Wikimedia\Parsoid\Config\Env;
	use Wikimedia\Parsoid\Config\SiteConfig;
	use Wikimedia\Parsoid\Config\WikitextConstants;
	use Wikimedia\Parsoid\Core\DomSourceRange;
	use Wikimedia\Parsoid\Tokens\CommentTk;
	use Wikimedia\Parsoid\Tokens\EndTagTk;
	use Wikimedia\Parsoid\Tokens\EOFTk;
	use Wikimedia\Parsoid\Tokens\KV;
	use Wikimedia\Parsoid\Tokens\KVSourceRange;
	use Wikimedia\Parsoid\Tokens\NlTk;
	use Wikimedia\Parsoid\Tokens\SelfclosingTagTk;
	use Wikimedia\Parsoid\Tokens\SourceRange;
	use Wikimedia\Parsoid\Tokens\TagTk;
	use Wikimedia\Parsoid\Tokens\Token;
	use Wikimedia\Parsoid\Utils\TokenUtils;
	use Wikimedia\Parsoid\Utils\Utils;
	use Wikimedia\Parsoid\Utils\PHPUtils;
	use Wikimedia\Parsoid\Utils\WTUtils;


class Grammar extends \WikiPEG\PEGParserBase {
  // initializer
  
  	/** @var Env */
  	private $env;
  
  	/** @var SiteConfig */
  	private $siteConfig;
  
  	/** @var array */
  	private $pipelineOpts;
  
  	/** @var int */
  	private $pipelineOffset;
  
  	private $extTags;
  
  	protected function initialize() {
  		$this->env = $this->options['env'];
  		$this->siteConfig = $this->env->getSiteConfig();
  
  		$tokenizer = $this->options['pegTokenizer'];
  		$this->pipelineOpts = $tokenizer->getOptions();
  		$this->pipelineOffset = $this->options['pipelineOffset'] ?? 0;
  		$this->extTags = $this->siteConfig->getExtensionTagNameMap();
  	}
  
  	private $prevOffset = 0;
  	private $headingIndex = 0;
  
  	private function assert( $condition, $text ) {
  		if ( !$condition ) {
  			throw new \Exception( "Grammar.pegphp assertion failure: $text" );
  		}
  	}
  
  	private function unreachable() {
  		throw new \Exception( "Grammar.pegphp: this should be unreachable" );
  	}
  
  	// Some shorthands for legibility
  	private function startOffset() {
  		return $this->savedPos;
  	}
  
  	private function endOffset() {
  		return $this->currPos;
  	}
  
  	private function tsrOffsets( $flag = 'default' ): SourceRange {
  		switch ( $flag ) {
              case 'start':
                  return new SourceRange( $this->savedPos, $this->savedPos );
              case 'end':
                  return new SourceRange( $this->currPos, $this->currPos );
              default:
                  return new SourceRange( $this->savedPos, $this->currPos );
          }
  	}
  
  	/*
  	 * Emit a chunk of tokens to our consumers.  Once this has been done, the
  	 * current expression can return an empty list (true).
  	 */
  	private function emitChunk( $tokens ) {
  		// Shift tsr of all tokens by the pipeline offset
  		TokenUtils::shiftTokenTSR( $tokens, $this->pipelineOffset );
  		$this->env->log( 'trace/peg', $this->options['pipelineId'] ?? '0', '---->   ', $tokens );
  
  		$i = null;
  		$n = count( $tokens );
  
  		// Enforce parsing resource limits
  		for ( $i = 0;  $i < $n;  $i++ ) {
  			TokenizerUtils::enforceParserResourceLimits( $this->env, $tokens[ $i ] );
  		}
  
  		return $tokens;
  	}
  
  	/* ------------------------------------------------------------------------
  	 * Extension tags should be parsed with higher priority than anything else.
  	 *
  	 * The trick we use is to strip out the content inside a matching tag-pair
  	 * and not tokenize it. The content, if it needs to parsed (for example,
  	 * for <ref>, <*include*> tags), is parsed in a fresh tokenizer context
  	 * which means any error correction that needs to happen is restricted to
  	 * the scope of the extension content and doesn't spill over to the higher
  	 * level.  Ex: <math><!--foo</math>.
  	 *
  	 * IGNORE: {{ this just balances the blocks in this comment for pegjs
  	 *
  	 * This trick also lets us prevent extension content (that don't accept WT)
  	 * from being parsed as wikitext (Ex: <math>\frac{foo\frac{bar}}</math>)
  	 * We don't want the "}}" being treated as a template closing tag and
  	 * closing outer templates.
  	 * --------------------------------------------------------------------- */
  
  	private function isXMLTag( string $name, bool $block ): bool {
  		$lName = mb_strtolower( $name );
  		return $block ?
  			TokenUtils::isBlockTag( $lName ) :
  			isset( WikitextConstants::$HTML['HTML5Tags'][$lName] )
  			|| isset( WikitextConstants::$HTML['OlderHTMLTags'][$lName] );
  	}
  
  	private function isExtTag( string $name ): bool {
  		$lName = mb_strtolower( $name );
  		$isInstalledExt = isset( $this->extTags[$lName] );
  		$isIncludeTag = TokenizerUtils::isIncludeTag( $lName );
  		return $isInstalledExt || $isIncludeTag;
  	}
  
  	private function maybeExtensionTag( Token $t ) {
  		$tagName = mb_strtolower( $t->getName() );
  
  		$isInstalledExt = isset( $this->extTags[$tagName] );
  		$isIncludeTag = TokenizerUtils::isIncludeTag( $tagName );
  
  		// Extensions have higher precedence when they shadow html tags.
  		if ( !( $isInstalledExt || $isIncludeTag ) ) {
  			return $t;
  		}
  
  		$dp = $t->dataAttribs;
  		$skipPos = $this->currPos;
  
  		switch ( get_class( $t ) ) {
  			case EndTagTk::class:
  				if ( $isIncludeTag ) {
  					return $t;
  				}
  				// Similar to TagTk, we rely on the sanitizer to convert to text
  				// where necessary and emit tokens to ease the wikitext escaping
  				// code.  However, extension tags that shadow html tags will see
  				// their unmatched end tags dropped while tree building, since
  				// the sanitizer will let them through.
  				return $t; // not text()
  
  			case SelfclosingTagTk::class:
  				$dp->src = $dp->tsr->substr( $this->input );
  				$dp->extTagOffsets = new DomSourceRange(
  					$dp->tsr->start, $dp->tsr->end,
  					$dp->tsr->length(), 0
  				);
  				if ( $isIncludeTag ) {
  					return $t;
  				}
  				break;
  
  			case TagTk::class:
  				$endTagRE = '~.*?(</\s*' . preg_quote( $tagName, '~' ) . '\s*>)~iusA';
  				$tagContentFound = preg_match( $endTagRE, $this->input, $tagContent, 0, $dp->tsr->start );
  
  				if ( !$tagContentFound ) {
  					$dp->src = $dp->tsr->substr( $this->input );
  					$dp->extTagOffsets = new DomSourceRange(
  						$dp->tsr->start, $dp->tsr->end,
  						$dp->tsr->length(), 0
  					);
  					if ( $isIncludeTag ) {
  						return $t;
  					} else {
  						// This is undefined behaviour.  The old parser currently
  						// returns text here (see core commit 674e8388cba),
  						// whereas this results in unclosed
  						// extension tags that shadow html tags falling back to
  						// their html equivalent.  The sanitizer will take care
  						// of converting to text where necessary.  We do this to
  						// simplify `hasWikitextTokens` when escaping wikitext,
  						// which wants these as tokens because it's otherwise
  						// lacking in context.
  						return $t; // not text()
  					}
  				}
  
  				$extSrc = $tagContent[0];
  				$extEndOffset = $dp->tsr->start + strlen( $extSrc );
  				$extEndTagWidth = strlen( $tagContent[1] );
  
  				if ( !empty( $this->pipelineOpts['inTemplate'] ) ) {
  					// Support 1-level of nesting in extensions tags while
  					// tokenizing in templates to support the #tag parser function.
  					//
  					// It's necessary to permit this broadly in templates because
  					// there's no way to distinguish whether the nesting happened
  					// while expanding the #tag parser function, or just a general
  					// syntax errors.  In other words,
  					//
  					//   hi<ref>ho<ref>hi</ref>ho</ref>
  					//
  					// and
  					//
  					//   hi{{#tag:ref|ho<ref>hi</ref>ho}}
  					//
  					// found in template are returned indistinguishably after a
  					// preprocessing request, though the old parser renders them
  					// differently.  #tag in template is probably a common enough
  					// use case that we want to accept these false positives,
  					// though another approach could be to drop this code here, and
  					// invoke a native #tag handler and forgo those in templates.
  					//
  					// Expand `extSrc` as long as there is a <tagName> found in the
  					// extension source body.
  					$startTagRE = '~<' . preg_quote( $tagName, '~' ) . '[^/<>]*>~i';
  					$s = substr( $extSrc, $dp->tsr->end - $dp->tsr->start );
  					while ( strlen( $s ) ) {
  						if ( !preg_match( $startTagRE, $s ) ) {
  							break;
  						}
  						if ( !preg_match( $endTagRE, $this->input, $tagContent, 0, $extEndOffset ) ) {
  							break;
  						}
  						$s = $tagContent[0];
  						$extEndOffset += strlen( $s );
  						$extEndTagWidth = strlen( $tagContent[1] );
  						$extSrc .= $s;
  					}
  				}
  
  				// Extension content source
  				$dp->src = $extSrc;
  				$dp->extTagOffsets = new DomSourceRange(
  					$dp->tsr->start, $extEndOffset,
  					$dp->tsr->length(), $extEndTagWidth
  				);
  
  				$skipPos = $dp->extTagOffsets->innerEnd();
  
  				// If the xml-tag is a known installed (not native) extension,
  				// skip the end-tag as well.
  				if ( $isInstalledExt ) {
  					$skipPos = $dp->extTagOffsets->end;
  				}
  				break;
  
  			default:
  				$this->unreachable();
  		}
  
  		$this->currPos = $skipPos;
  
  		if ( $isInstalledExt ) {
  			// update tsr->end to span the start and end tags.
  			$dp->tsr->end = $this->endOffset(); // was just modified above
  			return new SelfclosingTagTk( 'extension', [
  					new KV( 'typeof', 'mw:Extension' ),
  					new KV( 'name', $tagName ),
  					new KV( 'about', $this->env->newAboutId() ),
  					new KV( 'source', $dp->src ),
  					new KV( 'options', $t->attribs )
  				], $dp
  			);
  		} elseif ( $isIncludeTag ) {
  			// Parse ext-content, strip eof, and shift tsr
  			$extContent = $dp->extTagOffsets->stripTags( $dp->src );
  			$tokenizer = new PegTokenizer( $this->env );
  			$tokenizer->setSourceOffsets( new SourceRange( $dp->extTagOffsets->innerStart(), $dp->extTagOffsets->innerEnd() ) );
  			$extContentToks = $tokenizer->tokenizeSync( $extContent );
  			if ( $dp->extTagOffsets->closeWidth > 0 ) {
  				TokenUtils::stripEOFTkFromTokens( $extContentToks );
  			}
  			array_unshift( $extContentToks, $t );
  			return $extContentToks;
  		} else {
  			$this->unreachable();
  		}
  	}
  

  // cache init
    protected $cache = [];

  // expectations
  protected $expectations = [
    0 => ["type" => "end", "description" => "end of input"],
    1 => ["type" => "other", "description" => "start"],
    2 => ["type" => "other", "description" => "table_start_tag"],
    3 => ["type" => "class", "value" => "['{]", "description" => "['{]"],
    4 => ["type" => "literal", "value" => "&", "description" => "\"&\""],
    5 => ["type" => "other", "description" => "table_attributes"],
    6 => ["type" => "other", "description" => "generic_newline_attributes"],
    7 => ["type" => "any", "description" => "any character"],
    8 => ["type" => "other", "description" => "extlink"],
    9 => ["type" => "other", "description" => "tlb"],
    10 => ["type" => "class", "value" => "[ \\t]", "description" => "[ \\t]"],
    11 => ["type" => "literal", "value" => "<!--", "description" => "\"<!--\""],
    12 => ["type" => "literal", "value" => "-->", "description" => "\"-->\""],
    13 => ["type" => "literal", "value" => "|", "description" => "\"|\""],
    14 => ["type" => "literal", "value" => "{{!}}", "description" => "\"{{!}}\""],
    15 => ["type" => "literal", "value" => "//", "description" => "\"//\""],
    16 => ["type" => "class", "value" => "[A-Za-z]", "description" => "[A-Za-z]"],
    17 => ["type" => "class", "value" => "[-A-Za-z0-9+.]", "description" => "[-A-Za-z0-9+.]"],
    18 => ["type" => "literal", "value" => ":", "description" => "\":\""],
    19 => ["type" => "literal", "value" => "[", "description" => "\"[\""],
    20 => ["type" => "class", "value" => "[0-9A-Fa-f:.]", "description" => "[0-9A-Fa-f:.]"],
    21 => ["type" => "literal", "value" => "]", "description" => "\"]\""],
    22 => ["type" => "class", "value" => "[^ \\]\\[\\r\\n\"'<>\\x00-\\x20\\x7f&\\u00A0\\u1680\\u180E\\u2000-\\u200A\\u202F\\u205F\\u3000{]", "description" => "[^ \\]\\[\\r\\n\"'<>\\x00-\\x20\\x7f&\\u00A0\\u1680\\u180E\\u2000-\\u200A\\u202F\\u205F\\u3000{]"],
    23 => ["type" => "literal", "value" => "=", "description" => "\"=\""],
    24 => ["type" => "class", "value" => "[\\0/=>]", "description" => "[\\0/=>]"],
    25 => ["type" => "class", "value" => "[ \\u00A0\\u1680\\u2000-\\u200A\\u202F\\u205F\\u3000]", "description" => "[ \\u00A0\\u1680\\u2000-\\u200A\\u202F\\u205F\\u3000]"],
    26 => ["type" => "literal", "value" => "\x0a", "description" => "\"\\n\""],
    27 => ["type" => "literal", "value" => "\x0d\x0a", "description" => "\"\\r\\n\""],
    28 => ["type" => "literal", "value" => "{", "description" => "\"{\""],
    29 => ["type" => "class", "value" => "[#0-9a-zA-Z]", "description" => "[#0-9a-zA-Z]"],
    30 => ["type" => "literal", "value" => ";", "description" => "\";\""],
    31 => ["type" => "class", "value" => "[\"'=]", "description" => "[\"'=]"],
    32 => ["type" => "class", "value" => "[^ \\t\\r\\n\\0/=><&{}\\-!|\\[]", "description" => "[^ \\t\\r\\n\\0/=><&{}\\-!|\\[]"],
    33 => ["type" => "literal", "value" => "'", "description" => "\"'\""],
    34 => ["type" => "literal", "value" => "\"", "description" => "\"\\\"\""],
    35 => ["type" => "literal", "value" => "/", "description" => "\"/\""],
    36 => ["type" => "class", "value" => "[^ \\t\\r\\n\\0/=><&{}\\-!|]", "description" => "[^ \\t\\r\\n\\0/=><&{}\\-!|]"],
    37 => ["type" => "class", "value" => "[ \\t\\n\\r\\x0c]", "description" => "[ \\t\\n\\r\\x0c]"],
    38 => ["type" => "class", "value" => "[^<[{\\n\\r|!\\]}\\-\\t&=\"' \\u00A0\\u1680\\u180E\\u2000-\\u200A\\u202F\\u205F\\u3000]", "description" => "[^<[{\\n\\r|!\\]}\\-\\t&=\"' \\u00A0\\u1680\\u180E\\u2000-\\u200A\\u202F\\u205F\\u3000]"],
    39 => ["type" => "class", "value" => "[&|{\\-!}=]", "description" => "[&|{\\-!}=]"],
    40 => ["type" => "class", "value" => "[']", "description" => "[']"],
    41 => ["type" => "class", "value" => "[^-'<[{\\n\\r:;\\]}|!=]", "description" => "[^-'<[{\\n\\r:;\\]}|!=]"],
    42 => ["type" => "literal", "value" => "[[", "description" => "\"[[\""],
    43 => ["type" => "literal", "value" => "<", "description" => "\"<\""],
    44 => ["type" => "literal", "value" => "{{", "description" => "\"{{\""],
    45 => ["type" => "class", "value" => "[^{}&<\\-!\\['\\r\\n|]", "description" => "[^{}&<\\-!\\['\\r\\n|]"],
    46 => ["type" => "class", "value" => "[{}&<\\-!\\[]", "description" => "[{}&<\\-!\\[]"],
    47 => ["type" => "class", "value" => "[^{}&<\\-!\\[\"\\r\\n|]", "description" => "[^{}&<\\-!\\[\"\\r\\n|]"],
    48 => ["type" => "class", "value" => "[^{}&<\\-!\\[ \\t\\n\\r\\x0c|]", "description" => "[^{}&<\\-!\\[ \\t\\n\\r\\x0c|]"],
    49 => ["type" => "class", "value" => "[^{}&<\\-|/'>]", "description" => "[^{}&<\\-|/'>]"],
    50 => ["type" => "class", "value" => "[{}&\\-|/]", "description" => "[{}&\\-|/]"],
    51 => ["type" => "class", "value" => "[^{}&<\\-|/\">]", "description" => "[^{}&<\\-|/\">]"],
    52 => ["type" => "class", "value" => "[^{}&<\\-|/ \\t\\n\\r\\x0c>]", "description" => "[^{}&<\\-|/ \\t\\n\\r\\x0c>]"],
    53 => ["type" => "literal", "value" => "__", "description" => "\"__\""],
    54 => ["type" => "literal", "value" => "-", "description" => "\"-\""],
    55 => ["type" => "literal", "value" => "''", "description" => "\"''\""],
    56 => ["type" => "class", "value" => "[ \\t\\n\\r\\0\\x0b]", "description" => "[ \\t\\n\\r\\0\\x0b]"],
    57 => ["type" => "literal", "value" => "----", "description" => "\"----\""],
    58 => ["type" => "literal", "value" => ">", "description" => "\">\""],
    59 => ["type" => "literal", "value" => "{{{", "description" => "\"{{{\""],
    60 => ["type" => "literal", "value" => "}}}", "description" => "\"}}}\""],
    61 => ["type" => "literal", "value" => "}}", "description" => "\"}}\""],
    62 => ["type" => "literal", "value" => "]]", "description" => "\"]]\""],
    63 => ["type" => "literal", "value" => "RFC", "description" => "\"RFC\""],
    64 => ["type" => "literal", "value" => "PMID", "description" => "\"PMID\""],
    65 => ["type" => "class", "value" => "[0-9]", "description" => "[0-9]"],
    66 => ["type" => "literal", "value" => "ISBN", "description" => "\"ISBN\""],
    67 => ["type" => "class", "value" => "[xX]", "description" => "[xX]"],
    68 => ["type" => "class", "value" => "[\\n\\r\\t ]", "description" => "[\\n\\r\\t ]"],
    69 => ["type" => "literal", "value" => "}", "description" => "\"}\""],
    70 => ["type" => "class", "value" => "[^<[{\\n\\r\\t|!\\]}{ &\\-]", "description" => "[^<[{\\n\\r\\t|!\\]}{ &\\-]"],
    71 => ["type" => "class", "value" => "[!<\\-\\}\\]\\n\\r]", "description" => "[!<\\-\\}\\]\\n\\r]"],
    72 => ["type" => "literal", "value" => "-{", "description" => "\"-{\""],
    73 => ["type" => "literal", "value" => "}-", "description" => "\"}-\""],
    74 => ["type" => "class", "value" => "[*#:;]", "description" => "[*#:;]"],
    75 => ["type" => "literal", "value" => "+", "description" => "\"+\""],
    76 => ["type" => "class", "value" => "[^\\t\\n\\v />\\0]", "description" => "[^\\t\\n\\v />\\0]"],
    77 => ["type" => "literal", "value" => "!", "description" => "\"!\""],
    78 => ["type" => "literal", "value" => "!!", "description" => "\"!!\""],
    79 => ["type" => "literal", "value" => "=>", "description" => "\"=>\""],
    80 => ["type" => "literal", "value" => "||", "description" => "\"||\""],
    81 => ["type" => "literal", "value" => "{{!}}{{!}}", "description" => "\"{{!}}{{!}}\""],
    82 => ["type" => "class", "value" => "[-+A-Z]", "description" => "[-+A-Z]"],
    83 => ["type" => "class", "value" => "[^{}|;]", "description" => "[^{}|;]"],
    84 => ["type" => "class", "value" => "[a-z]", "description" => "[a-z]"],
    85 => ["type" => "class", "value" => "[-a-zA-Z]", "description" => "[-a-zA-Z]"],
  ];

  // actions
  private function a0() {
  
  			// "tlb" matches "block" matches "sol" matches "newlineToken"
  			// But, "tlb" is prefixed with a !eof clause, so, we should only
  			// get here on eof. So, safe to unconditionally terminate the
  			// generator loop here.
  			return false;
  		
  }
  private function a1($t, $n) {
  
  		if ( count( $t ) ) {
  			$ret = TokenizerUtils::flattenIfArray( $t );
  		} else {
  			$ret = [];
  		}
  		if ( count( $n ) ) {
  			PHPUtils::pushArray($ret, $n);
  		}
  		$ret[] = new EOFTk();
  		return $ret;
  	
  }
  private function a2($sc) {
   return $this->endOffset(); 
  }
  private function a3($sc, $startPos, $b, $p) {
   $this->unreachable(); 
  }
  private function a4($sc, $startPos, $b, $p, $ta) {
   return $this->endOffset(); 
  }
  private function a5($sc, $startPos, $b, $p, $ta, $tsEndPos, $s2) {
  
  		$coms = TokenizerUtils::popComments( $ta );
  		if ( $coms ) {
  			$tsEndPos = $coms['commentStartPos'];
  		}
  
  		$da = (object)[ 'tsr' => new SourceRange( $startPos, $tsEndPos ) ];
  		if ( $p !== '|' ) {
  			// Variation from default
  			$da->startTagSrc = $b . $p;
  		}
  
  		return array_merge( $sc,
  			[ new TagTk( 'table', $ta, $da ) ],
  			$coms ? $coms['buf'] : [],
  			$s2 );
  	
  }
  private function a6($proto, $addr, $he) {
   return $he; 
  }
  private function a7($proto, $addr, $r) {
   return $r; 
  }
  private function a8($proto, $addr, $c) {
   return $c; 
  }
  private function a9($proto, $addr, $path) {
   return $addr !== '' || count( $path ) > 0; 
  }
  private function a10($proto, $addr, $path) {
  
  		return TokenizerUtils::flattenString( array_merge( [ $proto, $addr ], $path ) );
  	
  }
  private function a11($as, $s, $p) {
  
  		return [ $as, $s, $p ];
  	
  }
  private function a12($b) {
   return $b; 
  }
  private function a13($r) {
   return TokenizerUtils::flattenIfArray( $r ); 
  }
  private function a14() {
   return $this->endOffset(); 
  }
  private function a15($p0, $addr, $target) {
   return $this->endOffset(); 
  }
  private function a16($p0, $addr, $target, $p1) {
  
  			// Protocol must be valid and there ought to be at least one
  			// post-protocol character.  So strip last char off target
  			// before testing protocol.
  			$flat = TokenizerUtils::flattenString( [ $addr, $target ] );
  			if ( is_array( $flat ) ) {
  				// There are templates present, alas.
  				return count( $flat ) > 0;
  			}
  			return Utils::isProtocolValid( substr( $flat, 0, -1 ), $this->env );
  		
  }
  private function a17($p0, $addr, $target, $p1, $sp) {
   return $this->endOffset(); 
  }
  private function a18($p0, $addr, $target, $p1, $sp, $p2, $content) {
   return $this->endOffset(); 
  }
  private function a19($p0, $addr, $target, $p1, $sp, $p2, $content, $p3) {
  
  			$tsr1 = new SourceRange( $p0, $p1 );
  			$tsr2 = new SourceRange( $p2, $p3 );
  			return [
  				new SelfclosingTagTk( 'extlink', [
  						new KV( 'href', TokenizerUtils::flattenString( [ $addr, $target ] ), $tsr1->expandTsrV() ),
  						new KV( 'mw:content', $content ?? '', $tsr2->expandTsrV() ),
  						new KV( 'spaces', $sp )
  					], (object)[
  						'tsr' => $this->tsrOffsets(),
  						'extLinkContentOffsets' => $tsr2,
  					]
  				)
  			]; 
  }
  private function a20($r) {
   return $r; 
  }
  private function a21($b) {
  
  		// Clear the tokenizer's backtracking cache after matching each
  		// toplevelblock. There won't be any backtracking as a document is just a
  		// sequence of toplevelblocks, so the cache for previous toplevelblocks
  		// will never be needed.
  		$end = $this->startOffset();
  		for ( ;  $this->prevOffset < $end;  $this->prevOffset++ ) {
  			unset( $this->cache[$this->prevOffset] );
  		}
  
  		$tokens = null;
  		if ( is_array( $b ) && count( $b ) ) {
  			$tokens = TokenizerUtils::flattenIfArray( $b );
  		} elseif ( is_string( $b ) ) {
  			$tokens = [ $b ];
  		}
  
  		// Emit tokens for this toplevelblock. This feeds a chunk to the parser pipeline.
  		return $this->emitChunk( $tokens );
  	
  }
  private function a22() {
   return [ new NlTk( $this->tsrOffsets() ) ]; 
  }
  private function a23($c) {
  
  		$data = WTUtils::encodeComment( $c );
  		return [ new CommentTk( $data, (object)[ 'tsr' => $this->tsrOffsets() ] ) ];
  	
  }
  private function a24($p) {
   return Utils::isProtocolValid( $p, $this->env ); 
  }
  private function a25($p) {
   return $p; 
  }
  private function a26($extTag, $h, $extlink, $templatedepth, &$preproc, $equal, $table, $templateArg, $tableCellArg, $semicolon, $arrow, $linkdesc, $colon, &$th) {
  
  			return TokenizerUtils::inlineBreaks( $this->input, $this->endOffset(), [
  				'extTag' => $extTag,
  				'h' => $h,
  				'extlink' => $extlink,
  				'templatedepth' => $templatedepth,
  				'preproc' => $preproc,
  				'equal' => $equal,
  				'table' => $table,
  				'templateArg' => $templateArg,
  				'tableCellArg' => $tableCellArg,
  				'semicolon' => $semicolon,
  				'arrow' => $arrow,
  				'linkdesc' => $linkdesc,
  				'colon' => $colon,
  				'th' => $th
  			] );
  		
  }
  private function a27($templatedepth) {
  
  		// Refuse to recurse beyond `maxDepth` levels. Default in the old parser
  		// is $wgMaxTemplateDepth = 40; This is to prevent crashing from
  		// buggy wikitext with lots of unclosed template calls, as in
  		// eswiki/Usuario:C%C3%A1rdenas/PRUEBAS?oldid=651094
  		return $templatedepth + 1 < $this->siteConfig->getMaxTemplateDepth();
  	
  }
  private function a28($templatedepth, $t) {
  
  		return $t;
  	
  }
  private function a29($cc) {
  
  		// if this is an invalid entity, don't tag it with 'mw:Entity'
  		if ( mb_strlen( $cc ) > 1 /* decoded entity would be 1 character */ ) {
  			return $cc;
  		}
  		return [
  			// If this changes, the nowiki extension's toDOM will need to follow suit
  			new TagTk( 'span', [ new KV( 'typeof', 'mw:Entity' ) ],
  				(object)[ 'src' => $this->text(), 'srcContent' => $cc, 'tsr' => $this->tsrOffsets( 'start' ) ] ),
  			$cc,
  			new EndTagTk( 'span', [], (object)[ 'tsr' => $this->tsrOffsets( 'end' ) ] )
  		];
  	
  }
  private function a30($s) {
   return $this->endOffset(); 
  }
  private function a31($s, $namePos0, $name) {
   return $this->endOffset(); 
  }
  private function a32($s, $namePos0, $name, $namePos, $v) {
   return $v; 
  }
  private function a33($s, $namePos0, $name, $namePos, $vd) {
  
  	// NB: Keep in sync w/ generic_newline_attribute
  	$res = null;
  	// Encapsulate protected attributes.
  	if ( gettype( $name ) === 'string' ) {
  		$name = TokenizerUtils::protectAttrs( $name );
  	}
  	$nameSO = new SourceRange( $namePos0, $namePos );
  	if ( $vd !== null ) {
  		$res = new KV( $name, $vd['value'], $nameSO->join( $vd['srcOffsets'] ) );
  		$res->vsrc = $vd['srcOffsets']->substr( $this->input );
  	} else {
  		$res = new KV( $name, '', $nameSO->expandTsrK() );
  	}
  	if ( is_array( $name ) ) {
  		$res->ksrc = $nameSO->substr( $this->input );
  	}
  	return $res;
  
  }
  private function a34($s) {
  
  		if ( $s !== '' ) {
  			return [ $s ];
  		} else {
  			return [];
  		}
  	
  }
  private function a35($c) {
   return new KV( $c, '' ); 
  }
  private function a36($namePos0, $name) {
   return $this->endOffset(); 
  }
  private function a37($namePos0, $name, $namePos, $v) {
   return $v; 
  }
  private function a38($namePos0, $name, $namePos, $vd) {
  
  	// NB: Keep in sync w/ table_attibute
  	$res = null;
  	// Encapsulate protected attributes.
  	if ( is_string( $name ) ) {
  		$name = TokenizerUtils::protectAttrs( $name );
  	}
  	$nameSO = new SourceRange( $namePos0, $namePos );
  	if ( $vd !== null ) {
  		$res = new KV( $name, $vd['value'], $nameSO->join( $vd['srcOffsets'] ) );
  		$res->vsrc = $vd['srcOffsets']->substr( $this->input );
  	} else {
  		$res = new KV( $name, '', $nameSO->expandTsrK() );
  	}
  	if ( is_array( $name ) ) {
  		$res->ksrc = $nameSO->substr( $this->input );
  	}
  	return $res;
  
  }
  private function a39($s) {
   return $s; 
  }
  private function a40($c) {
  
  		return TokenizerUtils::flattenStringlist( $c );
  	
  }
  private function a41() {
   return $this->endOffset() === $this->inputLength; 
  }
  private function a42($r, $cil, $bl) {
  
  		return array_merge( [ $r ], $cil, $bl ?: [] );
  	
  }
  private function a43($c) {
   return $c; 
  }
  private function a44($rs) {
   return $rs; 
  }
  private function a45($a) {
   return $a; 
  }
  private function a46($a, $b) {
   return [ $a, $b ]; 
  }
  private function a47($m) {
  
  		return Utils::decodeWtEntities( $m );
  	
  }
  private function a48($q, $ill) {
   return $ill; 
  }
  private function a49($q, $t) {
   return $t; 
  }
  private function a50($q, $r) {
   return count( $r ) > 0 || $q !== ''; 
  }
  private function a51($q, $r) {
  
  		array_unshift( $r, $q );
  		return TokenizerUtils::flattenString( $r );
  	
  }
  private function a52($s, $t, $q) {
  
  		return TokenizerUtils::getAttrVal( $t, $this->startOffset() + strlen( $s ), $this->endOffset() - strlen( $q ) );
  	
  }
  private function a53($s, $t) {
  
  		return TokenizerUtils::getAttrVal( $t, $this->startOffset() + strlen( $s ), $this->endOffset() );
  	
  }
  private function a54($r) {
  
  		return TokenizerUtils::flattenString( $r );
  	
  }
  private function a55($al) {
   return $al; 
  }
  private function a56($he) {
   return $he; 
  }
  private function a57($bs) {
   return $bs; 
  }
  private function a58() {
   return $this->endOffset() === 0 && !$this->pipelineOffset; 
  }
  private function a59($rw, $sp, $c, $wl) {
  
  		return count( $wl ) === 1 && $wl[0] instanceof Token;
  	
  }
  private function a60($rw, $sp, $c, $wl) {
  
  		$link = $wl[0];
  		if ( $sp ) {
  			$rw .= $sp;
  		}
  		if ( $c ) {
  			$rw .= $c;
  		}
  		// Build a redirect token
  		$redirect = new SelfclosingTagTk( 'mw:redirect',
  			// Put 'href' into attributes so it gets template-expanded
  			[ $link->getAttributeKV( 'href' ) ],
  			(object)[
  				'src' => $rw,
  				'tsr' => $this->tsrOffsets(),
  				'linkTk' => $link
  			]
  		);
  		return $redirect;
  	
  }
  private function a61($st, $tl) {
   return $tl; 
  }
  private function a62($st, $bt, $stl) {
   return array_merge( $bt, $stl ); 
  }
  private function a63($st, $bts) {
   return $bts; 
  }
  private function a64($st, $r) {
  
  		return array_merge( $st, $r );
  	
  }
  private function a65($s, $os, $so) {
   return array_merge( $os, $so ); 
  }
  private function a66($s, $s2, $bl) {
  
  		return array_merge( $s, $s2 ?: [], is_array( $bl ) ? $bl : [ $bl ] );
  	
  }
  private function a67($tag) {
   return $tag; 
  }
  private function a68($s1, $s2, $c) {
  
  		return array_merge( $s1, $s2, $c );
  	
  }
  private function a69(&$preproc, $t) {
  
  		$preproc = null;
  		return $t;
  	
  }
  private function a70($v) {
   return $v; 
  }
  private function a71($e) {
   return $e; 
  }
  private function a72() {
   return Utils::isUniWord(Utils::lastUniChar( $this->input, $this->endOffset() ) ); 
  }
  private function a73($bs) {
  
  		if ( $this->siteConfig->isMagicWord( $bs ) ) {
  			return [
  				new SelfclosingTagTk( 'behavior-switch', [ new KV( 'word', $bs ) ],
  					(object)[ 'tsr' => $this->tsrOffsets(), 'src' => $bs, 'magicSrc' => $bs ]
  				)
  			];
  		} else {
  			return [ $bs ];
  		}
  	
  }
  private function a74($quotes) {
  
  		// sequences of four or more than five quotes are assumed to start
  		// with some number of plain-text apostrophes.
  		$plainticks = 0;
  		$result = [];
  		if ( strlen( $quotes ) === 4 ) {
  			$plainticks = 1;
  		} elseif ( strlen( $quotes ) > 5 ) {
  			$plainticks = strlen( $quotes ) - 5;
  		}
  		if ( $plainticks > 0 ) {
  			$result[] = substr( $quotes, 0, $plainticks );
  		}
  		// mw-quote token will be consumed in token transforms
  		$tsr = $this->tsrOffsets();
  		$tsr->start += $plainticks;
  		$mwq = new SelfclosingTagTk( 'mw-quote',
  			[ new KV( 'value', substr( $quotes, $plainticks ) ) ],
  			(object)[ 'tsr' => $tsr ] );
  		if ( strlen( $quotes ) > 2 ) {
  			$mwq->addAttribute( 'isSpace_1', $tsr->start > 0 && substr( $this->input, $tsr->start - 1, 1 ) === ' ');
  			$mwq->addAttribute( 'isSpace_2', $tsr->start > 1 && substr( $this->input, $tsr->start - 2, 1 ) === ' ');
  		}
  		$result[] = $mwq;
  		return $result;
  	
  }
  private function a75($rw) {
  
  			return preg_match( $this->env->getSiteConfig()->getMagicWordMatcher( 'redirect' ), $rw );
  		
  }
  private function a76($il, $sol_il) {
  
  		$il = $il[0];
  		$lname = mb_strtolower( $il->getName() );
  		if ( !TokenizerUtils::isIncludeTag( $lname ) ) { return false;  }
  		// Preserve SOL where necessary (for onlyinclude and noinclude)
  		// Note that this only works because we encounter <*include*> tags in
  		// the toplevel content and we rely on the php preprocessor to expand
  		// templates, so we shouldn't ever be tokenizing inInclude.
  		// Last line should be empty (except for comments)
  		if ( $lname !== 'includeonly' && $sol_il && $il instanceof TagTk ) {
  			$dp = $il->dataAttribs;
  			$inclContent = $dp->extTagOffsets->stripTags( $dp->src );
  			$nlpos = strrpos( $inclContent, "\n" );
  			$last = $nlpos === false ? $inclContent : substr( $inclContent, $nlpos + 1 );
  			if ( !preg_match( '/^(<!--([^-]|-(?!->))*-->)*$/D', $last ) ) {
  				return false;
  			}
  		}
  		return true;
  	
  }
  private function a77($il, $sol_il) {
  
  		return $il;
  	
  }
  private function a78($s, $ill) {
   return $ill ?: []; 
  }
  private function a79($s, $ce) {
   return $ce || strlen( $s ) > 2; 
  }
  private function a80($s, $ce) {
   return $this->endOffset(); 
  }
  private function a81($s, $ce, $endTPos, $spc) {
  
  			$c = null;
  			$e = null;
  			$level = null;
  			if ( $ce ) {
  				$c = $ce[0];
  				$e = $ce[1];
  				$level = min( strlen( $s ), strlen( $e ) );
  			} else {
  				// split up equal signs into two equal parts, with at least
  				// one character in the middle.
  				$level = (int)floor( ( strlen( $s ) - 1 ) / 2 );
  				$c = [ str_repeat( '=', strlen( $s ) - 2 * $level ) ];
  				$s = $e = str_repeat( '=', $level );
  			}
  			$level = min( 6, $level );
  			// convert surplus equals into text
  			if ( strlen( $s ) > $level ) {
  				$extras1 = substr( $s, 0, strlen( $s ) - $level );
  				if ( is_string( $c[0] ) ) {
  					$c[0] = $extras1 . $c[0];
  				} else {
  					array_unshift( $c, $extras1 );
  				}
  			}
  			if ( strlen( $e ) > $level ) {
  				$extras2 = substr( $e, 0, strlen( $e ) - $level );
  				$lastElem = PHPUtils::lastItem( $c );
  				if ( is_string( $lastElem ) ) {
  					$c[count( $c ) - 1] .= $extras2;
  				} else {
  					$c[] = $extras2;
  				}
  			}
  
  			$tsr = $this->tsrOffsets( 'start' );
  			$tsr->end += $level;
  			// Match the old parser's behavior by (a) making headingIndex part of tokenizer
  			// state(don't reuse pipeline!) and (b) assigning the index when
  			// ==*== is tokenized, even if we're inside a template argument
  			// or other context which won't end up putting the heading
  			// on the output page.  T213468/T214538
  			$this->headingIndex++;
  			return array_merge(
  				[ new TagTk( 'h' . $level, [], (object)[
  					'tsr' => $tsr,
  					'tmp' => (object)[ 'headingIndex' => $this->headingIndex ]
  				] ) ],
  				$c,
  				[
  					new EndTagTk( 'h' . $level, [], (object)[
  						'tsr' => new SourceRange( $endTPos - $level, $endTPos ),
  					] ),
  					$spc
  				]
  			);
  		
  }
  private function a82($d) {
   return null; 
  }
  private function a83($d) {
   return true; 
  }
  private function a84($d, $lineContent) {
  
  		$dataAttribs = (object)[ 'tsr' => $this->tsrOffsets() ];
  		if ( $lineContent !== null ) {
  			$dataAttribs->lineContent = $lineContent;
  		}
  		if ( strlen( $d ) > 0 ) {
  			$dataAttribs->extra_dashes = strlen( $d );
  		}
  		return new SelfclosingTagTk( 'hr', [], $dataAttribs );
  	
  }
  private function a85($tl) {
  
  		return $tl;
  	
  }
  private function a86($end, $name, $extTag, $isBlock) {
  
  		if ( $extTag ) {
  			return $this->isExtTag( $name );
  		} else {
  			return $this->isXMLTag( $name, $isBlock );
  		}
  	
  }
  private function a87($end, $name, $extTag, $isBlock, $attribs, $selfclose) {
  
  		$lcName = mb_strtolower( $name );
  
  		// Extension tags don't necessarily have the same semantics as html tags,
  		// so don't treat them as void elements.
  		$isVoidElt = Utils::isVoidElement( $lcName ) && !$extTag;
  
  		// Support </br>
  		if ( $lcName === 'br' && $end ) {
  			$end = null;
  		}
  
  		$tsr = $this->tsrOffsets();
  		$tsr->start--; // For "<" matched at the start of xmlish_tag rule
  		$res = TokenizerUtils::buildXMLTag( $name, $lcName, $attribs, $end, !!$selfclose || $isVoidElt, $tsr );
  
  		// change up data-attribs in one scenario
  		// void-elts that aren't self-closed ==> useful for accurate RT-ing
  		if ( !$selfclose && $isVoidElt ) {
  			unset( $res->dataAttribs->selfClose );
  			$res->dataAttribs->noClose = true;
  		}
  
  		$met = $this->maybeExtensionTag( $res );
  		return ( is_array( $met ) ) ? $met : [ $met ];
  	
  }
  private function a88($sp) {
   return $this->endOffset(); 
  }
  private function a89($sp, $p, $c) {
  
  		return [
  			$sp,
  			new SelfclosingTagTk( 'meta', [ new KV( 'typeof', 'mw:EmptyLine' ) ], (object)[
  					'tokens' => TokenizerUtils::flattenIfArray( $c ),
  					'tsr' => new SourceRange( $p, $this->endOffset() ),
  				]
  			)
  		];
  	
  }
  private function a90() {
  
  		// Use the sol flag only at the start of the input
  		// Flag should always be an actual boolean (not falsy or undefined)
  		$this->assert( is_bool( $this->options['sol'] ), 'sol should be boolean' );
  		return $this->endOffset() === 0 && $this->options['sol'];
  	
  }
  private function a91() {
  
  		return [];
  	
  }
  private function a92($p, $target) {
   return $this->endOffset(); 
  }
  private function a93($p, $target, $p0, $v) {
   return $this->endOffset(); 
  }
  private function a94($p, $target, $p0, $v, $p1) {
  
  				// empty argument
  				return [ 'tokens' => $v, 'srcOffsets' => new SourceRange( $p0, $p1 ) ];
  			
  }
  private function a95($p, $target, $r) {
   return $r; 
  }
  private function a96($p, $target, $params) {
  
  		$kvs = [];
  
  		if ( $target === null ) {
  			$target = [ 'tokens' => '', 'srcOffsets' => new SourceRange( $p, $p ) ];
  		}
  		// Insert target as first positional attribute, so that it can be
  		// generically expanded. The TemplateHandler then needs to shift it out
  		// again.
  		$kvs[] = new KV( TokenizerUtils::flattenIfArray( $target['tokens'] ), '', $target['srcOffsets']->expandTsrK() );
  
  		foreach ( $params as $o ) {
  			$s = $o['srcOffsets'];
  			$kvs[] = new KV( '', TokenizerUtils::flattenIfArray( $o['tokens'] ), $s->expandTsrV() );
  		}
  
  		$obj = new SelfclosingTagTk( 'templatearg', $kvs, (object)[ 'tsr' => $this->tsrOffsets(), 'src' => $this->text() ] );
  		return $obj;
  	
  }
  private function a97($leadWS, $target) {
   return $this->endOffset(); 
  }
  private function a98($leadWS, $target, $p0, $v) {
   return $this->endOffset(); 
  }
  private function a99($leadWS, $target, $p0, $v, $p) {
  
  				// empty argument
  				$tsr0 = new SourceRange( $p0, $p );
  				return new KV( '', TokenizerUtils::flattenIfArray( $v ), $tsr0->expandTsrV() );
  			
  }
  private function a100($leadWS, $target, $r) {
   return $r; 
  }
  private function a101($leadWS, $target, $params, $trailWS) {
  
  		// Insert target as first positional attribute, so that it can be
  		// generically expanded. The TemplateHandler then needs to shift it out
  		// again.
  		array_unshift( $params, new KV( TokenizerUtils::flattenIfArray( $target['tokens'] ), '', $target['srcOffsets']->expandTsrK() ) );
  		$obj = new SelfclosingTagTk( 'template', $params,
  			(object)[
  				'tsr' => $this->tsrOffsets(), 'src' => $this->text(),
  				'tmp' => (object)[ 'leadWS' => $leadWS, 'trailWS' => $trailWS ]
  			] );
  		return $obj;
  	
  }
  private function a102($spos, $target) {
   return $this->endOffset(); 
  }
  private function a103($spos, $target, $tpos, $lcs) {
  
  		$pipeTrick = count( $lcs ) === 1 && count( $lcs[0]->v ) === 0;
  		$textTokens = [];
  		if ( $target === null || $pipeTrick ) {
  			$textTokens[] = '[[';
  			if ( $target ) {
  				$textTokens[] = $target;
  			}
  			foreach ( $lcs as $a ) {
  				// a is a mw:maybeContent attribute
  				$textTokens[] = '|';
  				if ( count( $a->v ) > 0 ) {
  					$textTokens[] = $a->v;
  				}
  			}
  			$textTokens[] = ']]';
  			return $textTokens;
  		}
  		$obj = new SelfclosingTagTk( 'wikilink' );
  		$tsr = new SourceRange( $spos, $tpos );
  		$hrefKV = new KV( 'href', $target, $tsr->expandTsrV() );
  		$hrefKV->vsrc = $tsr->substr( $this->input );
  		// XXX: Point to object with path, revision and input information
  		// obj.source = input;
  		$obj->attribs[] = $hrefKV;
  		$obj->attribs = array_merge( $obj->attribs, $lcs );
  		$obj->dataAttribs = (object)[
  			'tsr' => $this->tsrOffsets(),
  			'src' => $this->text()
  		];
  		return [ $obj ];
  	
  }
  private function a104(&$preproc) {
   $preproc =  null; return true; 
  }
  private function a105(&$preproc, $a) {
  
  		return $a;
  	
  }
  private function a106($extToken) {
   return $extToken[0]->getName() === 'extension'; 
  }
  private function a107($extToken) {
   return $extToken[0]; 
  }
  private function a108($proto, $addr, $rhe) {
   return $rhe === '<' || $rhe === '>' || $rhe === "\u{A0}"; 
  }
  private function a109($proto, $addr, $path) {
  
  			// as in Parser.php::makeFreeExternalLink, we're going to
  			// yank trailing punctuation out of this match.
  			$url = TokenizerUtils::flattenStringlist( array_merge( [ $proto, $addr ], $path ) );
  			// only need to look at last element; HTML entities are strip-proof.
  			$last = PHPUtils::lastItem( $url );
  			$trim = 0;
  			if ( is_string( $last ) ) {
  				$strip = ',;\.:!?';
  				if ( array_search( '(', $path ) === false ) {
  					$strip .= ')';
  				}
  				$trim = strspn( strrev( $last ), $strip );
  				$url[ count( $url ) - 1 ] = substr( $last, 0, strlen( $last ) - $trim );
  			}
  			$url = TokenizerUtils::flattenStringlist( $url );
  			if ( count( $url ) === 1 && is_string( $url[0] ) && strlen( $url[0] ) <= strlen( $proto ) ) {
  				return null; // ensure we haven't stripped everything: T106945
  			}
  			$this->currPos -= $trim;
  			return $url;
  		
  }
  private function a110($r) {
   return $r !== null; 
  }
  private function a111($r) {
  
  		$tsr = $this->tsrOffsets();
  		$res = [ new SelfclosingTagTk( 'urllink', [ new KV( 'href', $r, $tsr->expandTsrV() ) ], (object)[ 'tsr' => $tsr ] ) ];
  		return $res;
  	
  }
  private function a112($ref, $sp, $identifier) {
  
  		$base_urls = [
  			'RFC' => 'https://tools.ietf.org/html/rfc%s',
  			'PMID' => '//www.ncbi.nlm.nih.gov/pubmed/%s?dopt=Abstract'
  		];
  		$tsr = $this->tsrOffsets();
  		return [
  			new SelfclosingTagTk( 'extlink', [
  					new KV( 'href', sprintf( $base_urls[ $ref ], $identifier ) ),
  					new KV( 'mw:content', TokenizerUtils::flattenString( [ $ref, $sp, $identifier ] ), $tsr->expandTsrV() ),
  					new KV( 'typeof', 'mw:ExtLink/' . $ref )
  				],
  				(object)[ 'stx' => 'magiclink', 'tsr' => $tsr ]
  			)
  		];
  	
  }
  private function a113($sp, $isbn) {
  
  			// Convert isbn token-and-entity array to stripped string.
  			$stripped = '';
  			foreach ( TokenizerUtils::flattenStringlist( $isbn ) as $part ) {
  				if ( is_string( $part ) ) {
  					$stripped .= $part;
  				}
  			}
  			return strtoupper( preg_replace( '/[^\dX]/i', '', $stripped ) );
  		
  }
  private function a114($sp, $isbn, $isbncode) {
  
  		// ISBNs can only be 10 or 13 digits long (with a specific format)
  		return strlen( $isbncode ) === 10
  			|| ( strlen( $isbncode ) === 13 && preg_match( '/^97[89]/', $isbncode ) );
  	
  }
  private function a115($sp, $isbn, $isbncode) {
  
  		$tsr = $this->tsrOffsets();
  		return [
  			new SelfclosingTagTk( 'extlink', [
  					new KV( 'href', 'Special:BookSources/' . $isbncode ),
  					new KV( 'mw:content', TokenizerUtils::flattenString( [ 'ISBN', $sp, $isbn ] ), $tsr->expandTsrV() ),
  					new KV( 'typeof', 'mw:WikiLink/ISBN' )
  				],
  				(object)[ 'stx' => 'magiclink', 'tsr' => $tsr ]
  			)
  		];
  	
  }
  private function a116($lc) {
   return $lc; 
  }
  private function a117($bullets, $c) {
   return $this->endOffset(); 
  }
  private function a118($bullets, $c, $cpos, $d) {
  
  		// Leave bullets as an array -- list handler expects this
  		// TSR: +1 for the leading ";"
  		$numBullets = count( $bullets ) + 1;
  		$tsr = $this->tsrOffsets( 'start' );
  		$tsr->end += $numBullets;
  		$li1Bullets = $bullets;
  		$li1Bullets[] = ';';
  		$li1 = new TagTk( 'listItem', [ new KV( 'bullets', $li1Bullets, $tsr->expandTsrV() ) ], (object)[ 'tsr' => $tsr ] );
  		// TSR: -1 for the intermediate ":"
  		$li2Bullets = $bullets;
  		$li2Bullets[] = ':';
  		$tsr2 = new SourceRange( $cpos - 1, $cpos );
  		$li2 = new TagTk( 'listItem', [ new KV( 'bullets', $li2Bullets, $tsr2->expandTsrV() ) ],
  			(object)[ 'tsr' => $tsr2, 'stx' => 'row' ] );
  
  		return array_merge( [ $li1 ], $c ?: [], [ $li2 ], $d ?: [] );
  	
  }
  private function a119($bullets, $tbl, $line) {
  
  	// Leave bullets as an array -- list handler expects this
  	$tsr = $this->tsrOffsets( 'start' );
  	$tsr->end += count( $bullets );
  	$li = new TagTk( 'listItem', [ new KV( 'bullets', $bullets, $tsr->expandTsrV() ) ], (object)[ 'tsr' => $tsr ] );
  	return TokenizerUtils::flattenIfArray( [ $li, $tbl, $line ?: [] ] );
  
  }
  private function a120($bullets, $c) {
  
  		// Leave bullets as an array -- list handler expects this
  		$tsr = $this->tsrOffsets( 'start' );
  		$tsr->end += count( $bullets );
  		$li = new TagTk( 'listItem', [ new KV( 'bullets', $bullets, $tsr->expandTsrV() ) ], (object)[ 'tsr' => $tsr ] );
  		return array_merge( [ $li ], $c ?: [] );
  	
  }
  private function a121($spc) {
  
  		if ( strlen( $spc ) ) {
  			return [ $spc ];
  		} else {
  			return [];
  		}
  	
  }
  private function a122($sc, $startPos, $p, $b) {
  
  		$tblEnd = new EndTagTk( 'table', [], (object)[
  			'tsr' => new SourceRange( $startPos, $this->endOffset() ),
  		] );
  		if ( $p !== '|' ) {
  			// p+"<brace-char>" is triggering some bug in pegJS
  			// I cannot even use that expression in the comment!
  			$tblEnd->dataAttribs->endTagSrc = $p . $b;
  		}
  		array_push( $sc, $tblEnd );
  		return $sc;
  	
  }
  private function a123($tpt) {
  
  		return [ 'tokens' => $tpt, 'srcOffsets' => $this->tsrOffsets() ];
  	
  }
  private function a124($name) {
   return $this->endOffset(); 
  }
  private function a125($name, $kEndPos) {
   return $this->endOffset(); 
  }
  private function a126($name, $kEndPos, $vStartPos, $tpv) {
  
  			return [
  				'kEndPos' => $kEndPos,
  				'vStartPos' => $vStartPos,
  				'value' => $tpv ? $tpv['tokens'] : []
  			];
  		
  }
  private function a127($name, $val) {
  
  		if ( $val !== null ) {
  			if ( $val['value'] !== null ) {
  				$so = new KVSourceRange(
  					$this->startOffset(), $val['kEndPos'],
  					$val['vStartPos'], $this->endOffset()
  				);
  				return new KV(
  					$name,
  					TokenizerUtils::flattenIfArray( $val['value'] ),
  					$so
  				);
  			} else {
  				return new KV(
  					TokenizerUtils::flattenIfArray( $name ),
  					'',
  					$so
  				);
  			}
  		} else {
  			$so = new SourceRange( $this->startOffset(), $this->endOffset() );
  			return new KV(
  				'',
  				TokenizerUtils::flattenIfArray( $name ),
  				$so->expandTsrV()
  			);
  		}
  	
  }
  private function a128() {
  
  		$so = new SourceRange( $this->startOffset(), $this->endOffset() );
  		return new KV( '', '', $so->expandTsrV() );
  	
  }
  private function a129($t, $wr) {
   return $wr; 
  }
  private function a130($r) {
  
  		return TokenizerUtils::flattenStringlist( $r );
  	
  }
  private function a131($startPos, $lt) {
  
  			$tsr = new SourceRange( $startPos, $this->endOffset() );
  			$maybeContent = new KV( 'mw:maybeContent', $lt ?? [], $tsr->expandTsrV() );
  			$maybeContent->vsrc = substr( $this->input, $startPos, $this->endOffset() - $startPos );
  			return $maybeContent;
  		
  }
  private function a132($he) {
   return is_array( $he ) && $he[ 1 ] === "\u{A0}"; 
  }
  private function a133() {
   return $this->startOffset(); 
  }
  private function a134($lv0) {
   return $this->env->langConverterEnabled(); 
  }
  private function a135($lv0, $ff) {
  
  			// if flags contains 'R', then don't treat ; or : specially inside.
  			if ( isset( $ff['flags'] ) ) {
  				$ff['raw'] = isset( $ff['flags']['R'] ) || isset( $ff['flags']['N'] );
  			} elseif ( isset( $ff['variants'] ) ) {
  				$ff['raw'] = true;
  			}
  			return $ff;
  		
  }
  private function a136($lv0) {
   return !$this->env->langConverterEnabled(); 
  }
  private function a137($lv0) {
  
  			// if language converter not enabled, don't try to parse inside.
  			return [ 'raw' => true ];
  		
  }
  private function a138($lv0, $f) {
   return $f['raw']; 
  }
  private function a139($lv0, $f, $lv) {
   return [ [ 'text' => $lv ] ]; 
  }
  private function a140($lv0, $f) {
   return !$f['raw']; 
  }
  private function a141($lv0, $f, $lv) {
   return $lv; 
  }
  private function a142($lv0, $f, $ts) {
   return $this->endOffset(); 
  }
  private function a143($lv0, $f, $ts, $lv1) {
  
  		if ( !$this->env->langConverterEnabled() ) {
  			return [ '-{', $ts[0]['text']['tokens'], '}-' ];
  		}
  		$lvsrc = substr( $this->input, $lv0, $lv1 - $lv0 );
  		$attribs = [];
  
  		foreach ( $ts as &$t ) {
  			// move token strings into KV attributes so that they are
  			// properly expanded by early stages of the token pipeline
  			foreach ( [ 'text', 'from', 'to' ] as $fld ) {
  				if ( !isset( $t[$fld] ) ) {
  					continue;
  				}
  				$name = 'mw:lv' . count( $attribs );
  				// Note that AttributeExpander will expect the tokens array to be
  				// flattened.  We do that in lang_variant_text / lang_variant_nowiki
  				$attribs[] = new KV( $name, $t[$fld]['tokens'], $t[$fld]['srcOffsets']->expandTsrV() );
  				$t[$fld] = $name;
  			}
  		}
  		unset( $t );
  
  		$flags = isset( $f['flags'] ) ? array_keys( $f['flags'] ) : [];
  		sort( $flags );
  		$variants = isset( $f['variants'] ) ? array_keys( $f['variants'] ) : [];
  		sort( $variants );
  
  		return [
  			new SelfclosingTagTk(
  				'language-variant',
  				$attribs,
  				(object)[
  					'tsr' => new SourceRange( $lv0, $lv1 ),
  					'src' => $lvsrc,
  					'flags' => $flags,
  					'variants' => $variants,
  					'original' => $f['original'],
  					'flagSp' => $f['sp'],
  					'texts' => $ts
  				]
  			)
  		];
  	
  }
  private function a144($r, &$preproc) {
  
  		$preproc = null;
  		return $r;
  	
  }
  private function a145($p, $dashes) {
   $this->unreachable(); 
  }
  private function a146($p, $dashes, $a) {
   return $this->endOffset(); 
  }
  private function a147($p, $dashes, $a, $tagEndPos) {
  
  		$coms = TokenizerUtils::popComments( $a );
  		if ( $coms ) {
  			$tagEndPos = $coms['commentStartPos'];
  		}
  
  		$da = (object)[
  			'tsr' => new SourceRange( $this->startOffset(), $tagEndPos ),
  			'startTagSrc' => $p . $dashes
  		];
  
  		// We rely on our tree builder to close the row as needed. This is
  		// needed to support building tables from fragment templates with
  		// individual cells or rows.
  		$trToken = new TagTk( 'tr', $a, $da );
  
  		return array_merge( [ $trToken ], $coms ? $coms['buf'] : [] );
  	
  }
  private function a148($p, $td) {
   return $this->endOffset(); 
  }
  private function a149($p, $td, $tagEndPos, $tds) {
  
  		// Avoid modifying a cached result
  		$td[0] = clone $td[0];
  		$da = $td[0]->dataAttribs = clone $td[0]->dataAttribs;
  		$da->tsr = clone $da->tsr;
  
  		$da->tsr->start -= strlen( $p ); // include "|"
  		if ( $p !== '|' ) {
  			// Variation from default
  			$da->startTagSrc = $p;
  		}
  		return array_merge( $td, $tds );
  	
  }
  private function a150($p, $args) {
   return $this->endOffset(); 
  }
  private function a151($p, $args, $tagEndPos, $c) {
  
  		$tsr = new SourceRange( $this->startOffset(), $tagEndPos );
  		return TokenizerUtils::buildTableTokens(
  			'caption', '|+', $args, $tsr, $this->endOffset(), $c, true );
  	
  }
  private function a152($il) {
  
  		// il is guaranteed to be an array -- so, tu.flattenIfArray will
  		// always return an array
  		$r = TokenizerUtils::flattenIfArray( $il );
  		if ( count( $r ) === 1 && is_string( $r[0] ) ) {
  			$r = $r[0];
  		}
  		return $r;
  	
  }
  private function a153() {
   return ''; 
  }
  private function a154($ff) {
   return $ff; 
  }
  private function a155($f) {
  
  		// Collect & separate flags and variants into a hashtable (by key) and ordered list
  		$flags = [];
  		$variants = [];
  		$flagList = [];
  		$flagSpace = [];
  		$variantList = [];
  		$variantSpace = [];
  		$useVariants = false;
  		if ( $f !== null ) {
  			// lang_variant_flags returns arrays in reverse order.
  			$spPtr = count( $f['sp'] ) - 1;
  			for ( $i = count( $f['flags'] ) - 1; $i >= 0; $i--) {
  				$item = $f['flags'][$i];
  				if ( isset( $item['flag'] ) ) {
  					$flagSpace[] = $f['sp'][$spPtr--];
  					$flags[$item['flag']] = true;
  					$flagList[] = $item['flag'];
  					$flagSpace[] = $f['sp'][$spPtr--];
  				}
  				if ( isset( $item['variant'] ) ) {
  					$variantSpace[] = $f['sp'][$spPtr--];
  					$variants[$item['variant']] = true;
  					$variantList[] = $item['variant'];
  					$variantSpace[] = $f['sp'][$spPtr--];
  				}
  			}
  			if ( $spPtr >= 0 ) {
  				// handle space after a trailing semicolon
  				$flagSpace[] = $f['sp'][$spPtr];
  				$variantSpace[] = $f['sp'][$spPtr];
  			}
  		}
  		// Parse flags (this logic is from core/languages/ConverterRule.php
  		// in the parseFlags() function)
  		if ( count( $flags ) === 0 && count( $variants ) === 0 ) {
  			$flags['$S'] = true;
  		} elseif ( isset( $flags['R'] ) ) {
  			$flags = [ 'R' => true ]; // remove other flags
  		} elseif ( isset( $flags['N'] ) ) {
  			$flags = [ 'N' => true ]; // remove other flags
  		} elseif ( isset( $flags['-'] ) ) {
  			$flags = [ '-' => true ]; // remove other flags
  		} elseif ( isset( $flags['T'] ) && count( $flags ) === 1 ) {
  			$flags['H'] = true;
  		} elseif ( isset( $flags['H'] ) ) {
  			// Replace A flag, and remove other flags except T and D
  			$nf = [ '$+' => true, 'H' => true ];
  			if ( isset( $flags['T'] ) ) { $nf['T'] = true; }
  			if ( isset( $flags['D'] ) ) { $nf['D'] = true; }
  			$flags = $nf;
  		} elseif ( count( $variants ) > 0 ) {
  			$useVariants = true;
  		} else {
  			if ( isset( $flags['A'] ) ) {
  				$flags['$+'] = true;
  				$flags['$S'] = true;
  			}
  			if ( isset( $flags['D'] ) ) {
  				unset( $flags['$S'] );
  			}
  		}
  		if ( $useVariants ) {
  			return [ 'variants' => $variants, 'original' => $variantList, 'sp' => $variantSpace ];
  		} else {
  			return [ 'flags' => $flags, 'original' => $flagList, 'sp' => $flagSpace ];
  		}
  	
  }
  private function a156($tokens) {
  
  		return [
  			'tokens' => TokenizerUtils::flattenStringlist( $tokens ),
  			'srcOffsets' => $this->tsrOffsets(),
  		];
  	
  }
  private function a157($o, $oo) {
   return $oo; 
  }
  private function a158($o, $rest, $tr) {
  
  		array_unshift( $rest, $o );
  		// if the last bogus option is just spaces, keep them; otherwise
  		// drop all this bogus stuff on the ground
  		if ( count($tr) > 0 ) {
  			$last = $tr[count($tr)-1];
  			if (preg_match('/^\s*$/Du', $last[1])) {
  				$rest[] = [ 'semi' => true, 'sp' => $last[1] ];
  			}
  		}
  		return $rest;
  	
  }
  private function a159($lvtext) {
   return [ [ 'text' => $lvtext ] ]; 
  }
  private function a160($thTag, $pp, $tht) {
  
  			// Avoid modifying a cached result
  			$tht[0] = clone $tht[0];
  			$da = $tht[0]->dataAttribs = clone $tht[0]->dataAttribs;
  			$da->tsr = clone $da->tsr;
  
  			$da->stx = 'row';
  			$da->tsr->start -= strlen( $pp ); // include "!!" or "||"
  
  			if ( $pp !== '!!' || ( isset( $da->startTagSrc ) && $da->startTagSrc !== $pp ) ) {
  				// Variation from default
  				$da->startTagSrc = $pp . ( isset( $da->startTagSrc ) ? substr( $da->startTagSrc, 1 ) : '' );
  			}
  			return $tht;
  		
  }
  private function a161($thTag, $thTags) {
  
  		$thTag[0] = clone $thTag[0];
  		$da = $thTag[0]->dataAttribs = clone $thTag[0]->dataAttribs;
  		$da->tsr = clone $da->tsr;
  		$da->tsr->start--; // include "!"
  		array_unshift( $thTags, $thTag );
  		return $thTags;
  	
  }
  private function a162($arg) {
   return $this->endOffset(); 
  }
  private function a163($arg, $tagEndPos, $td) {
  
  		$tsr = new SourceRange( $this->startOffset(), $tagEndPos );
  		return TokenizerUtils::buildTableTokens( 'td', '|', $arg,
  			$tsr, $this->endOffset(), $td );
  	
  }
  private function a164($pp, $tdt) {
  
  			// Avoid modifying cached dataAttribs object
  			$tdt[0] = clone $tdt[0];
  			$da = $tdt[0]->dataAttribs = clone $tdt[0]->dataAttribs;
  			$da->tsr = clone $da->tsr;
  
  			$da->stx = 'row';
  			$da->tsr->start -= strlen( $pp ); // include "||"
  			if ( $pp !== '||' || ( isset( $da->startTagSrc ) && $da->startTagSrc !== $pp ) ) {
  				// Variation from default
  				$da->startTagSrc = $pp . ( isset( $da->startTagSrc ) ? substr( $da->startTagSrc, 1 ) : '' );
  			}
  			return $tdt;
  		
  }
  private function a165($b) {
  
  		return $b;
  	
  }
  private function a166($sp1, $f, $sp2, $more) {
  
  		$r = ( $more && $more[1] ) ? $more[1] : [ 'sp' => [], 'flags' => [] ];
  		// Note that sp and flags are in reverse order, since we're using
  		// right recursion and want to push instead of unshift.
  		$r['sp'][] = $sp2;
  		$r['sp'][] = $sp1;
  		$r['flags'][] = $f;
  		return $r;
  	
  }
  private function a167($sp) {
  
  		return [ 'sp' => [ $sp ], 'flags' => [] ];
  	
  }
  private function a168($sp1, $lang, $sp2, $sp3, $lvtext) {
  
  		return [
  			'twoway' => true,
  			'lang' => $lang,
  			'text' => $lvtext,
  			'sp' => [ $sp1, $sp2, $sp3 ]
  		];
  	
  }
  private function a169($sp1, $from, $sp2, $lang, $sp3, $sp4, $to) {
  
  		return [
  			'oneway' => true,
  			'from' => $from,
  			'lang' => $lang,
  			'to' => $to,
  			'sp' => [ $sp1, $sp2, $sp3, $sp4 ]
  		];
  	
  }
  private function a170($arg, $tagEndPos, &$th, $d) {
  
  			if ( $th !== false && strpos( $this->text(), "\n" ) !== false ) {
  				// There's been a newline. Remove the break and continue
  				// tokenizing nested_block_in_tables.
  				$th = false;
  			}
  			return $d;
  		
  }
  private function a171($arg, $tagEndPos, $c) {
  
  		$tsr = new SourceRange( $this->startOffset(), $tagEndPos );
  		return TokenizerUtils::buildTableTokens( 'th', '!', $arg,
  			$tsr, $this->endOffset(), $c );
  	
  }
  private function a172($r) {
  
  		return $r;
  	
  }
  private function a173($f) {
   return [ 'flag' => $f ]; 
  }
  private function a174($v) {
   return [ 'variant' => $v ]; 
  }
  private function a175($b) {
   return [ 'bogus' => $b ]; /* bad flag */
  }
  private function a176($n, $sp) {
  
  		$tsr = $this->tsrOffsets();
  		$tsr->end -= strlen( $sp );
  		return [
  			'tokens' => [ $n ],
  			'srcOffsets' => $tsr,
  		];
  	
  }
  private function a177($extToken) {
   return $extToken->getAttribute( 'name' ) === 'nowiki'; 
  }
  private function a178($extToken) {
   return $extToken; 
  }
  private function a179($extToken) {
  
  		$txt = Utils::extractExtBody( $extToken );
  		return Utils::decodeWtEntities( $txt );
  	
  }

  // generated
  private function streamstart_async($silence, &$param_preproc) {
    for (;;) {
      // start choice_1
      $r1 = $this->parsetlb($silence, $param_preproc);
      if ($r1!==self::$FAILED) {
        goto choice_1;
      }
      // start seq_1
      $p2 = $this->currPos;
      $r3 = [];
      for (;;) {
        $r4 = $this->parsenewlineToken($silence);
        if ($r4!==self::$FAILED) {
          $r3[] = $r4;
        } else {
          break;
        }
      }
      // free $r4
      $this->savedPos = $this->currPos;
      $r4 = $this->a0();
      if ($r4) {
        $r4 = false;
      } else {
        $r4 = self::$FAILED;
        $this->currPos = $p2;
        $r1 = self::$FAILED;
        goto seq_1;
      }
      $r1 = [$r3,$r4];
      seq_1:
      // free $p2
      choice_1:
      if ($r1!==self::$FAILED) {
        yield $r1;
      } else {
        if ($this->currPos < $this->inputLength) {
          $this->fail(0);
          throw $this->buildParseException();
        }
        break;
      }
    }
  }
  private function parsestart($silence, &$param_preproc) {
    $key = json_encode([284, $param_preproc]);
    $bucket = $this->currPos;
    $cached = $this->cache[$bucket][$key] ?? null;
    if ($cached) {
      $this->currPos = $cached['nextPos'];
        if (array_key_exists("\$preproc", $cached)) $param_preproc = $cached["\$preproc"];
      return $cached['result'];
    }
        $saved_preproc=$param_preproc;
    $p2 = $this->currPos;
    // start seq_1
    $p3 = $this->currPos;
    $r4 = [];
    for (;;) {
      $r5 = $this->parsetlb(true, $param_preproc);
      if ($r5!==self::$FAILED) {
        $r4[] = $r5;
      } else {
        break;
      }
    }
    // t <- $r4
    // free $r5
    $r5 = [];
    for (;;) {
      $r6 = $this->parsenewlineToken(true);
      if ($r6!==self::$FAILED) {
        $r5[] = $r6;
      } else {
        break;
      }
    }
    // n <- $r5
    // free $r6
    $r1 = true;
    seq_1:
    if ($r1!==self::$FAILED) {
      $this->savedPos = $p2;
      $r1 = $this->a1($r4, $r5);
    } else {
      if (!$silence) {$this->fail(1);}
    }
    // free $p3
    $cached = ['nextPos' => $this->currPos, 'result' => $r1];
      if ($saved_preproc !== $param_preproc) $cached["\$preproc"] = $param_preproc;
    $this->cache[$bucket][$key] = $cached;
    return $r1;
  }
  private function parsetable_start_tag($silence, $boolParams, $param_templatedepth, &$param_preproc, &$param_th) {
    $key = json_encode([468, $boolParams & 0x3bee, $param_templatedepth, $param_preproc, $param_th]);
    $bucket = $this->currPos;
    $cached = $this->cache[$bucket][$key] ?? null;
    if ($cached) {
      $this->currPos = $cached['nextPos'];
        if (array_key_exists("\$preproc", $cached)) $param_preproc = $cached["\$preproc"];
        if (array_key_exists("\$th", $cached)) $param_th = $cached["\$th"];
      return $cached['result'];
    }
        $saved_preproc=$param_preproc;
        $saved_th=$param_th;
    $p2 = $this->currPos;
    // start seq_1
    $p3 = $this->currPos;
    $r4 = [];
    for (;;) {
      // start choice_1
      $r5 = $this->parsespace(true);
      if ($r5!==self::$FAILED) {
        goto choice_1;
      }
      $r5 = $this->parsecomment(true);
      choice_1:
      if ($r5!==self::$FAILED) {
        $r4[] = $r5;
      } else {
        break;
      }
    }
    // sc <- $r4
    // free $r5
    $p6 = $this->currPos;
    $r5 = '';
    // startPos <- $r5
    if ($r5!==self::$FAILED) {
      $this->savedPos = $p6;
      $r5 = $this->a2($r4);
    } else {
      $this->currPos = $p3;
      $r1 = self::$FAILED;
      goto seq_1;
    }
    // b <- $r7
    if (($this->input[$this->currPos] ?? null) === "{") {
      $this->currPos++;
      $r7 = "{";
    } else {
      $r7 = self::$FAILED;
      $this->currPos = $p3;
      $r1 = self::$FAILED;
      goto seq_1;
    }
    $r8 = $this->parsepipe(true);
    // p <- $r8
    if ($r8===self::$FAILED) {
      $this->currPos = $p3;
      $r1 = self::$FAILED;
      goto seq_1;
    }
    // start choice_2
    $r9 = $this->parsetable_attributes(true, $boolParams & ~0x10, $param_templatedepth, $param_preproc, $param_th);
    if ($r9!==self::$FAILED) {
      goto choice_2;
    }
    $this->savedPos = $this->currPos;
    $r9 = $this->a3($r4, $r5, $r7, $r8);
    if ($r9) {
      $r9 = false;
    } else {
      $r9 = self::$FAILED;
    }
    choice_2:
    // ta <- $r9
    if ($r9===self::$FAILED) {
      $this->currPos = $p3;
      $r1 = self::$FAILED;
      goto seq_1;
    }
    $p11 = $this->currPos;
    $r10 = '';
    // tsEndPos <- $r10
    if ($r10!==self::$FAILED) {
      $this->savedPos = $p11;
      $r10 = $this->a4($r4, $r5, $r7, $r8, $r9);
    } else {
      $this->currPos = $p3;
      $r1 = self::$FAILED;
      goto seq_1;
    }
    $r12 = [];
    for (;;) {
      $r13 = $this->parsespace(true);
      if ($r13!==self::$FAILED) {
        $r12[] = $r13;
      } else {
        break;
      }
    }
    // s2 <- $r12
    // free $r13
    $r1 = true;
    seq_1:
    if ($r1!==self::$FAILED) {
      $this->savedPos = $p2;
      $r1 = $this->a5($r4, $r5, $r7, $r8, $r9, $r10, $r12);
    } else {
      if (!$silence) {$this->fail(2);}
    }
    // free $p3
    $cached = ['nextPos' => $this->currPos, 'result' => $r1];
      if ($saved_preproc !== $param_preproc) $cached["\$preproc"] = $param_preproc;
      if ($saved_th !== $param_th) $cached["\$th"] = $param_th;
    $this->cache[$bucket][$key] = $cached;
    return $r1;
  }
  private function parseurl($silence, &$param_preproc) {
    $key = json_encode([340, $param_preproc]);
    $bucket = $this->currPos;
    $cached = $this->cache[$bucket][$key] ?? null;
    if ($cached) {
      $this->currPos = $cached['nextPos'];
        if (array_key_exists("\$preproc", $cached)) $param_preproc = $cached["\$preproc"];
      return $cached['result'];
    }
        $saved_preproc=$param_preproc;
    $p2 = $this->currPos;
    // start seq_1
    $p3 = $this->currPos;
    $r4 = $this->parseurl_protocol($silence);
    // proto <- $r4
    if ($r4===self::$FAILED) {
      $r1 = self::$FAILED;
      goto seq_1;
    }
    // start choice_1
    $r5 = $this->parseipv6urladdr($silence);
    if ($r5!==self::$FAILED) {
      goto choice_1;
    }
    $r5 = '';
    choice_1:
    // addr <- $r5
    if ($r5===self::$FAILED) {
      $this->currPos = $p3;
      $r1 = self::$FAILED;
      goto seq_1;
    }
    $r6 = [];
    for (;;) {
      $p8 = $this->currPos;
      // start seq_2
      $p9 = $this->currPos;
      $p10 = $this->currPos;
      $r11 = $this->discardinline_breaks(true, 0x0, 0, $param_preproc, self::newRef(null));
      if ($r11 === self::$FAILED) {
        $r11 = false;
      } else {
        $r11 = self::$FAILED;
        $this->currPos = $p10;
        $r7 = self::$FAILED;
        goto seq_2;
      }
      // free $p10
      // start choice_2
      $r12 = $this->parseno_punctuation_char($silence);
      if ($r12!==self::$FAILED) {
        goto choice_2;
      }
      $r12 = $this->parsecomment($silence);
      if ($r12!==self::$FAILED) {
        goto choice_2;
      }
      $r12 = $this->parsetplarg_or_template($silence, 0x0, 0, self::newRef(null), $param_preproc);
      if ($r12!==self::$FAILED) {
        goto choice_2;
      }
      $r12 = $this->input[$this->currPos] ?? '';
      if ($r12 === "'" || $r12 === "{") {
        $this->currPos++;
        goto choice_2;
      } else {
        $r12 = self::$FAILED;
        if (!$silence) {$this->fail(3);}
      }
      $p10 = $this->currPos;
      // start seq_3
      $p13 = $this->currPos;
      $p14 = $this->currPos;
      // start seq_4
      $p16 = $this->currPos;
      if (($this->input[$this->currPos] ?? null) === "&") {
        $this->currPos++;
        $r17 = "&";
      } else {
        $r17 = self::$FAILED;
        $r15 = self::$FAILED;
        goto seq_4;
      }
      // start choice_3
      // start seq_5
      $p19 = $this->currPos;
      $r20 = $this->input[$this->currPos] ?? '';
      if ($r20 === "l" || $r20 === "L") {
        $this->currPos++;
      } else {
        $r20 = self::$FAILED;
        $r18 = self::$FAILED;
        goto seq_5;
      }
      $r21 = $this->input[$this->currPos] ?? '';
      if ($r21 === "t" || $r21 === "T") {
        $this->currPos++;
      } else {
        $r21 = self::$FAILED;
        $this->currPos = $p19;
        $r18 = self::$FAILED;
        goto seq_5;
      }
      $r18 = true;
      seq_5:
      if ($r18!==self::$FAILED) {
        goto choice_3;
      }
      // free $p19
      // start seq_6
      $p19 = $this->currPos;
      $r22 = $this->input[$this->currPos] ?? '';
      if ($r22 === "g" || $r22 === "G") {
        $this->currPos++;
      } else {
        $r22 = self::$FAILED;
        $r18 = self::$FAILED;
        goto seq_6;
      }
      $r23 = $this->input[$this->currPos] ?? '';
      if ($r23 === "t" || $r23 === "T") {
        $this->currPos++;
      } else {
        $r23 = self::$FAILED;
        $this->currPos = $p19;
        $r18 = self::$FAILED;
        goto seq_6;
      }
      $r18 = true;
      seq_6:
      // free $p19
      choice_3:
      if ($r18===self::$FAILED) {
        $this->currPos = $p16;
        $r15 = self::$FAILED;
        goto seq_4;
      }
      if (($this->input[$this->currPos] ?? null) === ";") {
        $this->currPos++;
        $r24 = ";";
      } else {
        $r24 = self::$FAILED;
        $this->currPos = $p16;
        $r15 = self::$FAILED;
        goto seq_4;
      }
      $r15 = true;
      seq_4:
      // free $p16
      if ($r15 === self::$FAILED) {
        $r15 = false;
      } else {
        $r15 = self::$FAILED;
        $this->currPos = $p14;
        $r12 = self::$FAILED;
        goto seq_3;
      }
      // free $p14
      // start choice_4
      $p14 = $this->currPos;
      // start seq_7
      $p16 = $this->currPos;
      $p19 = $this->currPos;
      if (($this->input[$this->currPos] ?? null) === "&") {
        $this->currPos++;
        $r26 = "&";
        $r26 = false;
        $this->currPos = $p19;
      } else {
        $r26 = self::$FAILED;
        $r25 = self::$FAILED;
        goto seq_7;
      }
      // free $p19
      $r27 = $this->parsehtmlentity($silence);
      // he <- $r27
      if ($r27===self::$FAILED) {
        $this->currPos = $p16;
        $r25 = self::$FAILED;
        goto seq_7;
      }
      $r25 = true;
      seq_7:
      if ($r25!==self::$FAILED) {
        $this->savedPos = $p14;
        $r25 = $this->a6($r4, $r5, $r27);
        goto choice_4;
      }
      // free $p16
      if (($this->input[$this->currPos] ?? null) === "&") {
        $this->currPos++;
        $r25 = "&";
      } else {
        if (!$silence) {$this->fail(4);}
        $r25 = self::$FAILED;
      }
      choice_4:
      // r <- $r25
      if ($r25===self::$FAILED) {
        $this->currPos = $p13;
        $r12 = self::$FAILED;
        goto seq_3;
      }
      $r12 = true;
      seq_3:
      if ($r12!==self::$FAILED) {
        $this->savedPos = $p10;
        $r12 = $this->a7($r4, $r5, $r25);
      }
      // free $p13
      choice_2:
      // c <- $r12
      if ($r12===self::$FAILED) {
        $this->currPos = $p9;
        $r7 = self::$FAILED;
        goto seq_2;
      }
      $r7 = true;
      seq_2:
      if ($r7!==self::$FAILED) {
        $this->savedPos = $p8;
        $r7 = $this->a8($r4, $r5, $r12);
        $r6[] = $r7;
      } else {
        break;
      }
      // free $p9
    }
    // path <- $r6
    // free $r7
    $this->savedPos = $this->currPos;
    $r7 = $this->a9($r4, $r5, $r6);
    if ($r7) {
      $r7 = false;
    } else {
      $r7 = self::$FAILED;
      $this->currPos = $p3;
      $r1 = self::$FAILED;
      goto seq_1;
    }
    $r1 = true;
    seq_1:
    if ($r1!==self::$FAILED) {
      $this->savedPos = $p2;
      $r1 = $this->a10($r4, $r5, $r6);
    }
    // free $p3
    $cached = ['nextPos' => $this->currPos, 'result' => $r1];
      if ($saved_preproc !== $param_preproc) $cached["\$preproc"] = $param_preproc;
    $this->cache[$bucket][$key] = $cached;
    return $r1;
  }
  private function parserow_syntax_table_args($silence, $boolParams, $param_templatedepth, &$param_preproc, &$param_th) {
    $key = json_encode([488, $boolParams & 0x3bbe, $param_templatedepth, $param_preproc, $param_th]);
    $bucket = $this->currPos;
    $cached = $this->cache[$bucket][$key] ?? null;
    if ($cached) {
      $this->currPos = $cached['nextPos'];
        if (array_key_exists("\$preproc", $cached)) $param_preproc = $cached["\$preproc"];
        if (array_key_exists("\$th", $cached)) $param_th = $cached["\$th"];
      return $cached['result'];
    }
        $saved_preproc=$param_preproc;
        $saved_th=$param_th;
    $p2 = $this->currPos;
    // start seq_1
    $p3 = $this->currPos;
    $r4 = $this->parsetable_attributes($silence, $boolParams | 0x40, $param_templatedepth, $param_preproc, $param_th);
    // as <- $r4
    if ($r4===self::$FAILED) {
      $r1 = self::$FAILED;
      goto seq_1;
    }
    $r5 = $this->parseoptional_spaces($silence);
    // s <- $r5
    if ($r5===self::$FAILED) {
      $this->currPos = $p3;
      $r1 = self::$FAILED;
      goto seq_1;
    }
    $r6 = $this->parsepipe($silence);
    // p <- $r6
    if ($r6===self::$FAILED) {
      $this->currPos = $p3;
      $r1 = self::$FAILED;
      goto seq_1;
    }
    $p7 = $this->currPos;
    $r8 = $this->discardpipe(true);
    if ($r8 === self::$FAILED) {
      $r8 = false;
    } else {
      $r8 = self::$FAILED;
      $this->currPos = $p7;
      $this->currPos = $p3;
      $r1 = self::$FAILED;
      goto seq_1;
    }
    // free $p7
    $r1 = true;
    seq_1:
    if ($r1!==self::$FAILED) {
      $this->savedPos = $p2;
      $r1 = $this->a11($r4, $r5, $r6);
    }
    // free $p3
    $cached = ['nextPos' => $this->currPos, 'result' => $r1];
      if ($saved_preproc !== $param_preproc) $cached["\$preproc"] = $param_preproc;
      if ($saved_th !== $param_th) $cached["\$th"] = $param_th;
    $this->cache[$bucket][$key] = $cached;
    return $r1;
  }
  private function parsetable_attributes($silence, $boolParams, $param_templatedepth, &$param_preproc, &$param_th) {
    $key = json_encode([290, $boolParams & 0x3bfe, $param_templatedepth, $param_preproc, $param_th]);
    $bucket = $this->currPos;
    $cached = $this->cache[$bucket][$key] ?? null;
    if ($cached) {
      $this->currPos = $cached['nextPos'];
        if (array_key_exists("\$preproc", $cached)) $param_preproc = $cached["\$preproc"];
        if (array_key_exists("\$th", $cached)) $param_th = $cached["\$th"];
      return $cached['result'];
    }
        $saved_preproc=$param_preproc;
        $saved_th=$param_th;
    $r1 = [];
    for (;;) {
      // start choice_1
      $r2 = $this->parsetable_attribute(true, $boolParams, $param_templatedepth, $param_preproc, $param_th);
      if ($r2!==self::$FAILED) {
        goto choice_1;
      }
      $p3 = $this->currPos;
      // start seq_1
      $p4 = $this->currPos;
      $r5 = $this->discardoptionalSpaceToken(true);
      if ($r5===self::$FAILED) {
        $r2 = self::$FAILED;
        goto seq_1;
      }
      $r6 = $this->parsebroken_table_attribute_name_char(true);
      // b <- $r6
      if ($r6===self::$FAILED) {
        $this->currPos = $p4;
        $r2 = self::$FAILED;
        goto seq_1;
      }
      $r2 = true;
      seq_1:
      if ($r2!==self::$FAILED) {
        $this->savedPos = $p3;
        $r2 = $this->a12($r6);
      }
      // free $p4
      choice_1:
      if ($r2!==self::$FAILED) {
        $r1[] = $r2;
      } else {
        break;
      }
    }
    // free $r2
    $cached = ['nextPos' => $this->currPos, 'result' => $r1];
      if ($saved_preproc !== $param_preproc) $cached["\$preproc"] = $param_preproc;
      if ($saved_th !== $param_th) $cached["\$th"] = $param_th;
    $this->cache[$bucket][$key] = $cached;
    return $r1;
  }
  private function parsegeneric_newline_attributes($silence, $boolParams, $param_templatedepth, &$param_preproc, &$param_th) {
    $key = json_encode([288, $boolParams & 0x1bfe, $param_templatedepth, $param_preproc, $param_th]);
    $bucket = $this->currPos;
    $cached = $this->cache[$bucket][$key] ?? null;
    if ($cached) {
      $this->currPos = $cached['nextPos'];
        if (array_key_exists("\$preproc", $cached)) $param_preproc = $cached["\$preproc"];
        if (array_key_exists("\$th", $cached)) $param_th = $cached["\$th"];
      return $cached['result'];
    }
        $saved_preproc=$param_preproc;
        $saved_th=$param_th;
    $r1 = [];
    for (;;) {
      $r2 = $this->parsegeneric_newline_attribute(true, $boolParams, $param_templatedepth, $param_preproc, $param_th);
      if ($r2!==self::$FAILED) {
        $r1[] = $r2;
      } else {
        break;
      }
    }
    // free $r2
    $cached = ['nextPos' => $this->currPos, 'result' => $r1];
      if ($saved_preproc !== $param_preproc) $cached["\$preproc"] = $param_preproc;
      if ($saved_th !== $param_th) $cached["\$th"] = $param_th;
    $this->cache[$bucket][$key] = $cached;
    return $r1;
  }
  private function parsetplarg_or_template_or_bust($silence, &$param_preproc) {
    $key = json_encode([350, $param_preproc]);
    $bucket = $this->currPos;
    $cached = $this->cache[$bucket][$key] ?? null;
    if ($cached) {
      $this->currPos = $cached['nextPos'];
        if (array_key_exists("\$preproc", $cached)) $param_preproc = $cached["\$preproc"];
      return $cached['result'];
    }
        $saved_preproc=$param_preproc;
    $p2 = $this->currPos;
    $r3 = [];
    for (;;) {
      // start choice_1
      $r4 = $this->parsetplarg_or_template($silence, 0x0, 0, self::newRef(null), $param_preproc);
      if ($r4!==self::$FAILED) {
        goto choice_1;
      }
      if ($this->currPos < $this->inputLength) {
        $r4 = self::consumeChar($this->input, $this->currPos);;
      } else {
        $r4 = self::$FAILED;
        if (!$silence) {$this->fail(7);}
      }
      choice_1:
      if ($r4!==self::$FAILED) {
        $r3[] = $r4;
      } else {
        break;
      }
    }
    if (count($r3) === 0) {
      $r3 = self::$FAILED;
    }
    // r <- $r3
    // free $r4
    $r1 = $r3;
    if ($r1!==self::$FAILED) {
      $this->savedPos = $p2;
      $r1 = $this->a13($r3);
    }
    $cached = ['nextPos' => $this->currPos, 'result' => $r1];
      if ($saved_preproc !== $param_preproc) $cached["\$preproc"] = $param_preproc;
    $this->cache[$bucket][$key] = $cached;
    return $r1;
  }
  private function parseextlink($silence, $boolParams, $param_templatedepth, &$param_preproc, &$param_th) {
    $key = json_encode([330, $boolParams & 0x19fe, $param_templatedepth, $param_preproc, $param_th]);
    $bucket = $this->currPos;
    $cached = $this->cache[$bucket][$key] ?? null;
    if ($cached) {
      $this->currPos = $cached['nextPos'];
        if (array_key_exists("\$preproc", $cached)) $param_preproc = $cached["\$preproc"];
        if (array_key_exists("\$th", $cached)) $param_th = $cached["\$th"];
      return $cached['result'];
    }
        $saved_preproc=$param_preproc;
        $saved_th=$param_th;
    $p2 = $this->currPos;
    // start seq_1
    $p3 = $this->currPos;
    if (!(/*extlink*/($boolParams & 0x4) !== 0)) {
      $r4 = false;
    } else {
      $r4 = self::$FAILED;
      $r1 = self::$FAILED;
      goto seq_1;
    }
    $p6 = $this->currPos;
    // start seq_2
    $p7 = $this->currPos;
    if (($this->input[$this->currPos] ?? null) === "[") {
      $this->currPos++;
      $r8 = "[";
    } else {
      $r8 = self::$FAILED;
      $r5 = self::$FAILED;
      goto seq_2;
    }
    $p10 = $this->currPos;
    $r9 = '';
    // p0 <- $r9
    if ($r9!==self::$FAILED) {
      $this->savedPos = $p10;
      $r9 = $this->a14();
    } else {
      $this->currPos = $p7;
      $r5 = self::$FAILED;
      goto seq_2;
    }
    // start choice_1
    // start seq_3
    $p12 = $this->currPos;
    $r13 = $this->parseurl_protocol(true);
    if ($r13===self::$FAILED) {
      $r11 = self::$FAILED;
      goto seq_3;
    }
    $r14 = $this->parseipv6urladdr(true);
    if ($r14===self::$FAILED) {
      $this->currPos = $p12;
      $r11 = self::$FAILED;
      goto seq_3;
    }
    $r11 = [$r13,$r14];
    seq_3:
    if ($r11!==self::$FAILED) {
      goto choice_1;
    }
    // free $p12
    $r11 = '';
    choice_1:
    // addr <- $r11
    if ($r11===self::$FAILED) {
      $this->currPos = $p7;
      $r5 = self::$FAILED;
      goto seq_2;
    }
    // start choice_2
    $r15 = $this->parseextlink_nonipv6url(true, $boolParams | 0x4, $param_templatedepth, $param_preproc, $param_th);
    if ($r15!==self::$FAILED) {
      goto choice_2;
    }
    $r15 = '';
    choice_2:
    // target <- $r15
    if ($r15===self::$FAILED) {
      $this->currPos = $p7;
      $r5 = self::$FAILED;
      goto seq_2;
    }
    $p12 = $this->currPos;
    $r16 = '';
    // p1 <- $r16
    if ($r16!==self::$FAILED) {
      $this->savedPos = $p12;
      $r16 = $this->a15($r9, $r11, $r15);
    } else {
      $this->currPos = $p7;
      $r5 = self::$FAILED;
      goto seq_2;
    }
    $this->savedPos = $this->currPos;
    $r17 = $this->a16($r9, $r11, $r15, $r16);
    if ($r17) {
      $r17 = false;
    } else {
      $r17 = self::$FAILED;
      $this->currPos = $p7;
      $r5 = self::$FAILED;
      goto seq_2;
    }
    $p19 = $this->currPos;
    for (;;) {
      // start choice_3
      $r20 = $this->discardspace(true);
      if ($r20!==self::$FAILED) {
        goto choice_3;
      }
      $r20 = $this->discardunispace(true);
      choice_3:
      if ($r20===self::$FAILED) {
        break;
      }
    }
    // free $r20
    $r18 = true;
    // sp <- $r18
    if ($r18!==self::$FAILED) {
      $r18 = substr($this->input, $p19, $this->currPos - $p19);
    } else {
      $r18 = self::$FAILED;
      $this->currPos = $p7;
      $r5 = self::$FAILED;
      goto seq_2;
    }
    // free $p19
    $p19 = $this->currPos;
    $r20 = '';
    // p2 <- $r20
    if ($r20!==self::$FAILED) {
      $this->savedPos = $p19;
      $r20 = $this->a17($r9, $r11, $r15, $r16, $r18);
    } else {
      $this->currPos = $p7;
      $r5 = self::$FAILED;
      goto seq_2;
    }
    $r21 = $this->parseinlineline(true, $boolParams | 0x4, $param_templatedepth, $param_preproc, $param_th);
    if ($r21===self::$FAILED) {
      $r21 = null;
    }
    // content <- $r21
    $p23 = $this->currPos;
    $r22 = '';
    // p3 <- $r22
    if ($r22!==self::$FAILED) {
      $this->savedPos = $p23;
      $r22 = $this->a18($r9, $r11, $r15, $r16, $r18, $r20, $r21);
    } else {
      $this->currPos = $p7;
      $r5 = self::$FAILED;
      goto seq_2;
    }
    if (($this->input[$this->currPos] ?? null) === "]") {
      $this->currPos++;
      $r24 = "]";
    } else {
      $r24 = self::$FAILED;
      $this->currPos = $p7;
      $r5 = self::$FAILED;
      goto seq_2;
    }
    $r5 = true;
    seq_2:
    // r <- $r5
    if ($r5!==self::$FAILED) {
      $this->savedPos = $p6;
      $r5 = $this->a19($r9, $r11, $r15, $r16, $r18, $r20, $r21, $r22);
    } else {
      $this->currPos = $p3;
      $r1 = self::$FAILED;
      goto seq_1;
    }
    // free $p7
    $r1 = true;
    seq_1:
    if ($r1!==self::$FAILED) {
      $this->savedPos = $p2;
      $r1 = $this->a20($r5);
    } else {
      if (!$silence) {$this->fail(8);}
    }
    // free $p3
    $cached = ['nextPos' => $this->currPos, 'result' => $r1];
      if ($saved_preproc !== $param_preproc) $cached["\$preproc"] = $param_preproc;
      if ($saved_th !== $param_th) $cached["\$th"] = $param_th;
    $this->cache[$bucket][$key] = $cached;
    return $r1;
  }
  private function parsetlb($silence, &$param_preproc) {
    $key = json_encode([296, $param_preproc]);
    $bucket = $this->currPos;
    $cached = $this->cache[$bucket][$key] ?? null;
    if ($cached) {
      $this->currPos = $cached['nextPos'];
        if (array_key_exists("\$preproc", $cached)) $param_preproc = $cached["\$preproc"];
      return $cached['result'];
    }
        $saved_preproc=$param_preproc;
    $p2 = $this->currPos;
    // start seq_1
    $p3 = $this->currPos;
    $p4 = $this->currPos;
    $r5 = $this->discardeof(true);
    if ($r5 === self::$FAILED) {
      $r5 = false;
    } else {
      $r5 = self::$FAILED;
      $this->currPos = $p4;
      $r1 = self::$FAILED;
      goto seq_1;
    }
    // free $p4
    $r6 = $this->parseblock(true, 0x0, 0, self::newRef(null), $param_preproc);
    // b <- $r6
    if ($r6===self::$FAILED) {
      $this->currPos = $p3;
      $r1 = self::$FAILED;
      goto seq_1;
    }
    $r1 = true;
    seq_1:
    if ($r1!==self::$FAILED) {
      $this->savedPos = $p2;
      $r1 = $this->a21($r6);
    } else {
      if (!$silence) {$this->fail(9);}
    }
    // free $p3
    $cached = ['nextPos' => $this->currPos, 'result' => $r1];
      if ($saved_preproc !== $param_preproc) $cached["\$preproc"] = $param_preproc;
    $this->cache[$bucket][$key] = $cached;
    return $r1;
  }
  private function parsenewlineToken($silence) {
    $key = 538;
    $bucket = $this->currPos;
    $cached = $this->cache[$bucket][$key] ?? null;
    if ($cached) {
      $this->currPos = $cached['nextPos'];
  
      return $cached['result'];
    }
  
    $p2 = $this->currPos;
    $r1 = $this->discardnewline($silence);
    if ($r1!==self::$FAILED) {
      $this->savedPos = $p2;
      $r1 = $this->a22();
    }
    $cached = ['nextPos' => $this->currPos, 'result' => $r1];
  
    $this->cache[$bucket][$key] = $cached;
    return $r1;
  }
  private function parsespace($silence) {
    $key = 502;
    $bucket = $this->currPos;
    $cached = $this->cache[$bucket][$key] ?? null;
    if ($cached) {
      $this->currPos = $cached['nextPos'];
  
      return $cached['result'];
    }
  
    $r1 = $this->input[$this->currPos] ?? '';
    if ($r1 === " " || $r1 === "\x09") {
      $this->currPos++;
    } else {
      $r1 = self::$FAILED;
      if (!$silence) {$this->fail(10);}
    }
    $cached = ['nextPos' => $this->currPos, 'result' => $r1];
  
    $this->cache[$bucket][$key] = $cached;
    return $r1;
  }
  private function parsecomment($silence) {
    $key = 322;
    $bucket = $this->currPos;
    $cached = $this->cache[$bucket][$key] ?? null;
    if ($cached) {
      $this->currPos = $cached['nextPos'];
  
      return $cached['result'];
    }
  
    $p2 = $this->currPos;
    // start seq_1
    $p3 = $this->currPos;
    if ($this->currPos >= $this->inputLength ? false : substr_compare($this->input, "<!--", $this->currPos, 4, false) === 0) {
      $r4 = "<!--";
      $this->currPos += 4;
    } else {
      if (!$silence) {$this->fail(11);}
      $r4 = self::$FAILED;
      $r1 = self::$FAILED;
      goto seq_1;
    }
    $p6 = $this->currPos;
    for (;;) {
      // start seq_2
      $p8 = $this->currPos;
      $p9 = $this->currPos;
      if ($this->currPos >= $this->inputLength ? false : substr_compare($this->input, "-->", $this->currPos, 3, false) === 0) {
        $r10 = "-->";
        $this->currPos += 3;
      } else {
        $r10 = self::$FAILED;
      }
      if ($r10 === self::$FAILED) {
        $r10 = false;
      } else {
        $r10 = self::$FAILED;
        $this->currPos = $p9;
        $r7 = self::$FAILED;
        goto seq_2;
      }
      // free $p9
      if ($this->currPos < $this->inputLength) {
        $r11 = self::consumeChar($this->input, $this->currPos);;
      } else {
        $r11 = self::$FAILED;
        if (!$silence) {$this->fail(7);}
        $this->currPos = $p8;
        $r7 = self::$FAILED;
        goto seq_2;
      }
      $r7 = true;
      seq_2:
      if ($r7===self::$FAILED) {
        break;
      }
      // free $p8
    }
    // free $r7
    $r5 = true;
    // c <- $r5
    if ($r5!==self::$FAILED) {
      $r5 = substr($this->input, $p6, $this->currPos - $p6);
    } else {
      $r5 = self::$FAILED;
      $this->currPos = $p3;
      $r1 = self::$FAILED;
      goto seq_1;
    }
    // free $p6
    // start choice_1
    if ($this->currPos >= $this->inputLength ? false : substr_compare($this->input, "-->", $this->currPos, 3, false) === 0) {
      $r7 = "-->";
      $this->currPos += 3;
      goto choice_1;
    } else {
      if (!$silence) {$this->fail(12);}
      $r7 = self::$FAILED;
    }
    $r7 = $this->discardeof($silence);
    choice_1:
    if ($r7===self::$FAILED) {
      $this->currPos = $p3;
      $r1 = self::$FAILED;
      goto seq_1;
    }
    $r1 = true;
    seq_1:
    if ($r1!==self::$FAILED) {
      $this->savedPos = $p2;
      $r1 = $this->a23($r5);
    }
    // free $p3
    $cached = ['nextPos' => $this->currPos, 'result' => $r1];
  
    $this->cache[$bucket][$key] = $cached;
    return $r1;
  }
  private function parsepipe($silence) {
    $key = 564;
    $bucket = $this->currPos;
    $cached = $this->cache[$bucket][$key] ?? null;
    if ($cached) {
      $this->currPos = $cached['nextPos'];
  
      return $cached['result'];
    }
  
    // start choice_1
    if (($this->input[$this->currPos] ?? null) === "|") {
      $this->currPos++;
      $r1 = "|";
      goto choice_1;
    } else {
      if (!$silence) {$this->fail(13);}
      $r1 = self::$FAILED;
    }
    if ($this->currPos >= $this->inputLength ? false : substr_compare($this->input, "{{!}}", $this->currPos, 5, false) === 0) {
      $r1 = "{{!}}";
      $this->currPos += 5;
    } else {
      if (!$silence) {$this->fail(14);}
      $r1 = self::$FAILED;
    }
    choice_1:
    $cached = ['nextPos' => $this->currPos, 'result' => $r1];
  
    $this->cache[$bucket][$key] = $cached;
    return $r1;
  }
  private function parseurl_protocol($silence) {
    $key = 336;
    $bucket = $this->currPos;
    $cached = $this->cache[$bucket][$key] ?? null;
    if ($cached) {
      $this->currPos = $cached['nextPos'];
  
      return $cached['result'];
    }
  
    $p2 = $this->currPos;
    // start seq_1
    $p3 = $this->currPos;
    $p5 = $this->currPos;
    // start choice_1
    if ($this->currPos >= $this->inputLength ? false : substr_compare($this->input, "//", $this->currPos, 2, false) === 0) {
      $r4 = "//";
      $this->currPos += 2;
      goto choice_1;
    } else {
      if (!$silence) {$this->fail(15);}
      $r4 = self::$FAILED;
    }
    // start seq_2
    $p6 = $this->currPos;
    $r7 = $this->input[$this->currPos] ?? '';
    if (preg_match("/^[A-Za-z]/", $r7)) {
      $this->currPos++;
    } else {
      $r7 = self::$FAILED;
      if (!$silence) {$this->fail(16);}
      $r4 = self::$FAILED;
      goto seq_2;
    }
    for (;;) {
      $r9 = $this->input[$this->currPos] ?? '';
      if (preg_match("/^[\\-A-Za-z0-9+.]/", $r9)) {
        $this->currPos++;
      } else {
        $r9 = self::$FAILED;
        if (!$silence) {$this->fail(17);}
        break;
      }
    }
    // free $r9
    $r8 = true;
    if ($r8===self::$FAILED) {
      $this->currPos = $p6;
      $r4 = self::$FAILED;
      goto seq_2;
    }
    // free $r8
    if (($this->input[$this->currPos] ?? null) === ":") {
      $this->currPos++;
      $r8 = ":";
    } else {
      if (!$silence) {$this->fail(18);}
      $r8 = self::$FAILED;
      $this->currPos = $p6;
      $r4 = self::$FAILED;
      goto seq_2;
    }
    if ($this->currPos >= $this->inputLength ? false : substr_compare($this->input, "//", $this->currPos, 2, false) === 0) {
      $r9 = "//";
      $this->currPos += 2;
    } else {
      if (!$silence) {$this->fail(15);}
      $r9 = self::$FAILED;
      $r9 = null;
    }
    $r4 = true;
    seq_2:
    // free $p6
    choice_1:
    // p <- $r4
    if ($r4!==self::$FAILED) {
      $r4 = substr($this->input, $p5, $this->currPos - $p5);
    } else {
      $r4 = self::$FAILED;
      $r1 = self::$FAILED;
      goto seq_1;
    }
    // free $p5
    $this->savedPos = $this->currPos;
    $r10 = $this->a24($r4);
    if ($r10) {
      $r10 = false;
    } else {
      $r10 = self::$FAILED;
      $this->currPos = $p3;
      $r1 = self::$FAILED;
      goto seq_1;
    }
    $r1 = true;
    seq_1:
    if ($r1!==self::$FAILED) {
      $this->savedPos = $p2;
      $r1 = $this->a25($r4);
    }
    // free $p3
    $cached = ['nextPos' => $this->currPos, 'result' => $r1];
  
    $this->cache[$bucket][$key] = $cached;
    return $r1;
  }
  private function parseipv6urladdr($silence) {
    $key = 344;
    $bucket = $this->currPos;
    $cached = $this->cache[$bucket][$key] ?? null;
    if ($cached) {
      $this->currPos = $cached['nextPos'];
  
      return $cached['result'];
    }
  
    $p1 = $this->currPos;
    // start seq_1
    $p3 = $this->currPos;
    if (($this->input[$this->currPos] ?? null) === "[") {
      $this->currPos++;
      $r4 = "[";
    } else {
      if (!$silence) {$this->fail(19);}
      $r4 = self::$FAILED;
      $r2 = self::$FAILED;
      goto seq_1;
    }
    $r5 = self::$FAILED;
    for (;;) {
      $r6 = $this->input[$this->currPos] ?? '';
      if (preg_match("/^[0-9A-Fa-f:.]/", $r6)) {
        $this->currPos++;
        $r5 = true;
      } else {
        $r6 = self::$FAILED;
        if (!$silence) {$this->fail(20);}
        break;
      }
    }
    if ($r5===self::$FAILED) {
      $this->currPos = $p3;
      $r2 = self::$FAILED;
      goto seq_1;
    }
    // free $r6
    if (($this->input[$this->currPos] ?? null) === "]") {
      $this->currPos++;
      $r6 = "]";
    } else {
      if (!$silence) {$this->fail(21);}
      $r6 = self::$FAILED;
      $this->currPos = $p3;
      $r2 = self::$FAILED;
      goto seq_1;
    }
    $r2 = true;
    seq_1:
    if ($r2!==self::$FAILED) {
      $r2 = substr($this->input, $p1, $this->currPos - $p1);
    } else {
      $r2 = self::$FAILED;
    }
    // free $p3
    // free $p1
    $cached = ['nextPos' => $this->currPos, 'result' => $r2];
  
    $this->cache[$bucket][$key] = $cached;
    return $r2;
  }
  private function discardinline_breaks($silence, $boolParams, $param_templatedepth, &$param_preproc, &$param_th) {
    $key = json_encode([315, $boolParams & 0x1bfe, $param_templatedepth, $param_preproc, $param_th]);
    $bucket = $this->currPos;
    $cached = $this->cache[$bucket][$key] ?? null;
    if ($cached) {
      $this->currPos = $cached['nextPos'];
        if (array_key_exists("\$preproc", $cached)) $param_preproc = $cached["\$preproc"];
        if (array_key_exists("\$th", $cached)) $param_th = $cached["\$th"];
      return $cached['result'];
    }
        $saved_preproc=$param_preproc;
        $saved_th=$param_th;
    // start seq_1
    $p1 = $this->currPos;
    $p3 = $this->currPos;
    if (strspn($this->input, "=|!{}:;\x0d\x0a[]-", $this->currPos, 1) !== 0) {
      $r4 = $this->input[$this->currPos++];
      $r4 = false;
      $this->currPos = $p3;
    } else {
      $r4 = self::$FAILED;
      $r2 = self::$FAILED;
      goto seq_1;
    }
    // free $p3
    // start seq_2
    $p3 = $this->currPos;
    $this->savedPos = $this->currPos;
    $r6 = $this->a26(/*extTag*/($boolParams & 0x800) !== 0, /*h*/($boolParams & 0x2) !== 0, /*extlink*/($boolParams & 0x4) !== 0, $param_templatedepth, $param_preproc, /*equal*/($boolParams & 0x8) !== 0, /*table*/($boolParams & 0x10) !== 0, /*templateArg*/($boolParams & 0x20) !== 0, /*tableCellArg*/($boolParams & 0x40) !== 0, /*semicolon*/($boolParams & 0x80) !== 0, /*arrow*/($boolParams & 0x100) !== 0, /*linkdesc*/($boolParams & 0x200) !== 0, /*colon*/($boolParams & 0x1000) !== 0, $param_th);
    if ($r6) {
      $r6 = false;
    } else {
      $r6 = self::$FAILED;
      $this->currPos = $p3;
      $r5 = self::$FAILED;
      goto seq_2;
    }
    $r5 = true;
    seq_2:
    if ($r5===self::$FAILED) {
      $this->currPos = $p1;
      $r2 = self::$FAILED;
      goto seq_1;
    }
    // free $r5,$p3
    $r2 = true;
    seq_1:
    // free $r2,$p1
    $cached = ['nextPos' => $this->currPos, 'result' => $r2];
      if ($saved_preproc !== $param_preproc) $cached["\$preproc"] = $param_preproc;
      if ($saved_th !== $param_th) $cached["\$th"] = $param_th;
    $this->cache[$bucket][$key] = $cached;
    return $r2;
  }
  private function parseno_punctuation_char($silence) {
    $key = 338;
    $bucket = $this->currPos;
    $cached = $this->cache[$bucket][$key] ?? null;
    if ($cached) {
      $this->currPos = $cached['nextPos'];
  
      return $cached['result'];
    }
  
    $r1 = self::charAt($this->input, $this->currPos);
    if (preg_match("/^[^ \\]\\[\\x0d\\x0a\"'<>\\x00- \\x7f&\\x{a0}\\x{1680}\\x{180e}\\x{2000}-\\x{200a}\\x{202f}\\x{205f}\\x{3000}{]/u", $r1)) {
      $this->currPos += strlen($r1);
    } else {
      $r1 = self::$FAILED;
      if (!$silence) {$this->fail(22);}
    }
    $cached = ['nextPos' => $this->currPos, 'result' => $r1];
  
    $this->cache[$bucket][$key] = $cached;
    return $r1;
  }
  private function parsetplarg_or_template($silence, $boolParams, $param_templatedepth, &$param_th, &$param_preproc) {
    $key = json_encode([346, $boolParams & 0x1bfe, $param_templatedepth, $param_th, $param_preproc]);
    $bucket = $this->currPos;
    $cached = $this->cache[$bucket][$key] ?? null;
    if ($cached) {
      $this->currPos = $cached['nextPos'];
        if (array_key_exists("\$th", $cached)) $param_th = $cached["\$th"];
        if (array_key_exists("\$preproc", $cached)) $param_preproc = $cached["\$preproc"];
      return $cached['result'];
    }
        $saved_th=$param_th;
        $saved_preproc=$param_preproc;
    $p2 = $this->currPos;
    // start seq_1
    $p3 = $this->currPos;
    $p4 = $this->currPos;
    if ($this->currPos >= $this->inputLength ? false : substr_compare($this->input, "{{", $this->currPos, 2, false) === 0) {
      $r5 = "{{";
      $this->currPos += 2;
      $r5 = false;
      $this->currPos = $p4;
    } else {
      $r5 = self::$FAILED;
      $r1 = self::$FAILED;
      goto seq_1;
    }
    // free $p4
    $this->savedPos = $this->currPos;
    $r6 = $this->a27($param_templatedepth);
    if ($r6) {
      $r6 = false;
    } else {
      $r6 = self::$FAILED;
      $this->currPos = $p3;
      $r1 = self::$FAILED;
      goto seq_1;
    }
    $r7 = $this->parsetplarg_or_template_guarded($silence, $boolParams, $param_templatedepth + 1, $param_th, $param_preproc);
    // t <- $r7
    if ($r7===self::$FAILED) {
      $this->currPos = $p3;
      $r1 = self::$FAILED;
      goto seq_1;
    }
    $r1 = true;
    seq_1:
    if ($r1!==self::$FAILED) {
      $this->savedPos = $p2;
      $r1 = $this->a28($param_templatedepth, $r7);
    }
    // free $p3
    $cached = ['nextPos' => $this->currPos, 'result' => $r1];
      if ($saved_th !== $param_th) $cached["\$th"] = $param_th;
      if ($saved_preproc !== $param_preproc) $cached["\$preproc"] = $param_preproc;
    $this->cache[$bucket][$key] = $cached;
    return $r1;
  }
  private function parsehtmlentity($silence) {
    $key = 496;
    $bucket = $this->currPos;
    $cached = $this->cache[$bucket][$key] ?? null;
    if ($cached) {
      $this->currPos = $cached['nextPos'];
  
      return $cached['result'];
    }
  
    $p2 = $this->currPos;
    $r3 = $this->parseraw_htmlentity($silence);
    // cc <- $r3
    $r1 = $r3;
    if ($r1!==self::$FAILED) {
      $this->savedPos = $p2;
      $r1 = $this->a29($r3);
    }
    $cached = ['nextPos' => $this->currPos, 'result' => $r1];
  
    $this->cache[$bucket][$key] = $cached;
    return $r1;
  }
  private function parseoptional_spaces($silence) {
    $key = 500;
    $bucket = $this->currPos;
    $cached = $this->cache[$bucket][$key] ?? null;
    if ($cached) {
      $this->currPos = $cached['nextPos'];
  
      return $cached['result'];
    }
  
    $p1 = $this->currPos;
    for (;;) {
      $r3 = $this->input[$this->currPos] ?? '';
      if ($r3 === " " || $r3 === "\x09") {
        $this->currPos++;
      } else {
        $r3 = self::$FAILED;
        if (!$silence) {$this->fail(10);}
        break;
      }
    }
    // free $r3
    $r2 = true;
    if ($r2!==self::$FAILED) {
      $r2 = substr($this->input, $p1, $this->currPos - $p1);
    } else {
      $r2 = self::$FAILED;
    }
    // free $p1
    $cached = ['nextPos' => $this->currPos, 'result' => $r2];
  
    $this->cache[$bucket][$key] = $cached;
    return $r2;
  }
  private function discardpipe($silence) {
    $key = 565;
    $bucket = $this->currPos;
    $cached = $this->cache[$bucket][$key] ?? null;
    if ($cached) {
      $this->currPos = $cached['nextPos'];
  
      return $cached['result'];
    }
  
    // start choice_1
    if (($this->input[$this->currPos] ?? null) === "|") {
      $this->currPos++;
      $r1 = "|";
      goto choice_1;
    } else {
      if (!$silence) {$this->fail(13);}
      $r1 = self::$FAILED;
    }
    if ($this->currPos >= $this->inputLength ? false : substr_compare($this->input, "{{!}}", $this->currPos, 5, false) === 0) {
      $r1 = "{{!}}";
      $this->currPos += 5;
    } else {
      if (!$silence) {$this->fail(14);}
      $r1 = self::$FAILED;
    }
    choice_1:
    $cached = ['nextPos' => $this->currPos, 'result' => $r1];
  
    $this->cache[$bucket][$key] = $cached;
    return $r1;
  }
  private function parsetable_attribute($silence, $boolParams, $param_templatedepth, &$param_preproc, &$param_th) {
    $key = json_encode([434, $boolParams & 0x3bfe, $param_templatedepth, $param_preproc, $param_th]);
    $bucket = $this->currPos;
    $cached = $this->cache[$bucket][$key] ?? null;
    if ($cached) {
      $this->currPos = $cached['nextPos'];
        if (array_key_exists("\$preproc", $cached)) $param_preproc = $cached["\$preproc"];
        if (array_key_exists("\$th", $cached)) $param_th = $cached["\$th"];
      return $cached['result'];
    }
        $saved_preproc=$param_preproc;
        $saved_th=$param_th;
    $p2 = $this->currPos;
    // start seq_1
    $p3 = $this->currPos;
    $r4 = $this->parseoptionalSpaceToken($silence);
    // s <- $r4
    if ($r4===self::$FAILED) {
      $r1 = self::$FAILED;
      goto seq_1;
    }
    $p6 = $this->currPos;
    $r5 = '';
    // namePos0 <- $r5
    if ($r5!==self::$FAILED) {
      $this->savedPos = $p6;
      $r5 = $this->a30($r4);
    } else {
      $this->currPos = $p3;
      $r1 = self::$FAILED;
      goto seq_1;
    }
    $r7 = $this->parsetable_attribute_name($silence, $boolParams, $param_templatedepth, $param_preproc, $param_th);
    // name <- $r7
    if ($r7===self::$FAILED) {
      $this->currPos = $p3;
      $r1 = self::$FAILED;
      goto seq_1;
    }
    $p9 = $this->currPos;
    $r8 = '';
    // namePos <- $r8
    if ($r8!==self::$FAILED) {
      $this->savedPos = $p9;
      $r8 = $this->a31($r4, $r5, $r7);
    } else {
      $this->currPos = $p3;
      $r1 = self::$FAILED;
      goto seq_1;
    }
    $p11 = $this->currPos;
    // start seq_2
    $p12 = $this->currPos;
    $r13 = $this->discardoptionalSpaceToken($silence);
    if ($r13===self::$FAILED) {
      $r10 = self::$FAILED;
      goto seq_2;
    }
    if (($this->input[$this->currPos] ?? null) === "=") {
      $this->currPos++;
      $r14 = "=";
    } else {
      if (!$silence) {$this->fail(23);}
      $r14 = self::$FAILED;
      $this->currPos = $p12;
      $r10 = self::$FAILED;
      goto seq_2;
    }
    $r15 = $this->parsetable_att_value($silence, $boolParams, $param_templatedepth, $param_preproc, $param_th);
    if ($r15===self::$FAILED) {
      $r15 = null;
    }
    // v <- $r15
    $r10 = true;
    seq_2:
    if ($r10!==self::$FAILED) {
      $this->savedPos = $p11;
      $r10 = $this->a32($r4, $r5, $r7, $r8, $r15);
    } else {
      $r10 = null;
    }
    // free $p12
    // vd <- $r10
    $r1 = true;
    seq_1:
    if ($r1!==self::$FAILED) {
      $this->savedPos = $p2;
      $r1 = $this->a33($r4, $r5, $r7, $r8, $r10);
    }
    // free $p3
    $cached = ['nextPos' => $this->currPos, 'result' => $r1];
      if ($saved_preproc !== $param_preproc) $cached["\$preproc"] = $param_preproc;
      if ($saved_th !== $param_th) $cached["\$th"] = $param_th;
    $this->cache[$bucket][$key] = $cached;
    return $r1;
  }
  private function discardoptionalSpaceToken($silence) {
    $key = 505;
    $bucket = $this->currPos;
    $cached = $this->cache[$bucket][$key] ?? null;
    if ($cached) {
      $this->currPos = $cached['nextPos'];
  
      return $cached['result'];
    }
  
    $p2 = $this->currPos;
    $r3 = $this->parseoptional_spaces($silence);
    // s <- $r3
    $r1 = $r3;
    if ($r1!==self::$FAILED) {
      $this->savedPos = $p2;
      $r1 = $this->a34($r3);
    }
    $cached = ['nextPos' => $this->currPos, 'result' => $r1];
  
    $this->cache[$bucket][$key] = $cached;
    return $r1;
  }
  private function parsebroken_table_attribute_name_char($silence) {
    $key = 440;
    $bucket = $this->currPos;
    $cached = $this->cache[$bucket][$key] ?? null;
    if ($cached) {
      $this->currPos = $cached['nextPos'];
  
      return $cached['result'];
    }
  
    $p2 = $this->currPos;
    // c <- $r3
    if (strspn($this->input, "\x00/=>", $this->currPos, 1) !== 0) {
      $r3 = $this->input[$this->currPos++];
    } else {
      $r3 = self::$FAILED;
      if (!$silence) {$this->fail(24);}
    }
    $r1 = $r3;
    if ($r1!==self::$FAILED) {
      $this->savedPos = $p2;
      $r1 = $this->a35($r3);
    }
    $cached = ['nextPos' => $this->currPos, 'result' => $r1];
  
    $this->cache[$bucket][$key] = $cached;
    return $r1;
  }
  private function parsegeneric_newline_attribute($silence, $boolParams, $param_templatedepth, &$param_preproc, &$param_th) {
    $key = json_encode([432, $boolParams & 0x1bfe, $param_templatedepth, $param_preproc, $param_th]);
    $bucket = $this->currPos;
    $cached = $this->cache[$bucket][$key] ?? null;
    if ($cached) {
      $this->currPos = $cached['nextPos'];
        if (array_key_exists("\$preproc", $cached)) $param_preproc = $cached["\$preproc"];
        if (array_key_exists("\$th", $cached)) $param_th = $cached["\$th"];
      return $cached['result'];
    }
        $saved_preproc=$param_preproc;
        $saved_th=$param_th;
    $p2 = $this->currPos;
    // start seq_1
    $p3 = $this->currPos;
    for (;;) {
      $r5 = $this->discardspace_or_newline_or_solidus($silence);
      if ($r5===self::$FAILED) {
        break;
      }
    }
    // free $r5
    $r4 = true;
    if ($r4===self::$FAILED) {
      $r1 = self::$FAILED;
      goto seq_1;
    }
    // free $r4
    $p6 = $this->currPos;
    $r4 = '';
    // namePos0 <- $r4
    if ($r4!==self::$FAILED) {
      $this->savedPos = $p6;
      $r4 = $this->a14();
    } else {
      $this->currPos = $p3;
      $r1 = self::$FAILED;
      goto seq_1;
    }
    $r5 = $this->parsegeneric_attribute_name($silence, $boolParams, $param_templatedepth, $param_preproc, $param_th);
    // name <- $r5
    if ($r5===self::$FAILED) {
      $this->currPos = $p3;
      $r1 = self::$FAILED;
      goto seq_1;
    }
    $p8 = $this->currPos;
    $r7 = '';
    // namePos <- $r7
    if ($r7!==self::$FAILED) {
      $this->savedPos = $p8;
      $r7 = $this->a36($r4, $r5);
    } else {
      $this->currPos = $p3;
      $r1 = self::$FAILED;
      goto seq_1;
    }
    $p10 = $this->currPos;
    // start seq_2
    $p11 = $this->currPos;
    for (;;) {
      $r13 = $this->discardspace_or_newline($silence);
      if ($r13===self::$FAILED) {
        break;
      }
    }
    // free $r13
    $r12 = true;
    if ($r12===self::$FAILED) {
      $r9 = self::$FAILED;
      goto seq_2;
    }
    // free $r12
    if (($this->input[$this->currPos] ?? null) === "=") {
      $this->currPos++;
      $r12 = "=";
    } else {
      if (!$silence) {$this->fail(23);}
      $r12 = self::$FAILED;
      $this->currPos = $p11;
      $r9 = self::$FAILED;
      goto seq_2;
    }
    $r13 = $this->parsegeneric_att_value($silence, $boolParams, $param_templatedepth, $param_preproc, $param_th);
    if ($r13===self::$FAILED) {
      $r13 = null;
    }
    // v <- $r13
    $r9 = true;
    seq_2:
    if ($r9!==self::$FAILED) {
      $this->savedPos = $p10;
      $r9 = $this->a37($r4, $r5, $r7, $r13);
    } else {
      $r9 = null;
    }
    // free $p11
    // vd <- $r9
    $r1 = true;
    seq_1:
    if ($r1!==self::$FAILED) {
      $this->savedPos = $p2;
      $r1 = $this->a38($r4, $r5, $r7, $r9);
    }
    // free $p3
    $cached = ['nextPos' => $this->currPos, 'result' => $r1];
      if ($saved_preproc !== $param_preproc) $cached["\$preproc"] = $param_preproc;
      if ($saved_th !== $param_th) $cached["\$th"] = $param_th;
    $this->cache[$bucket][$key] = $cached;
    return $r1;
  }
  private function parseextlink_nonipv6url($silence, $boolParams, $param_templatedepth, &$param_preproc, &$param_th) {
    $key = json_encode([548, $boolParams & 0x19fe, $param_templatedepth, $param_preproc, $param_th]);
    $bucket = $this->currPos;
    $cached = $this->cache[$bucket][$key] ?? null;
    if ($cached) {
      $this->currPos = $cached['nextPos'];
        if (array_key_exists("\$preproc", $cached)) $param_preproc = $cached["\$preproc"];
        if (array_key_exists("\$th", $cached)) $param_th = $cached["\$th"];
      return $cached['result'];
    }
        $saved_preproc=$param_preproc;
        $saved_th=$param_th;
    $r1 = $this->parseextlink_nonipv6url_parameterized($silence, $boolParams & ~0x200, $param_templatedepth, $param_preproc, $param_th);
    $cached = ['nextPos' => $this->currPos, 'result' => $r1];
      if ($saved_preproc !== $param_preproc) $cached["\$preproc"] = $param_preproc;
      if ($saved_th !== $param_th) $cached["\$th"] = $param_th;
    $this->cache[$bucket][$key] = $cached;
    return $r1;
  }
  private function discardspace($silence) {
    $key = 503;
    $bucket = $this->currPos;
    $cached = $this->cache[$bucket][$key] ?? null;
    if ($cached) {
      $this->currPos = $cached['nextPos'];
  
      return $cached['result'];
    }
  
    $r1 = $this->input[$this->currPos] ?? '';
    if ($r1 === " " || $r1 === "\x09") {
      $this->currPos++;
    } else {
      $r1 = self::$FAILED;
      if (!$silence) {$this->fail(10);}
    }
    $cached = ['nextPos' => $this->currPos, 'result' => $r1];
  
    $this->cache[$bucket][$key] = $cached;
    return $r1;
  }
  private function discardunispace($silence) {
    $key = 511;
    $bucket = $this->currPos;
    $cached = $this->cache[$bucket][$key] ?? null;
    if ($cached) {
      $this->currPos = $cached['nextPos'];
  
      return $cached['result'];
    }
  
    $r1 = self::charAt($this->input, $this->currPos);
    if (preg_match("/^[ \\x{a0}\\x{1680}\\x{2000}-\\x{200a}\\x{202f}\\x{205f}\\x{3000}]/u", $r1)) {
      $this->currPos += strlen($r1);
    } else {
      $r1 = self::$FAILED;
      if (!$silence) {$this->fail(25);}
    }
    $cached = ['nextPos' => $this->currPos, 'result' => $r1];
  
    $this->cache[$bucket][$key] = $cached;
    return $r1;
  }
  private function parseinlineline($silence, $boolParams, $param_templatedepth, &$param_preproc, &$param_th) {
    $key = json_encode([316, $boolParams & 0x1bfe, $param_templatedepth, $param_preproc, $param_th]);
    $bucket = $this->currPos;
    $cached = $this->cache[$bucket][$key] ?? null;
    if ($cached) {
      $this->currPos = $cached['nextPos'];
        if (array_key_exists("\$preproc", $cached)) $param_preproc = $cached["\$preproc"];
        if (array_key_exists("\$th", $cached)) $param_th = $cached["\$th"];
      return $cached['result'];
    }
        $saved_preproc=$param_preproc;
        $saved_th=$param_th;
    $p2 = $this->currPos;
    $r3 = [];
    for (;;) {
      // start choice_1
      $r4 = $this->parseurltext($silence, $boolParams, $param_templatedepth, $param_preproc, $param_th);
      if ($r4!==self::$FAILED) {
        goto choice_1;
      }
      $p5 = $this->currPos;
      // start seq_1
      $p6 = $this->currPos;
      $p7 = $this->currPos;
      $r8 = $this->discardinline_breaks(true, $boolParams, $param_templatedepth, $param_preproc, $param_th);
      if ($r8 === self::$FAILED) {
        $r8 = false;
      } else {
        $r8 = self::$FAILED;
        $this->currPos = $p7;
        $r4 = self::$FAILED;
        goto seq_1;
      }
      // free $p7
      // start choice_2
      $r9 = $this->parseinline_element($silence, $boolParams, $param_templatedepth, $param_preproc, $param_th);
      if ($r9!==self::$FAILED) {
        goto choice_2;
      }
      $p7 = $this->currPos;
      // start seq_2
      $p10 = $this->currPos;
      $p11 = $this->currPos;
      $r12 = $this->discardnewline(true);
      if ($r12 === self::$FAILED) {
        $r12 = false;
      } else {
        $r12 = self::$FAILED;
        $this->currPos = $p11;
        $r9 = self::$FAILED;
        goto seq_2;
      }
      // free $p11
      // s <- $r13
      if ($this->currPos < $this->inputLength) {
        $r13 = self::consumeChar($this->input, $this->currPos);;
      } else {
        $r13 = self::$FAILED;
        if (!$silence) {$this->fail(7);}
        $this->currPos = $p10;
        $r9 = self::$FAILED;
        goto seq_2;
      }
      $r9 = true;
      seq_2:
      if ($r9!==self::$FAILED) {
        $this->savedPos = $p7;
        $r9 = $this->a39($r13);
      }
      // free $p10
      choice_2:
      // r <- $r9
      if ($r9===self::$FAILED) {
        $this->currPos = $p6;
        $r4 = self::$FAILED;
        goto seq_1;
      }
      $r4 = true;
      seq_1:
      if ($r4!==self::$FAILED) {
        $this->savedPos = $p5;
        $r4 = $this->a20($r9);
      }
      // free $p6
      choice_1:
      if ($r4!==self::$FAILED) {
        $r3[] = $r4;
      } else {
        break;
      }
    }
    if (count($r3) === 0) {
      $r3 = self::$FAILED;
    }
    // c <- $r3
    // free $r4
    $r1 = $r3;
    if ($r1!==self::$FAILED) {
      $this->savedPos = $p2;
      $r1 = $this->a40($r3);
    }
    $cached = ['nextPos' => $this->currPos, 'result' => $r1];
      if ($saved_preproc !== $param_preproc) $cached["\$preproc"] = $param_preproc;
      if ($saved_th !== $param_th) $cached["\$th"] = $param_th;
    $this->cache[$bucket][$key] = $cached;
    return $r1;
  }
  private function discardeof($silence) {
    $key = 535;
    $bucket = $this->currPos;
    $cached = $this->cache[$bucket][$key] ?? null;
    if ($cached) {
      $this->currPos = $cached['nextPos'];
  
      return $cached['result'];
    }
  
    $this->savedPos = $this->currPos;
    $r1 = $this->a41();
    if ($r1) {
      $r1 = false;
    } else {
      $r1 = self::$FAILED;
    }
    $cached = ['nextPos' => $this->currPos, 'result' => $r1];
  
    $this->cache[$bucket][$key] = $cached;
    return $r1;
  }
  private function parseblock($silence, $boolParams, $param_templatedepth, &$param_th, &$param_preproc) {
    $key = json_encode([298, $boolParams & 0x3bff, $param_templatedepth, $param_th, $param_preproc]);
    $bucket = $this->currPos;
    $cached = $this->cache[$bucket][$key] ?? null;
    if ($cached) {
      $this->currPos = $cached['nextPos'];
        if (array_key_exists("\$th", $cached)) $param_th = $cached["\$th"];
        if (array_key_exists("\$preproc", $cached)) $param_preproc = $cached["\$preproc"];
      return $cached['result'];
    }
        $saved_th=$param_th;
        $saved_preproc=$param_preproc;
    // start choice_1
    $p2 = $this->currPos;
    // start seq_1
    $p3 = $this->currPos;
    $p4 = $this->currPos;
    $r5 = $this->discardsof(true);
    if ($r5!==self::$FAILED) {
      $r5 = false;
      $this->currPos = $p4;
    } else {
      $r1 = self::$FAILED;
      goto seq_1;
    }
    // free $p4
    $r6 = $this->parseredirect($silence, $boolParams, $param_templatedepth, $param_th, $param_preproc);
    // r <- $r6
    if ($r6===self::$FAILED) {
      $this->currPos = $p3;
      $r1 = self::$FAILED;
      goto seq_1;
    }
    $r7 = $this->parsecomment_or_includes($silence, $boolParams, $param_templatedepth, $param_preproc, $param_th);
    // cil <- $r7
    if ($r7===self::$FAILED) {
      $this->currPos = $p3;
      $r1 = self::$FAILED;
      goto seq_1;
    }
    $r8 = $this->parseblock_line($silence, $boolParams, $param_templatedepth, $param_preproc, $param_th);
    if ($r8===self::$FAILED) {
      $r8 = null;
    }
    // bl <- $r8
    $r1 = true;
    seq_1:
    if ($r1!==self::$FAILED) {
      $this->savedPos = $p2;
      $r1 = $this->a42($r6, $r7, $r8);
      goto choice_1;
    }
    // free $p3
    $r1 = $this->parseblock_lines($silence, $boolParams, $param_templatedepth, $param_preproc, $param_th);
    if ($r1!==self::$FAILED) {
      goto choice_1;
    }
    $p3 = $this->currPos;
    // start seq_2
    $p4 = $this->currPos;
    $p9 = $this->currPos;
    if (($this->input[$this->currPos] ?? null) === "<") {
      $this->currPos++;
      $r10 = "<";
      $r10 = false;
      $this->currPos = $p9;
    } else {
      $r10 = self::$FAILED;
      $r1 = self::$FAILED;
      goto seq_2;
    }
    // free $p9
    // start choice_2
    $p9 = $this->currPos;
    // start seq_3
    $p12 = $this->currPos;
    $r13 = $this->parsecomment($silence);
    // c <- $r13
    if ($r13===self::$FAILED) {
      $r11 = self::$FAILED;
      goto seq_3;
    }
    $p14 = $this->currPos;
    $r15 = $this->discardeolf(true);
    if ($r15!==self::$FAILED) {
      $r15 = false;
      $this->currPos = $p14;
    } else {
      $this->currPos = $p12;
      $r11 = self::$FAILED;
      goto seq_3;
    }
    // free $p14
    $r11 = true;
    seq_3:
    if ($r11!==self::$FAILED) {
      $this->savedPos = $p9;
      $r11 = $this->a43($r13);
      goto choice_2;
    }
    // free $p12
    $r16 = $this->parseblock_tag($silence, $boolParams, $param_templatedepth, $param_preproc, $param_th);
    // bt <- $r16
    $r11 = $r16;
    choice_2:
    // rs <- $r11
    if ($r11===self::$FAILED) {
      $this->currPos = $p4;
      $r1 = self::$FAILED;
      goto seq_2;
    }
    $r1 = true;
    seq_2:
    if ($r1!==self::$FAILED) {
      $this->savedPos = $p3;
      $r1 = $this->a44($r11);
      goto choice_1;
    }
    // free $p4
    $r1 = $this->parseparagraph($silence, $boolParams, $param_templatedepth, $param_preproc, $param_th);
    if ($r1!==self::$FAILED) {
      goto choice_1;
    }
    $r1 = $this->parseinlineline($silence, $boolParams, $param_templatedepth, $param_preproc, $param_th);
    if ($r1!==self::$FAILED) {
      goto choice_1;
    }
    $p4 = $this->currPos;
    // start seq_4
    $p12 = $this->currPos;
    $r17 = $this->parsesol($silence, $boolParams, $param_templatedepth, $param_preproc, $param_th);
    // s <- $r17
    if ($r17===self::$FAILED) {
      $r1 = self::$FAILED;
      goto seq_4;
    }
    $p14 = $this->currPos;
    $r18 = $this->discardsof(true);
    if ($r18 === self::$FAILED) {
      $r18 = false;
    } else {
      $r18 = self::$FAILED;
      $this->currPos = $p14;
      $this->currPos = $p12;
      $r1 = self::$FAILED;
      goto seq_4;
    }
    // free $p14
    $p14 = $this->currPos;
    $r19 = $this->discardinline_breaks(true, $boolParams, $param_templatedepth, $param_preproc, $param_th);
    if ($r19 === self::$FAILED) {
      $r19 = false;
    } else {
      $r19 = self::$FAILED;
      $this->currPos = $p14;
      $this->currPos = $p12;
      $r1 = self::$FAILED;
      goto seq_4;
    }
    // free $p14
    $r1 = true;
    seq_4:
    if ($r1!==self::$FAILED) {
      $this->savedPos = $p4;
      $r1 = $this->a39($r17);
    }
    // free $p12
    choice_1:
    $cached = ['nextPos' => $this->currPos, 'result' => $r1];
      if ($saved_th !== $param_th) $cached["\$th"] = $param_th;
      if ($saved_preproc !== $param_preproc) $cached["\$preproc"] = $param_preproc;
    $this->cache[$bucket][$key] = $cached;
    return $r1;
  }
  private function discardnewline($silence) {
    $key = 537;
    $bucket = $this->currPos;
    $cached = $this->cache[$bucket][$key] ?? null;
    if ($cached) {
      $this->currPos = $cached['nextPos'];
  
      return $cached['result'];
    }
  
    // start choice_1
    if (($this->input[$this->currPos] ?? null) === "\x0a") {
      $this->currPos++;
      $r1 = "\x0a";
      goto choice_1;
    } else {
      if (!$silence) {$this->fail(26);}
      $r1 = self::$FAILED;
    }
    if ($this->currPos >= $this->inputLength ? false : substr_compare($this->input, "\x0d\x0a", $this->currPos, 2, false) === 0) {
      $r1 = "\x0d\x0a";
      $this->currPos += 2;
    } else {
      if (!$silence) {$this->fail(27);}
      $r1 = self::$FAILED;
    }
    choice_1:
    $cached = ['nextPos' => $this->currPos, 'result' => $r1];
  
    $this->cache[$bucket][$key] = $cached;
    return $r1;
  }
  private function parsetplarg_or_template_guarded($silence, $boolParams, $param_templatedepth, &$param_th, &$param_preproc) {
    $key = json_encode([348, $boolParams & 0x1bfe, $param_templatedepth, $param_th, $param_preproc]);
    $bucket = $this->currPos;
    $cached = $this->cache[$bucket][$key] ?? null;
    if ($cached) {
      $this->currPos = $cached['nextPos'];
        if (array_key_exists("\$th", $cached)) $param_th = $cached["\$th"];
        if (array_key_exists("\$preproc", $cached)) $param_preproc = $cached["\$preproc"];
      return $cached['result'];
    }
        $saved_th=$param_th;
        $saved_preproc=$param_preproc;
    // start choice_1
    $p2 = $this->currPos;
    // start seq_1
    $p3 = $this->currPos;
    $p4 = $this->currPos;
    // start seq_2
    $p6 = $this->currPos;
    if ($this->currPos >= $this->inputLength ? false : substr_compare($this->input, "{{", $this->currPos, 2, false) === 0) {
      $r7 = "{{";
      $this->currPos += 2;
    } else {
      $r7 = self::$FAILED;
      $r5 = self::$FAILED;
      goto seq_2;
    }
    $p8 = $this->currPos;
    // start seq_3
    $p10 = $this->currPos;
    $r11 = self::$FAILED;
    for (;;) {
      if ($this->currPos >= $this->inputLength ? false : substr_compare($this->input, "{{{", $this->currPos, 3, false) === 0) {
        $r12 = "{{{";
        $this->currPos += 3;
        $r11 = true;
      } else {
        $r12 = self::$FAILED;
        break;
      }
    }
    if ($r11===self::$FAILED) {
      $r9 = self::$FAILED;
      goto seq_3;
    }
    // free $r12
    $p13 = $this->currPos;
    if (($this->input[$this->currPos] ?? null) === "{") {
      $this->currPos++;
      $r12 = "{";
    } else {
      $r12 = self::$FAILED;
    }
    if ($r12 === self::$FAILED) {
      $r12 = false;
    } else {
      $r12 = self::$FAILED;
      $this->currPos = $p13;
      $this->currPos = $p10;
      $r9 = self::$FAILED;
      goto seq_3;
    }
    // free $p13
    $r9 = true;
    seq_3:
    if ($r9!==self::$FAILED) {
      $r9 = false;
      $this->currPos = $p8;
    } else {
      $this->currPos = $p6;
      $r5 = self::$FAILED;
      goto seq_2;
    }
    // free $p10
    // free $p8
    $r14 = $this->discardtplarg(true, $boolParams, $param_templatedepth, $param_th);
    if ($r14===self::$FAILED) {
      $this->currPos = $p6;
      $r5 = self::$FAILED;
      goto seq_2;
    }
    $r5 = true;
    seq_2:
    if ($r5!==self::$FAILED) {
      $r5 = false;
      $this->currPos = $p4;
    } else {
      $r1 = self::$FAILED;
      goto seq_1;
    }
    // free $p6
    // free $p4
    // start choice_2
    $r15 = $this->parsetemplate($silence, $boolParams, $param_templatedepth, $param_th);
    if ($r15!==self::$FAILED) {
      goto choice_2;
    }
    $r15 = $this->parsebroken_template($silence, $param_preproc);
    choice_2:
    // a <- $r15
    if ($r15===self::$FAILED) {
      $this->currPos = $p3;
      $r1 = self::$FAILED;
      goto seq_1;
    }
    $r1 = true;
    seq_1:
    if ($r1!==self::$FAILED) {
      $this->savedPos = $p2;
      $r1 = $this->a45($r15);
      goto choice_1;
    }
    // free $p3
    $p3 = $this->currPos;
    // start seq_4
    $p4 = $this->currPos;
    $p6 = $this->currPos;
    // start seq_5
    $p8 = $this->currPos;
    if (($this->input[$this->currPos] ?? null) === "{") {
      $this->currPos++;
      $r17 = "{";
    } else {
      if (!$silence) {$this->fail(28);}
      $r17 = self::$FAILED;
      $r16 = self::$FAILED;
      goto seq_5;
    }
    $p10 = $this->currPos;
    // start seq_6
    $p13 = $this->currPos;
    $r19 = self::$FAILED;
    for (;;) {
      if ($this->currPos >= $this->inputLength ? false : substr_compare($this->input, "{{{", $this->currPos, 3, false) === 0) {
        $r20 = "{{{";
        $this->currPos += 3;
        $r19 = true;
      } else {
        $r20 = self::$FAILED;
        break;
      }
    }
    if ($r19===self::$FAILED) {
      $r18 = self::$FAILED;
      goto seq_6;
    }
    // free $r20
    $p21 = $this->currPos;
    if (($this->input[$this->currPos] ?? null) === "{") {
      $this->currPos++;
      $r20 = "{";
    } else {
      $r20 = self::$FAILED;
    }
    if ($r20 === self::$FAILED) {
      $r20 = false;
    } else {
      $r20 = self::$FAILED;
      $this->currPos = $p21;
      $this->currPos = $p13;
      $r18 = self::$FAILED;
      goto seq_6;
    }
    // free $p21
    $r18 = true;
    seq_6:
    if ($r18!==self::$FAILED) {
      $r18 = false;
      $this->currPos = $p10;
    } else {
      $this->currPos = $p8;
      $r16 = self::$FAILED;
      goto seq_5;
    }
    // free $p13
    // free $p10
    $r16 = true;
    seq_5:
    if ($r16===self::$FAILED) {
      $r16 = null;
    }
    // free $p8
    // a <- $r16
    $r16 = substr($this->input, $p6, $this->currPos - $p6);
    // free $p6
    $r22 = $this->parsetplarg($silence, $boolParams, $param_templatedepth, $param_th);
    // b <- $r22
    if ($r22===self::$FAILED) {
      $this->currPos = $p4;
      $r1 = self::$FAILED;
      goto seq_4;
    }
    $r1 = true;
    seq_4:
    if ($r1!==self::$FAILED) {
      $this->savedPos = $p3;
      $r1 = $this->a46($r16, $r22);
      goto choice_1;
    }
    // free $p4
    $p4 = $this->currPos;
    // start seq_7
    $p6 = $this->currPos;
    $p8 = $this->currPos;
    // start seq_8
    $p10 = $this->currPos;
    if (($this->input[$this->currPos] ?? null) === "{") {
      $this->currPos++;
      $r24 = "{";
    } else {
      if (!$silence) {$this->fail(28);}
      $r24 = self::$FAILED;
      $r23 = self::$FAILED;
      goto seq_8;
    }
    $p13 = $this->currPos;
    // start seq_9
    $p21 = $this->currPos;
    if ($this->currPos >= $this->inputLength ? false : substr_compare($this->input, "{{", $this->currPos, 2, false) === 0) {
      $r26 = "{{";
      $this->currPos += 2;
    } else {
      $r26 = self::$FAILED;
      $r25 = self::$FAILED;
      goto seq_9;
    }
    $p27 = $this->currPos;
    if (($this->input[$this->currPos] ?? null) === "{") {
      $this->currPos++;
      $r28 = "{";
    } else {
      $r28 = self::$FAILED;
    }
    if ($r28 === self::$FAILED) {
      $r28 = false;
    } else {
      $r28 = self::$FAILED;
      $this->currPos = $p27;
      $this->currPos = $p21;
      $r25 = self::$FAILED;
      goto seq_9;
    }
    // free $p27
    $r25 = true;
    seq_9:
    if ($r25!==self::$FAILED) {
      $r25 = false;
      $this->currPos = $p13;
    } else {
      $this->currPos = $p10;
      $r23 = self::$FAILED;
      goto seq_8;
    }
    // free $p21
    // free $p13
    $r23 = true;
    seq_8:
    if ($r23===self::$FAILED) {
      $r23 = null;
    }
    // free $p10
    // a <- $r23
    $r23 = substr($this->input, $p8, $this->currPos - $p8);
    // free $p8
    $r29 = $this->parsetemplate($silence, $boolParams, $param_templatedepth, $param_th);
    // b <- $r29
    if ($r29===self::$FAILED) {
      $this->currPos = $p6;
      $r1 = self::$FAILED;
      goto seq_7;
    }
    $r1 = true;
    seq_7:
    if ($r1!==self::$FAILED) {
      $this->savedPos = $p4;
      $r1 = $this->a46($r23, $r29);
      goto choice_1;
    }
    // free $p6
    $r1 = $this->parsebroken_template($silence, $param_preproc);
    choice_1:
    $cached = ['nextPos' => $this->currPos, 'result' => $r1];
      if ($saved_th !== $param_th) $cached["\$th"] = $param_th;
      if ($saved_preproc !== $param_preproc) $cached["\$preproc"] = $param_preproc;
    $this->cache[$bucket][$key] = $cached;
    return $r1;
  }
  private function parseraw_htmlentity($silence) {
    $key = 494;
    $bucket = $this->currPos;
    $cached = $this->cache[$bucket][$key] ?? null;
    if ($cached) {
      $this->currPos = $cached['nextPos'];
  
      return $cached['result'];
    }
  
    $p2 = $this->currPos;
    $p4 = $this->currPos;
    // start seq_1
    $p5 = $this->currPos;
    if (($this->input[$this->currPos] ?? null) === "&") {
      $this->currPos++;
      $r6 = "&";
    } else {
      if (!$silence) {$this->fail(4);}
      $r6 = self::$FAILED;
      $r3 = self::$FAILED;
      goto seq_1;
    }
    $r7 = self::$FAILED;
    for (;;) {
      $r8 = $this->input[$this->currPos] ?? '';
      if (preg_match("/^[#0-9a-zA-Z]/", $r8)) {
        $this->currPos++;
        $r7 = true;
      } else {
        $r8 = self::$FAILED;
        if (!$silence) {$this->fail(29);}
        break;
      }
    }
    if ($r7===self::$FAILED) {
      $this->currPos = $p5;
      $r3 = self::$FAILED;
      goto seq_1;
    }
    // free $r8
    if (($this->input[$this->currPos] ?? null) === ";") {
      $this->currPos++;
      $r8 = ";";
    } else {
      if (!$silence) {$this->fail(30);}
      $r8 = self::$FAILED;
      $this->currPos = $p5;
      $r3 = self::$FAILED;
      goto seq_1;
    }
    $r3 = true;
    seq_1:
    // m <- $r3
    if ($r3!==self::$FAILED) {
      $r3 = substr($this->input, $p4, $this->currPos - $p4);
    } else {
      $r3 = self::$FAILED;
    }
    // free $p5
    // free $p4
    $r1 = $r3;
    if ($r1!==self::$FAILED) {
      $this->savedPos = $p2;
      $r1 = $this->a47($r3);
    }
    $cached = ['nextPos' => $this->currPos, 'result' => $r1];
  
    $this->cache[$bucket][$key] = $cached;
    return $r1;
  }
  private function parseoptionalSpaceToken($silence) {
    $key = 504;
    $bucket = $this->currPos;
    $cached = $this->cache[$bucket][$key] ?? null;
    if ($cached) {
      $this->currPos = $cached['nextPos'];
  
      return $cached['result'];
    }
  
    $p2 = $this->currPos;
    $r3 = $this->parseoptional_spaces($silence);
    // s <- $r3
    $r1 = $r3;
    if ($r1!==self::$FAILED) {
      $this->savedPos = $p2;
      $r1 = $this->a34($r3);
    }
    $cached = ['nextPos' => $this->currPos, 'result' => $r1];
  
    $this->cache[$bucket][$key] = $cached;
    return $r1;
  }
  private function parsetable_attribute_name($silence, $boolParams, $param_templatedepth, &$param_preproc, &$param_th) {
    $key = json_encode([442, $boolParams & 0x3bfe, $param_templatedepth, $param_preproc, $param_th]);
    $bucket = $this->currPos;
    $cached = $this->cache[$bucket][$key] ?? null;
    if ($cached) {
      $this->currPos = $cached['nextPos'];
        if (array_key_exists("\$preproc", $cached)) $param_preproc = $cached["\$preproc"];
        if (array_key_exists("\$th", $cached)) $param_th = $cached["\$th"];
      return $cached['result'];
    }
        $saved_preproc=$param_preproc;
        $saved_th=$param_th;
    $p2 = $this->currPos;
    // start seq_1
    $p3 = $this->currPos;
    $p5 = $this->currPos;
    if (strspn($this->input, "\"'=", $this->currPos, 1) !== 0) {
      $r4 = $this->input[$this->currPos++];
    } else {
      $r4 = self::$FAILED;
      if (!$silence) {$this->fail(31);}
      $r4 = null;
    }
    // q <- $r4
    $r4 = substr($this->input, $p5, $this->currPos - $p5);
    // free $p5
    $r6 = [];
    for (;;) {
      // start choice_1
      $p5 = $this->currPos;
      $r7 = self::$FAILED;
      for (;;) {
        if (strcspn($this->input, " \x09\x0d\x0a\x00/=><&{}-!|[", $this->currPos, 1) !== 0) {
          $r8 = self::consumeChar($this->input, $this->currPos);
          $r7 = true;
        } else {
          $r8 = self::$FAILED;
          if (!$silence) {$this->fail(32);}
          break;
        }
      }
      if ($r7!==self::$FAILED) {
        $r7 = substr($this->input, $p5, $this->currPos - $p5);
        goto choice_1;
      } else {
        $r7 = self::$FAILED;
      }
      // free $r8
      // free $p5
      $p5 = $this->currPos;
      // start seq_2
      $p9 = $this->currPos;
      $p10 = $this->currPos;
      $r8 = $this->discardinline_breaks(true, $boolParams, $param_templatedepth, $param_preproc, $param_th);
      if ($r8 === self::$FAILED) {
        $r8 = false;
      } else {
        $r8 = self::$FAILED;
        $this->currPos = $p10;
        $r7 = self::$FAILED;
        goto seq_2;
      }
      // free $p10
      // start choice_2
      $p10 = $this->currPos;
      $r11 = $this->discardwikilink($silence, $boolParams, $param_templatedepth, $param_th, $param_preproc);
      if ($r11!==self::$FAILED) {
        $r11 = substr($this->input, $p10, $this->currPos - $p10);
        goto choice_2;
      } else {
        $r11 = self::$FAILED;
      }
      // free $p10
      $r11 = $this->parsedirective($silence, $boolParams, $param_templatedepth, $param_preproc, $param_th);
      if ($r11!==self::$FAILED) {
        goto choice_2;
      }
      $p10 = $this->currPos;
      // start seq_3
      $p12 = $this->currPos;
      $p13 = $this->currPos;
      $r14 = $this->discardxmlish_tag(true, $boolParams, $param_templatedepth, $param_preproc, $param_th);
      if ($r14!==self::$FAILED) {
        $r14 = false;
        $this->currPos = $p13;
      } else {
        $r11 = self::$FAILED;
        goto seq_3;
      }
      // free $p13
      $r15 = $this->parseinlineline($silence, $boolParams, $param_templatedepth, $param_preproc, $param_th);
      // ill <- $r15
      if ($r15===self::$FAILED) {
        $this->currPos = $p12;
        $r11 = self::$FAILED;
        goto seq_3;
      }
      $r11 = true;
      seq_3:
      if ($r11!==self::$FAILED) {
        $this->savedPos = $p10;
        $r11 = $this->a48($r4, $r15);
        goto choice_2;
      }
      // free $p12
      $p12 = $this->currPos;
      // start seq_4
      $p13 = $this->currPos;
      $p16 = $this->currPos;
      // start choice_3
      $r17 = $this->discardspace_or_newline(true);
      if ($r17!==self::$FAILED) {
        goto choice_3;
      }
      if (strspn($this->input, "\x00/=>", $this->currPos, 1) !== 0) {
        $r17 = $this->input[$this->currPos++];
      } else {
        $r17 = self::$FAILED;
      }
      choice_3:
      if ($r17 === self::$FAILED) {
        $r17 = false;
      } else {
        $r17 = self::$FAILED;
        $this->currPos = $p16;
        $r11 = self::$FAILED;
        goto seq_4;
      }
      // free $p16
      if ($this->currPos < $this->inputLength) {
        $r18 = self::consumeChar($this->input, $this->currPos);;
      } else {
        $r18 = self::$FAILED;
        if (!$silence) {$this->fail(7);}
        $this->currPos = $p13;
        $r11 = self::$FAILED;
        goto seq_4;
      }
      $r11 = true;
      seq_4:
      if ($r11!==self::$FAILED) {
        $r11 = substr($this->input, $p12, $this->currPos - $p12);
      } else {
        $r11 = self::$FAILED;
      }
      // free $p13
      // free $p12
      choice_2:
      // t <- $r11
      if ($r11===self::$FAILED) {
        $this->currPos = $p9;
        $r7 = self::$FAILED;
        goto seq_2;
      }
      $r7 = true;
      seq_2:
      if ($r7!==self::$FAILED) {
        $this->savedPos = $p5;
        $r7 = $this->a49($r4, $r11);
      }
      // free $p9
      choice_1:
      if ($r7!==self::$FAILED) {
        $r6[] = $r7;
      } else {
        break;
      }
    }
    // r <- $r6
    // free $r7
    $this->savedPos = $this->currPos;
    $r7 = $this->a50($r4, $r6);
    if ($r7) {
      $r7 = false;
    } else {
      $r7 = self::$FAILED;
      $this->currPos = $p3;
      $r1 = self::$FAILED;
      goto seq_1;
    }
    $r1 = true;
    seq_1:
    if ($r1!==self::$FAILED) {
      $this->savedPos = $p2;
      $r1 = $this->a51($r4, $r6);
    }
    // free $p3
    $cached = ['nextPos' => $this->currPos, 'result' => $r1];
      if ($saved_preproc !== $param_preproc) $cached["\$preproc"] = $param_preproc;
      if ($saved_th !== $param_th) $cached["\$th"] = $param_th;
    $this->cache[$bucket][$key] = $cached;
    return $r1;
  }
  private function parsetable_att_value($silence, $boolParams, $param_templatedepth, &$param_preproc, &$param_th) {
    $key = json_encode([446, $boolParams & 0x3bfe, $param_templatedepth, $param_preproc, $param_th]);
    $bucket = $this->currPos;
    $cached = $this->cache[$bucket][$key] ?? null;
    if ($cached) {
      $this->currPos = $cached['nextPos'];
        if (array_key_exists("\$preproc", $cached)) $param_preproc = $cached["\$preproc"];
        if (array_key_exists("\$th", $cached)) $param_th = $cached["\$th"];
      return $cached['result'];
    }
        $saved_preproc=$param_preproc;
        $saved_th=$param_th;
    // start choice_1
    $p2 = $this->currPos;
    // start seq_1
    $p3 = $this->currPos;
    $p5 = $this->currPos;
    // start seq_2
    $p6 = $this->currPos;
    for (;;) {
      $r8 = $this->discardspace($silence);
      if ($r8===self::$FAILED) {
        break;
      }
    }
    // free $r8
    $r7 = true;
    if ($r7===self::$FAILED) {
      $r4 = self::$FAILED;
      goto seq_2;
    }
    // free $r7
    if (($this->input[$this->currPos] ?? null) === "'") {
      $this->currPos++;
      $r7 = "'";
    } else {
      if (!$silence) {$this->fail(33);}
      $r7 = self::$FAILED;
      $this->currPos = $p6;
      $r4 = self::$FAILED;
      goto seq_2;
    }
    $r4 = true;
    seq_2:
    // s <- $r4
    if ($r4!==self::$FAILED) {
      $r4 = substr($this->input, $p5, $this->currPos - $p5);
    } else {
      $r4 = self::$FAILED;
      $r1 = self::$FAILED;
      goto seq_1;
    }
    // free $p6
    // free $p5
    $r8 = $this->parsetable_attribute_preprocessor_text_single($silence, $boolParams, $param_templatedepth, $param_preproc, $param_th);
    if ($r8===self::$FAILED) {
      $r8 = null;
    }
    // t <- $r8
    $p5 = $this->currPos;
    // start choice_2
    if (($this->input[$this->currPos] ?? null) === "'") {
      $this->currPos++;
      $r9 = "'";
      goto choice_2;
    } else {
      if (!$silence) {$this->fail(33);}
      $r9 = self::$FAILED;
    }
    $p6 = $this->currPos;
    // start choice_3
    if ($this->currPos >= $this->inputLength ? false : substr_compare($this->input, "!!", $this->currPos, 2, false) === 0) {
      $r9 = "!!";
      $this->currPos += 2;
      goto choice_3;
    } else {
      $r9 = self::$FAILED;
    }
    if (strspn($this->input, "|\x0d\x0a", $this->currPos, 1) !== 0) {
      $r9 = $this->input[$this->currPos++];
    } else {
      $r9 = self::$FAILED;
    }
    choice_3:
    if ($r9!==self::$FAILED) {
      $r9 = false;
      $this->currPos = $p6;
    }
    // free $p6
    choice_2:
    // q <- $r9
    if ($r9!==self::$FAILED) {
      $r9 = substr($this->input, $p5, $this->currPos - $p5);
    } else {
      $r9 = self::$FAILED;
      $this->currPos = $p3;
      $r1 = self::$FAILED;
      goto seq_1;
    }
    // free $p5
    $r1 = true;
    seq_1:
    if ($r1!==self::$FAILED) {
      $this->savedPos = $p2;
      $r1 = $this->a52($r4, $r8, $r9);
      goto choice_1;
    }
    // free $p3
    $p3 = $this->currPos;
    // start seq_3
    $p5 = $this->currPos;
    $p6 = $this->currPos;
    // start seq_4
    $p11 = $this->currPos;
    for (;;) {
      $r13 = $this->discardspace($silence);
      if ($r13===self::$FAILED) {
        break;
      }
    }
    // free $r13
    $r12 = true;
    if ($r12===self::$FAILED) {
      $r10 = self::$FAILED;
      goto seq_4;
    }
    // free $r12
    if (($this->input[$this->currPos] ?? null) === "\"") {
      $this->currPos++;
      $r12 = "\"";
    } else {
      if (!$silence) {$this->fail(34);}
      $r12 = self::$FAILED;
      $this->currPos = $p11;
      $r10 = self::$FAILED;
      goto seq_4;
    }
    $r10 = true;
    seq_4:
    // s <- $r10
    if ($r10!==self::$FAILED) {
      $r10 = substr($this->input, $p6, $this->currPos - $p6);
    } else {
      $r10 = self::$FAILED;
      $r1 = self::$FAILED;
      goto seq_3;
    }
    // free $p11
    // free $p6
    $r13 = $this->parsetable_attribute_preprocessor_text_double($silence, $boolParams, $param_templatedepth, $param_preproc, $param_th);
    if ($r13===self::$FAILED) {
      $r13 = null;
    }
    // t <- $r13
    $p6 = $this->currPos;
    // start choice_4
    if (($this->input[$this->currPos] ?? null) === "\"") {
      $this->currPos++;
      $r14 = "\"";
      goto choice_4;
    } else {
      if (!$silence) {$this->fail(34);}
      $r14 = self::$FAILED;
    }
    $p11 = $this->currPos;
    // start choice_5
    if ($this->currPos >= $this->inputLength ? false : substr_compare($this->input, "!!", $this->currPos, 2, false) === 0) {
      $r14 = "!!";
      $this->currPos += 2;
      goto choice_5;
    } else {
      $r14 = self::$FAILED;
    }
    if (strspn($this->input, "|\x0d\x0a", $this->currPos, 1) !== 0) {
      $r14 = $this->input[$this->currPos++];
    } else {
      $r14 = self::$FAILED;
    }
    choice_5:
    if ($r14!==self::$FAILED) {
      $r14 = false;
      $this->currPos = $p11;
    }
    // free $p11
    choice_4:
    // q <- $r14
    if ($r14!==self::$FAILED) {
      $r14 = substr($this->input, $p6, $this->currPos - $p6);
    } else {
      $r14 = self::$FAILED;
      $this->currPos = $p5;
      $r1 = self::$FAILED;
      goto seq_3;
    }
    // free $p6
    $r1 = true;
    seq_3:
    if ($r1!==self::$FAILED) {
      $this->savedPos = $p3;
      $r1 = $this->a52($r10, $r13, $r14);
      goto choice_1;
    }
    // free $p5
    $p5 = $this->currPos;
    // start seq_5
    $p6 = $this->currPos;
    $p11 = $this->currPos;
    for (;;) {
      $r16 = $this->discardspace($silence);
      if ($r16===self::$FAILED) {
        break;
      }
    }
    // free $r16
    $r15 = true;
    // s <- $r15
    if ($r15!==self::$FAILED) {
      $r15 = substr($this->input, $p11, $this->currPos - $p11);
    } else {
      $r15 = self::$FAILED;
      $r1 = self::$FAILED;
      goto seq_5;
    }
    // free $p11
    $r16 = $this->parsetable_attribute_preprocessor_text($silence, $boolParams, $param_templatedepth, $param_preproc, $param_th);
    // t <- $r16
    if ($r16===self::$FAILED) {
      $this->currPos = $p6;
      $r1 = self::$FAILED;
      goto seq_5;
    }
    $p11 = $this->currPos;
    // start choice_6
    $r17 = $this->discardspace_or_newline(true);
    if ($r17!==self::$FAILED) {
      goto choice_6;
    }
    $r17 = $this->discardeof(true);
    if ($r17!==self::$FAILED) {
      goto choice_6;
    }
    if ($this->currPos >= $this->inputLength ? false : substr_compare($this->input, "!!", $this->currPos, 2, false) === 0) {
      $r17 = "!!";
      $this->currPos += 2;
      goto choice_6;
    } else {
      $r17 = self::$FAILED;
    }
    if (($this->input[$this->currPos] ?? null) === "|") {
      $this->currPos++;
      $r17 = "|";
    } else {
      $r17 = self::$FAILED;
    }
    choice_6:
    if ($r17!==self::$FAILED) {
      $r17 = false;
      $this->currPos = $p11;
    } else {
      $this->currPos = $p6;
      $r1 = self::$FAILED;
      goto seq_5;
    }
    // free $p11
    $r1 = true;
    seq_5:
    if ($r1!==self::$FAILED) {
      $this->savedPos = $p5;
      $r1 = $this->a53($r15, $r16);
    }
    // free $p6
    choice_1:
    $cached = ['nextPos' => $this->currPos, 'result' => $r1];
      if ($saved_preproc !== $param_preproc) $cached["\$preproc"] = $param_preproc;
      if ($saved_th !== $param_th) $cached["\$th"] = $param_th;
    $this->cache[$bucket][$key] = $cached;
    return $r1;
  }
  private function discardspace_or_newline_or_solidus($silence) {
    $key = 425;
    $bucket = $this->currPos;
    $cached = $this->cache[$bucket][$key] ?? null;
    if ($cached) {
      $this->currPos = $cached['nextPos'];
  
      return $cached['result'];
    }
  
    // start choice_1
    $r1 = $this->discardspace_or_newline($silence);
    if ($r1!==self::$FAILED) {
      goto choice_1;
    }
    $p2 = $this->currPos;
    // start seq_1
    $p3 = $this->currPos;
    // s <- $r4
    if (($this->input[$this->currPos] ?? null) === "/") {
      $this->currPos++;
      $r4 = "/";
    } else {
      if (!$silence) {$this->fail(35);}
      $r4 = self::$FAILED;
      $r1 = self::$FAILED;
      goto seq_1;
    }
    $p5 = $this->currPos;
    if (($this->input[$this->currPos] ?? null) === ">") {
      $this->currPos++;
      $r6 = ">";
    } else {
      $r6 = self::$FAILED;
    }
    if ($r6 === self::$FAILED) {
      $r6 = false;
    } else {
      $r6 = self::$FAILED;
      $this->currPos = $p5;
      $this->currPos = $p3;
      $r1 = self::$FAILED;
      goto seq_1;
    }
    // free $p5
    $r1 = true;
    seq_1:
    if ($r1!==self::$FAILED) {
      $this->savedPos = $p2;
      $r1 = $this->a39($r4);
    }
    // free $p3
    choice_1:
    $cached = ['nextPos' => $this->currPos, 'result' => $r1];
  
    $this->cache[$bucket][$key] = $cached;
    return $r1;
  }
  private function parsegeneric_attribute_name($silence, $boolParams, $param_templatedepth, &$param_preproc, &$param_th) {
    $key = json_encode([438, $boolParams & 0x1bfe, $param_templatedepth, $param_preproc, $param_th]);
    $bucket = $this->currPos;
    $cached = $this->cache[$bucket][$key] ?? null;
    if ($cached) {
      $this->currPos = $cached['nextPos'];
        if (array_key_exists("\$preproc", $cached)) $param_preproc = $cached["\$preproc"];
        if (array_key_exists("\$th", $cached)) $param_th = $cached["\$th"];
      return $cached['result'];
    }
        $saved_preproc=$param_preproc;
        $saved_th=$param_th;
    $p2 = $this->currPos;
    // start seq_1
    $p3 = $this->currPos;
    $p5 = $this->currPos;
    if (strspn($this->input, "\"'=", $this->currPos, 1) !== 0) {
      $r4 = $this->input[$this->currPos++];
    } else {
      $r4 = self::$FAILED;
      if (!$silence) {$this->fail(31);}
      $r4 = null;
    }
    // q <- $r4
    $r4 = substr($this->input, $p5, $this->currPos - $p5);
    // free $p5
    $r6 = [];
    for (;;) {
      // start choice_1
      $p5 = $this->currPos;
      $r7 = self::$FAILED;
      for (;;) {
        if (strcspn($this->input, " \x09\x0d\x0a\x00/=><&{}-!|", $this->currPos, 1) !== 0) {
          $r8 = self::consumeChar($this->input, $this->currPos);
          $r7 = true;
        } else {
          $r8 = self::$FAILED;
          if (!$silence) {$this->fail(36);}
          break;
        }
      }
      if ($r7!==self::$FAILED) {
        $r7 = substr($this->input, $p5, $this->currPos - $p5);
        goto choice_1;
      } else {
        $r7 = self::$FAILED;
      }
      // free $r8
      // free $p5
      $p5 = $this->currPos;
      // start seq_2
      $p9 = $this->currPos;
      $p10 = $this->currPos;
      $r8 = $this->discardinline_breaks(true, $boolParams, $param_templatedepth, $param_preproc, $param_th);
      if ($r8 === self::$FAILED) {
        $r8 = false;
      } else {
        $r8 = self::$FAILED;
        $this->currPos = $p10;
        $r7 = self::$FAILED;
        goto seq_2;
      }
      // free $p10
      // start choice_2
      $r11 = $this->parsedirective($silence, $boolParams, $param_templatedepth, $param_preproc, $param_th);
      if ($r11!==self::$FAILED) {
        goto choice_2;
      }
      $r11 = $this->parseless_than($silence, $boolParams);
      if ($r11!==self::$FAILED) {
        goto choice_2;
      }
      $p10 = $this->currPos;
      // start seq_3
      $p12 = $this->currPos;
      $p13 = $this->currPos;
      // start choice_3
      $r14 = $this->discardspace_or_newline(true);
      if ($r14!==self::$FAILED) {
        goto choice_3;
      }
      if (strspn($this->input, "\x00/=><", $this->currPos, 1) !== 0) {
        $r14 = $this->input[$this->currPos++];
      } else {
        $r14 = self::$FAILED;
      }
      choice_3:
      if ($r14 === self::$FAILED) {
        $r14 = false;
      } else {
        $r14 = self::$FAILED;
        $this->currPos = $p13;
        $r11 = self::$FAILED;
        goto seq_3;
      }
      // free $p13
      if ($this->currPos < $this->inputLength) {
        $r15 = self::consumeChar($this->input, $this->currPos);;
      } else {
        $r15 = self::$FAILED;
        if (!$silence) {$this->fail(7);}
        $this->currPos = $p12;
        $r11 = self::$FAILED;
        goto seq_3;
      }
      $r11 = true;
      seq_3:
      if ($r11!==self::$FAILED) {
        $r11 = substr($this->input, $p10, $this->currPos - $p10);
      } else {
        $r11 = self::$FAILED;
      }
      // free $p12
      // free $p10
      choice_2:
      // t <- $r11
      if ($r11===self::$FAILED) {
        $this->currPos = $p9;
        $r7 = self::$FAILED;
        goto seq_2;
      }
      $r7 = true;
      seq_2:
      if ($r7!==self::$FAILED) {
        $this->savedPos = $p5;
        $r7 = $this->a49($r4, $r11);
      }
      // free $p9
      choice_1:
      if ($r7!==self::$FAILED) {
        $r6[] = $r7;
      } else {
        break;
      }
    }
    // r <- $r6
    // free $r7
    $this->savedPos = $this->currPos;
    $r7 = $this->a50($r4, $r6);
    if ($r7) {
      $r7 = false;
    } else {
      $r7 = self::$FAILED;
      $this->currPos = $p3;
      $r1 = self::$FAILED;
      goto seq_1;
    }
    $r1 = true;
    seq_1:
    if ($r1!==self::$FAILED) {
      $this->savedPos = $p2;
      $r1 = $this->a51($r4, $r6);
    }
    // free $p3
    $cached = ['nextPos' => $this->currPos, 'result' => $r1];
      if ($saved_preproc !== $param_preproc) $cached["\$preproc"] = $param_preproc;
      if ($saved_th !== $param_th) $cached["\$th"] = $param_th;
    $this->cache[$bucket][$key] = $cached;
    return $r1;
  }
  private function discardspace_or_newline($silence) {
    $key = 507;
    $bucket = $this->currPos;
    $cached = $this->cache[$bucket][$key] ?? null;
    if ($cached) {
      $this->currPos = $cached['nextPos'];
  
      return $cached['result'];
    }
  
    if (strspn($this->input, " \x09\x0a\x0d\x0c", $this->currPos, 1) !== 0) {
      $r1 = $this->input[$this->currPos++];
    } else {
      $r1 = self::$FAILED;
      if (!$silence) {$this->fail(37);}
    }
    $cached = ['nextPos' => $this->currPos, 'result' => $r1];
  
    $this->cache[$bucket][$key] = $cached;
    return $r1;
  }
  private function parsegeneric_att_value($silence, $boolParams, $param_templatedepth, &$param_preproc, &$param_th) {
    $key = json_encode([444, $boolParams & 0x1bfe, $param_templatedepth, $param_preproc, $param_th]);
    $bucket = $this->currPos;
    $cached = $this->cache[$bucket][$key] ?? null;
    if ($cached) {
      $this->currPos = $cached['nextPos'];
        if (array_key_exists("\$preproc", $cached)) $param_preproc = $cached["\$preproc"];
        if (array_key_exists("\$th", $cached)) $param_th = $cached["\$th"];
      return $cached['result'];
    }
        $saved_preproc=$param_preproc;
        $saved_th=$param_th;
    // start choice_1
    $p2 = $this->currPos;
    // start seq_1
    $p3 = $this->currPos;
    $p5 = $this->currPos;
    // start seq_2
    $p6 = $this->currPos;
    for (;;) {
      $r8 = $this->discardspace_or_newline($silence);
      if ($r8===self::$FAILED) {
        break;
      }
    }
    // free $r8
    $r7 = true;
    if ($r7===self::$FAILED) {
      $r4 = self::$FAILED;
      goto seq_2;
    }
    // free $r7
    if (($this->input[$this->currPos] ?? null) === "'") {
      $this->currPos++;
      $r7 = "'";
    } else {
      if (!$silence) {$this->fail(33);}
      $r7 = self::$FAILED;
      $this->currPos = $p6;
      $r4 = self::$FAILED;
      goto seq_2;
    }
    $r4 = true;
    seq_2:
    // s <- $r4
    if ($r4!==self::$FAILED) {
      $r4 = substr($this->input, $p5, $this->currPos - $p5);
    } else {
      $r4 = self::$FAILED;
      $r1 = self::$FAILED;
      goto seq_1;
    }
    // free $p6
    // free $p5
    $r8 = $this->parseattribute_preprocessor_text_single($silence, $boolParams, $param_templatedepth, $param_preproc, $param_th);
    if ($r8===self::$FAILED) {
      $r8 = null;
    }
    // t <- $r8
    $p5 = $this->currPos;
    // start choice_2
    if (($this->input[$this->currPos] ?? null) === "'") {
      $this->currPos++;
      $r9 = "'";
      goto choice_2;
    } else {
      if (!$silence) {$this->fail(33);}
      $r9 = self::$FAILED;
    }
    $p6 = $this->currPos;
    // start seq_3
    $p10 = $this->currPos;
    if (($this->input[$this->currPos] ?? null) === "/") {
      $this->currPos++;
      $r11 = "/";
    } else {
      $r11 = self::$FAILED;
      $r11 = null;
    }
    if (($this->input[$this->currPos] ?? null) === ">") {
      $this->currPos++;
      $r12 = ">";
    } else {
      $r12 = self::$FAILED;
      $this->currPos = $p10;
      $r9 = self::$FAILED;
      goto seq_3;
    }
    $r9 = true;
    seq_3:
    if ($r9!==self::$FAILED) {
      $r9 = false;
      $this->currPos = $p6;
    }
    // free $p10
    // free $p6
    choice_2:
    // q <- $r9
    if ($r9!==self::$FAILED) {
      $r9 = substr($this->input, $p5, $this->currPos - $p5);
    } else {
      $r9 = self::$FAILED;
      $this->currPos = $p3;
      $r1 = self::$FAILED;
      goto seq_1;
    }
    // free $p5
    $r1 = true;
    seq_1:
    if ($r1!==self::$FAILED) {
      $this->savedPos = $p2;
      $r1 = $this->a52($r4, $r8, $r9);
      goto choice_1;
    }
    // free $p3
    $p3 = $this->currPos;
    // start seq_4
    $p5 = $this->currPos;
    $p6 = $this->currPos;
    // start seq_5
    $p10 = $this->currPos;
    for (;;) {
      $r15 = $this->discardspace_or_newline($silence);
      if ($r15===self::$FAILED) {
        break;
      }
    }
    // free $r15
    $r14 = true;
    if ($r14===self::$FAILED) {
      $r13 = self::$FAILED;
      goto seq_5;
    }
    // free $r14
    if (($this->input[$this->currPos] ?? null) === "\"") {
      $this->currPos++;
      $r14 = "\"";
    } else {
      if (!$silence) {$this->fail(34);}
      $r14 = self::$FAILED;
      $this->currPos = $p10;
      $r13 = self::$FAILED;
      goto seq_5;
    }
    $r13 = true;
    seq_5:
    // s <- $r13
    if ($r13!==self::$FAILED) {
      $r13 = substr($this->input, $p6, $this->currPos - $p6);
    } else {
      $r13 = self::$FAILED;
      $r1 = self::$FAILED;
      goto seq_4;
    }
    // free $p10
    // free $p6
    $r15 = $this->parseattribute_preprocessor_text_double($silence, $boolParams, $param_templatedepth, $param_preproc, $param_th);
    if ($r15===self::$FAILED) {
      $r15 = null;
    }
    // t <- $r15
    $p6 = $this->currPos;
    // start choice_3
    if (($this->input[$this->currPos] ?? null) === "\"") {
      $this->currPos++;
      $r16 = "\"";
      goto choice_3;
    } else {
      if (!$silence) {$this->fail(34);}
      $r16 = self::$FAILED;
    }
    $p10 = $this->currPos;
    // start seq_6
    $p17 = $this->currPos;
    if (($this->input[$this->currPos] ?? null) === "/") {
      $this->currPos++;
      $r18 = "/";
    } else {
      $r18 = self::$FAILED;
      $r18 = null;
    }
    if (($this->input[$this->currPos] ?? null) === ">") {
      $this->currPos++;
      $r19 = ">";
    } else {
      $r19 = self::$FAILED;
      $this->currPos = $p17;
      $r16 = self::$FAILED;
      goto seq_6;
    }
    $r16 = true;
    seq_6:
    if ($r16!==self::$FAILED) {
      $r16 = false;
      $this->currPos = $p10;
    }
    // free $p17
    // free $p10
    choice_3:
    // q <- $r16
    if ($r16!==self::$FAILED) {
      $r16 = substr($this->input, $p6, $this->currPos - $p6);
    } else {
      $r16 = self::$FAILED;
      $this->currPos = $p5;
      $r1 = self::$FAILED;
      goto seq_4;
    }
    // free $p6
    $r1 = true;
    seq_4:
    if ($r1!==self::$FAILED) {
      $this->savedPos = $p3;
      $r1 = $this->a52($r13, $r15, $r16);
      goto choice_1;
    }
    // free $p5
    $p5 = $this->currPos;
    // start seq_7
    $p6 = $this->currPos;
    $p10 = $this->currPos;
    for (;;) {
      $r21 = $this->discardspace_or_newline($silence);
      if ($r21===self::$FAILED) {
        break;
      }
    }
    // free $r21
    $r20 = true;
    // s <- $r20
    if ($r20!==self::$FAILED) {
      $r20 = substr($this->input, $p10, $this->currPos - $p10);
    } else {
      $r20 = self::$FAILED;
      $r1 = self::$FAILED;
      goto seq_7;
    }
    // free $p10
    $r21 = $this->parseattribute_preprocessor_text($silence, $boolParams, $param_templatedepth, $param_preproc, $param_th);
    // t <- $r21
    if ($r21===self::$FAILED) {
      $this->currPos = $p6;
      $r1 = self::$FAILED;
      goto seq_7;
    }
    $p10 = $this->currPos;
    // start choice_4
    $r22 = $this->discardspace_or_newline(true);
    if ($r22!==self::$FAILED) {
      goto choice_4;
    }
    $r22 = $this->discardeof(true);
    if ($r22!==self::$FAILED) {
      goto choice_4;
    }
    // start seq_8
    $p17 = $this->currPos;
    if (($this->input[$this->currPos] ?? null) === "/") {
      $this->currPos++;
      $r23 = "/";
    } else {
      $r23 = self::$FAILED;
      $r23 = null;
    }
    if (($this->input[$this->currPos] ?? null) === ">") {
      $this->currPos++;
      $r24 = ">";
    } else {
      $r24 = self::$FAILED;
      $this->currPos = $p17;
      $r22 = self::$FAILED;
      goto seq_8;
    }
    $r22 = true;
    seq_8:
    // free $p17
    choice_4:
    if ($r22!==self::$FAILED) {
      $r22 = false;
      $this->currPos = $p10;
    } else {
      $this->currPos = $p6;
      $r1 = self::$FAILED;
      goto seq_7;
    }
    // free $p10
    $r1 = true;
    seq_7:
    if ($r1!==self::$FAILED) {
      $this->savedPos = $p5;
      $r1 = $this->a53($r20, $r21);
    }
    // free $p6
    choice_1:
    $cached = ['nextPos' => $this->currPos, 'result' => $r1];
      if ($saved_preproc !== $param_preproc) $cached["\$preproc"] = $param_preproc;
      if ($saved_th !== $param_th) $cached["\$th"] = $param_th;
    $this->cache[$bucket][$key] = $cached;
    return $r1;
  }
  private function parseextlink_nonipv6url_parameterized($silence, $boolParams, $param_templatedepth, &$param_preproc, &$param_th) {
    $key = json_encode([550, $boolParams & 0x1bfe, $param_templatedepth, $param_preproc, $param_th]);
    $bucket = $this->currPos;
    $cached = $this->cache[$bucket][$key] ?? null;
    if ($cached) {
      $this->currPos = $cached['nextPos'];
        if (array_key_exists("\$preproc", $cached)) $param_preproc = $cached["\$preproc"];
        if (array_key_exists("\$th", $cached)) $param_th = $cached["\$th"];
      return $cached['result'];
    }
        $saved_preproc=$param_preproc;
        $saved_th=$param_th;
    $p2 = $this->currPos;
    $r3 = [];
    for (;;) {
      // start choice_1
      $p5 = $this->currPos;
      $r4 = self::$FAILED;
      for (;;) {
        $r6 = self::charAt($this->input, $this->currPos);
        if (preg_match("/^[^<\\[{\\x0a\\x0d|!\\]}\\-\\x09&=\"' \\x{a0}\\x{1680}\\x{180e}\\x{2000}-\\x{200a}\\x{202f}\\x{205f}\\x{3000}]/u", $r6)) {
          $this->currPos += strlen($r6);
          $r4 = true;
        } else {
          $r6 = self::$FAILED;
          if (!$silence) {$this->fail(38);}
          break;
        }
      }
      if ($r4!==self::$FAILED) {
        $r4 = substr($this->input, $p5, $this->currPos - $p5);
        goto choice_1;
      } else {
        $r4 = self::$FAILED;
      }
      // free $r6
      // free $p5
      $p5 = $this->currPos;
      // start seq_1
      $p7 = $this->currPos;
      $p8 = $this->currPos;
      $r6 = $this->discardinline_breaks(true, $boolParams, $param_templatedepth, $param_preproc, $param_th);
      if ($r6 === self::$FAILED) {
        $r6 = false;
      } else {
        $r6 = self::$FAILED;
        $this->currPos = $p8;
        $r4 = self::$FAILED;
        goto seq_1;
      }
      // free $p8
      // start choice_2
      $r9 = $this->parsedirective($silence, $boolParams, $param_templatedepth, $param_preproc, $param_th);
      if ($r9!==self::$FAILED) {
        goto choice_2;
      }
      if (strspn($this->input, "&|{-!}=", $this->currPos, 1) !== 0) {
        $r9 = $this->input[$this->currPos++];
      } else {
        $r9 = self::$FAILED;
        if (!$silence) {$this->fail(39);}
      }
      choice_2:
      // s <- $r9
      if ($r9===self::$FAILED) {
        $this->currPos = $p7;
        $r4 = self::$FAILED;
        goto seq_1;
      }
      $r4 = true;
      seq_1:
      if ($r4!==self::$FAILED) {
        $this->savedPos = $p5;
        $r4 = $this->a39($r9);
        goto choice_1;
      }
      // free $p7
      $p7 = $this->currPos;
      // start seq_2
      $p8 = $this->currPos;
      $r10 = $this->input[$this->currPos] ?? '';
      if ($r10 === "'") {
        $this->currPos++;
      } else {
        $r10 = self::$FAILED;
        if (!$silence) {$this->fail(40);}
        $r4 = self::$FAILED;
        goto seq_2;
      }
      $p11 = $this->currPos;
      $r12 = $this->input[$this->currPos] ?? '';
      if ($r12 === "'") {
        $this->currPos++;
      } else {
        $r12 = self::$FAILED;
      }
      if ($r12 === self::$FAILED) {
        $r12 = false;
      } else {
        $r12 = self::$FAILED;
        $this->currPos = $p11;
        $this->currPos = $p8;
        $r4 = self::$FAILED;
        goto seq_2;
      }
      // free $p11
      $r4 = true;
      seq_2:
      if ($r4!==self::$FAILED) {
        $r4 = substr($this->input, $p7, $this->currPos - $p7);
      } else {
        $r4 = self::$FAILED;
      }
      // free $p8
      // free $p7
      choice_1:
      if ($r4!==self::$FAILED) {
        $r3[] = $r4;
      } else {
        break;
      }
    }
    if (count($r3) === 0) {
      $r3 = self::$FAILED;
    }
    // r <- $r3
    // free $r4
    $r1 = $r3;
    if ($r1!==self::$FAILED) {
      $this->savedPos = $p2;
      $r1 = $this->a54($r3);
    }
    $cached = ['nextPos' => $this->currPos, 'result' => $r1];
      if ($saved_preproc !== $param_preproc) $cached["\$preproc"] = $param_preproc;
      if ($saved_th !== $param_th) $cached["\$th"] = $param_th;
    $this->cache[$bucket][$key] = $cached;
    return $r1;
  }
  private function parseurltext($silence, $boolParams, $param_templatedepth, &$param_preproc, &$param_th) {
    $key = json_encode([492, $boolParams & 0x1bfe, $param_templatedepth, $param_preproc, $param_th]);
    $bucket = $this->currPos;
    $cached = $this->cache[$bucket][$key] ?? null;
    if ($cached) {
      $this->currPos = $cached['nextPos'];
        if (array_key_exists("\$preproc", $cached)) $param_preproc = $cached["\$preproc"];
        if (array_key_exists("\$th", $cached)) $param_th = $cached["\$th"];
      return $cached['result'];
    }
        $saved_preproc=$param_preproc;
        $saved_th=$param_th;
    $r1 = [];
    for (;;) {
      // start choice_1
      $p3 = $this->currPos;
      // start seq_1
      $p4 = $this->currPos;
      $p5 = $this->currPos;
      $r6 = $this->input[$this->currPos] ?? '';
      if (preg_match("/^[A-Za-z]/", $r6)) {
        $this->currPos++;
        $r6 = false;
        $this->currPos = $p5;
      } else {
        $r6 = self::$FAILED;
        $r2 = self::$FAILED;
        goto seq_1;
      }
      // free $p5
      $r7 = $this->parseautolink($silence, $boolParams, $param_templatedepth, $param_preproc, $param_th);
      // al <- $r7
      if ($r7===self::$FAILED) {
        $this->currPos = $p4;
        $r2 = self::$FAILED;
        goto seq_1;
      }
      $r2 = true;
      seq_1:
      if ($r2!==self::$FAILED) {
        $this->savedPos = $p3;
        $r2 = $this->a55($r7);
        goto choice_1;
      }
      // free $p4
      $p4 = $this->currPos;
      // start seq_2
      $p5 = $this->currPos;
      $p8 = $this->currPos;
      if (($this->input[$this->currPos] ?? null) === "&") {
        $this->currPos++;
        $r9 = "&";
        $r9 = false;
        $this->currPos = $p8;
      } else {
        $r9 = self::$FAILED;
        $r2 = self::$FAILED;
        goto seq_2;
      }
      // free $p8
      $r10 = $this->parsehtmlentity($silence);
      // he <- $r10
      if ($r10===self::$FAILED) {
        $this->currPos = $p5;
        $r2 = self::$FAILED;
        goto seq_2;
      }
      $r2 = true;
      seq_2:
      if ($r2!==self::$FAILED) {
        $this->savedPos = $p4;
        $r2 = $this->a56($r10);
        goto choice_1;
      }
      // free $p5
      $p5 = $this->currPos;
      // start seq_3
      $p8 = $this->currPos;
      $p11 = $this->currPos;
      if ($this->currPos >= $this->inputLength ? false : substr_compare($this->input, "__", $this->currPos, 2, false) === 0) {
        $r12 = "__";
        $this->currPos += 2;
        $r12 = false;
        $this->currPos = $p11;
      } else {
        $r12 = self::$FAILED;
        $r2 = self::$FAILED;
        goto seq_3;
      }
      // free $p11
      $r13 = $this->parsebehavior_switch($silence);
      // bs <- $r13
      if ($r13===self::$FAILED) {
        $this->currPos = $p8;
        $r2 = self::$FAILED;
        goto seq_3;
      }
      $r2 = true;
      seq_3:
      if ($r2!==self::$FAILED) {
        $this->savedPos = $p5;
        $r2 = $this->a57($r13);
        goto choice_1;
      }
      // free $p8
      if (strcspn($this->input, "-'<[{\x0a\x0d:;]}|!=", $this->currPos, 1) !== 0) {
        $r2 = self::consumeChar($this->input, $this->currPos);
      } else {
        $r2 = self::$FAILED;
        if (!$silence) {$this->fail(41);}
      }
      choice_1:
      if ($r2!==self::$FAILED) {
        $r1[] = $r2;
      } else {
        break;
      }
    }
    if (count($r1) === 0) {
      $r1 = self::$FAILED;
    }
    // free $r2
    $cached = ['nextPos' => $this->currPos, 'result' => $r1];
      if ($saved_preproc !== $param_preproc) $cached["\$preproc"] = $param_preproc;
      if ($saved_th !== $param_th) $cached["\$th"] = $param_th;
    $this->cache[$bucket][$key] = $cached;
    return $r1;
  }
  private function parseinline_element($silence, $boolParams, $param_templatedepth, &$param_preproc, &$param_th) {
    $key = json_encode([318, $boolParams & 0x1bfe, $param_templatedepth, $param_preproc, $param_th]);
    $bucket = $this->currPos;
    $cached = $this->cache[$bucket][$key] ?? null;
    if ($cached) {
      $this->currPos = $cached['nextPos'];
        if (array_key_exists("\$preproc", $cached)) $param_preproc = $cached["\$preproc"];
        if (array_key_exists("\$th", $cached)) $param_th = $cached["\$th"];
      return $cached['result'];
    }
        $saved_preproc=$param_preproc;
        $saved_th=$param_th;
    // start choice_1
    $p2 = $this->currPos;
    // start seq_1
    $p3 = $this->currPos;
    $p4 = $this->currPos;
    if (($this->input[$this->currPos] ?? null) === "<") {
      $this->currPos++;
      $r5 = "<";
      $r5 = false;
      $this->currPos = $p4;
    } else {
      $r5 = self::$FAILED;
      $r1 = self::$FAILED;
      goto seq_1;
    }
    // free $p4
    // start choice_2
    $r6 = $this->parsexmlish_tag($silence, $boolParams, $param_templatedepth, $param_preproc, $param_th);
    if ($r6!==self::$FAILED) {
      goto choice_2;
    }
    $r6 = $this->parsecomment($silence);
    choice_2:
    // r <- $r6
    if ($r6===self::$FAILED) {
      $this->currPos = $p3;
      $r1 = self::$FAILED;
      goto seq_1;
    }
    $r1 = true;
    seq_1:
    if ($r1!==self::$FAILED) {
      $this->savedPos = $p2;
      $r1 = $this->a20($r6);
      goto choice_1;
    }
    // free $p3
    $p3 = $this->currPos;
    // start seq_2
    $p4 = $this->currPos;
    $p7 = $this->currPos;
    if (($this->input[$this->currPos] ?? null) === "{") {
      $this->currPos++;
      $r8 = "{";
      $r8 = false;
      $this->currPos = $p7;
    } else {
      $r8 = self::$FAILED;
      $r1 = self::$FAILED;
      goto seq_2;
    }
    // free $p7
    $r9 = $this->parsetplarg_or_template($silence, $boolParams, $param_templatedepth, $param_th, $param_preproc);
    // r <- $r9
    if ($r9===self::$FAILED) {
      $this->currPos = $p4;
      $r1 = self::$FAILED;
      goto seq_2;
    }
    $r1 = true;
    seq_2:
    if ($r1!==self::$FAILED) {
      $this->savedPos = $p3;
      $r1 = $this->a20($r9);
      goto choice_1;
    }
    // free $p4
    $p4 = $this->currPos;
    // start seq_3
    $p7 = $this->currPos;
    $p10 = $this->currPos;
    if ($this->currPos >= $this->inputLength ? false : substr_compare($this->input, "-{", $this->currPos, 2, false) === 0) {
      $r11 = "-{";
      $this->currPos += 2;
      $r11 = false;
      $this->currPos = $p10;
    } else {
      $r11 = self::$FAILED;
      $r1 = self::$FAILED;
      goto seq_3;
    }
    // free $p10
    $r12 = $this->parselang_variant_or_tpl($silence, $boolParams, $param_templatedepth, $param_th, $param_preproc);
    // r <- $r12
    if ($r12===self::$FAILED) {
      $this->currPos = $p7;
      $r1 = self::$FAILED;
      goto seq_3;
    }
    $r1 = true;
    seq_3:
    if ($r1!==self::$FAILED) {
      $this->savedPos = $p4;
      $r1 = $this->a20($r12);
      goto choice_1;
    }
    // free $p7
    $p7 = $this->currPos;
    $r1 = self::$FAILED;
    for (;;) {
      // start seq_4
      $p10 = $this->currPos;
      if ($this->currPos >= $this->inputLength ? false : substr_compare($this->input, "[[", $this->currPos, 2, false) === 0) {
        $r14 = "[[";
        $this->currPos += 2;
      } else {
        if (!$silence) {$this->fail(42);}
        $r14 = self::$FAILED;
        $r13 = self::$FAILED;
        goto seq_4;
      }
      $p15 = $this->currPos;
      if (($this->input[$this->currPos] ?? null) === "[") {
        $this->currPos++;
        $r16 = "[";
        $r16 = false;
        $this->currPos = $p15;
      } else {
        $r16 = self::$FAILED;
        $this->currPos = $p10;
        $r13 = self::$FAILED;
        goto seq_4;
      }
      // free $p15
      $r13 = true;
      seq_4:
      if ($r13!==self::$FAILED) {
        $r1 = true;
      } else {
        break;
      }
      // free $p10
    }
    if ($r1!==self::$FAILED) {
      $r1 = substr($this->input, $p7, $this->currPos - $p7);
      goto choice_1;
    } else {
      $r1 = self::$FAILED;
    }
    // free $r13
    // free $p7
    $p7 = $this->currPos;
    // start seq_5
    $p10 = $this->currPos;
    $p15 = $this->currPos;
    if (($this->input[$this->currPos] ?? null) === "[") {
      $this->currPos++;
      $r13 = "[";
      $r13 = false;
      $this->currPos = $p15;
    } else {
      $r13 = self::$FAILED;
      $r1 = self::$FAILED;
      goto seq_5;
    }
    // free $p15
    // start choice_3
    $r17 = $this->parsewikilink($silence, $boolParams, $param_templatedepth, $param_th, $param_preproc);
    if ($r17!==self::$FAILED) {
      goto choice_3;
    }
    $r17 = $this->parseextlink($silence, $boolParams, $param_templatedepth, $param_preproc, $param_th);
    choice_3:
    // r <- $r17
    if ($r17===self::$FAILED) {
      $this->currPos = $p10;
      $r1 = self::$FAILED;
      goto seq_5;
    }
    $r1 = true;
    seq_5:
    if ($r1!==self::$FAILED) {
      $this->savedPos = $p7;
      $r1 = $this->a20($r17);
      goto choice_1;
    }
    // free $p10
    $p10 = $this->currPos;
    // start seq_6
    $p15 = $this->currPos;
    $p18 = $this->currPos;
    if (($this->input[$this->currPos] ?? null) === "'") {
      $this->currPos++;
      $r19 = "'";
      $r19 = false;
      $this->currPos = $p18;
    } else {
      $r19 = self::$FAILED;
      $r1 = self::$FAILED;
      goto seq_6;
    }
    // free $p18
    $r20 = $this->parsequote($silence);
    // r <- $r20
    if ($r20===self::$FAILED) {
      $this->currPos = $p15;
      $r1 = self::$FAILED;
      goto seq_6;
    }
    $r1 = true;
    seq_6:
    if ($r1!==self::$FAILED) {
      $this->savedPos = $p10;
      $r1 = $this->a20($r20);
    }
    // free $p15
    choice_1:
    $cached = ['nextPos' => $this->currPos, 'result' => $r1];
      if ($saved_preproc !== $param_preproc) $cached["\$preproc"] = $param_preproc;
      if ($saved_th !== $param_th) $cached["\$th"] = $param_th;
    $this->cache[$bucket][$key] = $cached;
    return $r1;
  }
  private function discardsof($silence) {
    $key = 533;
    $bucket = $this->currPos;
    $cached = $this->cache[$bucket][$key] ?? null;
    if ($cached) {
      $this->currPos = $cached['nextPos'];
  
      return $cached['result'];
    }
  
    $this->savedPos = $this->currPos;
    $r1 = $this->a58();
    if ($r1) {
      $r1 = false;
    } else {
      $r1 = self::$FAILED;
    }
    $cached = ['nextPos' => $this->currPos, 'result' => $r1];
  
    $this->cache[$bucket][$key] = $cached;
    return $r1;
  }
  private function parseredirect($silence, $boolParams, $param_templatedepth, &$param_th, &$param_preproc) {
    $key = json_encode([286, $boolParams & 0x3bff, $param_templatedepth, $param_th, $param_preproc]);
    $bucket = $this->currPos;
    $cached = $this->cache[$bucket][$key] ?? null;
    if ($cached) {
      $this->currPos = $cached['nextPos'];
        if (array_key_exists("\$th", $cached)) $param_th = $cached["\$th"];
        if (array_key_exists("\$preproc", $cached)) $param_preproc = $cached["\$preproc"];
      return $cached['result'];
    }
        $saved_th=$param_th;
        $saved_preproc=$param_preproc;
    $p2 = $this->currPos;
    // start seq_1
    $p3 = $this->currPos;
    $r4 = $this->parseredirect_word($silence);
    // rw <- $r4
    if ($r4===self::$FAILED) {
      $r1 = self::$FAILED;
      goto seq_1;
    }
    $p6 = $this->currPos;
    for (;;) {
      $r7 = $this->discardspace_or_newline($silence);
      if ($r7===self::$FAILED) {
        break;
      }
    }
    // free $r7
    $r5 = true;
    // sp <- $r5
    if ($r5!==self::$FAILED) {
      $r5 = substr($this->input, $p6, $this->currPos - $p6);
    } else {
      $r5 = self::$FAILED;
      $this->currPos = $p3;
      $r1 = self::$FAILED;
      goto seq_1;
    }
    // free $p6
    $p6 = $this->currPos;
    // start seq_2
    $p8 = $this->currPos;
    if (($this->input[$this->currPos] ?? null) === ":") {
      $this->currPos++;
      $r9 = ":";
    } else {
      if (!$silence) {$this->fail(18);}
      $r9 = self::$FAILED;
      $r7 = self::$FAILED;
      goto seq_2;
    }
    for (;;) {
      $r11 = $this->discardspace_or_newline($silence);
      if ($r11===self::$FAILED) {
        break;
      }
    }
    // free $r11
    $r10 = true;
    if ($r10===self::$FAILED) {
      $this->currPos = $p8;
      $r7 = self::$FAILED;
      goto seq_2;
    }
    // free $r10
    $r7 = true;
    seq_2:
    if ($r7===self::$FAILED) {
      $r7 = null;
    }
    // free $p8
    // c <- $r7
    $r7 = substr($this->input, $p6, $this->currPos - $p6);
    // free $p6
    $r10 = $this->parsewikilink($silence, $boolParams, $param_templatedepth, $param_th, $param_preproc);
    // wl <- $r10
    if ($r10===self::$FAILED) {
      $this->currPos = $p3;
      $r1 = self::$FAILED;
      goto seq_1;
    }
    $this->savedPos = $this->currPos;
    $r11 = $this->a59($r4, $r5, $r7, $r10);
    if ($r11) {
      $r11 = false;
    } else {
      $r11 = self::$FAILED;
      $this->currPos = $p3;
      $r1 = self::$FAILED;
      goto seq_1;
    }
    $r1 = true;
    seq_1:
    if ($r1!==self::$FAILED) {
      $this->savedPos = $p2;
      $r1 = $this->a60($r4, $r5, $r7, $r10);
    }
    // free $p3
    $cached = ['nextPos' => $this->currPos, 'result' => $r1];
      if ($saved_th !== $param_th) $cached["\$th"] = $param_th;
      if ($saved_preproc !== $param_preproc) $cached["\$preproc"] = $param_preproc;
    $this->cache[$bucket][$key] = $cached;
    return $r1;
  }
  private function parsecomment_or_includes($silence, $boolParams, $param_templatedepth, &$param_preproc, &$param_th) {
    $key = json_encode([518, $boolParams & 0x13ae, $param_templatedepth, $param_preproc, $param_th]);
    $bucket = $this->currPos;
    $cached = $this->cache[$bucket][$key] ?? null;
    if ($cached) {
      $this->currPos = $cached['nextPos'];
        if (array_key_exists("\$preproc", $cached)) $param_preproc = $cached["\$preproc"];
        if (array_key_exists("\$th", $cached)) $param_th = $cached["\$th"];
      return $cached['result'];
    }
        $saved_preproc=$param_preproc;
        $saved_th=$param_th;
    $r1 = [];
    for (;;) {
      // start choice_1
      $r2 = $this->parsecomment($silence);
      if ($r2!==self::$FAILED) {
        goto choice_1;
      }
      $r2 = $this->parseinclude_limits($silence, $boolParams | 0x2000, $param_templatedepth, $param_preproc, $param_th);
      choice_1:
      if ($r2!==self::$FAILED) {
        $r1[] = $r2;
      } else {
        break;
      }
    }
    // free $r2
    $cached = ['nextPos' => $this->currPos, 'result' => $r1];
      if ($saved_preproc !== $param_preproc) $cached["\$preproc"] = $param_preproc;
      if ($saved_th !== $param_th) $cached["\$th"] = $param_th;
    $this->cache[$bucket][$key] = $cached;
    return $r1;
  }
  private function parseblock_line($silence, $boolParams, $param_templatedepth, &$param_preproc, &$param_th) {
    $key = json_encode([308, $boolParams & 0x3bff, $param_templatedepth, $param_preproc, $param_th]);
    $bucket = $this->currPos;
    $cached = $this->cache[$bucket][$key] ?? null;
    if ($cached) {
      $this->currPos = $cached['nextPos'];
        if (array_key_exists("\$preproc", $cached)) $param_preproc = $cached["\$preproc"];
        if (array_key_exists("\$th", $cached)) $param_th = $cached["\$th"];
      return $cached['result'];
    }
        $saved_preproc=$param_preproc;
        $saved_th=$param_th;
    // start choice_1
    $r1 = $this->parseheading($silence, $boolParams, $param_templatedepth, $param_preproc, $param_th);
    if ($r1!==self::$FAILED) {
      goto choice_1;
    }
    $r1 = $this->parselist_item($silence, $boolParams, $param_templatedepth, $param_preproc, $param_th);
    if ($r1!==self::$FAILED) {
      goto choice_1;
    }
    $r1 = $this->parsehr($silence, $boolParams, $param_templatedepth, $param_preproc, $param_th);
    if ($r1!==self::$FAILED) {
      goto choice_1;
    }
    $p2 = $this->currPos;
    // start seq_1
    $p3 = $this->currPos;
    $r4 = $this->parseoptionalSpaceToken($silence);
    // st <- $r4
    if ($r4===self::$FAILED) {
      $r1 = self::$FAILED;
      goto seq_1;
    }
    // start choice_2
    $p6 = $this->currPos;
    // start seq_2
    $p7 = $this->currPos;
    $p8 = $this->currPos;
    if (strspn($this->input, " <{}|!", $this->currPos, 1) !== 0) {
      $r9 = $this->input[$this->currPos++];
      $r9 = false;
      $this->currPos = $p8;
    } else {
      $r9 = self::$FAILED;
      $r5 = self::$FAILED;
      goto seq_2;
    }
    // free $p8
    $r10 = $this->parsetable_line($silence, $boolParams, $param_templatedepth, $param_preproc, $param_th);
    // tl <- $r10
    if ($r10===self::$FAILED) {
      $this->currPos = $p7;
      $r5 = self::$FAILED;
      goto seq_2;
    }
    $r5 = true;
    seq_2:
    if ($r5!==self::$FAILED) {
      $this->savedPos = $p6;
      $r5 = $this->a61($r4, $r10);
      goto choice_2;
    }
    // free $p7
    $p7 = $this->currPos;
    // start seq_3
    $p8 = $this->currPos;
    $r11 = [];
    for (;;) {
      $p13 = $this->currPos;
      // start seq_4
      $p14 = $this->currPos;
      $r15 = $this->parseblock_tag($silence, $boolParams, $param_templatedepth, $param_preproc, $param_th);
      // bt <- $r15
      if ($r15===self::$FAILED) {
        $r12 = self::$FAILED;
        goto seq_4;
      }
      $r16 = $this->parseoptionalSpaceToken($silence);
      // stl <- $r16
      if ($r16===self::$FAILED) {
        $this->currPos = $p14;
        $r12 = self::$FAILED;
        goto seq_4;
      }
      $r12 = true;
      seq_4:
      if ($r12!==self::$FAILED) {
        $this->savedPos = $p13;
        $r12 = $this->a62($r4, $r15, $r16);
        $r11[] = $r12;
      } else {
        break;
      }
      // free $p14
    }
    if (count($r11) === 0) {
      $r11 = self::$FAILED;
    }
    // bts <- $r11
    if ($r11===self::$FAILED) {
      $r5 = self::$FAILED;
      goto seq_3;
    }
    // free $r12
    $p14 = $this->currPos;
    $r12 = $this->discardeolf(true);
    if ($r12!==self::$FAILED) {
      $r12 = false;
      $this->currPos = $p14;
    } else {
      $this->currPos = $p8;
      $r5 = self::$FAILED;
      goto seq_3;
    }
    // free $p14
    $r5 = true;
    seq_3:
    if ($r5!==self::$FAILED) {
      $this->savedPos = $p7;
      $r5 = $this->a63($r4, $r11);
    }
    // free $p8
    choice_2:
    // r <- $r5
    if ($r5===self::$FAILED) {
      $this->currPos = $p3;
      $r1 = self::$FAILED;
      goto seq_1;
    }
    $r1 = true;
    seq_1:
    if ($r1!==self::$FAILED) {
      $this->savedPos = $p2;
      $r1 = $this->a64($r4, $r5);
    }
    // free $p3
    choice_1:
    $cached = ['nextPos' => $this->currPos, 'result' => $r1];
      if ($saved_preproc !== $param_preproc) $cached["\$preproc"] = $param_preproc;
      if ($saved_th !== $param_th) $cached["\$th"] = $param_th;
    $this->cache[$bucket][$key] = $cached;
    return $r1;
  }
  private function parseblock_lines($silence, $boolParams, $param_templatedepth, &$param_preproc, &$param_th) {
    $key = json_encode([304, $boolParams & 0x3bff, $param_templatedepth, $param_preproc, $param_th]);
    $bucket = $this->currPos;
    $cached = $this->cache[$bucket][$key] ?? null;
    if ($cached) {
      $this->currPos = $cached['nextPos'];
        if (array_key_exists("\$preproc", $cached)) $param_preproc = $cached["\$preproc"];
        if (array_key_exists("\$th", $cached)) $param_th = $cached["\$th"];
      return $cached['result'];
    }
        $saved_preproc=$param_preproc;
        $saved_th=$param_th;
    $p2 = $this->currPos;
    // start seq_1
    $p3 = $this->currPos;
    $r4 = $this->parsesol($silence, $boolParams, $param_templatedepth, $param_preproc, $param_th);
    // s <- $r4
    if ($r4===self::$FAILED) {
      $r1 = self::$FAILED;
      goto seq_1;
    }
    $p6 = $this->currPos;
    // start seq_2
    $p7 = $this->currPos;
    $r8 = $this->parseoptionalSpaceToken($silence);
    // os <- $r8
    if ($r8===self::$FAILED) {
      $r5 = self::$FAILED;
      goto seq_2;
    }
    $r9 = $this->parsesol($silence, $boolParams, $param_templatedepth, $param_preproc, $param_th);
    // so <- $r9
    if ($r9===self::$FAILED) {
      $this->currPos = $p7;
      $r5 = self::$FAILED;
      goto seq_2;
    }
    $r5 = true;
    seq_2:
    if ($r5!==self::$FAILED) {
      $this->savedPos = $p6;
      $r5 = $this->a65($r4, $r8, $r9);
    } else {
      $r5 = null;
    }
    // free $p7
    // s2 <- $r5
    $r10 = $this->parseblock_line($silence, $boolParams, $param_templatedepth, $param_preproc, $param_th);
    // bl <- $r10
    if ($r10===self::$FAILED) {
      $this->currPos = $p3;
      $r1 = self::$FAILED;
      goto seq_1;
    }
    $r1 = true;
    seq_1:
    if ($r1!==self::$FAILED) {
      $this->savedPos = $p2;
      $r1 = $this->a66($r4, $r5, $r10);
    }
    // free $p3
    $cached = ['nextPos' => $this->currPos, 'result' => $r1];
      if ($saved_preproc !== $param_preproc) $cached["\$preproc"] = $param_preproc;
      if ($saved_th !== $param_th) $cached["\$th"] = $param_th;
    $this->cache[$bucket][$key] = $cached;
    return $r1;
  }
  private function discardeolf($silence) {
    $key = 541;
    $bucket = $this->currPos;
    $cached = $this->cache[$bucket][$key] ?? null;
    if ($cached) {
      $this->currPos = $cached['nextPos'];
  
      return $cached['result'];
    }
  
    // start choice_1
    $r1 = $this->discardnewline($silence);
    if ($r1!==self::$FAILED) {
      goto choice_1;
    }
    $r1 = $this->discardeof($silence);
    choice_1:
    $cached = ['nextPos' => $this->currPos, 'result' => $r1];
  
    $this->cache[$bucket][$key] = $cached;
    return $r1;
  }
  private function parseblock_tag($silence, $boolParams, $param_templatedepth, &$param_preproc, &$param_th) {
    $key = json_encode([430, $boolParams & 0x13ae, $param_templatedepth, $param_preproc, $param_th]);
    $bucket = $this->currPos;
    $cached = $this->cache[$bucket][$key] ?? null;
    if ($cached) {
      $this->currPos = $cached['nextPos'];
        if (array_key_exists("\$preproc", $cached)) $param_preproc = $cached["\$preproc"];
        if (array_key_exists("\$th", $cached)) $param_th = $cached["\$th"];
      return $cached['result'];
    }
        $saved_preproc=$param_preproc;
        $saved_th=$param_th;
    $p2 = $this->currPos;
    // start seq_1
    $p3 = $this->currPos;
    if (($this->input[$this->currPos] ?? null) === "<") {
      $this->currPos++;
      $r4 = "<";
    } else {
      if (!$silence) {$this->fail(43);}
      $r4 = self::$FAILED;
      $r1 = self::$FAILED;
      goto seq_1;
    }
    // start choice_1
    $r5 = $this->parsexmlish_tag_opened($silence, $boolParams | 0xc00, $param_templatedepth, $param_preproc, $param_th);
    if ($r5!==self::$FAILED) {
      goto choice_1;
    }
    $r5 = $this->parsexmlish_tag_opened($silence, ($boolParams & ~0x800) | 0x400, $param_templatedepth, $param_preproc, $param_th);
    choice_1:
    // tag <- $r5
    if ($r5===self::$FAILED) {
      $this->currPos = $p3;
      $r1 = self::$FAILED;
      goto seq_1;
    }
    $r1 = true;
    seq_1:
    if ($r1!==self::$FAILED) {
      $this->savedPos = $p2;
      $r1 = $this->a67($r5);
    }
    // free $p3
    $cached = ['nextPos' => $this->currPos, 'result' => $r1];
      if ($saved_preproc !== $param_preproc) $cached["\$preproc"] = $param_preproc;
      if ($saved_th !== $param_th) $cached["\$th"] = $param_th;
    $this->cache[$bucket][$key] = $cached;
    return $r1;
  }
  private function parseparagraph($silence, $boolParams, $param_templatedepth, &$param_preproc, &$param_th) {
    $key = json_encode([310, $boolParams & 0x1bfe, $param_templatedepth, $param_preproc, $param_th]);
    $bucket = $this->currPos;
    $cached = $this->cache[$bucket][$key] ?? null;
    if ($cached) {
      $this->currPos = $cached['nextPos'];
        if (array_key_exists("\$preproc", $cached)) $param_preproc = $cached["\$preproc"];
        if (array_key_exists("\$th", $cached)) $param_th = $cached["\$th"];
      return $cached['result'];
    }
        $saved_preproc=$param_preproc;
        $saved_th=$param_th;
    $p2 = $this->currPos;
    // start seq_1
    $p3 = $this->currPos;
    $r4 = $this->parsesol($silence, $boolParams, $param_templatedepth, $param_preproc, $param_th);
    // s1 <- $r4
    if ($r4===self::$FAILED) {
      $r1 = self::$FAILED;
      goto seq_1;
    }
    $r5 = $this->parsesol($silence, $boolParams, $param_templatedepth, $param_preproc, $param_th);
    // s2 <- $r5
    if ($r5===self::$FAILED) {
      $this->currPos = $p3;
      $r1 = self::$FAILED;
      goto seq_1;
    }
    $r6 = $this->parseinlineline($silence, $boolParams, $param_templatedepth, $param_preproc, $param_th);
    // c <- $r6
    if ($r6===self::$FAILED) {
      $this->currPos = $p3;
      $r1 = self::$FAILED;
      goto seq_1;
    }
    $r1 = true;
    seq_1:
    if ($r1!==self::$FAILED) {
      $this->savedPos = $p2;
      $r1 = $this->a68($r4, $r5, $r6);
    }
    // free $p3
    $cached = ['nextPos' => $this->currPos, 'result' => $r1];
      if ($saved_preproc !== $param_preproc) $cached["\$preproc"] = $param_preproc;
      if ($saved_th !== $param_th) $cached["\$th"] = $param_th;
    $this->cache[$bucket][$key] = $cached;
    return $r1;
  }
  private function parsesol($silence, $boolParams, $param_templatedepth, &$param_preproc, &$param_th) {
    $key = json_encode([520, $boolParams & 0x13ae, $param_templatedepth, $param_preproc, $param_th]);
    $bucket = $this->currPos;
    $cached = $this->cache[$bucket][$key] ?? null;
    if ($cached) {
      $this->currPos = $cached['nextPos'];
        if (array_key_exists("\$preproc", $cached)) $param_preproc = $cached["\$preproc"];
        if (array_key_exists("\$th", $cached)) $param_th = $cached["\$th"];
      return $cached['result'];
    }
        $saved_preproc=$param_preproc;
        $saved_th=$param_th;
    // start seq_1
    $p1 = $this->currPos;
    // start choice_1
    $r3 = $this->parseempty_line_with_comments($silence);
    if ($r3!==self::$FAILED) {
      goto choice_1;
    }
    $r3 = $this->parsesol_prefix($silence);
    choice_1:
    if ($r3===self::$FAILED) {
      $r2 = self::$FAILED;
      goto seq_1;
    }
    $r4 = $this->parsecomment_or_includes($silence, $boolParams, $param_templatedepth, $param_preproc, $param_th);
    if ($r4===self::$FAILED) {
      $this->currPos = $p1;
      $r2 = self::$FAILED;
      goto seq_1;
    }
    $r2 = [$r3,$r4];
    seq_1:
    // free $r2,$p1
    $cached = ['nextPos' => $this->currPos, 'result' => $r2];
      if ($saved_preproc !== $param_preproc) $cached["\$preproc"] = $param_preproc;
      if ($saved_th !== $param_th) $cached["\$th"] = $param_th;
    $this->cache[$bucket][$key] = $cached;
    return $r2;
  }
  private function discardtplarg($silence, $boolParams, $param_templatedepth, &$param_th) {
    $key = json_encode([359, $boolParams & 0x1bfe, $param_templatedepth, $param_th]);
    $bucket = $this->currPos;
    $cached = $this->cache[$bucket][$key] ?? null;
    if ($cached) {
      $this->currPos = $cached['nextPos'];
        if (array_key_exists("\$th", $cached)) $param_th = $cached["\$th"];
      return $cached['result'];
    }
        $saved_th=$param_th;
    $r1 = $this->discardtplarg_preproc($silence, $boolParams, $param_templatedepth, self::newRef("}}"), $param_th);
    $cached = ['nextPos' => $this->currPos, 'result' => $r1];
      if ($saved_th !== $param_th) $cached["\$th"] = $param_th;
    $this->cache[$bucket][$key] = $cached;
    return $r1;
  }
  private function parsetemplate($silence, $boolParams, $param_templatedepth, &$param_th) {
    $key = json_encode([352, $boolParams & 0x1bfe, $param_templatedepth, $param_th]);
    $bucket = $this->currPos;
    $cached = $this->cache[$bucket][$key] ?? null;
    if ($cached) {
      $this->currPos = $cached['nextPos'];
        if (array_key_exists("\$th", $cached)) $param_th = $cached["\$th"];
      return $cached['result'];
    }
        $saved_th=$param_th;
    $r1 = $this->parsetemplate_preproc($silence, $boolParams, $param_templatedepth, self::newRef("}}"), $param_th);
    $cached = ['nextPos' => $this->currPos, 'result' => $r1];
      if ($saved_th !== $param_th) $cached["\$th"] = $param_th;
    $this->cache[$bucket][$key] = $cached;
    return $r1;
  }
  private function parsebroken_template($silence, &$param_preproc) {
    $key = json_encode([354, $param_preproc]);
    $bucket = $this->currPos;
    $cached = $this->cache[$bucket][$key] ?? null;
    if ($cached) {
      $this->currPos = $cached['nextPos'];
        if (array_key_exists("\$preproc", $cached)) $param_preproc = $cached["\$preproc"];
      return $cached['result'];
    }
        $saved_preproc=$param_preproc;
    $p2 = $this->currPos;
    // start seq_1
    $p3 = $this->currPos;
    // t <- $r4
    if ($this->currPos >= $this->inputLength ? false : substr_compare($this->input, "{{", $this->currPos, 2, false) === 0) {
      $r4 = "{{";
      $this->currPos += 2;
    } else {
      if (!$silence) {$this->fail(44);}
      $r4 = self::$FAILED;
      $this->currPos = $p3;
      $r1 = self::$FAILED;
      goto seq_1;
    }
    $r1 = true;
    seq_1:
    if ($r1!==self::$FAILED) {
      $this->savedPos = $p2;
      $r1 = $this->a69($param_preproc, $r4);
    }
    // free $p3
    $cached = ['nextPos' => $this->currPos, 'result' => $r1];
      if ($saved_preproc !== $param_preproc) $cached["\$preproc"] = $param_preproc;
    $this->cache[$bucket][$key] = $cached;
    return $r1;
  }
  private function parsetplarg($silence, $boolParams, $param_templatedepth, &$param_th) {
    $key = json_encode([358, $boolParams & 0x1bfe, $param_templatedepth, $param_th]);
    $bucket = $this->currPos;
    $cached = $this->cache[$bucket][$key] ?? null;
    if ($cached) {
      $this->currPos = $cached['nextPos'];
        if (array_key_exists("\$th", $cached)) $param_th = $cached["\$th"];
      return $cached['result'];
    }
        $saved_th=$param_th;
    $r1 = $this->parsetplarg_preproc($silence, $boolParams, $param_templatedepth, self::newRef("}}"), $param_th);
    $cached = ['nextPos' => $this->currPos, 'result' => $r1];
      if ($saved_th !== $param_th) $cached["\$th"] = $param_th;
    $this->cache[$bucket][$key] = $cached;
    return $r1;
  }
  private function discardwikilink($silence, $boolParams, $param_templatedepth, &$param_th, &$param_preproc) {
    $key = json_encode([403, $boolParams & 0x3bff, $param_templatedepth, $param_th, $param_preproc]);
    $bucket = $this->currPos;
    $cached = $this->cache[$bucket][$key] ?? null;
    if ($cached) {
      $this->currPos = $cached['nextPos'];
        if (array_key_exists("\$th", $cached)) $param_th = $cached["\$th"];
        if (array_key_exists("\$preproc", $cached)) $param_preproc = $cached["\$preproc"];
      return $cached['result'];
    }
        $saved_th=$param_th;
        $saved_preproc=$param_preproc;
    // start choice_1
    $r1 = $this->discardwikilink_preproc($silence, $boolParams, $param_templatedepth, self::newRef("]]"), $param_th);
    if ($r1!==self::$FAILED) {
      goto choice_1;
    }
    $r1 = $this->discardbroken_wikilink($silence, $boolParams, $param_preproc, $param_templatedepth, $param_th);
    choice_1:
    $cached = ['nextPos' => $this->currPos, 'result' => $r1];
      if ($saved_th !== $param_th) $cached["\$th"] = $param_th;
      if ($saved_preproc !== $param_preproc) $cached["\$preproc"] = $param_preproc;
    $this->cache[$bucket][$key] = $cached;
    return $r1;
  }
  private function parsedirective($silence, $boolParams, $param_templatedepth, &$param_preproc, &$param_th) {
    $key = json_encode([544, $boolParams & 0x3bfe, $param_templatedepth, $param_preproc, $param_th]);
    $bucket = $this->currPos;
    $cached = $this->cache[$bucket][$key] ?? null;
    if ($cached) {
      $this->currPos = $cached['nextPos'];
        if (array_key_exists("\$preproc", $cached)) $param_preproc = $cached["\$preproc"];
        if (array_key_exists("\$th", $cached)) $param_th = $cached["\$th"];
      return $cached['result'];
    }
        $saved_preproc=$param_preproc;
        $saved_th=$param_th;
    // start choice_1
    $r1 = $this->parsecomment($silence);
    if ($r1!==self::$FAILED) {
      goto choice_1;
    }
    $r1 = $this->parseextension_tag($silence, $boolParams, $param_templatedepth, $param_preproc, $param_th);
    if ($r1!==self::$FAILED) {
      goto choice_1;
    }
    $r1 = $this->parsetplarg_or_template($silence, $boolParams, $param_templatedepth, $param_th, $param_preproc);
    if ($r1!==self::$FAILED) {
      goto choice_1;
    }
    $p2 = $this->currPos;
    // start seq_1
    $p3 = $this->currPos;
    $p4 = $this->currPos;
    if ($this->currPos >= $this->inputLength ? false : substr_compare($this->input, "-{", $this->currPos, 2, false) === 0) {
      $r5 = "-{";
      $this->currPos += 2;
      $r5 = false;
      $this->currPos = $p4;
    } else {
      $r5 = self::$FAILED;
      $r1 = self::$FAILED;
      goto seq_1;
    }
    // free $p4
    $r6 = $this->parselang_variant_or_tpl($silence, $boolParams, $param_templatedepth, $param_th, $param_preproc);
    // v <- $r6
    if ($r6===self::$FAILED) {
      $this->currPos = $p3;
      $r1 = self::$FAILED;
      goto seq_1;
    }
    $r1 = true;
    seq_1:
    if ($r1!==self::$FAILED) {
      $this->savedPos = $p2;
      $r1 = $this->a70($r6);
      goto choice_1;
    }
    // free $p3
    $p3 = $this->currPos;
    // start seq_2
    $p4 = $this->currPos;
    $p7 = $this->currPos;
    if (($this->input[$this->currPos] ?? null) === "&") {
      $this->currPos++;
      $r8 = "&";
      $r8 = false;
      $this->currPos = $p7;
    } else {
      $r8 = self::$FAILED;
      $r1 = self::$FAILED;
      goto seq_2;
    }
    // free $p7
    $r9 = $this->parsehtmlentity($silence);
    // e <- $r9
    if ($r9===self::$FAILED) {
      $this->currPos = $p4;
      $r1 = self::$FAILED;
      goto seq_2;
    }
    $r1 = true;
    seq_2:
    if ($r1!==self::$FAILED) {
      $this->savedPos = $p3;
      $r1 = $this->a71($r9);
      goto choice_1;
    }
    // free $p4
    $r1 = $this->parseinclude_limits($silence, $boolParams, $param_templatedepth, $param_preproc, $param_th);
    choice_1:
    $cached = ['nextPos' => $this->currPos, 'result' => $r1];
      if ($saved_preproc !== $param_preproc) $cached["\$preproc"] = $param_preproc;
      if ($saved_th !== $param_th) $cached["\$th"] = $param_th;
    $this->cache[$bucket][$key] = $cached;
    return $r1;
  }
  private function discardxmlish_tag($silence, $boolParams, $param_templatedepth, &$param_preproc, &$param_th) {
    $key = json_encode([427, $boolParams & 0x13ae, $param_templatedepth, $param_preproc, $param_th]);
    $bucket = $this->currPos;
    $cached = $this->cache[$bucket][$key] ?? null;
    if ($cached) {
      $this->currPos = $cached['nextPos'];
        if (array_key_exists("\$preproc", $cached)) $param_preproc = $cached["\$preproc"];
        if (array_key_exists("\$th", $cached)) $param_th = $cached["\$th"];
      return $cached['result'];
    }
        $saved_preproc=$param_preproc;
        $saved_th=$param_th;
    $p2 = $this->currPos;
    // start seq_1
    $p3 = $this->currPos;
    if (($this->input[$this->currPos] ?? null) === "<") {
      $this->currPos++;
      $r4 = "<";
    } else {
      if (!$silence) {$this->fail(43);}
      $r4 = self::$FAILED;
      $r1 = self::$FAILED;
      goto seq_1;
    }
    // start choice_1
    $r5 = $this->parsexmlish_tag_opened($silence, ($boolParams & ~0x400) | 0x800, $param_templatedepth, $param_preproc, $param_th);
    if ($r5!==self::$FAILED) {
      goto choice_1;
    }
    $r5 = $this->parsexmlish_tag_opened($silence, $boolParams & ~0xc00, $param_templatedepth, $param_preproc, $param_th);
    choice_1:
    // tag <- $r5
    if ($r5===self::$FAILED) {
      $this->currPos = $p3;
      $r1 = self::$FAILED;
      goto seq_1;
    }
    $r1 = true;
    seq_1:
    if ($r1!==self::$FAILED) {
      $this->savedPos = $p2;
      $r1 = $this->a67($r5);
    }
    // free $p3
    $cached = ['nextPos' => $this->currPos, 'result' => $r1];
      if ($saved_preproc !== $param_preproc) $cached["\$preproc"] = $param_preproc;
      if ($saved_th !== $param_th) $cached["\$th"] = $param_th;
    $this->cache[$bucket][$key] = $cached;
    return $r1;
  }
  private function parsetable_attribute_preprocessor_text_single($silence, $boolParams, $param_templatedepth, &$param_preproc, &$param_th) {
    $key = json_encode([560, $boolParams & 0x3bfe, $param_templatedepth, $param_preproc, $param_th]);
    $bucket = $this->currPos;
    $cached = $this->cache[$bucket][$key] ?? null;
    if ($cached) {
      $this->currPos = $cached['nextPos'];
        if (array_key_exists("\$preproc", $cached)) $param_preproc = $cached["\$preproc"];
        if (array_key_exists("\$th", $cached)) $param_th = $cached["\$th"];
      return $cached['result'];
    }
        $saved_preproc=$param_preproc;
        $saved_th=$param_th;
    $p2 = $this->currPos;
    $r3 = [];
    for (;;) {
      // start choice_1
      $p5 = $this->currPos;
      $r4 = self::$FAILED;
      for (;;) {
        if (strcspn($this->input, "{}&<-!['\x0d\x0a|", $this->currPos, 1) !== 0) {
          $r6 = self::consumeChar($this->input, $this->currPos);
          $r4 = true;
        } else {
          $r6 = self::$FAILED;
          if (!$silence) {$this->fail(45);}
          break;
        }
      }
      if ($r4!==self::$FAILED) {
        $r4 = substr($this->input, $p5, $this->currPos - $p5);
        goto choice_1;
      } else {
        $r4 = self::$FAILED;
      }
      // free $r6
      // free $p5
      $p5 = $this->currPos;
      // start seq_1
      $p7 = $this->currPos;
      $p8 = $this->currPos;
      $r6 = $this->discardinline_breaks(true, $boolParams, $param_templatedepth, $param_preproc, $param_th);
      if ($r6 === self::$FAILED) {
        $r6 = false;
      } else {
        $r6 = self::$FAILED;
        $this->currPos = $p8;
        $r4 = self::$FAILED;
        goto seq_1;
      }
      // free $p8
      // start choice_2
      $r9 = $this->parsedirective($silence, $boolParams, $param_templatedepth, $param_preproc, $param_th);
      if ($r9!==self::$FAILED) {
        goto choice_2;
      }
      if (strspn($this->input, "{}&<-![", $this->currPos, 1) !== 0) {
        $r9 = $this->input[$this->currPos++];
      } else {
        $r9 = self::$FAILED;
        if (!$silence) {$this->fail(46);}
      }
      choice_2:
      // s <- $r9
      if ($r9===self::$FAILED) {
        $this->currPos = $p7;
        $r4 = self::$FAILED;
        goto seq_1;
      }
      $r4 = true;
      seq_1:
      if ($r4!==self::$FAILED) {
        $this->savedPos = $p5;
        $r4 = $this->a39($r9);
      }
      // free $p7
      choice_1:
      if ($r4!==self::$FAILED) {
        $r3[] = $r4;
      } else {
        break;
      }
    }
    // r <- $r3
    // free $r4
    $r1 = $r3;
    if ($r1!==self::$FAILED) {
      $this->savedPos = $p2;
      $r1 = $this->a54($r3);
    }
    $cached = ['nextPos' => $this->currPos, 'result' => $r1];
      if ($saved_preproc !== $param_preproc) $cached["\$preproc"] = $param_preproc;
      if ($saved_th !== $param_th) $cached["\$th"] = $param_th;
    $this->cache[$bucket][$key] = $cached;
    return $r1;
  }
  private function parsetable_attribute_preprocessor_text_double($silence, $boolParams, $param_templatedepth, &$param_preproc, &$param_th) {
    $key = json_encode([562, $boolParams & 0x3bfe, $param_templatedepth, $param_preproc, $param_th]);
    $bucket = $this->currPos;
    $cached = $this->cache[$bucket][$key] ?? null;
    if ($cached) {
      $this->currPos = $cached['nextPos'];
        if (array_key_exists("\$preproc", $cached)) $param_preproc = $cached["\$preproc"];
        if (array_key_exists("\$th", $cached)) $param_th = $cached["\$th"];
      return $cached['result'];
    }
        $saved_preproc=$param_preproc;
        $saved_th=$param_th;
    $p2 = $this->currPos;
    $r3 = [];
    for (;;) {
      // start choice_1
      $p5 = $this->currPos;
      $r4 = self::$FAILED;
      for (;;) {
        if (strcspn($this->input, "{}&<-![\"\x0d\x0a|", $this->currPos, 1) !== 0) {
          $r6 = self::consumeChar($this->input, $this->currPos);
          $r4 = true;
        } else {
          $r6 = self::$FAILED;
          if (!$silence) {$this->fail(47);}
          break;
        }
      }
      if ($r4!==self::$FAILED) {
        $r4 = substr($this->input, $p5, $this->currPos - $p5);
        goto choice_1;
      } else {
        $r4 = self::$FAILED;
      }
      // free $r6
      // free $p5
      $p5 = $this->currPos;
      // start seq_1
      $p7 = $this->currPos;
      $p8 = $this->currPos;
      $r6 = $this->discardinline_breaks(true, $boolParams, $param_templatedepth, $param_preproc, $param_th);
      if ($r6 === self::$FAILED) {
        $r6 = false;
      } else {
        $r6 = self::$FAILED;
        $this->currPos = $p8;
        $r4 = self::$FAILED;
        goto seq_1;
      }
      // free $p8
      // start choice_2
      $r9 = $this->parsedirective($silence, $boolParams, $param_templatedepth, $param_preproc, $param_th);
      if ($r9!==self::$FAILED) {
        goto choice_2;
      }
      if (strspn($this->input, "{}&<-![", $this->currPos, 1) !== 0) {
        $r9 = $this->input[$this->currPos++];
      } else {
        $r9 = self::$FAILED;
        if (!$silence) {$this->fail(46);}
      }
      choice_2:
      // s <- $r9
      if ($r9===self::$FAILED) {
        $this->currPos = $p7;
        $r4 = self::$FAILED;
        goto seq_1;
      }
      $r4 = true;
      seq_1:
      if ($r4!==self::$FAILED) {
        $this->savedPos = $p5;
        $r4 = $this->a39($r9);
      }
      // free $p7
      choice_1:
      if ($r4!==self::$FAILED) {
        $r3[] = $r4;
      } else {
        break;
      }
    }
    // r <- $r3
    // free $r4
    $r1 = $r3;
    if ($r1!==self::$FAILED) {
      $this->savedPos = $p2;
      $r1 = $this->a54($r3);
    }
    $cached = ['nextPos' => $this->currPos, 'result' => $r1];
      if ($saved_preproc !== $param_preproc) $cached["\$preproc"] = $param_preproc;
      if ($saved_th !== $param_th) $cached["\$th"] = $param_th;
    $this->cache[$bucket][$key] = $cached;
    return $r1;
  }
  private function parsetable_attribute_preprocessor_text($silence, $boolParams, $param_templatedepth, &$param_preproc, &$param_th) {
    $key = json_encode([558, $boolParams & 0x3bfe, $param_templatedepth, $param_preproc, $param_th]);
    $bucket = $this->currPos;
    $cached = $this->cache[$bucket][$key] ?? null;
    if ($cached) {
      $this->currPos = $cached['nextPos'];
        if (array_key_exists("\$preproc", $cached)) $param_preproc = $cached["\$preproc"];
        if (array_key_exists("\$th", $cached)) $param_th = $cached["\$th"];
      return $cached['result'];
    }
        $saved_preproc=$param_preproc;
        $saved_th=$param_th;
    $p2 = $this->currPos;
    $r3 = [];
    for (;;) {
      // start choice_1
      $p5 = $this->currPos;
      $r4 = self::$FAILED;
      for (;;) {
        if (strcspn($this->input, "{}&<-![ \x09\x0a\x0d\x0c|", $this->currPos, 1) !== 0) {
          $r6 = self::consumeChar($this->input, $this->currPos);
          $r4 = true;
        } else {
          $r6 = self::$FAILED;
          if (!$silence) {$this->fail(48);}
          break;
        }
      }
      if ($r4!==self::$FAILED) {
        $r4 = substr($this->input, $p5, $this->currPos - $p5);
        goto choice_1;
      } else {
        $r4 = self::$FAILED;
      }
      // free $r6
      // free $p5
      $p5 = $this->currPos;
      // start seq_1
      $p7 = $this->currPos;
      $p8 = $this->currPos;
      $r6 = $this->discardinline_breaks(true, $boolParams, $param_templatedepth, $param_preproc, $param_th);
      if ($r6 === self::$FAILED) {
        $r6 = false;
      } else {
        $r6 = self::$FAILED;
        $this->currPos = $p8;
        $r4 = self::$FAILED;
        goto seq_1;
      }
      // free $p8
      // start choice_2
      $r9 = $this->parsedirective($silence, $boolParams, $param_templatedepth, $param_preproc, $param_th);
      if ($r9!==self::$FAILED) {
        goto choice_2;
      }
      if (strspn($this->input, "{}&<-![", $this->currPos, 1) !== 0) {
        $r9 = $this->input[$this->currPos++];
      } else {
        $r9 = self::$FAILED;
        if (!$silence) {$this->fail(46);}
      }
      choice_2:
      // s <- $r9
      if ($r9===self::$FAILED) {
        $this->currPos = $p7;
        $r4 = self::$FAILED;
        goto seq_1;
      }
      $r4 = true;
      seq_1:
      if ($r4!==self::$FAILED) {
        $this->savedPos = $p5;
        $r4 = $this->a39($r9);
      }
      // free $p7
      choice_1:
      if ($r4!==self::$FAILED) {
        $r3[] = $r4;
      } else {
        break;
      }
    }
    if (count($r3) === 0) {
      $r3 = self::$FAILED;
    }
    // r <- $r3
    // free $r4
    $r1 = $r3;
    if ($r1!==self::$FAILED) {
      $this->savedPos = $p2;
      $r1 = $this->a54($r3);
    }
    $cached = ['nextPos' => $this->currPos, 'result' => $r1];
      if ($saved_preproc !== $param_preproc) $cached["\$preproc"] = $param_preproc;
      if ($saved_th !== $param_th) $cached["\$th"] = $param_th;
    $this->cache[$bucket][$key] = $cached;
    return $r1;
  }
  private function parseless_than($silence, $boolParams) {
    $key = json_encode([436, $boolParams & 0x800]);
    $bucket = $this->currPos;
    $cached = $this->cache[$bucket][$key] ?? null;
    if ($cached) {
      $this->currPos = $cached['nextPos'];
  
      return $cached['result'];
    }
  
    $p1 = $this->currPos;
    // start seq_1
    $p3 = $this->currPos;
    if (/*extTag*/($boolParams & 0x800) !== 0) {
      $r4 = false;
    } else {
      $r4 = self::$FAILED;
      $r2 = self::$FAILED;
      goto seq_1;
    }
    if (($this->input[$this->currPos] ?? null) === "<") {
      $this->currPos++;
      $r5 = "<";
    } else {
      if (!$silence) {$this->fail(43);}
      $r5 = self::$FAILED;
      $this->currPos = $p3;
      $r2 = self::$FAILED;
      goto seq_1;
    }
    $r2 = true;
    seq_1:
    if ($r2!==self::$FAILED) {
      $r2 = substr($this->input, $p1, $this->currPos - $p1);
    } else {
      $r2 = self::$FAILED;
    }
    // free $p3
    // free $p1
    $cached = ['nextPos' => $this->currPos, 'result' => $r2];
  
    $this->cache[$bucket][$key] = $cached;
    return $r2;
  }
  private function parseattribute_preprocessor_text_single($silence, $boolParams, $param_templatedepth, &$param_preproc, &$param_th) {
    $key = json_encode([554, $boolParams & 0x1bfe, $param_templatedepth, $param_preproc, $param_th]);
    $bucket = $this->currPos;
    $cached = $this->cache[$bucket][$key] ?? null;
    if ($cached) {
      $this->currPos = $cached['nextPos'];
        if (array_key_exists("\$preproc", $cached)) $param_preproc = $cached["\$preproc"];
        if (array_key_exists("\$th", $cached)) $param_th = $cached["\$th"];
      return $cached['result'];
    }
        $saved_preproc=$param_preproc;
        $saved_th=$param_th;
    $p2 = $this->currPos;
    $r3 = [];
    for (;;) {
      // start choice_1
      $p5 = $this->currPos;
      $r4 = self::$FAILED;
      for (;;) {
        if (strcspn($this->input, "{}&<-|/'>", $this->currPos, 1) !== 0) {
          $r6 = self::consumeChar($this->input, $this->currPos);
          $r4 = true;
        } else {
          $r6 = self::$FAILED;
          if (!$silence) {$this->fail(49);}
          break;
        }
      }
      if ($r4!==self::$FAILED) {
        $r4 = substr($this->input, $p5, $this->currPos - $p5);
        goto choice_1;
      } else {
        $r4 = self::$FAILED;
      }
      // free $r6
      // free $p5
      $p5 = $this->currPos;
      // start seq_1
      $p7 = $this->currPos;
      $p8 = $this->currPos;
      $r6 = $this->discardinline_breaks(true, $boolParams, $param_templatedepth, $param_preproc, $param_th);
      if ($r6 === self::$FAILED) {
        $r6 = false;
      } else {
        $r6 = self::$FAILED;
        $this->currPos = $p8;
        $r4 = self::$FAILED;
        goto seq_1;
      }
      // free $p8
      $p8 = $this->currPos;
      if ($this->currPos >= $this->inputLength ? false : substr_compare($this->input, "/>", $this->currPos, 2, false) === 0) {
        $r9 = "/>";
        $this->currPos += 2;
      } else {
        $r9 = self::$FAILED;
      }
      if ($r9 === self::$FAILED) {
        $r9 = false;
      } else {
        $r9 = self::$FAILED;
        $this->currPos = $p8;
        $this->currPos = $p7;
        $r4 = self::$FAILED;
        goto seq_1;
      }
      // free $p8
      // start choice_2
      $r10 = $this->parsedirective($silence, $boolParams, $param_templatedepth, $param_preproc, $param_th);
      if ($r10!==self::$FAILED) {
        goto choice_2;
      }
      $r10 = $this->parseless_than($silence, $boolParams);
      if ($r10!==self::$FAILED) {
        goto choice_2;
      }
      if (strspn($this->input, "{}&-|/", $this->currPos, 1) !== 0) {
        $r10 = $this->input[$this->currPos++];
      } else {
        $r10 = self::$FAILED;
        if (!$silence) {$this->fail(50);}
      }
      choice_2:
      // s <- $r10
      if ($r10===self::$FAILED) {
        $this->currPos = $p7;
        $r4 = self::$FAILED;
        goto seq_1;
      }
      $r4 = true;
      seq_1:
      if ($r4!==self::$FAILED) {
        $this->savedPos = $p5;
        $r4 = $this->a39($r10);
      }
      // free $p7
      choice_1:
      if ($r4!==self::$FAILED) {
        $r3[] = $r4;
      } else {
        break;
      }
    }
    // r <- $r3
    // free $r4
    $r1 = $r3;
    if ($r1!==self::$FAILED) {
      $this->savedPos = $p2;
      $r1 = $this->a54($r3);
    }
    $cached = ['nextPos' => $this->currPos, 'result' => $r1];
      if ($saved_preproc !== $param_preproc) $cached["\$preproc"] = $param_preproc;
      if ($saved_th !== $param_th) $cached["\$th"] = $param_th;
    $this->cache[$bucket][$key] = $cached;
    return $r1;
  }
  private function parseattribute_preprocessor_text_double($silence, $boolParams, $param_templatedepth, &$param_preproc, &$param_th) {
    $key = json_encode([556, $boolParams & 0x1bfe, $param_templatedepth, $param_preproc, $param_th]);
    $bucket = $this->currPos;
    $cached = $this->cache[$bucket][$key] ?? null;
    if ($cached) {
      $this->currPos = $cached['nextPos'];
        if (array_key_exists("\$preproc", $cached)) $param_preproc = $cached["\$preproc"];
        if (array_key_exists("\$th", $cached)) $param_th = $cached["\$th"];
      return $cached['result'];
    }
        $saved_preproc=$param_preproc;
        $saved_th=$param_th;
    $p2 = $this->currPos;
    $r3 = [];
    for (;;) {
      // start choice_1
      $p5 = $this->currPos;
      $r4 = self::$FAILED;
      for (;;) {
        if (strcspn($this->input, "{}&<-|/\">", $this->currPos, 1) !== 0) {
          $r6 = self::consumeChar($this->input, $this->currPos);
          $r4 = true;
        } else {
          $r6 = self::$FAILED;
          if (!$silence) {$this->fail(51);}
          break;
        }
      }
      if ($r4!==self::$FAILED) {
        $r4 = substr($this->input, $p5, $this->currPos - $p5);
        goto choice_1;
      } else {
        $r4 = self::$FAILED;
      }
      // free $r6
      // free $p5
      $p5 = $this->currPos;
      // start seq_1
      $p7 = $this->currPos;
      $p8 = $this->currPos;
      $r6 = $this->discardinline_breaks(true, $boolParams, $param_templatedepth, $param_preproc, $param_th);
      if ($r6 === self::$FAILED) {
        $r6 = false;
      } else {
        $r6 = self::$FAILED;
        $this->currPos = $p8;
        $r4 = self::$FAILED;
        goto seq_1;
      }
      // free $p8
      $p8 = $this->currPos;
      if ($this->currPos >= $this->inputLength ? false : substr_compare($this->input, "/>", $this->currPos, 2, false) === 0) {
        $r9 = "/>";
        $this->currPos += 2;
      } else {
        $r9 = self::$FAILED;
      }
      if ($r9 === self::$FAILED) {
        $r9 = false;
      } else {
        $r9 = self::$FAILED;
        $this->currPos = $p8;
        $this->currPos = $p7;
        $r4 = self::$FAILED;
        goto seq_1;
      }
      // free $p8
      // start choice_2
      $r10 = $this->parsedirective($silence, $boolParams, $param_templatedepth, $param_preproc, $param_th);
      if ($r10!==self::$FAILED) {
        goto choice_2;
      }
      $r10 = $this->parseless_than($silence, $boolParams);
      if ($r10!==self::$FAILED) {
        goto choice_2;
      }
      if (strspn($this->input, "{}&-|/", $this->currPos, 1) !== 0) {
        $r10 = $this->input[$this->currPos++];
      } else {
        $r10 = self::$FAILED;
        if (!$silence) {$this->fail(50);}
      }
      choice_2:
      // s <- $r10
      if ($r10===self::$FAILED) {
        $this->currPos = $p7;
        $r4 = self::$FAILED;
        goto seq_1;
      }
      $r4 = true;
      seq_1:
      if ($r4!==self::$FAILED) {
        $this->savedPos = $p5;
        $r4 = $this->a39($r10);
      }
      // free $p7
      choice_1:
      if ($r4!==self::$FAILED) {
        $r3[] = $r4;
      } else {
        break;
      }
    }
    // r <- $r3
    // free $r4
    $r1 = $r3;
    if ($r1!==self::$FAILED) {
      $this->savedPos = $p2;
      $r1 = $this->a54($r3);
    }
    $cached = ['nextPos' => $this->currPos, 'result' => $r1];
      if ($saved_preproc !== $param_preproc) $cached["\$preproc"] = $param_preproc;
      if ($saved_th !== $param_th) $cached["\$th"] = $param_th;
    $this->cache[$bucket][$key] = $cached;
    return $r1;
  }
  private function parseattribute_preprocessor_text($silence, $boolParams, $param_templatedepth, &$param_preproc, &$param_th) {
    $key = json_encode([552, $boolParams & 0x1bfe, $param_templatedepth, $param_preproc, $param_th]);
    $bucket = $this->currPos;
    $cached = $this->cache[$bucket][$key] ?? null;
    if ($cached) {
      $this->currPos = $cached['nextPos'];
        if (array_key_exists("\$preproc", $cached)) $param_preproc = $cached["\$preproc"];
        if (array_key_exists("\$th", $cached)) $param_th = $cached["\$th"];
      return $cached['result'];
    }
        $saved_preproc=$param_preproc;
        $saved_th=$param_th;
    $p2 = $this->currPos;
    $r3 = [];
    for (;;) {
      // start choice_1
      $p5 = $this->currPos;
      $r4 = self::$FAILED;
      for (;;) {
        if (strcspn($this->input, "{}&<-|/ \x09\x0a\x0d\x0c>", $this->currPos, 1) !== 0) {
          $r6 = self::consumeChar($this->input, $this->currPos);
          $r4 = true;
        } else {
          $r6 = self::$FAILED;
          if (!$silence) {$this->fail(52);}
          break;
        }
      }
      if ($r4!==self::$FAILED) {
        $r4 = substr($this->input, $p5, $this->currPos - $p5);
        goto choice_1;
      } else {
        $r4 = self::$FAILED;
      }
      // free $r6
      // free $p5
      $p5 = $this->currPos;
      // start seq_1
      $p7 = $this->currPos;
      $p8 = $this->currPos;
      $r6 = $this->discardinline_breaks(true, $boolParams, $param_templatedepth, $param_preproc, $param_th);
      if ($r6 === self::$FAILED) {
        $r6 = false;
      } else {
        $r6 = self::$FAILED;
        $this->currPos = $p8;
        $r4 = self::$FAILED;
        goto seq_1;
      }
      // free $p8
      $p8 = $this->currPos;
      if ($this->currPos >= $this->inputLength ? false : substr_compare($this->input, "/>", $this->currPos, 2, false) === 0) {
        $r9 = "/>";
        $this->currPos += 2;
      } else {
        $r9 = self::$FAILED;
      }
      if ($r9 === self::$FAILED) {
        $r9 = false;
      } else {
        $r9 = self::$FAILED;
        $this->currPos = $p8;
        $this->currPos = $p7;
        $r4 = self::$FAILED;
        goto seq_1;
      }
      // free $p8
      // start choice_2
      $r10 = $this->parsedirective($silence, $boolParams, $param_templatedepth, $param_preproc, $param_th);
      if ($r10!==self::$FAILED) {
        goto choice_2;
      }
      $r10 = $this->parseless_than($silence, $boolParams);
      if ($r10!==self::$FAILED) {
        goto choice_2;
      }
      if (strspn($this->input, "{}&-|/", $this->currPos, 1) !== 0) {
        $r10 = $this->input[$this->currPos++];
      } else {
        $r10 = self::$FAILED;
        if (!$silence) {$this->fail(50);}
      }
      choice_2:
      // s <- $r10
      if ($r10===self::$FAILED) {
        $this->currPos = $p7;
        $r4 = self::$FAILED;
        goto seq_1;
      }
      $r4 = true;
      seq_1:
      if ($r4!==self::$FAILED) {
        $this->savedPos = $p5;
        $r4 = $this->a39($r10);
      }
      // free $p7
      choice_1:
      if ($r4!==self::$FAILED) {
        $r3[] = $r4;
      } else {
        break;
      }
    }
    if (count($r3) === 0) {
      $r3 = self::$FAILED;
    }
    // r <- $r3
    // free $r4
    $r1 = $r3;
    if ($r1!==self::$FAILED) {
      $this->savedPos = $p2;
      $r1 = $this->a54($r3);
    }
    $cached = ['nextPos' => $this->currPos, 'result' => $r1];
      if ($saved_preproc !== $param_preproc) $cached["\$preproc"] = $param_preproc;
      if ($saved_th !== $param_th) $cached["\$th"] = $param_th;
    $this->cache[$bucket][$key] = $cached;
    return $r1;
  }
  private function parseautolink($silence, $boolParams, $param_templatedepth, &$param_preproc, &$param_th) {
    $key = json_encode([328, $boolParams & 0x1bfe, $param_templatedepth, $param_preproc, $param_th]);
    $bucket = $this->currPos;
    $cached = $this->cache[$bucket][$key] ?? null;
    if ($cached) {
      $this->currPos = $cached['nextPos'];
        if (array_key_exists("\$preproc", $cached)) $param_preproc = $cached["\$preproc"];
        if (array_key_exists("\$th", $cached)) $param_th = $cached["\$th"];
      return $cached['result'];
    }
        $saved_preproc=$param_preproc;
        $saved_th=$param_th;
    $p2 = $this->currPos;
    // start seq_1
    $p3 = $this->currPos;
    if (!(/*extlink*/($boolParams & 0x4) !== 0)) {
      $r4 = false;
    } else {
      $r4 = self::$FAILED;
      $r1 = self::$FAILED;
      goto seq_1;
    }
    $this->savedPos = $this->currPos;
    $r5 = $this->a72();
    if (!$r5) {
      $r5 = false;
    } else {
      $r5 = self::$FAILED;
      $this->currPos = $p3;
      $r1 = self::$FAILED;
      goto seq_1;
    }
    // start choice_1
    $r6 = $this->parseautourl($silence, $boolParams, $param_templatedepth, $param_preproc, $param_th);
    if ($r6!==self::$FAILED) {
      goto choice_1;
    }
    $r6 = $this->parseautoref($silence);
    if ($r6!==self::$FAILED) {
      goto choice_1;
    }
    $r6 = $this->parseisbn($silence);
    choice_1:
    // r <- $r6
    if ($r6===self::$FAILED) {
      $this->currPos = $p3;
      $r1 = self::$FAILED;
      goto seq_1;
    }
    $r1 = true;
    seq_1:
    if ($r1!==self::$FAILED) {
      $this->savedPos = $p2;
      $r1 = $this->a20($r6);
    }
    // free $p3
    $cached = ['nextPos' => $this->currPos, 'result' => $r1];
      if ($saved_preproc !== $param_preproc) $cached["\$preproc"] = $param_preproc;
      if ($saved_th !== $param_th) $cached["\$th"] = $param_th;
    $this->cache[$bucket][$key] = $cached;
    return $r1;
  }
  private function parsebehavior_switch($silence) {
    $key = 324;
    $bucket = $this->currPos;
    $cached = $this->cache[$bucket][$key] ?? null;
    if ($cached) {
      $this->currPos = $cached['nextPos'];
  
      return $cached['result'];
    }
  
    $p2 = $this->currPos;
    $p4 = $this->currPos;
    // start seq_1
    $p5 = $this->currPos;
    if ($this->currPos >= $this->inputLength ? false : substr_compare($this->input, "__", $this->currPos, 2, false) === 0) {
      $r6 = "__";
      $this->currPos += 2;
    } else {
      if (!$silence) {$this->fail(53);}
      $r6 = self::$FAILED;
      $r3 = self::$FAILED;
      goto seq_1;
    }
    $r7 = $this->discardbehavior_text($silence);
    if ($r7===self::$FAILED) {
      $this->currPos = $p5;
      $r3 = self::$FAILED;
      goto seq_1;
    }
    if ($this->currPos >= $this->inputLength ? false : substr_compare($this->input, "__", $this->currPos, 2, false) === 0) {
      $r8 = "__";
      $this->currPos += 2;
    } else {
      if (!$silence) {$this->fail(53);}
      $r8 = self::$FAILED;
      $this->currPos = $p5;
      $r3 = self::$FAILED;
      goto seq_1;
    }
    $r3 = true;
    seq_1:
    // bs <- $r3
    if ($r3!==self::$FAILED) {
      $r3 = substr($this->input, $p4, $this->currPos - $p4);
    } else {
      $r3 = self::$FAILED;
    }
    // free $p5
    // free $p4
    $r1 = $r3;
    if ($r1!==self::$FAILED) {
      $this->savedPos = $p2;
      $r1 = $this->a73($r3);
    }
    $cached = ['nextPos' => $this->currPos, 'result' => $r1];
  
    $this->cache[$bucket][$key] = $cached;
    return $r1;
  }
  private function parsexmlish_tag($silence, $boolParams, $param_templatedepth, &$param_preproc, &$param_th) {
    $key = json_encode([426, $boolParams & 0x13ae, $param_templatedepth, $param_preproc, $param_th]);
    $bucket = $this->currPos;
    $cached = $this->cache[$bucket][$key] ?? null;
    if ($cached) {
      $this->currPos = $cached['nextPos'];
        if (array_key_exists("\$preproc", $cached)) $param_preproc = $cached["\$preproc"];
        if (array_key_exists("\$th", $cached)) $param_th = $cached["\$th"];
      return $cached['result'];
    }
        $saved_preproc=$param_preproc;
        $saved_th=$param_th;
    $p2 = $this->currPos;
    // start seq_1
    $p3 = $this->currPos;
    if (($this->input[$this->currPos] ?? null) === "<") {
      $this->currPos++;
      $r4 = "<";
    } else {
      if (!$silence) {$this->fail(43);}
      $r4 = self::$FAILED;
      $r1 = self::$FAILED;
      goto seq_1;
    }
    // start choice_1
    $r5 = $this->parsexmlish_tag_opened($silence, ($boolParams & ~0x400) | 0x800, $param_templatedepth, $param_preproc, $param_th);
    if ($r5!==self::$FAILED) {
      goto choice_1;
    }
    $r5 = $this->parsexmlish_tag_opened($silence, $boolParams & ~0xc00, $param_templatedepth, $param_preproc, $param_th);
    choice_1:
    // tag <- $r5
    if ($r5===self::$FAILED) {
      $this->currPos = $p3;
      $r1 = self::$FAILED;
      goto seq_1;
    }
    $r1 = true;
    seq_1:
    if ($r1!==self::$FAILED) {
      $this->savedPos = $p2;
      $r1 = $this->a67($r5);
    }
    // free $p3
    $cached = ['nextPos' => $this->currPos, 'result' => $r1];
      if ($saved_preproc !== $param_preproc) $cached["\$preproc"] = $param_preproc;
      if ($saved_th !== $param_th) $cached["\$th"] = $param_th;
    $this->cache[$bucket][$key] = $cached;
    return $r1;
  }
  private function parselang_variant_or_tpl($silence, $boolParams, $param_templatedepth, &$param_th, &$param_preproc) {
    $key = json_encode([370, $boolParams & 0x1bfe, $param_templatedepth, $param_th, $param_preproc]);
    $bucket = $this->currPos;
    $cached = $this->cache[$bucket][$key] ?? null;
    if ($cached) {
      $this->currPos = $cached['nextPos'];
        if (array_key_exists("\$th", $cached)) $param_th = $cached["\$th"];
        if (array_key_exists("\$preproc", $cached)) $param_preproc = $cached["\$preproc"];
      return $cached['result'];
    }
        $saved_th=$param_th;
        $saved_preproc=$param_preproc;
    // start choice_1
    $p2 = $this->currPos;
    // start seq_1
    $p3 = $this->currPos;
    $p4 = $this->currPos;
    // start seq_2
    $p6 = $this->currPos;
    if ($this->currPos >= $this->inputLength ? false : substr_compare($this->input, "-{", $this->currPos, 2, false) === 0) {
      $r7 = "-{";
      $this->currPos += 2;
    } else {
      $r7 = self::$FAILED;
      $r5 = self::$FAILED;
      goto seq_2;
    }
    $p8 = $this->currPos;
    // start seq_3
    $p10 = $this->currPos;
    $r11 = self::$FAILED;
    for (;;) {
      if ($this->currPos >= $this->inputLength ? false : substr_compare($this->input, "{{{", $this->currPos, 3, false) === 0) {
        $r12 = "{{{";
        $this->currPos += 3;
        $r11 = true;
      } else {
        $r12 = self::$FAILED;
        break;
      }
    }
    if ($r11===self::$FAILED) {
      $r9 = self::$FAILED;
      goto seq_3;
    }
    // free $r12
    $p13 = $this->currPos;
    if (($this->input[$this->currPos] ?? null) === "{") {
      $this->currPos++;
      $r12 = "{";
    } else {
      $r12 = self::$FAILED;
    }
    if ($r12 === self::$FAILED) {
      $r12 = false;
    } else {
      $r12 = self::$FAILED;
      $this->currPos = $p13;
      $this->currPos = $p10;
      $r9 = self::$FAILED;
      goto seq_3;
    }
    // free $p13
    $r9 = true;
    seq_3:
    if ($r9!==self::$FAILED) {
      $r9 = false;
      $this->currPos = $p8;
    } else {
      $this->currPos = $p6;
      $r5 = self::$FAILED;
      goto seq_2;
    }
    // free $p10
    // free $p8
    $r14 = $this->discardtplarg(true, $boolParams, $param_templatedepth, $param_th);
    if ($r14===self::$FAILED) {
      $this->currPos = $p6;
      $r5 = self::$FAILED;
      goto seq_2;
    }
    $r5 = true;
    seq_2:
    if ($r5!==self::$FAILED) {
      $r5 = false;
      $this->currPos = $p4;
    } else {
      $r1 = self::$FAILED;
      goto seq_1;
    }
    // free $p6
    // free $p4
    $r15 = $this->parselang_variant($silence, $boolParams, $param_templatedepth, $param_th, $param_preproc);
    // a <- $r15
    if ($r15===self::$FAILED) {
      $this->currPos = $p3;
      $r1 = self::$FAILED;
      goto seq_1;
    }
    $r1 = true;
    seq_1:
    if ($r1!==self::$FAILED) {
      $this->savedPos = $p2;
      $r1 = $this->a45($r15);
      goto choice_1;
    }
    // free $p3
    $p3 = $this->currPos;
    // start seq_4
    $p4 = $this->currPos;
    $p6 = $this->currPos;
    // start seq_5
    $p8 = $this->currPos;
    if (($this->input[$this->currPos] ?? null) === "-") {
      $this->currPos++;
      $r17 = "-";
    } else {
      if (!$silence) {$this->fail(54);}
      $r17 = self::$FAILED;
      $r16 = self::$FAILED;
      goto seq_5;
    }
    $p10 = $this->currPos;
    // start seq_6
    $p13 = $this->currPos;
    $r19 = self::$FAILED;
    for (;;) {
      if ($this->currPos >= $this->inputLength ? false : substr_compare($this->input, "{{{", $this->currPos, 3, false) === 0) {
        $r20 = "{{{";
        $this->currPos += 3;
        $r19 = true;
      } else {
        $r20 = self::$FAILED;
        break;
      }
    }
    if ($r19===self::$FAILED) {
      $r18 = self::$FAILED;
      goto seq_6;
    }
    // free $r20
    $p21 = $this->currPos;
    if (($this->input[$this->currPos] ?? null) === "{") {
      $this->currPos++;
      $r20 = "{";
    } else {
      $r20 = self::$FAILED;
    }
    if ($r20 === self::$FAILED) {
      $r20 = false;
    } else {
      $r20 = self::$FAILED;
      $this->currPos = $p21;
      $this->currPos = $p13;
      $r18 = self::$FAILED;
      goto seq_6;
    }
    // free $p21
    $r18 = true;
    seq_6:
    if ($r18!==self::$FAILED) {
      $r18 = false;
      $this->currPos = $p10;
    } else {
      $this->currPos = $p8;
      $r16 = self::$FAILED;
      goto seq_5;
    }
    // free $p13
    // free $p10
    $r16 = true;
    seq_5:
    // a <- $r16
    if ($r16!==self::$FAILED) {
      $r16 = substr($this->input, $p6, $this->currPos - $p6);
    } else {
      $r16 = self::$FAILED;
      $r1 = self::$FAILED;
      goto seq_4;
    }
    // free $p8
    // free $p6
    $r22 = $this->parsetplarg($silence, $boolParams, $param_templatedepth, $param_th);
    // b <- $r22
    if ($r22===self::$FAILED) {
      $this->currPos = $p4;
      $r1 = self::$FAILED;
      goto seq_4;
    }
    $r1 = true;
    seq_4:
    if ($r1!==self::$FAILED) {
      $this->savedPos = $p3;
      $r1 = $this->a46($r16, $r22);
      goto choice_1;
    }
    // free $p4
    $p4 = $this->currPos;
    // start seq_7
    $p6 = $this->currPos;
    $p8 = $this->currPos;
    // start seq_8
    $p10 = $this->currPos;
    if (($this->input[$this->currPos] ?? null) === "-") {
      $this->currPos++;
      $r24 = "-";
    } else {
      if (!$silence) {$this->fail(54);}
      $r24 = self::$FAILED;
      $r23 = self::$FAILED;
      goto seq_8;
    }
    $p13 = $this->currPos;
    // start seq_9
    $p21 = $this->currPos;
    if ($this->currPos >= $this->inputLength ? false : substr_compare($this->input, "{{", $this->currPos, 2, false) === 0) {
      $r26 = "{{";
      $this->currPos += 2;
    } else {
      $r26 = self::$FAILED;
      $r25 = self::$FAILED;
      goto seq_9;
    }
    for (;;) {
      if ($this->currPos >= $this->inputLength ? false : substr_compare($this->input, "{{{", $this->currPos, 3, false) === 0) {
        $r28 = "{{{";
        $this->currPos += 3;
      } else {
        $r28 = self::$FAILED;
        break;
      }
    }
    // free $r28
    $r27 = true;
    if ($r27===self::$FAILED) {
      $this->currPos = $p21;
      $r25 = self::$FAILED;
      goto seq_9;
    }
    // free $r27
    $p29 = $this->currPos;
    if (($this->input[$this->currPos] ?? null) === "{") {
      $this->currPos++;
      $r27 = "{";
    } else {
      $r27 = self::$FAILED;
    }
    if ($r27 === self::$FAILED) {
      $r27 = false;
    } else {
      $r27 = self::$FAILED;
      $this->currPos = $p29;
      $this->currPos = $p21;
      $r25 = self::$FAILED;
      goto seq_9;
    }
    // free $p29
    $r25 = true;
    seq_9:
    if ($r25!==self::$FAILED) {
      $r25 = false;
      $this->currPos = $p13;
    } else {
      $this->currPos = $p10;
      $r23 = self::$FAILED;
      goto seq_8;
    }
    // free $p21
    // free $p13
    $r23 = true;
    seq_8:
    // a <- $r23
    if ($r23!==self::$FAILED) {
      $r23 = substr($this->input, $p8, $this->currPos - $p8);
    } else {
      $r23 = self::$FAILED;
      $r1 = self::$FAILED;
      goto seq_7;
    }
    // free $p10
    // free $p8
    $r28 = $this->parsetemplate($silence, $boolParams, $param_templatedepth, $param_th);
    // b <- $r28
    if ($r28===self::$FAILED) {
      $this->currPos = $p6;
      $r1 = self::$FAILED;
      goto seq_7;
    }
    $r1 = true;
    seq_7:
    if ($r1!==self::$FAILED) {
      $this->savedPos = $p4;
      $r1 = $this->a46($r23, $r28);
      goto choice_1;
    }
    // free $p6
    $p6 = $this->currPos;
    // start seq_10
    $p8 = $this->currPos;
    $p10 = $this->currPos;
    if ($this->currPos >= $this->inputLength ? false : substr_compare($this->input, "-{", $this->currPos, 2, false) === 0) {
      $r30 = "-{";
      $this->currPos += 2;
      $r30 = false;
      $this->currPos = $p10;
    } else {
      $r30 = self::$FAILED;
      $r1 = self::$FAILED;
      goto seq_10;
    }
    // free $p10
    $r31 = $this->parselang_variant($silence, $boolParams, $param_templatedepth, $param_th, $param_preproc);
    // a <- $r31
    if ($r31===self::$FAILED) {
      $this->currPos = $p8;
      $r1 = self::$FAILED;
      goto seq_10;
    }
    $r1 = true;
    seq_10:
    if ($r1!==self::$FAILED) {
      $this->savedPos = $p6;
      $r1 = $this->a45($r31);
    }
    // free $p8
    choice_1:
    $cached = ['nextPos' => $this->currPos, 'result' => $r1];
      if ($saved_th !== $param_th) $cached["\$th"] = $param_th;
      if ($saved_preproc !== $param_preproc) $cached["\$preproc"] = $param_preproc;
    $this->cache[$bucket][$key] = $cached;
    return $r1;
  }
  private function parsewikilink($silence, $boolParams, $param_templatedepth, &$param_th, &$param_preproc) {
    $key = json_encode([402, $boolParams & 0x3bff, $param_templatedepth, $param_th, $param_preproc]);
    $bucket = $this->currPos;
    $cached = $this->cache[$bucket][$key] ?? null;
    if ($cached) {
      $this->currPos = $cached['nextPos'];
        if (array_key_exists("\$th", $cached)) $param_th = $cached["\$th"];
        if (array_key_exists("\$preproc", $cached)) $param_preproc = $cached["\$preproc"];
      return $cached['result'];
    }
        $saved_th=$param_th;
        $saved_preproc=$param_preproc;
    // start choice_1
    $r1 = $this->parsewikilink_preproc($silence, $boolParams, $param_templatedepth, self::newRef("]]"), $param_th);
    if ($r1!==self::$FAILED) {
      goto choice_1;
    }
    $r1 = $this->parsebroken_wikilink($silence, $boolParams, $param_preproc, $param_templatedepth, $param_th);
    choice_1:
    $cached = ['nextPos' => $this->currPos, 'result' => $r1];
      if ($saved_th !== $param_th) $cached["\$th"] = $param_th;
      if ($saved_preproc !== $param_preproc) $cached["\$preproc"] = $param_preproc;
    $this->cache[$bucket][$key] = $cached;
    return $r1;
  }
  private function parsequote($silence) {
    $key = 412;
    $bucket = $this->currPos;
    $cached = $this->cache[$bucket][$key] ?? null;
    if ($cached) {
      $this->currPos = $cached['nextPos'];
  
      return $cached['result'];
    }
  
    $p2 = $this->currPos;
    $p4 = $this->currPos;
    // start seq_1
    $p5 = $this->currPos;
    if ($this->currPos >= $this->inputLength ? false : substr_compare($this->input, "''", $this->currPos, 2, false) === 0) {
      $r6 = "''";
      $this->currPos += 2;
    } else {
      if (!$silence) {$this->fail(55);}
      $r6 = self::$FAILED;
      $r3 = self::$FAILED;
      goto seq_1;
    }
    for (;;) {
      if (($this->input[$this->currPos] ?? null) === "'") {
        $this->currPos++;
        $r8 = "'";
      } else {
        if (!$silence) {$this->fail(33);}
        $r8 = self::$FAILED;
        break;
      }
    }
    // free $r8
    $r7 = true;
    if ($r7===self::$FAILED) {
      $this->currPos = $p5;
      $r3 = self::$FAILED;
      goto seq_1;
    }
    // free $r7
    $r3 = true;
    seq_1:
    // quotes <- $r3
    if ($r3!==self::$FAILED) {
      $r3 = substr($this->input, $p4, $this->currPos - $p4);
    } else {
      $r3 = self::$FAILED;
    }
    // free $p5
    // free $p4
    $r1 = $r3;
    if ($r1!==self::$FAILED) {
      $this->savedPos = $p2;
      $r1 = $this->a74($r3);
    }
    $cached = ['nextPos' => $this->currPos, 'result' => $r1];
  
    $this->cache[$bucket][$key] = $cached;
    return $r1;
  }
  private function parseredirect_word($silence) {
    $key = 292;
    $bucket = $this->currPos;
    $cached = $this->cache[$bucket][$key] ?? null;
    if ($cached) {
      $this->currPos = $cached['nextPos'];
  
      return $cached['result'];
    }
  
    $p1 = $this->currPos;
    // start seq_1
    $p3 = $this->currPos;
    for (;;) {
      if (strspn($this->input, " \x09\x0a\x0d\x00\x0b", $this->currPos, 1) !== 0) {
        $r5 = $this->input[$this->currPos++];
      } else {
        $r5 = self::$FAILED;
        if (!$silence) {$this->fail(56);}
        break;
      }
    }
    // free $r5
    $r4 = true;
    if ($r4===self::$FAILED) {
      $r2 = self::$FAILED;
      goto seq_1;
    }
    // free $r4
    $p6 = $this->currPos;
    $r4 = self::$FAILED;
    for (;;) {
      // start seq_2
      $p7 = $this->currPos;
      $p8 = $this->currPos;
      $r9 = $this->discardspace_or_newline(true);
      if ($r9 === self::$FAILED) {
        $r9 = false;
      } else {
        $r9 = self::$FAILED;
        $this->currPos = $p8;
        $r5 = self::$FAILED;
        goto seq_2;
      }
      // free $p8
      $p8 = $this->currPos;
      $r10 = $this->input[$this->currPos] ?? '';
      if ($r10 === ":" || $r10 === "[") {
        $this->currPos++;
      } else {
        $r10 = self::$FAILED;
      }
      if ($r10 === self::$FAILED) {
        $r10 = false;
      } else {
        $r10 = self::$FAILED;
        $this->currPos = $p8;
        $this->currPos = $p7;
        $r5 = self::$FAILED;
        goto seq_2;
      }
      // free $p8
      if ($this->currPos < $this->inputLength) {
        $r11 = self::consumeChar($this->input, $this->currPos);;
      } else {
        $r11 = self::$FAILED;
        if (!$silence) {$this->fail(7);}
        $this->currPos = $p7;
        $r5 = self::$FAILED;
        goto seq_2;
      }
      $r5 = true;
      seq_2:
      if ($r5!==self::$FAILED) {
        $r4 = true;
      } else {
        break;
      }
      // free $p7
    }
    // rw <- $r4
    if ($r4!==self::$FAILED) {
      $r4 = substr($this->input, $p6, $this->currPos - $p6);
    } else {
      $r4 = self::$FAILED;
      $this->currPos = $p3;
      $r2 = self::$FAILED;
      goto seq_1;
    }
    // free $r5
    // free $p6
    $this->savedPos = $this->currPos;
    $r5 = $this->a75($r4);
    if ($r5) {
      $r5 = false;
    } else {
      $r5 = self::$FAILED;
      $this->currPos = $p3;
      $r2 = self::$FAILED;
      goto seq_1;
    }
    $r2 = true;
    seq_1:
    if ($r2!==self::$FAILED) {
      $r2 = substr($this->input, $p1, $this->currPos - $p1);
    } else {
      $r2 = self::$FAILED;
    }
    // free $p3
    // free $p1
    $cached = ['nextPos' => $this->currPos, 'result' => $r2];
  
    $this->cache[$bucket][$key] = $cached;
    return $r2;
  }
  private function parseinclude_limits($silence, $boolParams, $param_templatedepth, &$param_preproc, &$param_th) {
    $key = json_encode([530, $boolParams & 0x33ae, $param_templatedepth, $param_preproc, $param_th]);
    $bucket = $this->currPos;
    $cached = $this->cache[$bucket][$key] ?? null;
    if ($cached) {
      $this->currPos = $cached['nextPos'];
        if (array_key_exists("\$preproc", $cached)) $param_preproc = $cached["\$preproc"];
        if (array_key_exists("\$th", $cached)) $param_th = $cached["\$th"];
      return $cached['result'];
    }
        $saved_preproc=$param_preproc;
        $saved_th=$param_th;
    $p2 = $this->currPos;
    // start seq_1
    $p3 = $this->currPos;
    $p4 = $this->currPos;
    // start seq_2
    $p6 = $this->currPos;
    if (($this->input[$this->currPos] ?? null) === "<") {
      $this->currPos++;
      $r7 = "<";
    } else {
      $r7 = self::$FAILED;
      $r5 = self::$FAILED;
      goto seq_2;
    }
    if (($this->input[$this->currPos] ?? null) === "/") {
      $this->currPos++;
      $r8 = "/";
    } else {
      $r8 = self::$FAILED;
      $r8 = null;
    }
    // start choice_1
    if ($this->currPos >= $this->inputLength ? false : substr_compare($this->input, "includeonly", $this->currPos, 11, true) === 0) {
      $r9 = substr($this->input, $this->currPos, 11);
      $this->currPos += 11;
      goto choice_1;
    } else {
      $r9 = self::$FAILED;
    }
    if ($this->currPos >= $this->inputLength ? false : substr_compare($this->input, "noinclude", $this->currPos, 9, true) === 0) {
      $r9 = substr($this->input, $this->currPos, 9);
      $this->currPos += 9;
      goto choice_1;
    } else {
      $r9 = self::$FAILED;
    }
    if ($this->currPos >= $this->inputLength ? false : substr_compare($this->input, "onlyinclude", $this->currPos, 11, true) === 0) {
      $r9 = substr($this->input, $this->currPos, 11);
      $this->currPos += 11;
    } else {
      $r9 = self::$FAILED;
    }
    choice_1:
    // n <- $r9
    if ($r9===self::$FAILED) {
      $this->currPos = $p6;
      $r5 = self::$FAILED;
      goto seq_2;
    }
    $r5 = true;
    seq_2:
    if ($r5!==self::$FAILED) {
      $r5 = false;
      $this->currPos = $p4;
    } else {
      $r1 = self::$FAILED;
      goto seq_1;
    }
    // free $p6
    // free $p4
    $r10 = $this->parsexmlish_tag($silence, $boolParams, $param_templatedepth, $param_preproc, $param_th);
    // il <- $r10
    if ($r10===self::$FAILED) {
      $this->currPos = $p3;
      $r1 = self::$FAILED;
      goto seq_1;
    }
    $this->savedPos = $this->currPos;
    $r11 = $this->a76($r10, /*sol_il*/($boolParams & 0x2000) !== 0);
    if ($r11) {
      $r11 = false;
    } else {
      $r11 = self::$FAILED;
      $this->currPos = $p3;
      $r1 = self::$FAILED;
      goto seq_1;
    }
    $r1 = true;
    seq_1:
    if ($r1!==self::$FAILED) {
      $this->savedPos = $p2;
      $r1 = $this->a77($r10, /*sol_il*/($boolParams & 0x2000) !== 0);
    }
    // free $p3
    $cached = ['nextPos' => $this->currPos, 'result' => $r1];
      if ($saved_preproc !== $param_preproc) $cached["\$preproc"] = $param_preproc;
      if ($saved_th !== $param_th) $cached["\$th"] = $param_th;
    $this->cache[$bucket][$key] = $cached;
    return $r1;
  }
  private function parseheading($silence, $boolParams, $param_templatedepth, &$param_preproc, &$param_th) {
    $key = json_encode([320, $boolParams & 0x1bfc, $param_templatedepth, $param_preproc, $param_th]);
    $bucket = $this->currPos;
    $cached = $this->cache[$bucket][$key] ?? null;
    if ($cached) {
      $this->currPos = $cached['nextPos'];
        if (array_key_exists("\$preproc", $cached)) $param_preproc = $cached["\$preproc"];
        if (array_key_exists("\$th", $cached)) $param_th = $cached["\$th"];
      return $cached['result'];
    }
        $saved_preproc=$param_preproc;
        $saved_th=$param_th;
    $p2 = $this->currPos;
    // start seq_1
    $p3 = $this->currPos;
    $p4 = $this->currPos;
    if (($this->input[$this->currPos] ?? null) === "=") {
      $this->currPos++;
      $r5 = "=";
      $r5 = false;
      $this->currPos = $p4;
    } else {
      $r5 = self::$FAILED;
      $r1 = self::$FAILED;
      goto seq_1;
    }
    // free $p4
    $p4 = $this->currPos;
    // start seq_2
    $p7 = $this->currPos;
    $p9 = $this->currPos;
    $r8 = self::$FAILED;
    for (;;) {
      if (($this->input[$this->currPos] ?? null) === "=") {
        $this->currPos++;
        $r10 = "=";
        $r8 = true;
      } else {
        if (!$silence) {$this->fail(23);}
        $r10 = self::$FAILED;
        break;
      }
    }
    // s <- $r8
    if ($r8!==self::$FAILED) {
      $r8 = substr($this->input, $p9, $this->currPos - $p9);
    } else {
      $r8 = self::$FAILED;
      $r6 = self::$FAILED;
      goto seq_2;
    }
    // free $r10
    // free $p9
    // start seq_3
    $p9 = $this->currPos;
    $p12 = $this->currPos;
    $r13 = $this->parseinlineline($silence, $boolParams | 0x2, $param_templatedepth, $param_preproc, $param_th);
    if ($r13===self::$FAILED) {
      $r13 = null;
    }
    // ill <- $r13
    $r11 = $r13;
    if ($r11!==self::$FAILED) {
      $this->savedPos = $p12;
      $r11 = $this->a78($r8, $r13);
    } else {
      $r10 = self::$FAILED;
      goto seq_3;
    }
    $p14 = $this->currPos;
    $r15 = self::$FAILED;
    for (;;) {
      if (($this->input[$this->currPos] ?? null) === "=") {
        $this->currPos++;
        $r16 = "=";
        $r15 = true;
      } else {
        if (!$silence) {$this->fail(23);}
        $r16 = self::$FAILED;
        break;
      }
    }
    if ($r15!==self::$FAILED) {
      $r15 = substr($this->input, $p14, $this->currPos - $p14);
    } else {
      $r15 = self::$FAILED;
      $this->currPos = $p9;
      $r10 = self::$FAILED;
      goto seq_3;
    }
    // free $r16
    // free $p14
    $r10 = [$r11,$r15];
    seq_3:
    if ($r10===self::$FAILED) {
      $r10 = null;
    }
    // free $p9
    // ce <- $r10
    $this->savedPos = $this->currPos;
    $r16 = $this->a79($r8, $r10);
    if ($r16) {
      $r16 = false;
    } else {
      $r16 = self::$FAILED;
      $this->currPos = $p7;
      $r6 = self::$FAILED;
      goto seq_2;
    }
    $p9 = $this->currPos;
    $r17 = '';
    // endTPos <- $r17
    if ($r17!==self::$FAILED) {
      $this->savedPos = $p9;
      $r17 = $this->a80($r8, $r10);
    } else {
      $this->currPos = $p7;
      $r6 = self::$FAILED;
      goto seq_2;
    }
    $r18 = [];
    for (;;) {
      // start choice_1
      $r19 = $this->parsespaces($silence);
      if ($r19!==self::$FAILED) {
        goto choice_1;
      }
      $r19 = $this->parsecomment($silence);
      choice_1:
      if ($r19!==self::$FAILED) {
        $r18[] = $r19;
      } else {
        break;
      }
    }
    // spc <- $r18
    // free $r19
    $p14 = $this->currPos;
    $r19 = $this->discardeolf(true);
    if ($r19!==self::$FAILED) {
      $r19 = false;
      $this->currPos = $p14;
    } else {
      $this->currPos = $p7;
      $r6 = self::$FAILED;
      goto seq_2;
    }
    // free $p14
    $r6 = true;
    seq_2:
    // r <- $r6
    if ($r6!==self::$FAILED) {
      $this->savedPos = $p4;
      $r6 = $this->a81($r8, $r10, $r17, $r18);
    } else {
      $this->currPos = $p3;
      $r1 = self::$FAILED;
      goto seq_1;
    }
    // free $p7
    $r1 = true;
    seq_1:
    if ($r1!==self::$FAILED) {
      $this->savedPos = $p2;
      $r1 = $this->a20($r6);
    }
    // free $p3
    $cached = ['nextPos' => $this->currPos, 'result' => $r1];
      if ($saved_preproc !== $param_preproc) $cached["\$preproc"] = $param_preproc;
      if ($saved_th !== $param_th) $cached["\$th"] = $param_th;
    $this->cache[$bucket][$key] = $cached;
    return $r1;
  }
  private function parselist_item($silence, $boolParams, $param_templatedepth, &$param_preproc, &$param_th) {
    $key = json_encode([448, $boolParams & 0x3bff, $param_templatedepth, $param_preproc, $param_th]);
    $bucket = $this->currPos;
    $cached = $this->cache[$bucket][$key] ?? null;
    if ($cached) {
      $this->currPos = $cached['nextPos'];
        if (array_key_exists("\$preproc", $cached)) $param_preproc = $cached["\$preproc"];
        if (array_key_exists("\$th", $cached)) $param_th = $cached["\$th"];
      return $cached['result'];
    }
        $saved_preproc=$param_preproc;
        $saved_th=$param_th;
    // start choice_1
    $r1 = $this->parsedtdd($silence, $boolParams, $param_templatedepth, $param_preproc, $param_th);
    if ($r1!==self::$FAILED) {
      goto choice_1;
    }
    $r1 = $this->parsehacky_dl_uses($silence, $boolParams, $param_templatedepth, $param_preproc, $param_th);
    if ($r1!==self::$FAILED) {
      goto choice_1;
    }
    $r1 = $this->parseli($silence, $boolParams, $param_templatedepth, $param_preproc, $param_th);
    choice_1:
    $cached = ['nextPos' => $this->currPos, 'result' => $r1];
      if ($saved_preproc !== $param_preproc) $cached["\$preproc"] = $param_preproc;
      if ($saved_th !== $param_th) $cached["\$th"] = $param_th;
    $this->cache[$bucket][$key] = $cached;
    return $r1;
  }
  private function parsehr($silence, $boolParams, $param_templatedepth, &$param_preproc, &$param_th) {
    $key = json_encode([306, $boolParams & 0x13ae, $param_templatedepth, $param_preproc, $param_th]);
    $bucket = $this->currPos;
    $cached = $this->cache[$bucket][$key] ?? null;
    if ($cached) {
      $this->currPos = $cached['nextPos'];
        if (array_key_exists("\$preproc", $cached)) $param_preproc = $cached["\$preproc"];
        if (array_key_exists("\$th", $cached)) $param_th = $cached["\$th"];
      return $cached['result'];
    }
        $saved_preproc=$param_preproc;
        $saved_th=$param_th;
    $p2 = $this->currPos;
    // start seq_1
    $p3 = $this->currPos;
    if ($this->currPos >= $this->inputLength ? false : substr_compare($this->input, "----", $this->currPos, 4, false) === 0) {
      $r4 = "----";
      $this->currPos += 4;
    } else {
      if (!$silence) {$this->fail(57);}
      $r4 = self::$FAILED;
      $r1 = self::$FAILED;
      goto seq_1;
    }
    $p6 = $this->currPos;
    for (;;) {
      if (($this->input[$this->currPos] ?? null) === "-") {
        $this->currPos++;
        $r7 = "-";
      } else {
        if (!$silence) {$this->fail(54);}
        $r7 = self::$FAILED;
        break;
      }
    }
    // free $r7
    $r5 = true;
    // d <- $r5
    if ($r5!==self::$FAILED) {
      $r5 = substr($this->input, $p6, $this->currPos - $p6);
    } else {
      $r5 = self::$FAILED;
      $this->currPos = $p3;
      $r1 = self::$FAILED;
      goto seq_1;
    }
    // free $p6
    // start choice_1
    $p6 = $this->currPos;
    // start seq_2
    $p8 = $this->currPos;
    $p9 = $this->currPos;
    $r10 = $this->discardsol(true, $boolParams, $param_templatedepth, $param_preproc, $param_th);
    if ($r10!==self::$FAILED) {
      $r10 = false;
      $this->currPos = $p9;
    } else {
      $r7 = self::$FAILED;
      goto seq_2;
    }
    // free $p9
    $r7 = true;
    seq_2:
    if ($r7!==self::$FAILED) {
      $this->savedPos = $p6;
      $r7 = $this->a82($r5);
      goto choice_1;
    }
    // free $p8
    $p8 = $this->currPos;
    $r7 = '';
    if ($r7!==self::$FAILED) {
      $this->savedPos = $p8;
      $r7 = $this->a83($r5);
    }
    choice_1:
    // lineContent <- $r7
    if ($r7===self::$FAILED) {
      $this->currPos = $p3;
      $r1 = self::$FAILED;
      goto seq_1;
    }
    $r1 = true;
    seq_1:
    if ($r1!==self::$FAILED) {
      $this->savedPos = $p2;
      $r1 = $this->a84($r5, $r7);
    }
    // free $p3
    $cached = ['nextPos' => $this->currPos, 'result' => $r1];
      if ($saved_preproc !== $param_preproc) $cached["\$preproc"] = $param_preproc;
      if ($saved_th !== $param_th) $cached["\$th"] = $param_th;
    $this->cache[$bucket][$key] = $cached;
    return $r1;
  }
  private function parsetable_line($silence, $boolParams, $param_templatedepth, &$param_preproc, &$param_th) {
    $key = json_encode([464, $boolParams & 0x3bff, $param_templatedepth, $param_preproc, $param_th]);
    $bucket = $this->currPos;
    $cached = $this->cache[$bucket][$key] ?? null;
    if ($cached) {
      $this->currPos = $cached['nextPos'];
        if (array_key_exists("\$preproc", $cached)) $param_preproc = $cached["\$preproc"];
        if (array_key_exists("\$th", $cached)) $param_th = $cached["\$th"];
      return $cached['result'];
    }
        $saved_preproc=$param_preproc;
        $saved_th=$param_th;
    $p2 = $this->currPos;
    // start seq_1
    $p3 = $this->currPos;
    // start choice_1
    $p5 = $this->currPos;
    $r4 = $this->discardinline_breaks(true, $boolParams, $param_templatedepth, $param_preproc, $param_th);
    if ($r4 === self::$FAILED) {
      $r4 = false;
      goto choice_1;
    } else {
      $r4 = self::$FAILED;
      $this->currPos = $p5;
    }
    // free $p5
    $p5 = $this->currPos;
    if ($this->currPos >= $this->inputLength ? false : substr_compare($this->input, "{{!}}", $this->currPos, 5, false) === 0) {
      $r4 = "{{!}}";
      $this->currPos += 5;
      $r4 = false;
      $this->currPos = $p5;
    } else {
      $r4 = self::$FAILED;
    }
    // free $p5
    choice_1:
    if ($r4===self::$FAILED) {
      $r1 = self::$FAILED;
      goto seq_1;
    }
    // start choice_2
    // start seq_2
    $p5 = $this->currPos;
    $r7 = $this->parsetable_start_tag($silence, $boolParams, $param_templatedepth, $param_preproc, $param_th);
    if ($r7===self::$FAILED) {
      $r6 = self::$FAILED;
      goto seq_2;
    }
    $r8 = $this->parseoptionalNewlines($silence);
    if ($r8===self::$FAILED) {
      $this->currPos = $p5;
      $r6 = self::$FAILED;
      goto seq_2;
    }
    $r6 = [$r7,$r8];
    seq_2:
    if ($r6!==self::$FAILED) {
      goto choice_2;
    }
    // free $p5
    // start seq_3
    $p5 = $this->currPos;
    $r9 = $this->parsetable_content_line($silence, $boolParams | 0x10, $param_templatedepth, $param_preproc, $param_th);
    if ($r9===self::$FAILED) {
      $r6 = self::$FAILED;
      goto seq_3;
    }
    $r10 = $this->parseoptionalNewlines($silence);
    if ($r10===self::$FAILED) {
      $this->currPos = $p5;
      $r6 = self::$FAILED;
      goto seq_3;
    }
    $r6 = [$r9,$r10];
    seq_3:
    if ($r6!==self::$FAILED) {
      goto choice_2;
    }
    // free $p5
    $r6 = $this->parsetable_end_tag($silence);
    choice_2:
    // tl <- $r6
    if ($r6===self::$FAILED) {
      $this->currPos = $p3;
      $r1 = self::$FAILED;
      goto seq_1;
    }
    $r1 = true;
    seq_1:
    if ($r1!==self::$FAILED) {
      $this->savedPos = $p2;
      $r1 = $this->a85($r6);
    }
    // free $p3
    $cached = ['nextPos' => $this->currPos, 'result' => $r1];
      if ($saved_preproc !== $param_preproc) $cached["\$preproc"] = $param_preproc;
      if ($saved_th !== $param_th) $cached["\$th"] = $param_th;
    $this->cache[$bucket][$key] = $cached;
    return $r1;
  }
  private function parsexmlish_tag_opened($silence, $boolParams, $param_templatedepth, &$param_preproc, &$param_th) {
    $key = json_encode([428, $boolParams & 0x1fae, $param_templatedepth, $param_preproc, $param_th]);
    $bucket = $this->currPos;
    $cached = $this->cache[$bucket][$key] ?? null;
    if ($cached) {
      $this->currPos = $cached['nextPos'];
        if (array_key_exists("\$preproc", $cached)) $param_preproc = $cached["\$preproc"];
        if (array_key_exists("\$th", $cached)) $param_th = $cached["\$th"];
      return $cached['result'];
    }
        $saved_preproc=$param_preproc;
        $saved_th=$param_th;
    $p2 = $this->currPos;
    // start seq_1
    $p3 = $this->currPos;
    if (($this->input[$this->currPos] ?? null) === "/") {
      $this->currPos++;
      $r4 = "/";
    } else {
      if (!$silence) {$this->fail(35);}
      $r4 = self::$FAILED;
      $r4 = null;
    }
    // end <- $r4
    $r5 = $this->parsetag_name($silence);
    // name <- $r5
    if ($r5===self::$FAILED) {
      $this->currPos = $p3;
      $r1 = self::$FAILED;
      goto seq_1;
    }
    $this->savedPos = $this->currPos;
    $r6 = $this->a86($r4, $r5, /*extTag*/($boolParams & 0x800) !== 0, /*isBlock*/($boolParams & 0x400) !== 0);
    if ($r6) {
      $r6 = false;
    } else {
      $r6 = self::$FAILED;
      $this->currPos = $p3;
      $r1 = self::$FAILED;
      goto seq_1;
    }
    $r7 = $this->parsegeneric_newline_attributes($silence, $boolParams & ~0x50, $param_templatedepth, $param_preproc, $param_th);
    // attribs <- $r7
    if ($r7===self::$FAILED) {
      $this->currPos = $p3;
      $r1 = self::$FAILED;
      goto seq_1;
    }
    for (;;) {
      $r9 = $this->discardspace_or_newline_or_solidus($silence);
      if ($r9===self::$FAILED) {
        break;
      }
    }
    // free $r9
    $r8 = true;
    if ($r8===self::$FAILED) {
      $this->currPos = $p3;
      $r1 = self::$FAILED;
      goto seq_1;
    }
    // free $r8
    if (($this->input[$this->currPos] ?? null) === "/") {
      $this->currPos++;
      $r8 = "/";
    } else {
      if (!$silence) {$this->fail(35);}
      $r8 = self::$FAILED;
      $r8 = null;
    }
    // selfclose <- $r8
    for (;;) {
      $r10 = $this->discardspace($silence);
      if ($r10===self::$FAILED) {
        break;
      }
    }
    // free $r10
    $r9 = true;
    if ($r9===self::$FAILED) {
      $this->currPos = $p3;
      $r1 = self::$FAILED;
      goto seq_1;
    }
    // free $r9
    if (($this->input[$this->currPos] ?? null) === ">") {
      $this->currPos++;
      $r9 = ">";
    } else {
      if (!$silence) {$this->fail(58);}
      $r9 = self::$FAILED;
      $this->currPos = $p3;
      $r1 = self::$FAILED;
      goto seq_1;
    }
    $r1 = true;
    seq_1:
    if ($r1!==self::$FAILED) {
      $this->savedPos = $p2;
      $r1 = $this->a87($r4, $r5, /*extTag*/($boolParams & 0x800) !== 0, /*isBlock*/($boolParams & 0x400) !== 0, $r7, $r8);
    }
    // free $p3
    $cached = ['nextPos' => $this->currPos, 'result' => $r1];
      if ($saved_preproc !== $param_preproc) $cached["\$preproc"] = $param_preproc;
      if ($saved_th !== $param_th) $cached["\$th"] = $param_th;
    $this->cache[$bucket][$key] = $cached;
    return $r1;
  }
  private function parseempty_line_with_comments($silence) {
    $key = 524;
    $bucket = $this->currPos;
    $cached = $this->cache[$bucket][$key] ?? null;
    if ($cached) {
      $this->currPos = $cached['nextPos'];
  
      return $cached['result'];
    }
  
    $p2 = $this->currPos;
    // start seq_1
    $p3 = $this->currPos;
    $r4 = $this->parsesol_prefix($silence);
    // sp <- $r4
    if ($r4===self::$FAILED) {
      $r1 = self::$FAILED;
      goto seq_1;
    }
    $p6 = $this->currPos;
    $r5 = '';
    // p <- $r5
    if ($r5!==self::$FAILED) {
      $this->savedPos = $p6;
      $r5 = $this->a88($r4);
    } else {
      $this->currPos = $p3;
      $r1 = self::$FAILED;
      goto seq_1;
    }
    $r7 = [];
    for (;;) {
      // start seq_2
      $p9 = $this->currPos;
      $r10 = [];
      for (;;) {
        $r11 = $this->parsespace($silence);
        if ($r11!==self::$FAILED) {
          $r10[] = $r11;
        } else {
          break;
        }
      }
      // free $r11
      $r11 = $this->parsecomment($silence);
      if ($r11===self::$FAILED) {
        $this->currPos = $p9;
        $r8 = self::$FAILED;
        goto seq_2;
      }
      $r12 = [];
      for (;;) {
        // start choice_1
        $r13 = $this->parsespace($silence);
        if ($r13!==self::$FAILED) {
          goto choice_1;
        }
        $r13 = $this->parsecomment($silence);
        choice_1:
        if ($r13!==self::$FAILED) {
          $r12[] = $r13;
        } else {
          break;
        }
      }
      // free $r13
      $r13 = $this->parsenewline($silence);
      if ($r13===self::$FAILED) {
        $this->currPos = $p9;
        $r8 = self::$FAILED;
        goto seq_2;
      }
      $r8 = [$r10,$r11,$r12,$r13];
      seq_2:
      if ($r8!==self::$FAILED) {
        $r7[] = $r8;
      } else {
        break;
      }
      // free $p9
    }
    if (count($r7) === 0) {
      $r7 = self::$FAILED;
    }
    // c <- $r7
    if ($r7===self::$FAILED) {
      $this->currPos = $p3;
      $r1 = self::$FAILED;
      goto seq_1;
    }
    // free $r8
    $r1 = true;
    seq_1:
    if ($r1!==self::$FAILED) {
      $this->savedPos = $p2;
      $r1 = $this->a89($r4, $r5, $r7);
    }
    // free $p3
    $cached = ['nextPos' => $this->currPos, 'result' => $r1];
  
    $this->cache[$bucket][$key] = $cached;
    return $r1;
  }
  private function parsesol_prefix($silence) {
    $key = 522;
    $bucket = $this->currPos;
    $cached = $this->cache[$bucket][$key] ?? null;
    if ($cached) {
      $this->currPos = $cached['nextPos'];
  
      return $cached['result'];
    }
  
    // start choice_1
    $r1 = $this->parsenewlineToken($silence);
    if ($r1!==self::$FAILED) {
      goto choice_1;
    }
    $p2 = $this->currPos;
    $this->savedPos = $this->currPos;
    $r1 = $this->a90();
    if ($r1) {
      $r1 = false;
      $this->savedPos = $p2;
      $r1 = $this->a91();
    } else {
      $r1 = self::$FAILED;
    }
    choice_1:
    $cached = ['nextPos' => $this->currPos, 'result' => $r1];
  
    $this->cache[$bucket][$key] = $cached;
    return $r1;
  }
  private function discardtplarg_preproc($silence, $boolParams, $param_templatedepth, &$param_preproc, &$param_th) {
    $key = json_encode([361, $boolParams & 0x1bfe, $param_templatedepth, $param_preproc, $param_th]);
    $bucket = $this->currPos;
    $cached = $this->cache[$bucket][$key] ?? null;
    if ($cached) {
      $this->currPos = $cached['nextPos'];
        if (array_key_exists("\$preproc", $cached)) $param_preproc = $cached["\$preproc"];
        if (array_key_exists("\$th", $cached)) $param_th = $cached["\$th"];
      return $cached['result'];
    }
        $saved_preproc=$param_preproc;
        $saved_th=$param_th;
    $p2 = $this->currPos;
    // start seq_1
    $p3 = $this->currPos;
    if ($this->currPos >= $this->inputLength ? false : substr_compare($this->input, "{{{", $this->currPos, 3, false) === 0) {
      $r4 = "{{{";
      $this->currPos += 3;
    } else {
      if (!$silence) {$this->fail(59);}
      $r4 = self::$FAILED;
      $r1 = self::$FAILED;
      goto seq_1;
    }
    $p6 = $this->currPos;
    $r5 = '';
    // p <- $r5
    if ($r5!==self::$FAILED) {
      $this->savedPos = $p6;
      $r5 = $this->a14();
    } else {
      $this->currPos = $p3;
      $r1 = self::$FAILED;
      goto seq_1;
    }
    $r7 = $this->parsetemplate_param_value($silence, $boolParams, $param_templatedepth, $param_preproc, $param_th);
    if ($r7===self::$FAILED) {
      $r7 = null;
    }
    // target <- $r7
    $r8 = [];
    for (;;) {
      $p10 = $this->currPos;
      // start seq_2
      $p11 = $this->currPos;
      for (;;) {
        $r13 = $this->discardnl_comment_space($silence);
        if ($r13===self::$FAILED) {
          break;
        }
      }
      // free $r13
      $r12 = true;
      if ($r12===self::$FAILED) {
        $r9 = self::$FAILED;
        goto seq_2;
      }
      // free $r12
      if (($this->input[$this->currPos] ?? null) === "|") {
        $this->currPos++;
        $r12 = "|";
      } else {
        if (!$silence) {$this->fail(13);}
        $r12 = self::$FAILED;
        $this->currPos = $p11;
        $r9 = self::$FAILED;
        goto seq_2;
      }
      // start choice_1
      $p14 = $this->currPos;
      // start seq_3
      $p15 = $this->currPos;
      $p17 = $this->currPos;
      $r16 = '';
      // p0 <- $r16
      if ($r16!==self::$FAILED) {
        $this->savedPos = $p17;
        $r16 = $this->a92($r5, $r7);
      } else {
        $r13 = self::$FAILED;
        goto seq_3;
      }
      $r18 = [];
      for (;;) {
        $r19 = $this->parsenl_comment_space($silence);
        if ($r19!==self::$FAILED) {
          $r18[] = $r19;
        } else {
          break;
        }
      }
      // v <- $r18
      // free $r19
      $p20 = $this->currPos;
      $r19 = '';
      // p1 <- $r19
      if ($r19!==self::$FAILED) {
        $this->savedPos = $p20;
        $r19 = $this->a93($r5, $r7, $r16, $r18);
      } else {
        $this->currPos = $p15;
        $r13 = self::$FAILED;
        goto seq_3;
      }
      $p21 = $this->currPos;
      // start choice_2
      if (($this->input[$this->currPos] ?? null) === "|") {
        $this->currPos++;
        $r22 = "|";
        goto choice_2;
      } else {
        $r22 = self::$FAILED;
      }
      if ($this->currPos >= $this->inputLength ? false : substr_compare($this->input, "}}}", $this->currPos, 3, false) === 0) {
        $r22 = "}}}";
        $this->currPos += 3;
      } else {
        $r22 = self::$FAILED;
      }
      choice_2:
      if ($r22!==self::$FAILED) {
        $r22 = false;
        $this->currPos = $p21;
      } else {
        $this->currPos = $p15;
        $r13 = self::$FAILED;
        goto seq_3;
      }
      // free $p21
      $r13 = true;
      seq_3:
      if ($r13!==self::$FAILED) {
        $this->savedPos = $p14;
        $r13 = $this->a94($r5, $r7, $r16, $r18, $r19);
        goto choice_1;
      }
      // free $p15
      $r13 = $this->parsetemplate_param_value($silence, $boolParams, $param_templatedepth, $param_preproc, $param_th);
      choice_1:
      // r <- $r13
      if ($r13===self::$FAILED) {
        $this->currPos = $p11;
        $r9 = self::$FAILED;
        goto seq_2;
      }
      $r9 = true;
      seq_2:
      if ($r9!==self::$FAILED) {
        $this->savedPos = $p10;
        $r9 = $this->a95($r5, $r7, $r13);
        $r8[] = $r9;
      } else {
        break;
      }
      // free $p11
    }
    // params <- $r8
    // free $r9
    for (;;) {
      $r23 = $this->discardnl_comment_space($silence);
      if ($r23===self::$FAILED) {
        break;
      }
    }
    // free $r23
    $r9 = true;
    if ($r9===self::$FAILED) {
      $this->currPos = $p3;
      $r1 = self::$FAILED;
      goto seq_1;
    }
    // free $r9
    $r9 = $this->discardinline_breaks($silence, $boolParams, $param_templatedepth, $param_preproc, $param_th);
    if ($r9===self::$FAILED) {
      $this->currPos = $p3;
      $r1 = self::$FAILED;
      goto seq_1;
    }
    if ($this->currPos >= $this->inputLength ? false : substr_compare($this->input, "}}}", $this->currPos, 3, false) === 0) {
      $r23 = "}}}";
      $this->currPos += 3;
    } else {
      if (!$silence) {$this->fail(60);}
      $r23 = self::$FAILED;
      $this->currPos = $p3;
      $r1 = self::$FAILED;
      goto seq_1;
    }
    $r1 = true;
    seq_1:
    if ($r1!==self::$FAILED) {
      $this->savedPos = $p2;
      $r1 = $this->a96($r5, $r7, $r8);
    }
    // free $p3
    $cached = ['nextPos' => $this->currPos, 'result' => $r1];
      if ($saved_preproc !== $param_preproc) $cached["\$preproc"] = $param_preproc;
      if ($saved_th !== $param_th) $cached["\$th"] = $param_th;
    $this->cache[$bucket][$key] = $cached;
    return $r1;
  }
  private function parsetemplate_preproc($silence, $boolParams, $param_templatedepth, &$param_preproc, &$param_th) {
    $key = json_encode([356, $boolParams & 0x1bfe, $param_templatedepth, $param_preproc, $param_th]);
    $bucket = $this->currPos;
    $cached = $this->cache[$bucket][$key] ?? null;
    if ($cached) {
      $this->currPos = $cached['nextPos'];
        if (array_key_exists("\$preproc", $cached)) $param_preproc = $cached["\$preproc"];
        if (array_key_exists("\$th", $cached)) $param_th = $cached["\$th"];
      return $cached['result'];
    }
        $saved_preproc=$param_preproc;
        $saved_th=$param_th;
    // start choice_1
    $p2 = $this->currPos;
    // start seq_1
    $p3 = $this->currPos;
    if ($this->currPos >= $this->inputLength ? false : substr_compare($this->input, "{{", $this->currPos, 2, false) === 0) {
      $r4 = "{{";
      $this->currPos += 2;
    } else {
      if (!$silence) {$this->fail(44);}
      $r4 = self::$FAILED;
      $r1 = self::$FAILED;
      goto seq_1;
    }
    $p6 = $this->currPos;
    for (;;) {
      $r7 = $this->discardnl_comment_space($silence);
      if ($r7===self::$FAILED) {
        break;
      }
    }
    // free $r7
    $r5 = true;
    // leadWS <- $r5
    if ($r5!==self::$FAILED) {
      $r5 = substr($this->input, $p6, $this->currPos - $p6);
    } else {
      $r5 = self::$FAILED;
      $this->currPos = $p3;
      $r1 = self::$FAILED;
      goto seq_1;
    }
    // free $p6
    $r7 = $this->parsetemplate_param_value($silence, $boolParams, $param_templatedepth, $param_preproc, $param_th);
    // target <- $r7
    if ($r7===self::$FAILED) {
      $this->currPos = $p3;
      $r1 = self::$FAILED;
      goto seq_1;
    }
    $r8 = [];
    for (;;) {
      $p6 = $this->currPos;
      // start seq_2
      $p10 = $this->currPos;
      for (;;) {
        $r12 = $this->discardnl_comment_space($silence);
        if ($r12===self::$FAILED) {
          break;
        }
      }
      // free $r12
      $r11 = true;
      if ($r11===self::$FAILED) {
        $r9 = self::$FAILED;
        goto seq_2;
      }
      // free $r11
      if (($this->input[$this->currPos] ?? null) === "|") {
        $this->currPos++;
        $r11 = "|";
      } else {
        if (!$silence) {$this->fail(13);}
        $r11 = self::$FAILED;
        $this->currPos = $p10;
        $r9 = self::$FAILED;
        goto seq_2;
      }
      // start choice_2
      $p13 = $this->currPos;
      // start seq_3
      $p14 = $this->currPos;
      $p16 = $this->currPos;
      $r15 = '';
      // p0 <- $r15
      if ($r15!==self::$FAILED) {
        $this->savedPos = $p16;
        $r15 = $this->a97($r5, $r7);
      } else {
        $r12 = self::$FAILED;
        goto seq_3;
      }
      $r17 = [];
      for (;;) {
        $r18 = $this->parsenl_comment_space($silence);
        if ($r18!==self::$FAILED) {
          $r17[] = $r18;
        } else {
          break;
        }
      }
      // v <- $r17
      // free $r18
      $p19 = $this->currPos;
      $r18 = '';
      // p <- $r18
      if ($r18!==self::$FAILED) {
        $this->savedPos = $p19;
        $r18 = $this->a98($r5, $r7, $r15, $r17);
      } else {
        $this->currPos = $p14;
        $r12 = self::$FAILED;
        goto seq_3;
      }
      $p20 = $this->currPos;
      // start choice_3
      if (($this->input[$this->currPos] ?? null) === "|") {
        $this->currPos++;
        $r21 = "|";
        goto choice_3;
      } else {
        $r21 = self::$FAILED;
      }
      if ($this->currPos >= $this->inputLength ? false : substr_compare($this->input, "}}", $this->currPos, 2, false) === 0) {
        $r21 = "}}";
        $this->currPos += 2;
      } else {
        $r21 = self::$FAILED;
      }
      choice_3:
      if ($r21!==self::$FAILED) {
        $r21 = false;
        $this->currPos = $p20;
      } else {
        $this->currPos = $p14;
        $r12 = self::$FAILED;
        goto seq_3;
      }
      // free $p20
      $r12 = true;
      seq_3:
      if ($r12!==self::$FAILED) {
        $this->savedPos = $p13;
        $r12 = $this->a99($r5, $r7, $r15, $r17, $r18);
        goto choice_2;
      }
      // free $p14
      $r12 = $this->parsetemplate_param($silence, $boolParams, $param_templatedepth, $param_preproc, $param_th);
      choice_2:
      // r <- $r12
      if ($r12===self::$FAILED) {
        $this->currPos = $p10;
        $r9 = self::$FAILED;
        goto seq_2;
      }
      $r9 = true;
      seq_2:
      if ($r9!==self::$FAILED) {
        $this->savedPos = $p6;
        $r9 = $this->a100($r5, $r7, $r12);
        $r8[] = $r9;
      } else {
        break;
      }
      // free $p10
    }
    // params <- $r8
    // free $r9
    $p10 = $this->currPos;
    for (;;) {
      $r22 = $this->discardnl_comment_space($silence);
      if ($r22===self::$FAILED) {
        break;
      }
    }
    // free $r22
    $r9 = true;
    // trailWS <- $r9
    if ($r9!==self::$FAILED) {
      $r9 = substr($this->input, $p10, $this->currPos - $p10);
    } else {
      $r9 = self::$FAILED;
      $this->currPos = $p3;
      $r1 = self::$FAILED;
      goto seq_1;
    }
    // free $p10
    $r22 = $this->discardinline_breaks($silence, $boolParams, $param_templatedepth, $param_preproc, $param_th);
    if ($r22===self::$FAILED) {
      $this->currPos = $p3;
      $r1 = self::$FAILED;
      goto seq_1;
    }
    if ($this->currPos >= $this->inputLength ? false : substr_compare($this->input, "}}", $this->currPos, 2, false) === 0) {
      $r23 = "}}";
      $this->currPos += 2;
    } else {
      if (!$silence) {$this->fail(61);}
      $r23 = self::$FAILED;
      $this->currPos = $p3;
      $r1 = self::$FAILED;
      goto seq_1;
    }
    $r1 = true;
    seq_1:
    if ($r1!==self::$FAILED) {
      $this->savedPos = $p2;
      $r1 = $this->a101($r5, $r7, $r8, $r9);
      goto choice_1;
    }
    // free $p3
    $p3 = $this->currPos;
    // start seq_4
    $p10 = $this->currPos;
    if ($this->currPos >= $this->inputLength ? false : substr_compare($this->input, "{{", $this->currPos, 2, false) === 0) {
      $r24 = "{{";
      $this->currPos += 2;
    } else {
      if (!$silence) {$this->fail(44);}
      $r24 = self::$FAILED;
      $r1 = self::$FAILED;
      goto seq_4;
    }
    for (;;) {
      $r26 = $this->discardspace_or_newline($silence);
      if ($r26===self::$FAILED) {
        break;
      }
    }
    // free $r26
    $r25 = true;
    if ($r25===self::$FAILED) {
      $this->currPos = $p10;
      $r1 = self::$FAILED;
      goto seq_4;
    }
    // free $r25
    if ($this->currPos >= $this->inputLength ? false : substr_compare($this->input, "}}", $this->currPos, 2, false) === 0) {
      $r25 = "}}";
      $this->currPos += 2;
    } else {
      if (!$silence) {$this->fail(61);}
      $r25 = self::$FAILED;
      $this->currPos = $p10;
      $r1 = self::$FAILED;
      goto seq_4;
    }
    $r1 = true;
    seq_4:
    if ($r1!==self::$FAILED) {
      $r1 = substr($this->input, $p3, $this->currPos - $p3);
    } else {
      $r1 = self::$FAILED;
    }
    // free $p10
    // free $p3
    choice_1:
    $cached = ['nextPos' => $this->currPos, 'result' => $r1];
      if ($saved_preproc !== $param_preproc) $cached["\$preproc"] = $param_preproc;
      if ($saved_th !== $param_th) $cached["\$th"] = $param_th;
    $this->cache[$bucket][$key] = $cached;
    return $r1;
  }
  private function parsetplarg_preproc($silence, $boolParams, $param_templatedepth, &$param_preproc, &$param_th) {
    $key = json_encode([360, $boolParams & 0x1bfe, $param_templatedepth, $param_preproc, $param_th]);
    $bucket = $this->currPos;
    $cached = $this->cache[$bucket][$key] ?? null;
    if ($cached) {
      $this->currPos = $cached['nextPos'];
        if (array_key_exists("\$preproc", $cached)) $param_preproc = $cached["\$preproc"];
        if (array_key_exists("\$th", $cached)) $param_th = $cached["\$th"];
      return $cached['result'];
    }
        $saved_preproc=$param_preproc;
        $saved_th=$param_th;
    $p2 = $this->currPos;
    // start seq_1
    $p3 = $this->currPos;
    if ($this->currPos >= $this->inputLength ? false : substr_compare($this->input, "{{{", $this->currPos, 3, false) === 0) {
      $r4 = "{{{";
      $this->currPos += 3;
    } else {
      if (!$silence) {$this->fail(59);}
      $r4 = self::$FAILED;
      $r1 = self::$FAILED;
      goto seq_1;
    }
    $p6 = $this->currPos;
    $r5 = '';
    // p <- $r5
    if ($r5!==self::$FAILED) {
      $this->savedPos = $p6;
      $r5 = $this->a14();
    } else {
      $this->currPos = $p3;
      $r1 = self::$FAILED;
      goto seq_1;
    }
    $r7 = $this->parsetemplate_param_value($silence, $boolParams, $param_templatedepth, $param_preproc, $param_th);
    if ($r7===self::$FAILED) {
      $r7 = null;
    }
    // target <- $r7
    $r8 = [];
    for (;;) {
      $p10 = $this->currPos;
      // start seq_2
      $p11 = $this->currPos;
      for (;;) {
        $r13 = $this->discardnl_comment_space($silence);
        if ($r13===self::$FAILED) {
          break;
        }
      }
      // free $r13
      $r12 = true;
      if ($r12===self::$FAILED) {
        $r9 = self::$FAILED;
        goto seq_2;
      }
      // free $r12
      if (($this->input[$this->currPos] ?? null) === "|") {
        $this->currPos++;
        $r12 = "|";
      } else {
        if (!$silence) {$this->fail(13);}
        $r12 = self::$FAILED;
        $this->currPos = $p11;
        $r9 = self::$FAILED;
        goto seq_2;
      }
      // start choice_1
      $p14 = $this->currPos;
      // start seq_3
      $p15 = $this->currPos;
      $p17 = $this->currPos;
      $r16 = '';
      // p0 <- $r16
      if ($r16!==self::$FAILED) {
        $this->savedPos = $p17;
        $r16 = $this->a92($r5, $r7);
      } else {
        $r13 = self::$FAILED;
        goto seq_3;
      }
      $r18 = [];
      for (;;) {
        $r19 = $this->parsenl_comment_space($silence);
        if ($r19!==self::$FAILED) {
          $r18[] = $r19;
        } else {
          break;
        }
      }
      // v <- $r18
      // free $r19
      $p20 = $this->currPos;
      $r19 = '';
      // p1 <- $r19
      if ($r19!==self::$FAILED) {
        $this->savedPos = $p20;
        $r19 = $this->a93($r5, $r7, $r16, $r18);
      } else {
        $this->currPos = $p15;
        $r13 = self::$FAILED;
        goto seq_3;
      }
      $p21 = $this->currPos;
      // start choice_2
      if (($this->input[$this->currPos] ?? null) === "|") {
        $this->currPos++;
        $r22 = "|";
        goto choice_2;
      } else {
        $r22 = self::$FAILED;
      }
      if ($this->currPos >= $this->inputLength ? false : substr_compare($this->input, "}}}", $this->currPos, 3, false) === 0) {
        $r22 = "}}}";
        $this->currPos += 3;
      } else {
        $r22 = self::$FAILED;
      }
      choice_2:
      if ($r22!==self::$FAILED) {
        $r22 = false;
        $this->currPos = $p21;
      } else {
        $this->currPos = $p15;
        $r13 = self::$FAILED;
        goto seq_3;
      }
      // free $p21
      $r13 = true;
      seq_3:
      if ($r13!==self::$FAILED) {
        $this->savedPos = $p14;
        $r13 = $this->a94($r5, $r7, $r16, $r18, $r19);
        goto choice_1;
      }
      // free $p15
      $r13 = $this->parsetemplate_param_value($silence, $boolParams, $param_templatedepth, $param_preproc, $param_th);
      choice_1:
      // r <- $r13
      if ($r13===self::$FAILED) {
        $this->currPos = $p11;
        $r9 = self::$FAILED;
        goto seq_2;
      }
      $r9 = true;
      seq_2:
      if ($r9!==self::$FAILED) {
        $this->savedPos = $p10;
        $r9 = $this->a95($r5, $r7, $r13);
        $r8[] = $r9;
      } else {
        break;
      }
      // free $p11
    }
    // params <- $r8
    // free $r9
    for (;;) {
      $r23 = $this->discardnl_comment_space($silence);
      if ($r23===self::$FAILED) {
        break;
      }
    }
    // free $r23
    $r9 = true;
    if ($r9===self::$FAILED) {
      $this->currPos = $p3;
      $r1 = self::$FAILED;
      goto seq_1;
    }
    // free $r9
    $r9 = $this->discardinline_breaks($silence, $boolParams, $param_templatedepth, $param_preproc, $param_th);
    if ($r9===self::$FAILED) {
      $this->currPos = $p3;
      $r1 = self::$FAILED;
      goto seq_1;
    }
    if ($this->currPos >= $this->inputLength ? false : substr_compare($this->input, "}}}", $this->currPos, 3, false) === 0) {
      $r23 = "}}}";
      $this->currPos += 3;
    } else {
      if (!$silence) {$this->fail(60);}
      $r23 = self::$FAILED;
      $this->currPos = $p3;
      $r1 = self::$FAILED;
      goto seq_1;
    }
    $r1 = true;
    seq_1:
    if ($r1!==self::$FAILED) {
      $this->savedPos = $p2;
      $r1 = $this->a96($r5, $r7, $r8);
    }
    // free $p3
    $cached = ['nextPos' => $this->currPos, 'result' => $r1];
      if ($saved_preproc !== $param_preproc) $cached["\$preproc"] = $param_preproc;
      if ($saved_th !== $param_th) $cached["\$th"] = $param_th;
    $this->cache[$bucket][$key] = $cached;
    return $r1;
  }
  private function discardwikilink_preproc($silence, $boolParams, $param_templatedepth, &$param_preproc, &$param_th) {
    $key = json_encode([407, $boolParams & 0x3bff, $param_templatedepth, $param_preproc, $param_th]);
    $bucket = $this->currPos;
    $cached = $this->cache[$bucket][$key] ?? null;
    if ($cached) {
      $this->currPos = $cached['nextPos'];
        if (array_key_exists("\$preproc", $cached)) $param_preproc = $cached["\$preproc"];
        if (array_key_exists("\$th", $cached)) $param_th = $cached["\$th"];
      return $cached['result'];
    }
        $saved_preproc=$param_preproc;
        $saved_th=$param_th;
    $p2 = $this->currPos;
    // start seq_1
    $p3 = $this->currPos;
    if ($this->currPos >= $this->inputLength ? false : substr_compare($this->input, "[[", $this->currPos, 2, false) === 0) {
      $r4 = "[[";
      $this->currPos += 2;
    } else {
      if (!$silence) {$this->fail(42);}
      $r4 = self::$FAILED;
      $r1 = self::$FAILED;
      goto seq_1;
    }
    $p6 = $this->currPos;
    $r5 = '';
    // spos <- $r5
    if ($r5!==self::$FAILED) {
      $this->savedPos = $p6;
      $r5 = $this->a14();
    } else {
      $this->currPos = $p3;
      $r1 = self::$FAILED;
      goto seq_1;
    }
    $r7 = $this->parsewikilink_preprocessor_text($silence, $boolParams, $param_templatedepth, $param_preproc, $param_th);
    if ($r7===self::$FAILED) {
      $r7 = null;
    }
    // target <- $r7
    $p9 = $this->currPos;
    $r8 = '';
    // tpos <- $r8
    if ($r8!==self::$FAILED) {
      $this->savedPos = $p9;
      $r8 = $this->a102($r5, $r7);
    } else {
      $this->currPos = $p3;
      $r1 = self::$FAILED;
      goto seq_1;
    }
    $r10 = $this->parsewikilink_content($silence, $boolParams, $param_templatedepth, $param_preproc, $param_th);
    // lcs <- $r10
    if ($r10===self::$FAILED) {
      $this->currPos = $p3;
      $r1 = self::$FAILED;
      goto seq_1;
    }
    $r11 = $this->discardinline_breaks($silence, $boolParams, $param_templatedepth, $param_preproc, $param_th);
    if ($r11===self::$FAILED) {
      $this->currPos = $p3;
      $r1 = self::$FAILED;
      goto seq_1;
    }
    if ($this->currPos >= $this->inputLength ? false : substr_compare($this->input, "]]", $this->currPos, 2, false) === 0) {
      $r12 = "]]";
      $this->currPos += 2;
    } else {
      if (!$silence) {$this->fail(62);}
      $r12 = self::$FAILED;
      $this->currPos = $p3;
      $r1 = self::$FAILED;
      goto seq_1;
    }
    $r1 = true;
    seq_1:
    if ($r1!==self::$FAILED) {
      $this->savedPos = $p2;
      $r1 = $this->a103($r5, $r7, $r8, $r10);
    }
    // free $p3
    $cached = ['nextPos' => $this->currPos, 'result' => $r1];
      if ($saved_preproc !== $param_preproc) $cached["\$preproc"] = $param_preproc;
      if ($saved_th !== $param_th) $cached["\$th"] = $param_th;
    $this->cache[$bucket][$key] = $cached;
    return $r1;
  }
  private function discardbroken_wikilink($silence, $boolParams, &$param_preproc, $param_templatedepth, &$param_th) {
    $key = json_encode([405, $boolParams & 0x19fe, $param_preproc, $param_templatedepth, $param_th]);
    $bucket = $this->currPos;
    $cached = $this->cache[$bucket][$key] ?? null;
    if ($cached) {
      $this->currPos = $cached['nextPos'];
        if (array_key_exists("\$preproc", $cached)) $param_preproc = $cached["\$preproc"];
        if (array_key_exists("\$th", $cached)) $param_th = $cached["\$th"];
      return $cached['result'];
    }
        $saved_preproc=$param_preproc;
        $saved_th=$param_th;
    $p2 = $this->currPos;
    // start seq_1
    $p3 = $this->currPos;
    $p4 = $this->currPos;
    if ($this->currPos >= $this->inputLength ? false : substr_compare($this->input, "[[", $this->currPos, 2, false) === 0) {
      $r5 = "[[";
      $this->currPos += 2;
      $r5 = false;
      $this->currPos = $p4;
    } else {
      $r5 = self::$FAILED;
      $r1 = self::$FAILED;
      goto seq_1;
    }
    // free $p4
    $this->savedPos = $this->currPos;
    $r6 = $this->a104($param_preproc);
    if ($r6) {
      $r6 = false;
    } else {
      $r6 = self::$FAILED;
      $this->currPos = $p3;
      $r1 = self::$FAILED;
      goto seq_1;
    }
    // start seq_2
    $p4 = $this->currPos;
    if (($this->input[$this->currPos] ?? null) === "[") {
      $this->currPos++;
      $r8 = "[";
    } else {
      if (!$silence) {$this->fail(19);}
      $r8 = self::$FAILED;
      $r7 = self::$FAILED;
      goto seq_2;
    }
    // start choice_1
    $r9 = $this->parseextlink($silence, $boolParams, $param_templatedepth, $param_preproc, $param_th);
    if ($r9!==self::$FAILED) {
      goto choice_1;
    }
    if (($this->input[$this->currPos] ?? null) === "[") {
      $this->currPos++;
      $r9 = "[";
    } else {
      if (!$silence) {$this->fail(19);}
      $r9 = self::$FAILED;
    }
    choice_1:
    if ($r9===self::$FAILED) {
      $this->currPos = $p4;
      $r7 = self::$FAILED;
      goto seq_2;
    }
    $r7 = [$r8,$r9];
    seq_2:
    // a <- $r7
    if ($r7===self::$FAILED) {
      $this->currPos = $p3;
      $r1 = self::$FAILED;
      goto seq_1;
    }
    // free $p4
    $r1 = true;
    seq_1:
    if ($r1!==self::$FAILED) {
      $this->savedPos = $p2;
      $r1 = $this->a105($param_preproc, $r7);
    }
    // free $p3
    $cached = ['nextPos' => $this->currPos, 'result' => $r1];
      if ($saved_preproc !== $param_preproc) $cached["\$preproc"] = $param_preproc;
      if ($saved_th !== $param_th) $cached["\$th"] = $param_th;
    $this->cache[$bucket][$key] = $cached;
    return $r1;
  }
  private function parseextension_tag($silence, $boolParams, $param_templatedepth, &$param_preproc, &$param_th) {
    $key = json_encode([414, $boolParams & 0x1bae, $param_templatedepth, $param_preproc, $param_th]);
    $bucket = $this->currPos;
    $cached = $this->cache[$bucket][$key] ?? null;
    if ($cached) {
      $this->currPos = $cached['nextPos'];
        if (array_key_exists("\$preproc", $cached)) $param_preproc = $cached["\$preproc"];
        if (array_key_exists("\$th", $cached)) $param_th = $cached["\$th"];
      return $cached['result'];
    }
        $saved_preproc=$param_preproc;
        $saved_th=$param_th;
    $p2 = $this->currPos;
    // start seq_1
    $p3 = $this->currPos;
    if (!(/*extTag*/($boolParams & 0x800) !== 0)) {
      $r4 = false;
    } else {
      $r4 = self::$FAILED;
      $r1 = self::$FAILED;
      goto seq_1;
    }
    $r5 = $this->parsexmlish_tag($silence, $boolParams, $param_templatedepth, $param_preproc, $param_th);
    // extToken <- $r5
    if ($r5===self::$FAILED) {
      $this->currPos = $p3;
      $r1 = self::$FAILED;
      goto seq_1;
    }
    $this->savedPos = $this->currPos;
    $r6 = $this->a106($r5);
    if ($r6) {
      $r6 = false;
    } else {
      $r6 = self::$FAILED;
      $this->currPos = $p3;
      $r1 = self::$FAILED;
      goto seq_1;
    }
    $r1 = true;
    seq_1:
    if ($r1!==self::$FAILED) {
      $this->savedPos = $p2;
      $r1 = $this->a107($r5);
    }
    // free $p3
    $cached = ['nextPos' => $this->currPos, 'result' => $r1];
      if ($saved_preproc !== $param_preproc) $cached["\$preproc"] = $param_preproc;
      if ($saved_th !== $param_th) $cached["\$th"] = $param_th;
    $this->cache[$bucket][$key] = $cached;
    return $r1;
  }
  private function parseautourl($silence, $boolParams, $param_templatedepth, &$param_preproc, &$param_th) {
    $key = json_encode([342, $boolParams & 0x1bfe, $param_templatedepth, $param_preproc, $param_th]);
    $bucket = $this->currPos;
    $cached = $this->cache[$bucket][$key] ?? null;
    if ($cached) {
      $this->currPos = $cached['nextPos'];
        if (array_key_exists("\$preproc", $cached)) $param_preproc = $cached["\$preproc"];
        if (array_key_exists("\$th", $cached)) $param_th = $cached["\$th"];
      return $cached['result'];
    }
        $saved_preproc=$param_preproc;
        $saved_th=$param_th;
    $p2 = $this->currPos;
    // start seq_1
    $p3 = $this->currPos;
    $p4 = $this->currPos;
    if ($this->currPos >= $this->inputLength ? false : substr_compare($this->input, "//", $this->currPos, 2, false) === 0) {
      $r5 = "//";
      $this->currPos += 2;
    } else {
      $r5 = self::$FAILED;
    }
    if ($r5 === self::$FAILED) {
      $r5 = false;
    } else {
      $r5 = self::$FAILED;
      $this->currPos = $p4;
      $r1 = self::$FAILED;
      goto seq_1;
    }
    // free $p4
    $p4 = $this->currPos;
    // start seq_2
    $p7 = $this->currPos;
    $r8 = $this->parseurl_protocol($silence);
    // proto <- $r8
    if ($r8===self::$FAILED) {
      $r6 = self::$FAILED;
      goto seq_2;
    }
    // start choice_1
    $r9 = $this->parseipv6urladdr($silence);
    if ($r9!==self::$FAILED) {
      goto choice_1;
    }
    $r9 = '';
    choice_1:
    // addr <- $r9
    if ($r9===self::$FAILED) {
      $this->currPos = $p7;
      $r6 = self::$FAILED;
      goto seq_2;
    }
    $r10 = [];
    for (;;) {
      $p12 = $this->currPos;
      // start seq_3
      $p13 = $this->currPos;
      $p14 = $this->currPos;
      $r15 = $this->discardinline_breaks(true, $boolParams, $param_templatedepth, $param_preproc, $param_th);
      if ($r15 === self::$FAILED) {
        $r15 = false;
      } else {
        $r15 = self::$FAILED;
        $this->currPos = $p14;
        $r11 = self::$FAILED;
        goto seq_3;
      }
      // free $p14
      // start choice_2
      $r16 = $this->parseno_punctuation_char($silence);
      if ($r16!==self::$FAILED) {
        goto choice_2;
      }
      $r16 = $this->parsecomment($silence);
      if ($r16!==self::$FAILED) {
        goto choice_2;
      }
      $r16 = $this->parsetplarg_or_template($silence, $boolParams, $param_templatedepth, $param_th, $param_preproc);
      if ($r16!==self::$FAILED) {
        goto choice_2;
      }
      $p14 = $this->currPos;
      // start seq_4
      $p17 = $this->currPos;
      if (($this->input[$this->currPos] ?? null) === "'") {
        $this->currPos++;
        $r18 = "'";
      } else {
        if (!$silence) {$this->fail(33);}
        $r18 = self::$FAILED;
        $r16 = self::$FAILED;
        goto seq_4;
      }
      $p19 = $this->currPos;
      if (($this->input[$this->currPos] ?? null) === "'") {
        $this->currPos++;
        $r20 = "'";
      } else {
        $r20 = self::$FAILED;
      }
      if ($r20 === self::$FAILED) {
        $r20 = false;
      } else {
        $r20 = self::$FAILED;
        $this->currPos = $p19;
        $this->currPos = $p17;
        $r16 = self::$FAILED;
        goto seq_4;
      }
      // free $p19
      $r16 = true;
      seq_4:
      if ($r16!==self::$FAILED) {
        $r16 = substr($this->input, $p14, $this->currPos - $p14);
        goto choice_2;
      } else {
        $r16 = self::$FAILED;
      }
      // free $p17
      // free $p14
      if (($this->input[$this->currPos] ?? null) === "{") {
        $this->currPos++;
        $r16 = "{";
        goto choice_2;
      } else {
        if (!$silence) {$this->fail(28);}
        $r16 = self::$FAILED;
      }
      $p14 = $this->currPos;
      // start seq_5
      $p17 = $this->currPos;
      $p19 = $this->currPos;
      // start seq_6
      $p22 = $this->currPos;
      $r23 = $this->parseraw_htmlentity(true);
      // rhe <- $r23
      if ($r23===self::$FAILED) {
        $r21 = self::$FAILED;
        goto seq_6;
      }
      $this->savedPos = $this->currPos;
      $r24 = $this->a108($r8, $r9, $r23);
      if ($r24) {
        $r24 = false;
      } else {
        $r24 = self::$FAILED;
        $this->currPos = $p22;
        $r21 = self::$FAILED;
        goto seq_6;
      }
      $r21 = true;
      seq_6:
      // free $p22
      if ($r21 === self::$FAILED) {
        $r21 = false;
      } else {
        $r21 = self::$FAILED;
        $this->currPos = $p19;
        $r16 = self::$FAILED;
        goto seq_5;
      }
      // free $p19
      // start choice_3
      $p19 = $this->currPos;
      // start seq_7
      $p22 = $this->currPos;
      $p26 = $this->currPos;
      if (($this->input[$this->currPos] ?? null) === "&") {
        $this->currPos++;
        $r27 = "&";
        $r27 = false;
        $this->currPos = $p26;
      } else {
        $r27 = self::$FAILED;
        $r25 = self::$FAILED;
        goto seq_7;
      }
      // free $p26
      $r28 = $this->parsehtmlentity($silence);
      // he <- $r28
      if ($r28===self::$FAILED) {
        $this->currPos = $p22;
        $r25 = self::$FAILED;
        goto seq_7;
      }
      $r25 = true;
      seq_7:
      if ($r25!==self::$FAILED) {
        $this->savedPos = $p19;
        $r25 = $this->a6($r8, $r9, $r28);
        goto choice_3;
      }
      // free $p22
      if (($this->input[$this->currPos] ?? null) === "&") {
        $this->currPos++;
        $r25 = "&";
      } else {
        if (!$silence) {$this->fail(4);}
        $r25 = self::$FAILED;
      }
      choice_3:
      // r <- $r25
      if ($r25===self::$FAILED) {
        $this->currPos = $p17;
        $r16 = self::$FAILED;
        goto seq_5;
      }
      $r16 = true;
      seq_5:
      if ($r16!==self::$FAILED) {
        $this->savedPos = $p14;
        $r16 = $this->a7($r8, $r9, $r25);
      }
      // free $p17
      choice_2:
      // c <- $r16
      if ($r16===self::$FAILED) {
        $this->currPos = $p13;
        $r11 = self::$FAILED;
        goto seq_3;
      }
      $r11 = true;
      seq_3:
      if ($r11!==self::$FAILED) {
        $this->savedPos = $p12;
        $r11 = $this->a8($r8, $r9, $r16);
        $r10[] = $r11;
      } else {
        break;
      }
      // free $p13
    }
    // path <- $r10
    // free $r11
    $r6 = true;
    seq_2:
    // r <- $r6
    if ($r6!==self::$FAILED) {
      $this->savedPos = $p4;
      $r6 = $this->a109($r8, $r9, $r10);
    } else {
      $this->currPos = $p3;
      $r1 = self::$FAILED;
      goto seq_1;
    }
    // free $p7
    $this->savedPos = $this->currPos;
    $r11 = $this->a110($r6);
    if ($r11) {
      $r11 = false;
    } else {
      $r11 = self::$FAILED;
      $this->currPos = $p3;
      $r1 = self::$FAILED;
      goto seq_1;
    }
    $r1 = true;
    seq_1:
    if ($r1!==self::$FAILED) {
      $this->savedPos = $p2;
      $r1 = $this->a111($r6);
    }
    // free $p3
    $cached = ['nextPos' => $this->currPos, 'result' => $r1];
      if ($saved_preproc !== $param_preproc) $cached["\$preproc"] = $param_preproc;
      if ($saved_th !== $param_th) $cached["\$th"] = $param_th;
    $this->cache[$bucket][$key] = $cached;
    return $r1;
  }
  private function parseautoref($silence) {
    $key = 332;
    $bucket = $this->currPos;
    $cached = $this->cache[$bucket][$key] ?? null;
    if ($cached) {
      $this->currPos = $cached['nextPos'];
  
      return $cached['result'];
    }
  
    $p2 = $this->currPos;
    // start seq_1
    $p3 = $this->currPos;
    // start choice_1
    if ($this->currPos >= $this->inputLength ? false : substr_compare($this->input, "RFC", $this->currPos, 3, false) === 0) {
      $r4 = "RFC";
      $this->currPos += 3;
      goto choice_1;
    } else {
      if (!$silence) {$this->fail(63);}
      $r4 = self::$FAILED;
    }
    if ($this->currPos >= $this->inputLength ? false : substr_compare($this->input, "PMID", $this->currPos, 4, false) === 0) {
      $r4 = "PMID";
      $this->currPos += 4;
    } else {
      if (!$silence) {$this->fail(64);}
      $r4 = self::$FAILED;
    }
    choice_1:
    // ref <- $r4
    if ($r4===self::$FAILED) {
      $r1 = self::$FAILED;
      goto seq_1;
    }
    $r5 = [];
    for (;;) {
      $r6 = $this->parsespace_or_nbsp($silence);
      if ($r6!==self::$FAILED) {
        $r5[] = $r6;
      } else {
        break;
      }
    }
    if (count($r5) === 0) {
      $r5 = self::$FAILED;
    }
    // sp <- $r5
    if ($r5===self::$FAILED) {
      $this->currPos = $p3;
      $r1 = self::$FAILED;
      goto seq_1;
    }
    // free $r6
    $p7 = $this->currPos;
    $r6 = self::$FAILED;
    for (;;) {
      $r8 = $this->input[$this->currPos] ?? '';
      if (preg_match("/^[0-9]/", $r8)) {
        $this->currPos++;
        $r6 = true;
      } else {
        $r8 = self::$FAILED;
        if (!$silence) {$this->fail(65);}
        break;
      }
    }
    // identifier <- $r6
    if ($r6!==self::$FAILED) {
      $r6 = substr($this->input, $p7, $this->currPos - $p7);
    } else {
      $r6 = self::$FAILED;
      $this->currPos = $p3;
      $r1 = self::$FAILED;
      goto seq_1;
    }
    // free $r8
    // free $p7
    $r8 = $this->discardend_of_word($silence);
    if ($r8===self::$FAILED) {
      $this->currPos = $p3;
      $r1 = self::$FAILED;
      goto seq_1;
    }
    $r1 = true;
    seq_1:
    if ($r1!==self::$FAILED) {
      $this->savedPos = $p2;
      $r1 = $this->a112($r4, $r5, $r6);
    }
    // free $p3
    $cached = ['nextPos' => $this->currPos, 'result' => $r1];
  
    $this->cache[$bucket][$key] = $cached;
    return $r1;
  }
  private function parseisbn($silence) {
    $key = 334;
    $bucket = $this->currPos;
    $cached = $this->cache[$bucket][$key] ?? null;
    if ($cached) {
      $this->currPos = $cached['nextPos'];
  
      return $cached['result'];
    }
  
    $p2 = $this->currPos;
    // start seq_1
    $p3 = $this->currPos;
    if ($this->currPos >= $this->inputLength ? false : substr_compare($this->input, "ISBN", $this->currPos, 4, false) === 0) {
      $r4 = "ISBN";
      $this->currPos += 4;
    } else {
      if (!$silence) {$this->fail(66);}
      $r4 = self::$FAILED;
      $r1 = self::$FAILED;
      goto seq_1;
    }
    $r5 = [];
    for (;;) {
      $r6 = $this->parsespace_or_nbsp($silence);
      if ($r6!==self::$FAILED) {
        $r5[] = $r6;
      } else {
        break;
      }
    }
    if (count($r5) === 0) {
      $r5 = self::$FAILED;
    }
    // sp <- $r5
    if ($r5===self::$FAILED) {
      $this->currPos = $p3;
      $r1 = self::$FAILED;
      goto seq_1;
    }
    // free $r6
    // start seq_2
    $p7 = $this->currPos;
    $r8 = $this->input[$this->currPos] ?? '';
    if (preg_match("/^[0-9]/", $r8)) {
      $this->currPos++;
    } else {
      $r8 = self::$FAILED;
      if (!$silence) {$this->fail(65);}
      $r6 = self::$FAILED;
      goto seq_2;
    }
    $r9 = [];
    for (;;) {
      // start seq_3
      $p11 = $this->currPos;
      // start choice_1
      $r12 = $this->parsespace_or_nbsp_or_dash($silence);
      if ($r12!==self::$FAILED) {
        goto choice_1;
      }
      $r12 = '';
      choice_1:
      if ($r12===self::$FAILED) {
        $r10 = self::$FAILED;
        goto seq_3;
      }
      $r13 = $this->input[$this->currPos] ?? '';
      if (preg_match("/^[0-9]/", $r13)) {
        $this->currPos++;
      } else {
        $r13 = self::$FAILED;
        if (!$silence) {$this->fail(65);}
        $this->currPos = $p11;
        $r10 = self::$FAILED;
        goto seq_3;
      }
      $r10 = [$r12,$r13];
      seq_3:
      if ($r10!==self::$FAILED) {
        $r9[] = $r10;
      } else {
        break;
      }
      // free $p11
    }
    if (count($r9) === 0) {
      $r9 = self::$FAILED;
    }
    if ($r9===self::$FAILED) {
      $this->currPos = $p7;
      $r6 = self::$FAILED;
      goto seq_2;
    }
    // free $r10
    // start choice_2
    // start seq_4
    $p11 = $this->currPos;
    // start choice_3
    $r14 = $this->parsespace_or_nbsp_or_dash($silence);
    if ($r14!==self::$FAILED) {
      goto choice_3;
    }
    $r14 = '';
    choice_3:
    if ($r14===self::$FAILED) {
      $r10 = self::$FAILED;
      goto seq_4;
    }
    $r15 = $this->input[$this->currPos] ?? '';
    if ($r15 === "x" || $r15 === "X") {
      $this->currPos++;
    } else {
      $r15 = self::$FAILED;
      if (!$silence) {$this->fail(67);}
      $this->currPos = $p11;
      $r10 = self::$FAILED;
      goto seq_4;
    }
    $r10 = [$r14,$r15];
    seq_4:
    if ($r10!==self::$FAILED) {
      goto choice_2;
    }
    // free $p11
    $r10 = '';
    choice_2:
    if ($r10===self::$FAILED) {
      $this->currPos = $p7;
      $r6 = self::$FAILED;
      goto seq_2;
    }
    $r6 = [$r8,$r9,$r10];
    seq_2:
    // isbn <- $r6
    if ($r6===self::$FAILED) {
      $this->currPos = $p3;
      $r1 = self::$FAILED;
      goto seq_1;
    }
    // free $p7
    $p7 = $this->currPos;
    $r16 = $this->discardend_of_word($silence);
    // isbncode <- $r16
    if ($r16!==self::$FAILED) {
      $this->savedPos = $p7;
      $r16 = $this->a113($r5, $r6);
    } else {
      $this->currPos = $p3;
      $r1 = self::$FAILED;
      goto seq_1;
    }
    $this->savedPos = $this->currPos;
    $r17 = $this->a114($r5, $r6, $r16);
    if ($r17) {
      $r17 = false;
    } else {
      $r17 = self::$FAILED;
      $this->currPos = $p3;
      $r1 = self::$FAILED;
      goto seq_1;
    }
    $r1 = true;
    seq_1:
    if ($r1!==self::$FAILED) {
      $this->savedPos = $p2;
      $r1 = $this->a115($r5, $r6, $r16);
    }
    // free $p3
    $cached = ['nextPos' => $this->currPos, 'result' => $r1];
  
    $this->cache[$bucket][$key] = $cached;
    return $r1;
  }
  private function discardbehavior_text($silence) {
    $key = 327;
    $bucket = $this->currPos;
    $cached = $this->cache[$bucket][$key] ?? null;
    if ($cached) {
      $this->currPos = $cached['nextPos'];
  
      return $cached['result'];
    }
  
    $p1 = $this->currPos;
    $r2 = self::$FAILED;
    for (;;) {
      // start seq_1
      $p4 = $this->currPos;
      $p5 = $this->currPos;
      if ($this->currPos >= $this->inputLength ? false : substr_compare($this->input, "__", $this->currPos, 2, false) === 0) {
        $r6 = "__";
        $this->currPos += 2;
      } else {
        $r6 = self::$FAILED;
      }
      if ($r6 === self::$FAILED) {
        $r6 = false;
      } else {
        $r6 = self::$FAILED;
        $this->currPos = $p5;
        $r3 = self::$FAILED;
        goto seq_1;
      }
      // free $p5
      // start choice_1
      $r7 = $this->discardtext_char($silence);
      if ($r7!==self::$FAILED) {
        goto choice_1;
      }
      if (($this->input[$this->currPos] ?? null) === "-") {
        $this->currPos++;
        $r7 = "-";
      } else {
        if (!$silence) {$this->fail(54);}
        $r7 = self::$FAILED;
      }
      choice_1:
      if ($r7===self::$FAILED) {
        $this->currPos = $p4;
        $r3 = self::$FAILED;
        goto seq_1;
      }
      $r3 = true;
      seq_1:
      if ($r3!==self::$FAILED) {
        $r2 = true;
      } else {
        break;
      }
      // free $p4
    }
    if ($r2!==self::$FAILED) {
      $r2 = substr($this->input, $p1, $this->currPos - $p1);
    } else {
      $r2 = self::$FAILED;
    }
    // free $r3
    // free $p1
    $cached = ['nextPos' => $this->currPos, 'result' => $r2];
  
    $this->cache[$bucket][$key] = $cached;
    return $r2;
  }
  private function parselang_variant($silence, $boolParams, $param_templatedepth, &$param_th, &$param_preproc) {
    $key = json_encode([374, $boolParams & 0x1bfe, $param_templatedepth, $param_th, $param_preproc]);
    $bucket = $this->currPos;
    $cached = $this->cache[$bucket][$key] ?? null;
    if ($cached) {
      $this->currPos = $cached['nextPos'];
        if (array_key_exists("\$th", $cached)) $param_th = $cached["\$th"];
        if (array_key_exists("\$preproc", $cached)) $param_preproc = $cached["\$preproc"];
      return $cached['result'];
    }
        $saved_th=$param_th;
        $saved_preproc=$param_preproc;
    // start choice_1
    $r1 = $this->parselang_variant_preproc($silence, $boolParams, $param_templatedepth, self::newRef("}-"), $param_th);
    if ($r1!==self::$FAILED) {
      goto choice_1;
    }
    $r1 = $this->parsebroken_lang_variant($silence, $param_preproc);
    choice_1:
    $cached = ['nextPos' => $this->currPos, 'result' => $r1];
      if ($saved_th !== $param_th) $cached["\$th"] = $param_th;
      if ($saved_preproc !== $param_preproc) $cached["\$preproc"] = $param_preproc;
    $this->cache[$bucket][$key] = $cached;
    return $r1;
  }
  private function parsewikilink_preproc($silence, $boolParams, $param_templatedepth, &$param_preproc, &$param_th) {
    $key = json_encode([406, $boolParams & 0x3bff, $param_templatedepth, $param_preproc, $param_th]);
    $bucket = $this->currPos;
    $cached = $this->cache[$bucket][$key] ?? null;
    if ($cached) {
      $this->currPos = $cached['nextPos'];
        if (array_key_exists("\$preproc", $cached)) $param_preproc = $cached["\$preproc"];
        if (array_key_exists("\$th", $cached)) $param_th = $cached["\$th"];
      return $cached['result'];
    }
        $saved_preproc=$param_preproc;
        $saved_th=$param_th;
    $p2 = $this->currPos;
    // start seq_1
    $p3 = $this->currPos;
    if ($this->currPos >= $this->inputLength ? false : substr_compare($this->input, "[[", $this->currPos, 2, false) === 0) {
      $r4 = "[[";
      $this->currPos += 2;
    } else {
      if (!$silence) {$this->fail(42);}
      $r4 = self::$FAILED;
      $r1 = self::$FAILED;
      goto seq_1;
    }
    $p6 = $this->currPos;
    $r5 = '';
    // spos <- $r5
    if ($r5!==self::$FAILED) {
      $this->savedPos = $p6;
      $r5 = $this->a14();
    } else {
      $this->currPos = $p3;
      $r1 = self::$FAILED;
      goto seq_1;
    }
    $r7 = $this->parsewikilink_preprocessor_text($silence, $boolParams, $param_templatedepth, $param_preproc, $param_th);
    if ($r7===self::$FAILED) {
      $r7 = null;
    }
    // target <- $r7
    $p9 = $this->currPos;
    $r8 = '';
    // tpos <- $r8
    if ($r8!==self::$FAILED) {
      $this->savedPos = $p9;
      $r8 = $this->a102($r5, $r7);
    } else {
      $this->currPos = $p3;
      $r1 = self::$FAILED;
      goto seq_1;
    }
    $r10 = $this->parsewikilink_content($silence, $boolParams, $param_templatedepth, $param_preproc, $param_th);
    // lcs <- $r10
    if ($r10===self::$FAILED) {
      $this->currPos = $p3;
      $r1 = self::$FAILED;
      goto seq_1;
    }
    $r11 = $this->discardinline_breaks($silence, $boolParams, $param_templatedepth, $param_preproc, $param_th);
    if ($r11===self::$FAILED) {
      $this->currPos = $p3;
      $r1 = self::$FAILED;
      goto seq_1;
    }
    if ($this->currPos >= $this->inputLength ? false : substr_compare($this->input, "]]", $this->currPos, 2, false) === 0) {
      $r12 = "]]";
      $this->currPos += 2;
    } else {
      if (!$silence) {$this->fail(62);}
      $r12 = self::$FAILED;
      $this->currPos = $p3;
      $r1 = self::$FAILED;
      goto seq_1;
    }
    $r1 = true;
    seq_1:
    if ($r1!==self::$FAILED) {
      $this->savedPos = $p2;
      $r1 = $this->a103($r5, $r7, $r8, $r10);
    }
    // free $p3
    $cached = ['nextPos' => $this->currPos, 'result' => $r1];
      if ($saved_preproc !== $param_preproc) $cached["\$preproc"] = $param_preproc;
      if ($saved_th !== $param_th) $cached["\$th"] = $param_th;
    $this->cache[$bucket][$key] = $cached;
    return $r1;
  }
  private function parsebroken_wikilink($silence, $boolParams, &$param_preproc, $param_templatedepth, &$param_th) {
    $key = json_encode([404, $boolParams & 0x19fe, $param_preproc, $param_templatedepth, $param_th]);
    $bucket = $this->currPos;
    $cached = $this->cache[$bucket][$key] ?? null;
    if ($cached) {
      $this->currPos = $cached['nextPos'];
        if (array_key_exists("\$preproc", $cached)) $param_preproc = $cached["\$preproc"];
        if (array_key_exists("\$th", $cached)) $param_th = $cached["\$th"];
      return $cached['result'];
    }
        $saved_preproc=$param_preproc;
        $saved_th=$param_th;
    $p2 = $this->currPos;
    // start seq_1
    $p3 = $this->currPos;
    $p4 = $this->currPos;
    if ($this->currPos >= $this->inputLength ? false : substr_compare($this->input, "[[", $this->currPos, 2, false) === 0) {
      $r5 = "[[";
      $this->currPos += 2;
      $r5 = false;
      $this->currPos = $p4;
    } else {
      $r5 = self::$FAILED;
      $r1 = self::$FAILED;
      goto seq_1;
    }
    // free $p4
    $this->savedPos = $this->currPos;
    $r6 = $this->a104($param_preproc);
    if ($r6) {
      $r6 = false;
    } else {
      $r6 = self::$FAILED;
      $this->currPos = $p3;
      $r1 = self::$FAILED;
      goto seq_1;
    }
    // start seq_2
    $p4 = $this->currPos;
    if (($this->input[$this->currPos] ?? null) === "[") {
      $this->currPos++;
      $r8 = "[";
    } else {
      if (!$silence) {$this->fail(19);}
      $r8 = self::$FAILED;
      $r7 = self::$FAILED;
      goto seq_2;
    }
    // start choice_1
    $r9 = $this->parseextlink($silence, $boolParams, $param_templatedepth, $param_preproc, $param_th);
    if ($r9!==self::$FAILED) {
      goto choice_1;
    }
    if (($this->input[$this->currPos] ?? null) === "[") {
      $this->currPos++;
      $r9 = "[";
    } else {
      if (!$silence) {$this->fail(19);}
      $r9 = self::$FAILED;
    }
    choice_1:
    if ($r9===self::$FAILED) {
      $this->currPos = $p4;
      $r7 = self::$FAILED;
      goto seq_2;
    }
    $r7 = [$r8,$r9];
    seq_2:
    // a <- $r7
    if ($r7===self::$FAILED) {
      $this->currPos = $p3;
      $r1 = self::$FAILED;
      goto seq_1;
    }
    // free $p4
    $r1 = true;
    seq_1:
    if ($r1!==self::$FAILED) {
      $this->savedPos = $p2;
      $r1 = $this->a105($param_preproc, $r7);
    }
    // free $p3
    $cached = ['nextPos' => $this->currPos, 'result' => $r1];
      if ($saved_preproc !== $param_preproc) $cached["\$preproc"] = $param_preproc;
      if ($saved_th !== $param_th) $cached["\$th"] = $param_th;
    $this->cache[$bucket][$key] = $cached;
    return $r1;
  }
  private function parsespaces($silence) {
    $key = 498;
    $bucket = $this->currPos;
    $cached = $this->cache[$bucket][$key] ?? null;
    if ($cached) {
      $this->currPos = $cached['nextPos'];
  
      return $cached['result'];
    }
  
    $p1 = $this->currPos;
    $r2 = self::$FAILED;
    for (;;) {
      $r3 = $this->input[$this->currPos] ?? '';
      if ($r3 === " " || $r3 === "\x09") {
        $this->currPos++;
        $r2 = true;
      } else {
        $r3 = self::$FAILED;
        if (!$silence) {$this->fail(10);}
        break;
      }
    }
    if ($r2!==self::$FAILED) {
      $r2 = substr($this->input, $p1, $this->currPos - $p1);
    } else {
      $r2 = self::$FAILED;
    }
    // free $r3
    // free $p1
    $cached = ['nextPos' => $this->currPos, 'result' => $r2];
  
    $this->cache[$bucket][$key] = $cached;
    return $r2;
  }
  private function parsedtdd($silence, $boolParams, $param_templatedepth, &$param_preproc, &$param_th) {
    $key = json_encode([454, $boolParams & 0x1bfe, $param_templatedepth, $param_preproc, $param_th]);
    $bucket = $this->currPos;
    $cached = $this->cache[$bucket][$key] ?? null;
    if ($cached) {
      $this->currPos = $cached['nextPos'];
        if (array_key_exists("\$preproc", $cached)) $param_preproc = $cached["\$preproc"];
        if (array_key_exists("\$th", $cached)) $param_th = $cached["\$th"];
      return $cached['result'];
    }
        $saved_preproc=$param_preproc;
        $saved_th=$param_th;
    $p2 = $this->currPos;
    // start seq_1
    $p3 = $this->currPos;
    $r4 = [];
    for (;;) {
      $p6 = $this->currPos;
      // start seq_2
      $p7 = $this->currPos;
      $p8 = $this->currPos;
      // start seq_3
      $p10 = $this->currPos;
      if (($this->input[$this->currPos] ?? null) === ";") {
        $this->currPos++;
        $r11 = ";";
      } else {
        $r11 = self::$FAILED;
        $r9 = self::$FAILED;
        goto seq_3;
      }
      $p12 = $this->currPos;
      $r13 = $this->discardlist_char(true);
      if ($r13 === self::$FAILED) {
        $r13 = false;
      } else {
        $r13 = self::$FAILED;
        $this->currPos = $p12;
        $this->currPos = $p10;
        $r9 = self::$FAILED;
        goto seq_3;
      }
      // free $p12
      $r9 = true;
      seq_3:
      // free $p10
      if ($r9 === self::$FAILED) {
        $r9 = false;
      } else {
        $r9 = self::$FAILED;
        $this->currPos = $p8;
        $r5 = self::$FAILED;
        goto seq_2;
      }
      // free $p8
      $r14 = $this->parselist_char($silence);
      // lc <- $r14
      if ($r14===self::$FAILED) {
        $this->currPos = $p7;
        $r5 = self::$FAILED;
        goto seq_2;
      }
      $r5 = true;
      seq_2:
      if ($r5!==self::$FAILED) {
        $this->savedPos = $p6;
        $r5 = $this->a116($r14);
        $r4[] = $r5;
      } else {
        break;
      }
      // free $p7
    }
    // bullets <- $r4
    // free $r5
    if (($this->input[$this->currPos] ?? null) === ";") {
      $this->currPos++;
      $r5 = ";";
    } else {
      if (!$silence) {$this->fail(30);}
      $r5 = self::$FAILED;
      $this->currPos = $p3;
      $r1 = self::$FAILED;
      goto seq_1;
    }
    $r15 = $this->parseinlineline_break_on_colon($silence, $boolParams, $param_templatedepth, $param_preproc, $param_th);
    if ($r15===self::$FAILED) {
      $r15 = null;
    }
    // c <- $r15
    $p7 = $this->currPos;
    // cpos <- $r16
    if (($this->input[$this->currPos] ?? null) === ":") {
      $this->currPos++;
      $r16 = ":";
      $this->savedPos = $p7;
      $r16 = $this->a117($r4, $r15);
    } else {
      if (!$silence) {$this->fail(18);}
      $r16 = self::$FAILED;
      $this->currPos = $p3;
      $r1 = self::$FAILED;
      goto seq_1;
    }
    $r17 = $this->parseinlineline($silence, $boolParams, $param_templatedepth, $param_preproc, $param_th);
    if ($r17===self::$FAILED) {
      $r17 = null;
    }
    // d <- $r17
    $p8 = $this->currPos;
    $r18 = $this->discardeolf(true);
    if ($r18!==self::$FAILED) {
      $r18 = false;
      $this->currPos = $p8;
    } else {
      $this->currPos = $p3;
      $r1 = self::$FAILED;
      goto seq_1;
    }
    // free $p8
    $r1 = true;
    seq_1:
    if ($r1!==self::$FAILED) {
      $this->savedPos = $p2;
      $r1 = $this->a118($r4, $r15, $r16, $r17);
    }
    // free $p3
    $cached = ['nextPos' => $this->currPos, 'result' => $r1];
      if ($saved_preproc !== $param_preproc) $cached["\$preproc"] = $param_preproc;
      if ($saved_th !== $param_th) $cached["\$th"] = $param_th;
    $this->cache[$bucket][$key] = $cached;
    return $r1;
  }
  private function parsehacky_dl_uses($silence, $boolParams, $param_templatedepth, &$param_preproc, &$param_th) {
    $key = json_encode([452, $boolParams & 0x3bff, $param_templatedepth, $param_preproc, $param_th]);
    $bucket = $this->currPos;
    $cached = $this->cache[$bucket][$key] ?? null;
    if ($cached) {
      $this->currPos = $cached['nextPos'];
        if (array_key_exists("\$preproc", $cached)) $param_preproc = $cached["\$preproc"];
        if (array_key_exists("\$th", $cached)) $param_th = $cached["\$th"];
      return $cached['result'];
    }
        $saved_preproc=$param_preproc;
        $saved_th=$param_th;
    $p2 = $this->currPos;
    // start seq_1
    $p3 = $this->currPos;
    $r4 = [];
    for (;;) {
      if (($this->input[$this->currPos] ?? null) === ":") {
        $this->currPos++;
        $r5 = ":";
        $r4[] = $r5;
      } else {
        if (!$silence) {$this->fail(18);}
        $r5 = self::$FAILED;
        break;
      }
    }
    if (count($r4) === 0) {
      $r4 = self::$FAILED;
    }
    // bullets <- $r4
    if ($r4===self::$FAILED) {
      $r1 = self::$FAILED;
      goto seq_1;
    }
    // free $r5
    // start seq_2
    $p6 = $this->currPos;
    $r7 = $this->parsetable_line($silence, $boolParams, $param_templatedepth, $param_preproc, $param_th);
    if ($r7===self::$FAILED) {
      $r5 = self::$FAILED;
      goto seq_2;
    }
    $r8 = [];
    for (;;) {
      // start seq_3
      $p10 = $this->currPos;
      $r11 = $this->parsesol($silence, $boolParams, $param_templatedepth, $param_preproc, $param_th);
      if ($r11===self::$FAILED) {
        $r9 = self::$FAILED;
        goto seq_3;
      }
      $r12 = $this->parsetable_line($silence, $boolParams, $param_templatedepth, $param_preproc, $param_th);
      if ($r12===self::$FAILED) {
        $this->currPos = $p10;
        $r9 = self::$FAILED;
        goto seq_3;
      }
      $r9 = [$r11,$r12];
      seq_3:
      if ($r9!==self::$FAILED) {
        $r8[] = $r9;
      } else {
        break;
      }
      // free $p10
    }
    // free $r9
    $r5 = [$r7,$r8];
    seq_2:
    // tbl <- $r5
    if ($r5===self::$FAILED) {
      $this->currPos = $p3;
      $r1 = self::$FAILED;
      goto seq_1;
    }
    // free $p6
    $r9 = $this->parseinlineline($silence, $boolParams, $param_templatedepth, $param_preproc, $param_th);
    if ($r9===self::$FAILED) {
      $r9 = null;
    }
    // line <- $r9
    $p6 = $this->currPos;
    $r13 = $this->discardcomment_space_eolf(true);
    if ($r13!==self::$FAILED) {
      $r13 = false;
      $this->currPos = $p6;
    } else {
      $this->currPos = $p3;
      $r1 = self::$FAILED;
      goto seq_1;
    }
    // free $p6
    $r1 = true;
    seq_1:
    if ($r1!==self::$FAILED) {
      $this->savedPos = $p2;
      $r1 = $this->a119($r4, $r5, $r9);
    }
    // free $p3
    $cached = ['nextPos' => $this->currPos, 'result' => $r1];
      if ($saved_preproc !== $param_preproc) $cached["\$preproc"] = $param_preproc;
      if ($saved_th !== $param_th) $cached["\$th"] = $param_th;
    $this->cache[$bucket][$key] = $cached;
    return $r1;
  }
  private function parseli($silence, $boolParams, $param_templatedepth, &$param_preproc, &$param_th) {
    $key = json_encode([450, $boolParams & 0x1bfe, $param_templatedepth, $param_preproc, $param_th]);
    $bucket = $this->currPos;
    $cached = $this->cache[$bucket][$key] ?? null;
    if ($cached) {
      $this->currPos = $cached['nextPos'];
        if (array_key_exists("\$preproc", $cached)) $param_preproc = $cached["\$preproc"];
        if (array_key_exists("\$th", $cached)) $param_th = $cached["\$th"];
      return $cached['result'];
    }
        $saved_preproc=$param_preproc;
        $saved_th=$param_th;
    $p2 = $this->currPos;
    // start seq_1
    $p3 = $this->currPos;
    $r4 = [];
    for (;;) {
      $r5 = $this->parselist_char($silence);
      if ($r5!==self::$FAILED) {
        $r4[] = $r5;
      } else {
        break;
      }
    }
    if (count($r4) === 0) {
      $r4 = self::$FAILED;
    }
    // bullets <- $r4
    if ($r4===self::$FAILED) {
      $r1 = self::$FAILED;
      goto seq_1;
    }
    // free $r5
    $r5 = $this->parseinlineline($silence, $boolParams, $param_templatedepth, $param_preproc, $param_th);
    if ($r5===self::$FAILED) {
      $r5 = null;
    }
    // c <- $r5
    $p6 = $this->currPos;
    // start choice_1
    $r7 = $this->discardeolf(true);
    if ($r7!==self::$FAILED) {
      goto choice_1;
    }
    $r7 = $this->discardinline_breaks(true, $boolParams, $param_templatedepth, $param_preproc, $param_th);
    choice_1:
    if ($r7!==self::$FAILED) {
      $r7 = false;
      $this->currPos = $p6;
    } else {
      $this->currPos = $p3;
      $r1 = self::$FAILED;
      goto seq_1;
    }
    // free $p6
    $r1 = true;
    seq_1:
    if ($r1!==self::$FAILED) {
      $this->savedPos = $p2;
      $r1 = $this->a120($r4, $r5);
    }
    // free $p3
    $cached = ['nextPos' => $this->currPos, 'result' => $r1];
      if ($saved_preproc !== $param_preproc) $cached["\$preproc"] = $param_preproc;
      if ($saved_th !== $param_th) $cached["\$th"] = $param_th;
    $this->cache[$bucket][$key] = $cached;
    return $r1;
  }
  private function discardsol($silence, $boolParams, $param_templatedepth, &$param_preproc, &$param_th) {
    $key = json_encode([521, $boolParams & 0x13ae, $param_templatedepth, $param_preproc, $param_th]);
    $bucket = $this->currPos;
    $cached = $this->cache[$bucket][$key] ?? null;
    if ($cached) {
      $this->currPos = $cached['nextPos'];
        if (array_key_exists("\$preproc", $cached)) $param_preproc = $cached["\$preproc"];
        if (array_key_exists("\$th", $cached)) $param_th = $cached["\$th"];
      return $cached['result'];
    }
        $saved_preproc=$param_preproc;
        $saved_th=$param_th;
    // start seq_1
    $p1 = $this->currPos;
    // start choice_1
    $r3 = $this->discardempty_line_with_comments($silence);
    if ($r3!==self::$FAILED) {
      goto choice_1;
    }
    $r3 = $this->discardsol_prefix($silence);
    choice_1:
    if ($r3===self::$FAILED) {
      $r2 = self::$FAILED;
      goto seq_1;
    }
    $r4 = $this->discardcomment_or_includes($silence, $boolParams, $param_templatedepth, $param_preproc, $param_th);
    if ($r4===self::$FAILED) {
      $this->currPos = $p1;
      $r2 = self::$FAILED;
      goto seq_1;
    }
    $r2 = true;
    seq_1:
    // free $r2,$p1
    $cached = ['nextPos' => $this->currPos, 'result' => $r2];
      if ($saved_preproc !== $param_preproc) $cached["\$preproc"] = $param_preproc;
      if ($saved_th !== $param_th) $cached["\$th"] = $param_th;
    $this->cache[$bucket][$key] = $cached;
    return $r2;
  }
  private function parseoptionalNewlines($silence) {
    $key = 516;
    $bucket = $this->currPos;
    $cached = $this->cache[$bucket][$key] ?? null;
    if ($cached) {
      $this->currPos = $cached['nextPos'];
  
      return $cached['result'];
    }
  
    $p2 = $this->currPos;
    $p4 = $this->currPos;
    for (;;) {
      // start seq_1
      $p6 = $this->currPos;
      if (strspn($this->input, "\x0a\x0d\x09 ", $this->currPos, 1) !== 0) {
        $r7 = $this->input[$this->currPos++];
      } else {
        $r7 = self::$FAILED;
        if (!$silence) {$this->fail(68);}
        $r5 = self::$FAILED;
        goto seq_1;
      }
      $p8 = $this->currPos;
      $r9 = $this->input[$this->currPos] ?? '';
      if ($r9 === "\x0a" || $r9 === "\x0d") {
        $this->currPos++;
        $r9 = false;
        $this->currPos = $p8;
      } else {
        $r9 = self::$FAILED;
        $this->currPos = $p6;
        $r5 = self::$FAILED;
        goto seq_1;
      }
      // free $p8
      $r5 = true;
      seq_1:
      if ($r5===self::$FAILED) {
        break;
      }
      // free $p6
    }
    // free $r5
    $r3 = true;
    // spc <- $r3
    if ($r3!==self::$FAILED) {
      $r3 = substr($this->input, $p4, $this->currPos - $p4);
    } else {
      $r3 = self::$FAILED;
    }
    // free $p4
    $r1 = $r3;
    if ($r1!==self::$FAILED) {
      $this->savedPos = $p2;
      $r1 = $this->a121($r3);
    }
    $cached = ['nextPos' => $this->currPos, 'result' => $r1];
  
    $this->cache[$bucket][$key] = $cached;
    return $r1;
  }
  private function parsetable_content_line($silence, $boolParams, $param_templatedepth, &$param_preproc, &$param_th) {
    $key = json_encode([466, $boolParams & 0x3bff, $param_templatedepth, $param_preproc, $param_th]);
    $bucket = $this->currPos;
    $cached = $this->cache[$bucket][$key] ?? null;
    if ($cached) {
      $this->currPos = $cached['nextPos'];
        if (array_key_exists("\$preproc", $cached)) $param_preproc = $cached["\$preproc"];
        if (array_key_exists("\$th", $cached)) $param_th = $cached["\$th"];
      return $cached['result'];
    }
        $saved_preproc=$param_preproc;
        $saved_th=$param_th;
    // start seq_1
    $p1 = $this->currPos;
    $r3 = [];
    for (;;) {
      // start choice_1
      $r4 = $this->parsespace($silence);
      if ($r4!==self::$FAILED) {
        goto choice_1;
      }
      $r4 = $this->parsecomment($silence);
      choice_1:
      if ($r4!==self::$FAILED) {
        $r3[] = $r4;
      } else {
        break;
      }
    }
    // free $r4
    // start choice_2
    $r4 = $this->parsetable_heading_tags($silence, $boolParams, $param_templatedepth, $param_preproc);
    if ($r4!==self::$FAILED) {
      goto choice_2;
    }
    $r4 = $this->parsetable_row_tag($silence, $boolParams, $param_templatedepth, $param_preproc, $param_th);
    if ($r4!==self::$FAILED) {
      goto choice_2;
    }
    $r4 = $this->parsetable_data_tags($silence, $boolParams, $param_templatedepth, $param_preproc, $param_th);
    if ($r4!==self::$FAILED) {
      goto choice_2;
    }
    $r4 = $this->parsetable_caption_tag($silence, $boolParams, $param_templatedepth, $param_preproc, $param_th);
    choice_2:
    if ($r4===self::$FAILED) {
      $this->currPos = $p1;
      $r2 = self::$FAILED;
      goto seq_1;
    }
    $r2 = [$r3,$r4];
    seq_1:
    // free $r2,$p1
    $cached = ['nextPos' => $this->currPos, 'result' => $r2];
      if ($saved_preproc !== $param_preproc) $cached["\$preproc"] = $param_preproc;
      if ($saved_th !== $param_th) $cached["\$th"] = $param_th;
    $this->cache[$bucket][$key] = $cached;
    return $r2;
  }
  private function parsetable_end_tag($silence) {
    $key = 486;
    $bucket = $this->currPos;
    $cached = $this->cache[$bucket][$key] ?? null;
    if ($cached) {
      $this->currPos = $cached['nextPos'];
  
      return $cached['result'];
    }
  
    $p2 = $this->currPos;
    // start seq_1
    $p3 = $this->currPos;
    $r4 = [];
    for (;;) {
      // start choice_1
      $r5 = $this->parsespace($silence);
      if ($r5!==self::$FAILED) {
        goto choice_1;
      }
      $r5 = $this->parsecomment($silence);
      choice_1:
      if ($r5!==self::$FAILED) {
        $r4[] = $r5;
      } else {
        break;
      }
    }
    // sc <- $r4
    // free $r5
    $p6 = $this->currPos;
    $r5 = '';
    // startPos <- $r5
    if ($r5!==self::$FAILED) {
      $this->savedPos = $p6;
      $r5 = $this->a2($r4);
    } else {
      $this->currPos = $p3;
      $r1 = self::$FAILED;
      goto seq_1;
    }
    $r7 = $this->parsepipe($silence);
    // p <- $r7
    if ($r7===self::$FAILED) {
      $this->currPos = $p3;
      $r1 = self::$FAILED;
      goto seq_1;
    }
    // b <- $r8
    if (($this->input[$this->currPos] ?? null) === "}") {
      $this->currPos++;
      $r8 = "}";
    } else {
      if (!$silence) {$this->fail(69);}
      $r8 = self::$FAILED;
      $this->currPos = $p3;
      $r1 = self::$FAILED;
      goto seq_1;
    }
    $r1 = true;
    seq_1:
    if ($r1!==self::$FAILED) {
      $this->savedPos = $p2;
      $r1 = $this->a122($r4, $r5, $r7, $r8);
    }
    // free $p3
    $cached = ['nextPos' => $this->currPos, 'result' => $r1];
  
    $this->cache[$bucket][$key] = $cached;
    return $r1;
  }
  private function parsetag_name($silence) {
    $key = 422;
    $bucket = $this->currPos;
    $cached = $this->cache[$bucket][$key] ?? null;
    if ($cached) {
      $this->currPos = $cached['nextPos'];
  
      return $cached['result'];
    }
  
    $p1 = $this->currPos;
    // start seq_1
    $p3 = $this->currPos;
    $r4 = $this->input[$this->currPos] ?? '';
    if (preg_match("/^[A-Za-z]/", $r4)) {
      $this->currPos++;
    } else {
      $r4 = self::$FAILED;
      if (!$silence) {$this->fail(16);}
      $r2 = self::$FAILED;
      goto seq_1;
    }
    for (;;) {
      $r6 = $this->discardtag_name_chars($silence);
      if ($r6===self::$FAILED) {
        break;
      }
    }
    // free $r6
    $r5 = true;
    if ($r5===self::$FAILED) {
      $this->currPos = $p3;
      $r2 = self::$FAILED;
      goto seq_1;
    }
    // free $r5
    $r2 = true;
    seq_1:
    if ($r2!==self::$FAILED) {
      $r2 = substr($this->input, $p1, $this->currPos - $p1);
    } else {
      $r2 = self::$FAILED;
    }
    // free $p3
    // free $p1
    $cached = ['nextPos' => $this->currPos, 'result' => $r2];
  
    $this->cache[$bucket][$key] = $cached;
    return $r2;
  }
  private function parsenewline($silence) {
    $key = 536;
    $bucket = $this->currPos;
    $cached = $this->cache[$bucket][$key] ?? null;
    if ($cached) {
      $this->currPos = $cached['nextPos'];
  
      return $cached['result'];
    }
  
    // start choice_1
    if (($this->input[$this->currPos] ?? null) === "\x0a") {
      $this->currPos++;
      $r1 = "\x0a";
      goto choice_1;
    } else {
      if (!$silence) {$this->fail(26);}
      $r1 = self::$FAILED;
    }
    if ($this->currPos >= $this->inputLength ? false : substr_compare($this->input, "\x0d\x0a", $this->currPos, 2, false) === 0) {
      $r1 = "\x0d\x0a";
      $this->currPos += 2;
    } else {
      if (!$silence) {$this->fail(27);}
      $r1 = self::$FAILED;
    }
    choice_1:
    $cached = ['nextPos' => $this->currPos, 'result' => $r1];
  
    $this->cache[$bucket][$key] = $cached;
    return $r1;
  }
  private function parsetemplate_param_value($silence, $boolParams, $param_templatedepth, &$param_preproc, &$param_th) {
    $key = json_encode([366, $boolParams & 0x1b82, $param_templatedepth, $param_preproc, $param_th]);
    $bucket = $this->currPos;
    $cached = $this->cache[$bucket][$key] ?? null;
    if ($cached) {
      $this->currPos = $cached['nextPos'];
        if (array_key_exists("\$preproc", $cached)) $param_preproc = $cached["\$preproc"];
        if (array_key_exists("\$th", $cached)) $param_th = $cached["\$th"];
      return $cached['result'];
    }
        $saved_preproc=$param_preproc;
        $saved_th=$param_th;
    $p2 = $this->currPos;
    $r3 = $this->parsetemplate_param_text($silence, $boolParams & ~0x8, $param_templatedepth, $param_preproc, $param_th);
    // tpt <- $r3
    $r1 = $r3;
    if ($r1!==self::$FAILED) {
      $this->savedPos = $p2;
      $r1 = $this->a123($r3);
    }
    $cached = ['nextPos' => $this->currPos, 'result' => $r1];
      if ($saved_preproc !== $param_preproc) $cached["\$preproc"] = $param_preproc;
      if ($saved_th !== $param_th) $cached["\$th"] = $param_th;
    $this->cache[$bucket][$key] = $cached;
    return $r1;
  }
  private function discardnl_comment_space($silence) {
    $key = 529;
    $bucket = $this->currPos;
    $cached = $this->cache[$bucket][$key] ?? null;
    if ($cached) {
      $this->currPos = $cached['nextPos'];
  
      return $cached['result'];
    }
  
    // start choice_1
    $r1 = $this->discardnewlineToken($silence);
    if ($r1!==self::$FAILED) {
      goto choice_1;
    }
    $r1 = $this->discardcomment_space($silence);
    choice_1:
    $cached = ['nextPos' => $this->currPos, 'result' => $r1];
  
    $this->cache[$bucket][$key] = $cached;
    return $r1;
  }
  private function parsenl_comment_space($silence) {
    $key = 528;
    $bucket = $this->currPos;
    $cached = $this->cache[$bucket][$key] ?? null;
    if ($cached) {
      $this->currPos = $cached['nextPos'];
  
      return $cached['result'];
    }
  
    // start choice_1
    $r1 = $this->parsenewlineToken($silence);
    if ($r1!==self::$FAILED) {
      goto choice_1;
    }
    $r1 = $this->parsecomment_space($silence);
    choice_1:
    $cached = ['nextPos' => $this->currPos, 'result' => $r1];
  
    $this->cache[$bucket][$key] = $cached;
    return $r1;
  }
  private function parsetemplate_param($silence, $boolParams, $param_templatedepth, &$param_preproc, &$param_th) {
    $key = json_encode([362, $boolParams & 0x1b82, $param_templatedepth, $param_preproc, $param_th]);
    $bucket = $this->currPos;
    $cached = $this->cache[$bucket][$key] ?? null;
    if ($cached) {
      $this->currPos = $cached['nextPos'];
        if (array_key_exists("\$preproc", $cached)) $param_preproc = $cached["\$preproc"];
        if (array_key_exists("\$th", $cached)) $param_th = $cached["\$th"];
      return $cached['result'];
    }
        $saved_preproc=$param_preproc;
        $saved_th=$param_th;
    // start choice_1
    $p2 = $this->currPos;
    // start seq_1
    $p3 = $this->currPos;
    $r4 = $this->parsetemplate_param_name($silence, $boolParams, $param_templatedepth, $param_preproc, $param_th);
    // name <- $r4
    if ($r4===self::$FAILED) {
      $r1 = self::$FAILED;
      goto seq_1;
    }
    $p6 = $this->currPos;
    // start seq_2
    $p7 = $this->currPos;
    $p9 = $this->currPos;
    $r8 = '';
    // kEndPos <- $r8
    if ($r8!==self::$FAILED) {
      $this->savedPos = $p9;
      $r8 = $this->a124($r4);
    } else {
      $r5 = self::$FAILED;
      goto seq_2;
    }
    $r10 = $this->discardoptionalSpaceToken($silence);
    if ($r10===self::$FAILED) {
      $this->currPos = $p7;
      $r5 = self::$FAILED;
      goto seq_2;
    }
    if (($this->input[$this->currPos] ?? null) === "=") {
      $this->currPos++;
      $r11 = "=";
    } else {
      if (!$silence) {$this->fail(23);}
      $r11 = self::$FAILED;
      $this->currPos = $p7;
      $r5 = self::$FAILED;
      goto seq_2;
    }
    $p13 = $this->currPos;
    $r12 = '';
    // vStartPos <- $r12
    if ($r12!==self::$FAILED) {
      $this->savedPos = $p13;
      $r12 = $this->a125($r4, $r8);
    } else {
      $this->currPos = $p7;
      $r5 = self::$FAILED;
      goto seq_2;
    }
    $r14 = $this->discardoptionalSpaceToken($silence);
    if ($r14===self::$FAILED) {
      $this->currPos = $p7;
      $r5 = self::$FAILED;
      goto seq_2;
    }
    $r15 = $this->parsetemplate_param_value($silence, $boolParams, $param_templatedepth, $param_preproc, $param_th);
    if ($r15===self::$FAILED) {
      $r15 = null;
    }
    // tpv <- $r15
    $r5 = true;
    seq_2:
    if ($r5!==self::$FAILED) {
      $this->savedPos = $p6;
      $r5 = $this->a126($r4, $r8, $r12, $r15);
    } else {
      $r5 = null;
    }
    // free $p7
    // val <- $r5
    $r1 = true;
    seq_1:
    if ($r1!==self::$FAILED) {
      $this->savedPos = $p2;
      $r1 = $this->a127($r4, $r5);
      goto choice_1;
    }
    // free $p3
    $p3 = $this->currPos;
    $p7 = $this->currPos;
    $r1 = $this->input[$this->currPos] ?? '';
    if ($r1 === "|" || $r1 === "}") {
      $this->currPos++;
      $r1 = false;
      $this->currPos = $p7;
      $this->savedPos = $p3;
      $r1 = $this->a128();
    } else {
      $r1 = self::$FAILED;
    }
    // free $p7
    choice_1:
    $cached = ['nextPos' => $this->currPos, 'result' => $r1];
      if ($saved_preproc !== $param_preproc) $cached["\$preproc"] = $param_preproc;
      if ($saved_th !== $param_th) $cached["\$th"] = $param_th;
    $this->cache[$bucket][$key] = $cached;
    return $r1;
  }
  private function parsewikilink_preprocessor_text($silence, $boolParams, $param_templatedepth, &$param_preproc, &$param_th) {
    $key = json_encode([546, $boolParams & 0x3bfe, $param_templatedepth, $param_preproc, $param_th]);
    $bucket = $this->currPos;
    $cached = $this->cache[$bucket][$key] ?? null;
    if ($cached) {
      $this->currPos = $cached['nextPos'];
        if (array_key_exists("\$preproc", $cached)) $param_preproc = $cached["\$preproc"];
        if (array_key_exists("\$th", $cached)) $param_th = $cached["\$th"];
      return $cached['result'];
    }
        $saved_preproc=$param_preproc;
        $saved_th=$param_th;
    $p2 = $this->currPos;
    $r3 = [];
    for (;;) {
      // start choice_1
      $p6 = $this->currPos;
      $r5 = self::$FAILED;
      for (;;) {
        if (strcspn($this->input, "<[{\x0a\x0d\x09|!]}{ &-", $this->currPos, 1) !== 0) {
          $r7 = self::consumeChar($this->input, $this->currPos);
          $r5 = true;
        } else {
          $r7 = self::$FAILED;
          if (!$silence) {$this->fail(70);}
          break;
        }
      }
      // t <- $r5
      if ($r5!==self::$FAILED) {
        $r5 = substr($this->input, $p6, $this->currPos - $p6);
      } else {
        $r5 = self::$FAILED;
      }
      // free $r7
      // free $p6
      $r4 = $r5;
      if ($r4!==self::$FAILED) {
        goto choice_1;
      }
      $p6 = $this->currPos;
      // start seq_1
      $p8 = $this->currPos;
      $p9 = $this->currPos;
      $r7 = $this->discardinline_breaks(true, $boolParams, $param_templatedepth, $param_preproc, $param_th);
      if ($r7 === self::$FAILED) {
        $r7 = false;
      } else {
        $r7 = self::$FAILED;
        $this->currPos = $p9;
        $r4 = self::$FAILED;
        goto seq_1;
      }
      // free $p9
      // start choice_2
      $r10 = $this->parsedirective($silence, $boolParams, $param_templatedepth, $param_preproc, $param_th);
      if ($r10!==self::$FAILED) {
        goto choice_2;
      }
      $p9 = $this->currPos;
      // start seq_2
      $p11 = $this->currPos;
      $p12 = $this->currPos;
      if ($this->currPos >= $this->inputLength ? false : substr_compare($this->input, "]]", $this->currPos, 2, false) === 0) {
        $r13 = "]]";
        $this->currPos += 2;
      } else {
        $r13 = self::$FAILED;
      }
      if ($r13 === self::$FAILED) {
        $r13 = false;
      } else {
        $r13 = self::$FAILED;
        $this->currPos = $p12;
        $r10 = self::$FAILED;
        goto seq_2;
      }
      // free $p12
      // start choice_3
      $r14 = $this->discardtext_char($silence);
      if ($r14!==self::$FAILED) {
        goto choice_3;
      }
      if (strspn($this->input, "!<-}]\x0a\x0d", $this->currPos, 1) !== 0) {
        $r14 = $this->input[$this->currPos++];
      } else {
        $r14 = self::$FAILED;
        if (!$silence) {$this->fail(71);}
      }
      choice_3:
      if ($r14===self::$FAILED) {
        $this->currPos = $p11;
        $r10 = self::$FAILED;
        goto seq_2;
      }
      $r10 = true;
      seq_2:
      if ($r10!==self::$FAILED) {
        $r10 = substr($this->input, $p9, $this->currPos - $p9);
      } else {
        $r10 = self::$FAILED;
      }
      // free $p11
      // free $p9
      choice_2:
      // wr <- $r10
      if ($r10===self::$FAILED) {
        $this->currPos = $p8;
        $r4 = self::$FAILED;
        goto seq_1;
      }
      $r4 = true;
      seq_1:
      if ($r4!==self::$FAILED) {
        $this->savedPos = $p6;
        $r4 = $this->a129($r5, $r10);
      }
      // free $p8
      choice_1:
      if ($r4!==self::$FAILED) {
        $r3[] = $r4;
      } else {
        break;
      }
    }
    if (count($r3) === 0) {
      $r3 = self::$FAILED;
    }
    // r <- $r3
    // free $r4
    $r1 = $r3;
    if ($r1!==self::$FAILED) {
      $this->savedPos = $p2;
      $r1 = $this->a130($r3);
    }
    $cached = ['nextPos' => $this->currPos, 'result' => $r1];
      if ($saved_preproc !== $param_preproc) $cached["\$preproc"] = $param_preproc;
      if ($saved_th !== $param_th) $cached["\$th"] = $param_th;
    $this->cache[$bucket][$key] = $cached;
    return $r1;
  }
  private function parsewikilink_content($silence, $boolParams, $param_templatedepth, &$param_preproc, &$param_th) {
    $key = json_encode([400, $boolParams & 0x39f7, $param_templatedepth, $param_preproc, $param_th]);
    $bucket = $this->currPos;
    $cached = $this->cache[$bucket][$key] ?? null;
    if ($cached) {
      $this->currPos = $cached['nextPos'];
        if (array_key_exists("\$preproc", $cached)) $param_preproc = $cached["\$preproc"];
        if (array_key_exists("\$th", $cached)) $param_th = $cached["\$th"];
      return $cached['result'];
    }
        $saved_preproc=$param_preproc;
        $saved_th=$param_th;
    $r1 = [];
    for (;;) {
      $p3 = $this->currPos;
      // start seq_1
      $p4 = $this->currPos;
      $r5 = $this->discardpipe($silence);
      if ($r5===self::$FAILED) {
        $r2 = self::$FAILED;
        goto seq_1;
      }
      $p7 = $this->currPos;
      $r6 = '';
      // startPos <- $r6
      if ($r6!==self::$FAILED) {
        $this->savedPos = $p7;
        $r6 = $this->a14();
      } else {
        $this->currPos = $p4;
        $r2 = self::$FAILED;
        goto seq_1;
      }
      $r8 = $this->parselink_text($silence, $boolParams, $param_templatedepth, $param_preproc, $param_th);
      if ($r8===self::$FAILED) {
        $r8 = null;
      }
      // lt <- $r8
      $r2 = true;
      seq_1:
      if ($r2!==self::$FAILED) {
        $this->savedPos = $p3;
        $r2 = $this->a131($r6, $r8);
        $r1[] = $r2;
      } else {
        break;
      }
      // free $p4
    }
    // free $r2
    $cached = ['nextPos' => $this->currPos, 'result' => $r1];
      if ($saved_preproc !== $param_preproc) $cached["\$preproc"] = $param_preproc;
      if ($saved_th !== $param_th) $cached["\$th"] = $param_th;
    $this->cache[$bucket][$key] = $cached;
    return $r1;
  }
  private function parsespace_or_nbsp($silence) {
    $key = 512;
    $bucket = $this->currPos;
    $cached = $this->cache[$bucket][$key] ?? null;
    if ($cached) {
      $this->currPos = $cached['nextPos'];
  
      return $cached['result'];
    }
  
    // start choice_1
    $r1 = $this->parsespace($silence);
    if ($r1!==self::$FAILED) {
      goto choice_1;
    }
    $r1 = $this->parseunispace($silence);
    if ($r1!==self::$FAILED) {
      goto choice_1;
    }
    $p2 = $this->currPos;
    // start seq_1
    $p3 = $this->currPos;
    $p4 = $this->currPos;
    if (($this->input[$this->currPos] ?? null) === "&") {
      $this->currPos++;
      $r5 = "&";
      $r5 = false;
      $this->currPos = $p4;
    } else {
      $r5 = self::$FAILED;
      $r1 = self::$FAILED;
      goto seq_1;
    }
    // free $p4
    $r6 = $this->parsehtmlentity($silence);
    // he <- $r6
    if ($r6===self::$FAILED) {
      $this->currPos = $p3;
      $r1 = self::$FAILED;
      goto seq_1;
    }
    $this->savedPos = $this->currPos;
    $r7 = $this->a132($r6);
    if ($r7) {
      $r7 = false;
    } else {
      $r7 = self::$FAILED;
      $this->currPos = $p3;
      $r1 = self::$FAILED;
      goto seq_1;
    }
    $r1 = true;
    seq_1:
    if ($r1!==self::$FAILED) {
      $this->savedPos = $p2;
      $r1 = $this->a56($r6);
    }
    // free $p3
    choice_1:
    $cached = ['nextPos' => $this->currPos, 'result' => $r1];
  
    $this->cache[$bucket][$key] = $cached;
    return $r1;
  }
  private function discardend_of_word($silence) {
    $key = 509;
    $bucket = $this->currPos;
    $cached = $this->cache[$bucket][$key] ?? null;
    if ($cached) {
      $this->currPos = $cached['nextPos'];
  
      return $cached['result'];
    }
  
    // start choice_1
    $r1 = $this->discardeof($silence);
    if ($r1!==self::$FAILED) {
      goto choice_1;
    }
    $p2 = $this->currPos;
    $r1 = $this->input[$this->currPos] ?? '';
    if (preg_match("/^[A-Za-z0-9_]/", $r1)) {
      $this->currPos++;
    } else {
      $r1 = self::$FAILED;
    }
    if ($r1 === self::$FAILED) {
      $r1 = false;
    } else {
      $r1 = self::$FAILED;
      $this->currPos = $p2;
    }
    // free $p2
    choice_1:
    $cached = ['nextPos' => $this->currPos, 'result' => $r1];
  
    $this->cache[$bucket][$key] = $cached;
    return $r1;
  }
  private function parsespace_or_nbsp_or_dash($silence) {
    $key = 514;
    $bucket = $this->currPos;
    $cached = $this->cache[$bucket][$key] ?? null;
    if ($cached) {
      $this->currPos = $cached['nextPos'];
  
      return $cached['result'];
    }
  
    // start choice_1
    $r1 = $this->parsespace_or_nbsp($silence);
    if ($r1!==self::$FAILED) {
      goto choice_1;
    }
    if (($this->input[$this->currPos] ?? null) === "-") {
      $this->currPos++;
      $r1 = "-";
    } else {
      if (!$silence) {$this->fail(54);}
      $r1 = self::$FAILED;
    }
    choice_1:
    $cached = ['nextPos' => $this->currPos, 'result' => $r1];
  
    $this->cache[$bucket][$key] = $cached;
    return $r1;
  }
  private function discardtext_char($silence) {
    $key = 491;
    $bucket = $this->currPos;
    $cached = $this->cache[$bucket][$key] ?? null;
    if ($cached) {
      $this->currPos = $cached['nextPos'];
  
      return $cached['result'];
    }
  
    if (strcspn($this->input, "-'<[{\x0a\x0d:;]}|!=", $this->currPos, 1) !== 0) {
      $r1 = self::consumeChar($this->input, $this->currPos);
    } else {
      $r1 = self::$FAILED;
      if (!$silence) {$this->fail(41);}
    }
    $cached = ['nextPos' => $this->currPos, 'result' => $r1];
  
    $this->cache[$bucket][$key] = $cached;
    return $r1;
  }
  private function parselang_variant_preproc($silence, $boolParams, $param_templatedepth, &$param_preproc, &$param_th) {
    $key = json_encode([376, $boolParams & 0x1bfe, $param_templatedepth, $param_preproc, $param_th]);
    $bucket = $this->currPos;
    $cached = $this->cache[$bucket][$key] ?? null;
    if ($cached) {
      $this->currPos = $cached['nextPos'];
        if (array_key_exists("\$preproc", $cached)) $param_preproc = $cached["\$preproc"];
        if (array_key_exists("\$th", $cached)) $param_th = $cached["\$th"];
      return $cached['result'];
    }
        $saved_preproc=$param_preproc;
        $saved_th=$param_th;
    $p2 = $this->currPos;
    // start seq_1
    $p3 = $this->currPos;
    $p5 = $this->currPos;
    // lv0 <- $r4
    if ($this->currPos >= $this->inputLength ? false : substr_compare($this->input, "-{", $this->currPos, 2, false) === 0) {
      $r4 = "-{";
      $this->currPos += 2;
      $this->savedPos = $p5;
      $r4 = $this->a133();
    } else {
      if (!$silence) {$this->fail(72);}
      $r4 = self::$FAILED;
      $r1 = self::$FAILED;
      goto seq_1;
    }
    // start choice_1
    $p7 = $this->currPos;
    // start seq_2
    $p8 = $this->currPos;
    $this->savedPos = $this->currPos;
    $r9 = $this->a134($r4);
    if ($r9) {
      $r9 = false;
    } else {
      $r9 = self::$FAILED;
      $r6 = self::$FAILED;
      goto seq_2;
    }
    $r10 = $this->parseopt_lang_variant_flags($silence, $boolParams, $param_templatedepth, $param_preproc, $param_th);
    // ff <- $r10
    if ($r10===self::$FAILED) {
      $this->currPos = $p8;
      $r6 = self::$FAILED;
      goto seq_2;
    }
    $r6 = true;
    seq_2:
    if ($r6!==self::$FAILED) {
      $this->savedPos = $p7;
      $r6 = $this->a135($r4, $r10);
      goto choice_1;
    }
    // free $p8
    $p8 = $this->currPos;
    // start seq_3
    $p11 = $this->currPos;
    $this->savedPos = $this->currPos;
    $r12 = $this->a136($r4);
    if ($r12) {
      $r12 = false;
    } else {
      $r12 = self::$FAILED;
      $r6 = self::$FAILED;
      goto seq_3;
    }
    $r6 = true;
    seq_3:
    if ($r6!==self::$FAILED) {
      $this->savedPos = $p8;
      $r6 = $this->a137($r4);
    }
    // free $p11
    choice_1:
    // f <- $r6
    if ($r6===self::$FAILED) {
      $this->currPos = $p3;
      $r1 = self::$FAILED;
      goto seq_1;
    }
    // start choice_2
    $p11 = $this->currPos;
    // start seq_4
    $p14 = $this->currPos;
    $this->savedPos = $this->currPos;
    $r15 = $this->a138($r4, $r6);
    if ($r15) {
      $r15 = false;
    } else {
      $r15 = self::$FAILED;
      $r13 = self::$FAILED;
      goto seq_4;
    }
    $r16 = $this->parselang_variant_text($silence, $boolParams, $param_templatedepth, $param_preproc, $param_th);
    // lv <- $r16
    if ($r16===self::$FAILED) {
      $this->currPos = $p14;
      $r13 = self::$FAILED;
      goto seq_4;
    }
    $r13 = true;
    seq_4:
    if ($r13!==self::$FAILED) {
      $this->savedPos = $p11;
      $r13 = $this->a139($r4, $r6, $r16);
      goto choice_2;
    }
    // free $p14
    $p14 = $this->currPos;
    // start seq_5
    $p17 = $this->currPos;
    $this->savedPos = $this->currPos;
    $r18 = $this->a140($r4, $r6);
    if ($r18) {
      $r18 = false;
    } else {
      $r18 = self::$FAILED;
      $r13 = self::$FAILED;
      goto seq_5;
    }
    $r19 = $this->parselang_variant_option_list($silence, $boolParams, $param_templatedepth, $param_preproc, $param_th);
    // lv <- $r19
    if ($r19===self::$FAILED) {
      $this->currPos = $p17;
      $r13 = self::$FAILED;
      goto seq_5;
    }
    $r13 = true;
    seq_5:
    if ($r13!==self::$FAILED) {
      $this->savedPos = $p14;
      $r13 = $this->a141($r4, $r6, $r19);
    }
    // free $p17
    choice_2:
    // ts <- $r13
    if ($r13===self::$FAILED) {
      $this->currPos = $p3;
      $r1 = self::$FAILED;
      goto seq_1;
    }
    $r20 = $this->discardinline_breaks($silence, $boolParams, $param_templatedepth, $param_preproc, $param_th);
    if ($r20===self::$FAILED) {
      $this->currPos = $p3;
      $r1 = self::$FAILED;
      goto seq_1;
    }
    $p17 = $this->currPos;
    // lv1 <- $r21
    if ($this->currPos >= $this->inputLength ? false : substr_compare($this->input, "}-", $this->currPos, 2, false) === 0) {
      $r21 = "}-";
      $this->currPos += 2;
      $this->savedPos = $p17;
      $r21 = $this->a142($r4, $r6, $r13);
    } else {
      if (!$silence) {$this->fail(73);}
      $r21 = self::$FAILED;
      $this->currPos = $p3;
      $r1 = self::$FAILED;
      goto seq_1;
    }
    $r1 = true;
    seq_1:
    if ($r1!==self::$FAILED) {
      $this->savedPos = $p2;
      $r1 = $this->a143($r4, $r6, $r13, $r21);
    }
    // free $p3
    $cached = ['nextPos' => $this->currPos, 'result' => $r1];
      if ($saved_preproc !== $param_preproc) $cached["\$preproc"] = $param_preproc;
      if ($saved_th !== $param_th) $cached["\$th"] = $param_th;
    $this->cache[$bucket][$key] = $cached;
    return $r1;
  }
  private function parsebroken_lang_variant($silence, &$param_preproc) {
    $key = json_encode([372, $param_preproc]);
    $bucket = $this->currPos;
    $cached = $this->cache[$bucket][$key] ?? null;
    if ($cached) {
      $this->currPos = $cached['nextPos'];
        if (array_key_exists("\$preproc", $cached)) $param_preproc = $cached["\$preproc"];
      return $cached['result'];
    }
        $saved_preproc=$param_preproc;
    $p2 = $this->currPos;
    // start seq_1
    $p3 = $this->currPos;
    // r <- $r4
    if ($this->currPos >= $this->inputLength ? false : substr_compare($this->input, "-{", $this->currPos, 2, false) === 0) {
      $r4 = "-{";
      $this->currPos += 2;
    } else {
      if (!$silence) {$this->fail(72);}
      $r4 = self::$FAILED;
      $r1 = self::$FAILED;
      goto seq_1;
    }
    $r1 = true;
    seq_1:
    if ($r1!==self::$FAILED) {
      $this->savedPos = $p2;
      $r1 = $this->a144($r4, $param_preproc);
    }
    // free $p3
    $cached = ['nextPos' => $this->currPos, 'result' => $r1];
      if ($saved_preproc !== $param_preproc) $cached["\$preproc"] = $param_preproc;
    $this->cache[$bucket][$key] = $cached;
    return $r1;
  }
  private function discardlist_char($silence) {
    $key = 457;
    $bucket = $this->currPos;
    $cached = $this->cache[$bucket][$key] ?? null;
    if ($cached) {
      $this->currPos = $cached['nextPos'];
  
      return $cached['result'];
    }
  
    if (strspn($this->input, "*#:;", $this->currPos, 1) !== 0) {
      $r1 = $this->input[$this->currPos++];
    } else {
      $r1 = self::$FAILED;
      if (!$silence) {$this->fail(74);}
    }
    $cached = ['nextPos' => $this->currPos, 'result' => $r1];
  
    $this->cache[$bucket][$key] = $cached;
    return $r1;
  }
  private function parselist_char($silence) {
    $key = 456;
    $bucket = $this->currPos;
    $cached = $this->cache[$bucket][$key] ?? null;
    if ($cached) {
      $this->currPos = $cached['nextPos'];
  
      return $cached['result'];
    }
  
    if (strspn($this->input, "*#:;", $this->currPos, 1) !== 0) {
      $r1 = $this->input[$this->currPos++];
    } else {
      $r1 = self::$FAILED;
      if (!$silence) {$this->fail(74);}
    }
    $cached = ['nextPos' => $this->currPos, 'result' => $r1];
  
    $this->cache[$bucket][$key] = $cached;
    return $r1;
  }
  private function parseinlineline_break_on_colon($silence, $boolParams, $param_templatedepth, &$param_preproc, &$param_th) {
    $key = json_encode([458, $boolParams & 0xbfe, $param_templatedepth, $param_preproc, $param_th]);
    $bucket = $this->currPos;
    $cached = $this->cache[$bucket][$key] ?? null;
    if ($cached) {
      $this->currPos = $cached['nextPos'];
        if (array_key_exists("\$preproc", $cached)) $param_preproc = $cached["\$preproc"];
        if (array_key_exists("\$th", $cached)) $param_th = $cached["\$th"];
      return $cached['result'];
    }
        $saved_preproc=$param_preproc;
        $saved_th=$param_th;
    $r1 = $this->parseinlineline($silence, $boolParams | 0x1000, $param_templatedepth, $param_preproc, $param_th);
    $cached = ['nextPos' => $this->currPos, 'result' => $r1];
      if ($saved_preproc !== $param_preproc) $cached["\$preproc"] = $param_preproc;
      if ($saved_th !== $param_th) $cached["\$th"] = $param_th;
    $this->cache[$bucket][$key] = $cached;
    return $r1;
  }
  private function discardcomment_space_eolf($silence) {
    $key = 543;
    $bucket = $this->currPos;
    $cached = $this->cache[$bucket][$key] ?? null;
    if ($cached) {
      $this->currPos = $cached['nextPos'];
  
      return $cached['result'];
    }
  
    // start seq_1
    $p1 = $this->currPos;
    for (;;) {
      // start choice_1
      $r4 = self::$FAILED;
      for (;;) {
        $r5 = $this->discardspace($silence);
        if ($r5!==self::$FAILED) {
          $r4 = true;
        } else {
          break;
        }
      }
      if ($r4!==self::$FAILED) {
        goto choice_1;
      }
      // free $r5
      $r4 = $this->discardcomment($silence);
      choice_1:
      if ($r4===self::$FAILED) {
        break;
      }
    }
    // free $r4
    $r3 = true;
    if ($r3===self::$FAILED) {
      $r2 = self::$FAILED;
      goto seq_1;
    }
    // free $r3
    $r3 = $this->discardeolf($silence);
    if ($r3===self::$FAILED) {
      $this->currPos = $p1;
      $r2 = self::$FAILED;
      goto seq_1;
    }
    $r2 = true;
    seq_1:
    // free $r2,$p1
    $cached = ['nextPos' => $this->currPos, 'result' => $r2];
  
    $this->cache[$bucket][$key] = $cached;
    return $r2;
  }
  private function discardempty_line_with_comments($silence) {
    $key = 525;
    $bucket = $this->currPos;
    $cached = $this->cache[$bucket][$key] ?? null;
    if ($cached) {
      $this->currPos = $cached['nextPos'];
  
      return $cached['result'];
    }
  
    $p2 = $this->currPos;
    // start seq_1
    $p3 = $this->currPos;
    $r4 = $this->parsesol_prefix($silence);
    // sp <- $r4
    if ($r4===self::$FAILED) {
      $r1 = self::$FAILED;
      goto seq_1;
    }
    $p6 = $this->currPos;
    $r5 = '';
    // p <- $r5
    if ($r5!==self::$FAILED) {
      $this->savedPos = $p6;
      $r5 = $this->a88($r4);
    } else {
      $this->currPos = $p3;
      $r1 = self::$FAILED;
      goto seq_1;
    }
    $r7 = [];
    for (;;) {
      // start seq_2
      $p9 = $this->currPos;
      $r10 = [];
      for (;;) {
        $r11 = $this->parsespace($silence);
        if ($r11!==self::$FAILED) {
          $r10[] = $r11;
        } else {
          break;
        }
      }
      // free $r11
      $r11 = $this->parsecomment($silence);
      if ($r11===self::$FAILED) {
        $this->currPos = $p9;
        $r8 = self::$FAILED;
        goto seq_2;
      }
      $r12 = [];
      for (;;) {
        // start choice_1
        $r13 = $this->parsespace($silence);
        if ($r13!==self::$FAILED) {
          goto choice_1;
        }
        $r13 = $this->parsecomment($silence);
        choice_1:
        if ($r13!==self::$FAILED) {
          $r12[] = $r13;
        } else {
          break;
        }
      }
      // free $r13
      $r13 = $this->parsenewline($silence);
      if ($r13===self::$FAILED) {
        $this->currPos = $p9;
        $r8 = self::$FAILED;
        goto seq_2;
      }
      $r8 = [$r10,$r11,$r12,$r13];
      seq_2:
      if ($r8!==self::$FAILED) {
        $r7[] = $r8;
      } else {
        break;
      }
      // free $p9
    }
    if (count($r7) === 0) {
      $r7 = self::$FAILED;
    }
    // c <- $r7
    if ($r7===self::$FAILED) {
      $this->currPos = $p3;
      $r1 = self::$FAILED;
      goto seq_1;
    }
    // free $r8
    $r1 = true;
    seq_1:
    if ($r1!==self::$FAILED) {
      $this->savedPos = $p2;
      $r1 = $this->a89($r4, $r5, $r7);
    }
    // free $p3
    $cached = ['nextPos' => $this->currPos, 'result' => $r1];
  
    $this->cache[$bucket][$key] = $cached;
    return $r1;
  }
  private function discardsol_prefix($silence) {
    $key = 523;
    $bucket = $this->currPos;
    $cached = $this->cache[$bucket][$key] ?? null;
    if ($cached) {
      $this->currPos = $cached['nextPos'];
  
      return $cached['result'];
    }
  
    // start choice_1
    $r1 = $this->discardnewlineToken($silence);
    if ($r1!==self::$FAILED) {
      goto choice_1;
    }
    $p2 = $this->currPos;
    $this->savedPos = $this->currPos;
    $r1 = $this->a90();
    if ($r1) {
      $r1 = false;
      $this->savedPos = $p2;
      $r1 = $this->a91();
    } else {
      $r1 = self::$FAILED;
    }
    choice_1:
    $cached = ['nextPos' => $this->currPos, 'result' => $r1];
  
    $this->cache[$bucket][$key] = $cached;
    return $r1;
  }
  private function discardcomment_or_includes($silence, $boolParams, $param_templatedepth, &$param_preproc, &$param_th) {
    $key = json_encode([519, $boolParams & 0x13ae, $param_templatedepth, $param_preproc, $param_th]);
    $bucket = $this->currPos;
    $cached = $this->cache[$bucket][$key] ?? null;
    if ($cached) {
      $this->currPos = $cached['nextPos'];
        if (array_key_exists("\$preproc", $cached)) $param_preproc = $cached["\$preproc"];
        if (array_key_exists("\$th", $cached)) $param_th = $cached["\$th"];
      return $cached['result'];
    }
        $saved_preproc=$param_preproc;
        $saved_th=$param_th;
    for (;;) {
      // start choice_1
      $r2 = $this->discardcomment($silence);
      if ($r2!==self::$FAILED) {
        goto choice_1;
      }
      $r2 = $this->discardinclude_limits($silence, $boolParams | 0x2000, $param_templatedepth, $param_preproc, $param_th);
      choice_1:
      if ($r2===self::$FAILED) {
        break;
      }
    }
    // free $r2
    $r1 = true;
    // free $r1
    $cached = ['nextPos' => $this->currPos, 'result' => $r1];
      if ($saved_preproc !== $param_preproc) $cached["\$preproc"] = $param_preproc;
      if ($saved_th !== $param_th) $cached["\$th"] = $param_th;
    $this->cache[$bucket][$key] = $cached;
    return $r1;
  }
  private function parsetable_heading_tags($silence, $boolParams, $param_templatedepth, &$param_preproc) {
    $key = json_encode([480, $boolParams & 0x3bfe, $param_templatedepth, $param_preproc]);
    $bucket = $this->currPos;
    $cached = $this->cache[$bucket][$key] ?? null;
    if ($cached) {
      $this->currPos = $cached['nextPos'];
        if (array_key_exists("\$preproc", $cached)) $param_preproc = $cached["\$preproc"];
      return $cached['result'];
    }
        $saved_preproc=$param_preproc;
    $r1 = $this->parsetable_heading_tags_parameterized($silence, $boolParams, $param_templatedepth, $param_preproc, self::newRef(true));
    $cached = ['nextPos' => $this->currPos, 'result' => $r1];
      if ($saved_preproc !== $param_preproc) $cached["\$preproc"] = $param_preproc;
    $this->cache[$bucket][$key] = $cached;
    return $r1;
  }
  private function parsetable_row_tag($silence, $boolParams, $param_templatedepth, &$param_preproc, &$param_th) {
    $key = json_encode([472, $boolParams & 0x3bef, $param_templatedepth, $param_preproc, $param_th]);
    $bucket = $this->currPos;
    $cached = $this->cache[$bucket][$key] ?? null;
    if ($cached) {
      $this->currPos = $cached['nextPos'];
        if (array_key_exists("\$preproc", $cached)) $param_preproc = $cached["\$preproc"];
        if (array_key_exists("\$th", $cached)) $param_th = $cached["\$th"];
      return $cached['result'];
    }
        $saved_preproc=$param_preproc;
        $saved_th=$param_th;
    $p2 = $this->currPos;
    // start seq_1
    $p3 = $this->currPos;
    if (!(/*tableDataBlock*/($boolParams & 0x1) !== 0)) {
      $r4 = false;
    } else {
      $r4 = self::$FAILED;
      $r1 = self::$FAILED;
      goto seq_1;
    }
    $r5 = $this->parsepipe($silence);
    // p <- $r5
    if ($r5===self::$FAILED) {
      $this->currPos = $p3;
      $r1 = self::$FAILED;
      goto seq_1;
    }
    $p7 = $this->currPos;
    $r6 = self::$FAILED;
    for (;;) {
      if (($this->input[$this->currPos] ?? null) === "-") {
        $this->currPos++;
        $r8 = "-";
        $r6 = true;
      } else {
        if (!$silence) {$this->fail(54);}
        $r8 = self::$FAILED;
        break;
      }
    }
    // dashes <- $r6
    if ($r6!==self::$FAILED) {
      $r6 = substr($this->input, $p7, $this->currPos - $p7);
    } else {
      $r6 = self::$FAILED;
      $this->currPos = $p3;
      $r1 = self::$FAILED;
      goto seq_1;
    }
    // free $r8
    // free $p7
    // start choice_1
    $r8 = $this->parsetable_attributes($silence, $boolParams & ~0x10, $param_templatedepth, $param_preproc, $param_th);
    if ($r8!==self::$FAILED) {
      goto choice_1;
    }
    $this->savedPos = $this->currPos;
    $r8 = $this->a145($r5, $r6);
    if ($r8) {
      $r8 = false;
    } else {
      $r8 = self::$FAILED;
    }
    choice_1:
    // a <- $r8
    if ($r8===self::$FAILED) {
      $this->currPos = $p3;
      $r1 = self::$FAILED;
      goto seq_1;
    }
    $p7 = $this->currPos;
    $r9 = '';
    // tagEndPos <- $r9
    if ($r9!==self::$FAILED) {
      $this->savedPos = $p7;
      $r9 = $this->a146($r5, $r6, $r8);
    } else {
      $this->currPos = $p3;
      $r1 = self::$FAILED;
      goto seq_1;
    }
    $r1 = true;
    seq_1:
    if ($r1!==self::$FAILED) {
      $this->savedPos = $p2;
      $r1 = $this->a147($r5, $r6, $r8, $r9);
    }
    // free $p3
    $cached = ['nextPos' => $this->currPos, 'result' => $r1];
      if ($saved_preproc !== $param_preproc) $cached["\$preproc"] = $param_preproc;
      if ($saved_th !== $param_th) $cached["\$th"] = $param_th;
    $this->cache[$bucket][$key] = $cached;
    return $r1;
  }
  private function parsetable_data_tags($silence, $boolParams, $param_templatedepth, &$param_preproc, &$param_th) {
    $key = json_encode([476, $boolParams & 0x3bff, $param_templatedepth, $param_preproc, $param_th]);
    $bucket = $this->currPos;
    $cached = $this->cache[$bucket][$key] ?? null;
    if ($cached) {
      $this->currPos = $cached['nextPos'];
        if (array_key_exists("\$preproc", $cached)) $param_preproc = $cached["\$preproc"];
        if (array_key_exists("\$th", $cached)) $param_th = $cached["\$th"];
      return $cached['result'];
    }
        $saved_preproc=$param_preproc;
        $saved_th=$param_th;
    $p2 = $this->currPos;
    // start seq_1
    $p3 = $this->currPos;
    if (!(/*tableDataBlock*/($boolParams & 0x1) !== 0)) {
      $r4 = false;
    } else {
      $r4 = self::$FAILED;
      $r1 = self::$FAILED;
      goto seq_1;
    }
    $r5 = $this->parsepipe($silence);
    // p <- $r5
    if ($r5===self::$FAILED) {
      $this->currPos = $p3;
      $r1 = self::$FAILED;
      goto seq_1;
    }
    $p6 = $this->currPos;
    $r7 = $this->input[$this->currPos] ?? '';
    if ($r7 === "+" || $r7 === "-") {
      $this->currPos++;
    } else {
      $r7 = self::$FAILED;
    }
    if ($r7 === self::$FAILED) {
      $r7 = false;
    } else {
      $r7 = self::$FAILED;
      $this->currPos = $p6;
      $this->currPos = $p3;
      $r1 = self::$FAILED;
      goto seq_1;
    }
    // free $p6
    $r8 = $this->parsetable_data_tag($silence, $boolParams, $param_templatedepth, $param_preproc, $param_th);
    // td <- $r8
    if ($r8===self::$FAILED) {
      $this->currPos = $p3;
      $r1 = self::$FAILED;
      goto seq_1;
    }
    $p6 = $this->currPos;
    $r9 = '';
    // tagEndPos <- $r9
    if ($r9!==self::$FAILED) {
      $this->savedPos = $p6;
      $r9 = $this->a148($r5, $r8);
    } else {
      $this->currPos = $p3;
      $r1 = self::$FAILED;
      goto seq_1;
    }
    $r10 = $this->parsetds($silence, $boolParams, $param_templatedepth, $param_preproc, $param_th);
    // tds <- $r10
    if ($r10===self::$FAILED) {
      $this->currPos = $p3;
      $r1 = self::$FAILED;
      goto seq_1;
    }
    $r1 = true;
    seq_1:
    if ($r1!==self::$FAILED) {
      $this->savedPos = $p2;
      $r1 = $this->a149($r5, $r8, $r9, $r10);
    }
    // free $p3
    $cached = ['nextPos' => $this->currPos, 'result' => $r1];
      if ($saved_preproc !== $param_preproc) $cached["\$preproc"] = $param_preproc;
      if ($saved_th !== $param_th) $cached["\$th"] = $param_th;
    $this->cache[$bucket][$key] = $cached;
    return $r1;
  }
  private function parsetable_caption_tag($silence, $boolParams, $param_templatedepth, &$param_preproc, &$param_th) {
    $key = json_encode([470, $boolParams & 0x3bff, $param_templatedepth, $param_preproc, $param_th]);
    $bucket = $this->currPos;
    $cached = $this->cache[$bucket][$key] ?? null;
    if ($cached) {
      $this->currPos = $cached['nextPos'];
        if (array_key_exists("\$preproc", $cached)) $param_preproc = $cached["\$preproc"];
        if (array_key_exists("\$th", $cached)) $param_th = $cached["\$th"];
      return $cached['result'];
    }
        $saved_preproc=$param_preproc;
        $saved_th=$param_th;
    $p2 = $this->currPos;
    // start seq_1
    $p3 = $this->currPos;
    if (!(/*tableDataBlock*/($boolParams & 0x1) !== 0)) {
      $r4 = false;
    } else {
      $r4 = self::$FAILED;
      $r1 = self::$FAILED;
      goto seq_1;
    }
    $r5 = $this->parsepipe($silence);
    // p <- $r5
    if ($r5===self::$FAILED) {
      $this->currPos = $p3;
      $r1 = self::$FAILED;
      goto seq_1;
    }
    if (($this->input[$this->currPos] ?? null) === "+") {
      $this->currPos++;
      $r6 = "+";
    } else {
      if (!$silence) {$this->fail(75);}
      $r6 = self::$FAILED;
      $this->currPos = $p3;
      $r1 = self::$FAILED;
      goto seq_1;
    }
    $r7 = $this->parserow_syntax_table_args($silence, $boolParams, $param_templatedepth, $param_preproc, $param_th);
    if ($r7===self::$FAILED) {
      $r7 = null;
    }
    // args <- $r7
    $p9 = $this->currPos;
    $r8 = '';
    // tagEndPos <- $r8
    if ($r8!==self::$FAILED) {
      $this->savedPos = $p9;
      $r8 = $this->a150($r5, $r7);
    } else {
      $this->currPos = $p3;
      $r1 = self::$FAILED;
      goto seq_1;
    }
    $r10 = [];
    for (;;) {
      $r11 = $this->parsenested_block_in_table($silence, $boolParams, $param_templatedepth, $param_preproc, $param_th);
      if ($r11!==self::$FAILED) {
        $r10[] = $r11;
      } else {
        break;
      }
    }
    // c <- $r10
    // free $r11
    $r1 = true;
    seq_1:
    if ($r1!==self::$FAILED) {
      $this->savedPos = $p2;
      $r1 = $this->a151($r5, $r7, $r8, $r10);
    }
    // free $p3
    $cached = ['nextPos' => $this->currPos, 'result' => $r1];
      if ($saved_preproc !== $param_preproc) $cached["\$preproc"] = $param_preproc;
      if ($saved_th !== $param_th) $cached["\$th"] = $param_th;
    $this->cache[$bucket][$key] = $cached;
    return $r1;
  }
  private function discardtag_name_chars($silence) {
    $key = 421;
    $bucket = $this->currPos;
    $cached = $this->cache[$bucket][$key] ?? null;
    if ($cached) {
      $this->currPos = $cached['nextPos'];
  
      return $cached['result'];
    }
  
    if (strcspn($this->input, "\x09\x0a\x0b />\x00", $this->currPos, 1) !== 0) {
      $r1 = self::consumeChar($this->input, $this->currPos);
    } else {
      $r1 = self::$FAILED;
      if (!$silence) {$this->fail(76);}
    }
    $cached = ['nextPos' => $this->currPos, 'result' => $r1];
  
    $this->cache[$bucket][$key] = $cached;
    return $r1;
  }
  private function parsetemplate_param_text($silence, $boolParams, $param_templatedepth, &$param_preproc, &$param_th) {
    $key = json_encode([368, $boolParams & 0x1b8a, $param_templatedepth, $param_preproc, $param_th]);
    $bucket = $this->currPos;
    $cached = $this->cache[$bucket][$key] ?? null;
    if ($cached) {
      $this->currPos = $cached['nextPos'];
        if (array_key_exists("\$preproc", $cached)) $param_preproc = $cached["\$preproc"];
        if (array_key_exists("\$th", $cached)) $param_th = $cached["\$th"];
      return $cached['result'];
    }
        $saved_preproc=$param_preproc;
        $saved_th=$param_th;
    $p2 = $this->currPos;
    $r3 = [];
    for (;;) {
      // start choice_1
      $r4 = $this->parsenested_block($silence, ($boolParams & ~0x54) | 0x20, $param_templatedepth, $param_preproc, $param_th);
      if ($r4!==self::$FAILED) {
        goto choice_1;
      }
      $r4 = $this->parsenewlineToken($silence);
      choice_1:
      if ($r4!==self::$FAILED) {
        $r3[] = $r4;
      } else {
        break;
      }
    }
    if (count($r3) === 0) {
      $r3 = self::$FAILED;
    }
    // il <- $r3
    // free $r4
    $r1 = $r3;
    if ($r1!==self::$FAILED) {
      $this->savedPos = $p2;
      $r1 = $this->a152($r3);
    }
    $cached = ['nextPos' => $this->currPos, 'result' => $r1];
      if ($saved_preproc !== $param_preproc) $cached["\$preproc"] = $param_preproc;
      if ($saved_th !== $param_th) $cached["\$th"] = $param_th;
    $this->cache[$bucket][$key] = $cached;
    return $r1;
  }
  private function discardnewlineToken($silence) {
    $key = 539;
    $bucket = $this->currPos;
    $cached = $this->cache[$bucket][$key] ?? null;
    if ($cached) {
      $this->currPos = $cached['nextPos'];
  
      return $cached['result'];
    }
  
    $p2 = $this->currPos;
    $r1 = $this->discardnewline($silence);
    if ($r1!==self::$FAILED) {
      $this->savedPos = $p2;
      $r1 = $this->a22();
    }
    $cached = ['nextPos' => $this->currPos, 'result' => $r1];
  
    $this->cache[$bucket][$key] = $cached;
    return $r1;
  }
  private function discardcomment_space($silence) {
    $key = 527;
    $bucket = $this->currPos;
    $cached = $this->cache[$bucket][$key] ?? null;
    if ($cached) {
      $this->currPos = $cached['nextPos'];
  
      return $cached['result'];
    }
  
    // start choice_1
    $r1 = $this->discardcomment($silence);
    if ($r1!==self::$FAILED) {
      goto choice_1;
    }
    $r1 = $this->discardspace($silence);
    choice_1:
    $cached = ['nextPos' => $this->currPos, 'result' => $r1];
  
    $this->cache[$bucket][$key] = $cached;
    return $r1;
  }
  private function parsecomment_space($silence) {
    $key = 526;
    $bucket = $this->currPos;
    $cached = $this->cache[$bucket][$key] ?? null;
    if ($cached) {
      $this->currPos = $cached['nextPos'];
  
      return $cached['result'];
    }
  
    // start choice_1
    $r1 = $this->parsecomment($silence);
    if ($r1!==self::$FAILED) {
      goto choice_1;
    }
    $r1 = $this->parsespace($silence);
    choice_1:
    $cached = ['nextPos' => $this->currPos, 'result' => $r1];
  
    $this->cache[$bucket][$key] = $cached;
    return $r1;
  }
  private function parsetemplate_param_name($silence, $boolParams, $param_templatedepth, &$param_preproc, &$param_th) {
    $key = json_encode([364, $boolParams & 0x1b82, $param_templatedepth, $param_preproc, $param_th]);
    $bucket = $this->currPos;
    $cached = $this->cache[$bucket][$key] ?? null;
    if ($cached) {
      $this->currPos = $cached['nextPos'];
        if (array_key_exists("\$preproc", $cached)) $param_preproc = $cached["\$preproc"];
        if (array_key_exists("\$th", $cached)) $param_th = $cached["\$th"];
      return $cached['result'];
    }
        $saved_preproc=$param_preproc;
        $saved_th=$param_th;
    // start choice_1
    $r1 = $this->parsetemplate_param_text($silence, $boolParams | 0x8, $param_templatedepth, $param_preproc, $param_th);
    if ($r1!==self::$FAILED) {
      goto choice_1;
    }
    $p2 = $this->currPos;
    $p3 = $this->currPos;
    if (($this->input[$this->currPos] ?? null) === "=") {
      $this->currPos++;
      $r1 = "=";
      $r1 = false;
      $this->currPos = $p3;
      $this->savedPos = $p2;
      $r1 = $this->a153();
    } else {
      $r1 = self::$FAILED;
    }
    // free $p3
    choice_1:
    $cached = ['nextPos' => $this->currPos, 'result' => $r1];
      if ($saved_preproc !== $param_preproc) $cached["\$preproc"] = $param_preproc;
      if ($saved_th !== $param_th) $cached["\$th"] = $param_th;
    $this->cache[$bucket][$key] = $cached;
    return $r1;
  }
  private function parselink_text($silence, $boolParams, $param_templatedepth, &$param_preproc, &$param_th) {
    $key = json_encode([408, $boolParams & 0x39f7, $param_templatedepth, $param_preproc, $param_th]);
    $bucket = $this->currPos;
    $cached = $this->cache[$bucket][$key] ?? null;
    if ($cached) {
      $this->currPos = $cached['nextPos'];
        if (array_key_exists("\$preproc", $cached)) $param_preproc = $cached["\$preproc"];
        if (array_key_exists("\$th", $cached)) $param_th = $cached["\$th"];
      return $cached['result'];
    }
        $saved_preproc=$param_preproc;
        $saved_th=$param_th;
    $r1 = $this->parselink_text_parameterized($silence, ($boolParams & ~0x8) | 0x200, $param_templatedepth, $param_preproc, $param_th);
    $cached = ['nextPos' => $this->currPos, 'result' => $r1];
      if ($saved_preproc !== $param_preproc) $cached["\$preproc"] = $param_preproc;
      if ($saved_th !== $param_th) $cached["\$th"] = $param_th;
    $this->cache[$bucket][$key] = $cached;
    return $r1;
  }
  private function parseunispace($silence) {
    $key = 510;
    $bucket = $this->currPos;
    $cached = $this->cache[$bucket][$key] ?? null;
    if ($cached) {
      $this->currPos = $cached['nextPos'];
  
      return $cached['result'];
    }
  
    $r1 = self::charAt($this->input, $this->currPos);
    if (preg_match("/^[ \\x{a0}\\x{1680}\\x{2000}-\\x{200a}\\x{202f}\\x{205f}\\x{3000}]/u", $r1)) {
      $this->currPos += strlen($r1);
    } else {
      $r1 = self::$FAILED;
      if (!$silence) {$this->fail(25);}
    }
    $cached = ['nextPos' => $this->currPos, 'result' => $r1];
  
    $this->cache[$bucket][$key] = $cached;
    return $r1;
  }
  private function parseopt_lang_variant_flags($silence, $boolParams, $param_templatedepth, &$param_preproc, &$param_th) {
    $key = json_encode([378, $boolParams & 0x1bae, $param_templatedepth, $param_preproc, $param_th]);
    $bucket = $this->currPos;
    $cached = $this->cache[$bucket][$key] ?? null;
    if ($cached) {
      $this->currPos = $cached['nextPos'];
        if (array_key_exists("\$preproc", $cached)) $param_preproc = $cached["\$preproc"];
        if (array_key_exists("\$th", $cached)) $param_th = $cached["\$th"];
      return $cached['result'];
    }
        $saved_preproc=$param_preproc;
        $saved_th=$param_th;
    $p2 = $this->currPos;
    $p4 = $this->currPos;
    // start seq_1
    $p5 = $this->currPos;
    $r6 = $this->parselang_variant_flags($silence, $boolParams, $param_templatedepth, $param_preproc, $param_th);
    // ff <- $r6
    if ($r6===self::$FAILED) {
      $r3 = self::$FAILED;
      goto seq_1;
    }
    if (($this->input[$this->currPos] ?? null) === "|") {
      $this->currPos++;
      $r7 = "|";
    } else {
      if (!$silence) {$this->fail(13);}
      $r7 = self::$FAILED;
      $this->currPos = $p5;
      $r3 = self::$FAILED;
      goto seq_1;
    }
    $r3 = true;
    seq_1:
    if ($r3!==self::$FAILED) {
      $this->savedPos = $p4;
      $r3 = $this->a154($r6);
    } else {
      $r3 = null;
    }
    // free $p5
    // f <- $r3
    $r1 = $r3;
    if ($r1!==self::$FAILED) {
      $this->savedPos = $p2;
      $r1 = $this->a155($r3);
    }
    $cached = ['nextPos' => $this->currPos, 'result' => $r1];
      if ($saved_preproc !== $param_preproc) $cached["\$preproc"] = $param_preproc;
      if ($saved_th !== $param_th) $cached["\$th"] = $param_th;
    $this->cache[$bucket][$key] = $cached;
    return $r1;
  }
  private function parselang_variant_text($silence, $boolParams, $param_templatedepth, &$param_preproc, &$param_th) {
    $key = json_encode([394, $boolParams & 0x1bfe, $param_templatedepth, $param_preproc, $param_th]);
    $bucket = $this->currPos;
    $cached = $this->cache[$bucket][$key] ?? null;
    if ($cached) {
      $this->currPos = $cached['nextPos'];
        if (array_key_exists("\$preproc", $cached)) $param_preproc = $cached["\$preproc"];
        if (array_key_exists("\$th", $cached)) $param_th = $cached["\$th"];
      return $cached['result'];
    }
        $saved_preproc=$param_preproc;
        $saved_th=$param_th;
    $p2 = $this->currPos;
    $r3 = [];
    for (;;) {
      // start choice_1
      $r4 = $this->parseinlineline($silence, $boolParams, $param_templatedepth, $param_preproc, $param_th);
      if ($r4!==self::$FAILED) {
        goto choice_1;
      }
      if (($this->input[$this->currPos] ?? null) === "|") {
        $this->currPos++;
        $r4 = "|";
      } else {
        if (!$silence) {$this->fail(13);}
        $r4 = self::$FAILED;
      }
      choice_1:
      if ($r4!==self::$FAILED) {
        $r3[] = $r4;
      } else {
        break;
      }
    }
    // tokens <- $r3
    // free $r4
    $r1 = $r3;
    if ($r1!==self::$FAILED) {
      $this->savedPos = $p2;
      $r1 = $this->a156($r3);
    }
    $cached = ['nextPos' => $this->currPos, 'result' => $r1];
      if ($saved_preproc !== $param_preproc) $cached["\$preproc"] = $param_preproc;
      if ($saved_th !== $param_th) $cached["\$th"] = $param_th;
    $this->cache[$bucket][$key] = $cached;
    return $r1;
  }
  private function parselang_variant_option_list($silence, $boolParams, $param_templatedepth, &$param_preproc, &$param_th) {
    $key = json_encode([386, $boolParams & 0x1bfe, $param_templatedepth, $param_preproc, $param_th]);
    $bucket = $this->currPos;
    $cached = $this->cache[$bucket][$key] ?? null;
    if ($cached) {
      $this->currPos = $cached['nextPos'];
        if (array_key_exists("\$preproc", $cached)) $param_preproc = $cached["\$preproc"];
        if (array_key_exists("\$th", $cached)) $param_th = $cached["\$th"];
      return $cached['result'];
    }
        $saved_preproc=$param_preproc;
        $saved_th=$param_th;
    // start choice_1
    $p2 = $this->currPos;
    // start seq_1
    $p3 = $this->currPos;
    $r4 = $this->parselang_variant_option($silence, $boolParams, $param_templatedepth, $param_preproc, $param_th);
    // o <- $r4
    if ($r4===self::$FAILED) {
      $r1 = self::$FAILED;
      goto seq_1;
    }
    $r5 = [];
    for (;;) {
      $p7 = $this->currPos;
      // start seq_2
      $p8 = $this->currPos;
      if (($this->input[$this->currPos] ?? null) === ";") {
        $this->currPos++;
        $r9 = ";";
      } else {
        if (!$silence) {$this->fail(30);}
        $r9 = self::$FAILED;
        $r6 = self::$FAILED;
        goto seq_2;
      }
      $r10 = $this->parselang_variant_option($silence, $boolParams, $param_templatedepth, $param_preproc, $param_th);
      // oo <- $r10
      if ($r10===self::$FAILED) {
        $this->currPos = $p8;
        $r6 = self::$FAILED;
        goto seq_2;
      }
      $r6 = true;
      seq_2:
      if ($r6!==self::$FAILED) {
        $this->savedPos = $p7;
        $r6 = $this->a157($r4, $r10);
        $r5[] = $r6;
      } else {
        break;
      }
      // free $p8
    }
    // rest <- $r5
    // free $r6
    $r6 = [];
    for (;;) {
      // start seq_3
      $p8 = $this->currPos;
      if (($this->input[$this->currPos] ?? null) === ";") {
        $this->currPos++;
        $r12 = ";";
      } else {
        if (!$silence) {$this->fail(30);}
        $r12 = self::$FAILED;
        $r11 = self::$FAILED;
        goto seq_3;
      }
      $p13 = $this->currPos;
      $r14 = $this->discardbogus_lang_variant_option($silence, $boolParams, $param_templatedepth, $param_preproc, $param_th);
      if ($r14!==self::$FAILED) {
        $r14 = substr($this->input, $p13, $this->currPos - $p13);
      } else {
        $r14 = self::$FAILED;
        $this->currPos = $p8;
        $r11 = self::$FAILED;
        goto seq_3;
      }
      // free $p13
      $r11 = [$r12,$r14];
      seq_3:
      if ($r11!==self::$FAILED) {
        $r6[] = $r11;
      } else {
        break;
      }
      // free $p8
    }
    // tr <- $r6
    // free $r11
    $r1 = true;
    seq_1:
    if ($r1!==self::$FAILED) {
      $this->savedPos = $p2;
      $r1 = $this->a158($r4, $r5, $r6);
      goto choice_1;
    }
    // free $p3
    $p3 = $this->currPos;
    $r11 = $this->parselang_variant_text($silence, $boolParams, $param_templatedepth, $param_preproc, $param_th);
    // lvtext <- $r11
    $r1 = $r11;
    if ($r1!==self::$FAILED) {
      $this->savedPos = $p3;
      $r1 = $this->a159($r11);
    }
    choice_1:
    $cached = ['nextPos' => $this->currPos, 'result' => $r1];
      if ($saved_preproc !== $param_preproc) $cached["\$preproc"] = $param_preproc;
      if ($saved_th !== $param_th) $cached["\$th"] = $param_th;
    $this->cache[$bucket][$key] = $cached;
    return $r1;
  }
  private function discardcomment($silence) {
    $key = 323;
    $bucket = $this->currPos;
    $cached = $this->cache[$bucket][$key] ?? null;
    if ($cached) {
      $this->currPos = $cached['nextPos'];
  
      return $cached['result'];
    }
  
    $p2 = $this->currPos;
    // start seq_1
    $p3 = $this->currPos;
    if ($this->currPos >= $this->inputLength ? false : substr_compare($this->input, "<!--", $this->currPos, 4, false) === 0) {
      $r4 = "<!--";
      $this->currPos += 4;
    } else {
      if (!$silence) {$this->fail(11);}
      $r4 = self::$FAILED;
      $r1 = self::$FAILED;
      goto seq_1;
    }
    $p6 = $this->currPos;
    for (;;) {
      // start seq_2
      $p8 = $this->currPos;
      $p9 = $this->currPos;
      if ($this->currPos >= $this->inputLength ? false : substr_compare($this->input, "-->", $this->currPos, 3, false) === 0) {
        $r10 = "-->";
        $this->currPos += 3;
      } else {
        $r10 = self::$FAILED;
      }
      if ($r10 === self::$FAILED) {
        $r10 = false;
      } else {
        $r10 = self::$FAILED;
        $this->currPos = $p9;
        $r7 = self::$FAILED;
        goto seq_2;
      }
      // free $p9
      if ($this->currPos < $this->inputLength) {
        $r11 = self::consumeChar($this->input, $this->currPos);;
      } else {
        $r11 = self::$FAILED;
        if (!$silence) {$this->fail(7);}
        $this->currPos = $p8;
        $r7 = self::$FAILED;
        goto seq_2;
      }
      $r7 = true;
      seq_2:
      if ($r7===self::$FAILED) {
        break;
      }
      // free $p8
    }
    // free $r7
    $r5 = true;
    // c <- $r5
    if ($r5!==self::$FAILED) {
      $r5 = substr($this->input, $p6, $this->currPos - $p6);
    } else {
      $r5 = self::$FAILED;
      $this->currPos = $p3;
      $r1 = self::$FAILED;
      goto seq_1;
    }
    // free $p6
    // start choice_1
    if ($this->currPos >= $this->inputLength ? false : substr_compare($this->input, "-->", $this->currPos, 3, false) === 0) {
      $r7 = "-->";
      $this->currPos += 3;
      goto choice_1;
    } else {
      if (!$silence) {$this->fail(12);}
      $r7 = self::$FAILED;
    }
    $r7 = $this->discardeof($silence);
    choice_1:
    if ($r7===self::$FAILED) {
      $this->currPos = $p3;
      $r1 = self::$FAILED;
      goto seq_1;
    }
    $r1 = true;
    seq_1:
    if ($r1!==self::$FAILED) {
      $this->savedPos = $p2;
      $r1 = $this->a23($r5);
    }
    // free $p3
    $cached = ['nextPos' => $this->currPos, 'result' => $r1];
  
    $this->cache[$bucket][$key] = $cached;
    return $r1;
  }
  private function discardinclude_limits($silence, $boolParams, $param_templatedepth, &$param_preproc, &$param_th) {
    $key = json_encode([531, $boolParams & 0x33ae, $param_templatedepth, $param_preproc, $param_th]);
    $bucket = $this->currPos;
    $cached = $this->cache[$bucket][$key] ?? null;
    if ($cached) {
      $this->currPos = $cached['nextPos'];
        if (array_key_exists("\$preproc", $cached)) $param_preproc = $cached["\$preproc"];
        if (array_key_exists("\$th", $cached)) $param_th = $cached["\$th"];
      return $cached['result'];
    }
        $saved_preproc=$param_preproc;
        $saved_th=$param_th;
    $p2 = $this->currPos;
    // start seq_1
    $p3 = $this->currPos;
    $p4 = $this->currPos;
    // start seq_2
    $p6 = $this->currPos;
    if (($this->input[$this->currPos] ?? null) === "<") {
      $this->currPos++;
      $r7 = "<";
    } else {
      $r7 = self::$FAILED;
      $r5 = self::$FAILED;
      goto seq_2;
    }
    if (($this->input[$this->currPos] ?? null) === "/") {
      $this->currPos++;
      $r8 = "/";
    } else {
      $r8 = self::$FAILED;
      $r8 = null;
    }
    // start choice_1
    if ($this->currPos >= $this->inputLength ? false : substr_compare($this->input, "includeonly", $this->currPos, 11, true) === 0) {
      $r9 = substr($this->input, $this->currPos, 11);
      $this->currPos += 11;
      goto choice_1;
    } else {
      $r9 = self::$FAILED;
    }
    if ($this->currPos >= $this->inputLength ? false : substr_compare($this->input, "noinclude", $this->currPos, 9, true) === 0) {
      $r9 = substr($this->input, $this->currPos, 9);
      $this->currPos += 9;
      goto choice_1;
    } else {
      $r9 = self::$FAILED;
    }
    if ($this->currPos >= $this->inputLength ? false : substr_compare($this->input, "onlyinclude", $this->currPos, 11, true) === 0) {
      $r9 = substr($this->input, $this->currPos, 11);
      $this->currPos += 11;
    } else {
      $r9 = self::$FAILED;
    }
    choice_1:
    // n <- $r9
    if ($r9===self::$FAILED) {
      $this->currPos = $p6;
      $r5 = self::$FAILED;
      goto seq_2;
    }
    $r5 = true;
    seq_2:
    if ($r5!==self::$FAILED) {
      $r5 = false;
      $this->currPos = $p4;
    } else {
      $r1 = self::$FAILED;
      goto seq_1;
    }
    // free $p6
    // free $p4
    $r10 = $this->parsexmlish_tag($silence, $boolParams, $param_templatedepth, $param_preproc, $param_th);
    // il <- $r10
    if ($r10===self::$FAILED) {
      $this->currPos = $p3;
      $r1 = self::$FAILED;
      goto seq_1;
    }
    $this->savedPos = $this->currPos;
    $r11 = $this->a76($r10, /*sol_il*/($boolParams & 0x2000) !== 0);
    if ($r11) {
      $r11 = false;
    } else {
      $r11 = self::$FAILED;
      $this->currPos = $p3;
      $r1 = self::$FAILED;
      goto seq_1;
    }
    $r1 = true;
    seq_1:
    if ($r1!==self::$FAILED) {
      $this->savedPos = $p2;
      $r1 = $this->a77($r10, /*sol_il*/($boolParams & 0x2000) !== 0);
    }
    // free $p3
    $cached = ['nextPos' => $this->currPos, 'result' => $r1];
      if ($saved_preproc !== $param_preproc) $cached["\$preproc"] = $param_preproc;
      if ($saved_th !== $param_th) $cached["\$th"] = $param_th;
    $this->cache[$bucket][$key] = $cached;
    return $r1;
  }
  private function parsetable_heading_tags_parameterized($silence, $boolParams, $param_templatedepth, &$param_preproc, &$param_th) {
    $key = json_encode([482, $boolParams & 0x3bfe, $param_templatedepth, $param_preproc, $param_th]);
    $bucket = $this->currPos;
    $cached = $this->cache[$bucket][$key] ?? null;
    if ($cached) {
      $this->currPos = $cached['nextPos'];
        if (array_key_exists("\$preproc", $cached)) $param_preproc = $cached["\$preproc"];
        if (array_key_exists("\$th", $cached)) $param_th = $cached["\$th"];
      return $cached['result'];
    }
        $saved_preproc=$param_preproc;
        $saved_th=$param_th;
    $p2 = $this->currPos;
    // start seq_1
    $p3 = $this->currPos;
    if (($this->input[$this->currPos] ?? null) === "!") {
      $this->currPos++;
      $r4 = "!";
    } else {
      if (!$silence) {$this->fail(77);}
      $r4 = self::$FAILED;
      $r1 = self::$FAILED;
      goto seq_1;
    }
    $r5 = $this->parsetable_heading_tag($silence, $boolParams, $param_templatedepth, $param_preproc, $param_th);
    // thTag <- $r5
    if ($r5===self::$FAILED) {
      $this->currPos = $p3;
      $r1 = self::$FAILED;
      goto seq_1;
    }
    $r6 = [];
    for (;;) {
      $p8 = $this->currPos;
      // start seq_2
      $p9 = $this->currPos;
      // start choice_1
      if ($this->currPos >= $this->inputLength ? false : substr_compare($this->input, "!!", $this->currPos, 2, false) === 0) {
        $r10 = "!!";
        $this->currPos += 2;
        goto choice_1;
      } else {
        if (!$silence) {$this->fail(78);}
        $r10 = self::$FAILED;
      }
      $r10 = $this->parsepipe_pipe($silence);
      choice_1:
      // pp <- $r10
      if ($r10===self::$FAILED) {
        $r7 = self::$FAILED;
        goto seq_2;
      }
      $r11 = $this->parsetable_heading_tag($silence, $boolParams, $param_templatedepth, $param_preproc, $param_th);
      // tht <- $r11
      if ($r11===self::$FAILED) {
        $this->currPos = $p9;
        $r7 = self::$FAILED;
        goto seq_2;
      }
      $r7 = true;
      seq_2:
      if ($r7!==self::$FAILED) {
        $this->savedPos = $p8;
        $r7 = $this->a160($r5, $r10, $r11);
        $r6[] = $r7;
      } else {
        break;
      }
      // free $p9
    }
    // thTags <- $r6
    // free $r7
    $r1 = true;
    seq_1:
    if ($r1!==self::$FAILED) {
      $this->savedPos = $p2;
      $r1 = $this->a161($r5, $r6);
    }
    // free $p3
    $cached = ['nextPos' => $this->currPos, 'result' => $r1];
      if ($saved_preproc !== $param_preproc) $cached["\$preproc"] = $param_preproc;
      if ($saved_th !== $param_th) $cached["\$th"] = $param_th;
    $this->cache[$bucket][$key] = $cached;
    return $r1;
  }
  private function parsetable_data_tag($silence, $boolParams, $param_templatedepth, &$param_preproc, &$param_th) {
    $key = json_encode([478, $boolParams & 0x3bfe, $param_templatedepth, $param_preproc, $param_th]);
    $bucket = $this->currPos;
    $cached = $this->cache[$bucket][$key] ?? null;
    if ($cached) {
      $this->currPos = $cached['nextPos'];
        if (array_key_exists("\$preproc", $cached)) $param_preproc = $cached["\$preproc"];
        if (array_key_exists("\$th", $cached)) $param_th = $cached["\$th"];
      return $cached['result'];
    }
        $saved_preproc=$param_preproc;
        $saved_th=$param_th;
    $p2 = $this->currPos;
    // start seq_1
    $p3 = $this->currPos;
    $p4 = $this->currPos;
    if (($this->input[$this->currPos] ?? null) === "}") {
      $this->currPos++;
      $r5 = "}";
    } else {
      $r5 = self::$FAILED;
    }
    if ($r5 === self::$FAILED) {
      $r5 = false;
    } else {
      $r5 = self::$FAILED;
      $this->currPos = $p4;
      $r1 = self::$FAILED;
      goto seq_1;
    }
    // free $p4
    $r6 = $this->parserow_syntax_table_args($silence, $boolParams, $param_templatedepth, $param_preproc, $param_th);
    if ($r6===self::$FAILED) {
      $r6 = null;
    }
    // arg <- $r6
    $p4 = $this->currPos;
    $r7 = '';
    // tagEndPos <- $r7
    if ($r7!==self::$FAILED) {
      $this->savedPos = $p4;
      $r7 = $this->a162($r6);
    } else {
      $this->currPos = $p3;
      $r1 = self::$FAILED;
      goto seq_1;
    }
    $r8 = [];
    for (;;) {
      $r9 = $this->parsenested_block_in_table($silence, $boolParams, $param_templatedepth, $param_preproc, $param_th);
      if ($r9!==self::$FAILED) {
        $r8[] = $r9;
      } else {
        break;
      }
    }
    // td <- $r8
    // free $r9
    $r1 = true;
    seq_1:
    if ($r1!==self::$FAILED) {
      $this->savedPos = $p2;
      $r1 = $this->a163($r6, $r7, $r8);
    }
    // free $p3
    $cached = ['nextPos' => $this->currPos, 'result' => $r1];
      if ($saved_preproc !== $param_preproc) $cached["\$preproc"] = $param_preproc;
      if ($saved_th !== $param_th) $cached["\$th"] = $param_th;
    $this->cache[$bucket][$key] = $cached;
    return $r1;
  }
  private function parsetds($silence, $boolParams, $param_templatedepth, &$param_preproc, &$param_th) {
    $key = json_encode([474, $boolParams & 0x3bfe, $param_templatedepth, $param_preproc, $param_th]);
    $bucket = $this->currPos;
    $cached = $this->cache[$bucket][$key] ?? null;
    if ($cached) {
      $this->currPos = $cached['nextPos'];
        if (array_key_exists("\$preproc", $cached)) $param_preproc = $cached["\$preproc"];
        if (array_key_exists("\$th", $cached)) $param_th = $cached["\$th"];
      return $cached['result'];
    }
        $saved_preproc=$param_preproc;
        $saved_th=$param_th;
    $r1 = [];
    for (;;) {
      $p3 = $this->currPos;
      // start seq_1
      $p4 = $this->currPos;
      // start choice_1
      $r5 = $this->parsepipe_pipe($silence);
      if ($r5!==self::$FAILED) {
        goto choice_1;
      }
      $p6 = $this->currPos;
      // start seq_2
      $p7 = $this->currPos;
      $r8 = $this->parsepipe($silence);
      // p <- $r8
      if ($r8===self::$FAILED) {
        $r5 = self::$FAILED;
        goto seq_2;
      }
      $p9 = $this->currPos;
      $r10 = $this->discardrow_syntax_table_args(true, $boolParams, $param_templatedepth, $param_preproc, $param_th);
      if ($r10!==self::$FAILED) {
        $r10 = false;
        $this->currPos = $p9;
      } else {
        $this->currPos = $p7;
        $r5 = self::$FAILED;
        goto seq_2;
      }
      // free $p9
      $r5 = true;
      seq_2:
      if ($r5!==self::$FAILED) {
        $this->savedPos = $p6;
        $r5 = $this->a25($r8);
      }
      // free $p7
      choice_1:
      // pp <- $r5
      if ($r5===self::$FAILED) {
        $r2 = self::$FAILED;
        goto seq_1;
      }
      $r11 = $this->parsetable_data_tag($silence, $boolParams, $param_templatedepth, $param_preproc, $param_th);
      // tdt <- $r11
      if ($r11===self::$FAILED) {
        $this->currPos = $p4;
        $r2 = self::$FAILED;
        goto seq_1;
      }
      $r2 = true;
      seq_1:
      if ($r2!==self::$FAILED) {
        $this->savedPos = $p3;
        $r2 = $this->a164($r5, $r11);
        $r1[] = $r2;
      } else {
        break;
      }
      // free $p4
    }
    // free $r2
    $cached = ['nextPos' => $this->currPos, 'result' => $r1];
      if ($saved_preproc !== $param_preproc) $cached["\$preproc"] = $param_preproc;
      if ($saved_th !== $param_th) $cached["\$th"] = $param_th;
    $this->cache[$bucket][$key] = $cached;
    return $r1;
  }
  private function parsenested_block_in_table($silence, $boolParams, $param_templatedepth, &$param_preproc, &$param_th) {
    $key = json_encode([302, $boolParams & 0x1bfe, $param_templatedepth, $param_preproc, $param_th]);
    $bucket = $this->currPos;
    $cached = $this->cache[$bucket][$key] ?? null;
    if ($cached) {
      $this->currPos = $cached['nextPos'];
        if (array_key_exists("\$preproc", $cached)) $param_preproc = $cached["\$preproc"];
        if (array_key_exists("\$th", $cached)) $param_th = $cached["\$th"];
      return $cached['result'];
    }
        $saved_preproc=$param_preproc;
        $saved_th=$param_th;
    $p2 = $this->currPos;
    // start seq_1
    $p3 = $this->currPos;
    $p4 = $this->currPos;
    // start seq_2
    $p6 = $this->currPos;
    $r7 = $this->discardsol(true, $boolParams, $param_templatedepth, $param_preproc, $param_th);
    if ($r7===self::$FAILED) {
      $r5 = self::$FAILED;
      goto seq_2;
    }
    // start seq_3
    $p9 = $this->currPos;
    for (;;) {
      $r11 = $this->discardspace(true);
      if ($r11===self::$FAILED) {
        break;
      }
    }
    // free $r11
    $r10 = true;
    if ($r10===self::$FAILED) {
      $r8 = self::$FAILED;
      goto seq_3;
    }
    // free $r10
    $r10 = $this->discardsol(true, $boolParams, $param_templatedepth, $param_preproc, $param_th);
    if ($r10===self::$FAILED) {
      $this->currPos = $p9;
      $r8 = self::$FAILED;
      goto seq_3;
    }
    $r8 = true;
    seq_3:
    if ($r8===self::$FAILED) {
      $r8 = null;
    }
    // free $p9
    for (;;) {
      $r12 = $this->discardspace(true);
      if ($r12===self::$FAILED) {
        break;
      }
    }
    // free $r12
    $r11 = true;
    if ($r11===self::$FAILED) {
      $this->currPos = $p6;
      $r5 = self::$FAILED;
      goto seq_2;
    }
    // free $r11
    // start choice_1
    $r11 = $this->discardpipe(true);
    if ($r11!==self::$FAILED) {
      goto choice_1;
    }
    if (($this->input[$this->currPos] ?? null) === "!") {
      $this->currPos++;
      $r11 = "!";
    } else {
      $r11 = self::$FAILED;
    }
    choice_1:
    if ($r11===self::$FAILED) {
      $this->currPos = $p6;
      $r5 = self::$FAILED;
      goto seq_2;
    }
    $r5 = true;
    seq_2:
    // free $p6
    if ($r5 === self::$FAILED) {
      $r5 = false;
    } else {
      $r5 = self::$FAILED;
      $this->currPos = $p4;
      $r1 = self::$FAILED;
      goto seq_1;
    }
    // free $p4
    $r12 = $this->parsenested_block($silence, $boolParams | 0x1, $param_templatedepth, $param_preproc, $param_th);
    // b <- $r12
    if ($r12===self::$FAILED) {
      $this->currPos = $p3;
      $r1 = self::$FAILED;
      goto seq_1;
    }
    $r1 = true;
    seq_1:
    if ($r1!==self::$FAILED) {
      $this->savedPos = $p2;
      $r1 = $this->a165($r12);
    }
    // free $p3
    $cached = ['nextPos' => $this->currPos, 'result' => $r1];
      if ($saved_preproc !== $param_preproc) $cached["\$preproc"] = $param_preproc;
      if ($saved_th !== $param_th) $cached["\$th"] = $param_th;
    $this->cache[$bucket][$key] = $cached;
    return $r1;
  }
  private function parsenested_block($silence, $boolParams, $param_templatedepth, &$param_preproc, &$param_th) {
    $key = json_encode([300, $boolParams & 0x1bfe, $param_templatedepth, $param_preproc, $param_th]);
    $bucket = $this->currPos;
    $cached = $this->cache[$bucket][$key] ?? null;
    if ($cached) {
      $this->currPos = $cached['nextPos'];
        if (array_key_exists("\$preproc", $cached)) $param_preproc = $cached["\$preproc"];
        if (array_key_exists("\$th", $cached)) $param_th = $cached["\$th"];
      return $cached['result'];
    }
        $saved_preproc=$param_preproc;
        $saved_th=$param_th;
    $p2 = $this->currPos;
    // start seq_1
    $p3 = $this->currPos;
    $p4 = $this->currPos;
    $r5 = $this->discardinline_breaks(true, $boolParams, $param_templatedepth, $param_preproc, $param_th);
    if ($r5 === self::$FAILED) {
      $r5 = false;
    } else {
      $r5 = self::$FAILED;
      $this->currPos = $p4;
      $r1 = self::$FAILED;
      goto seq_1;
    }
    // free $p4
    $r6 = $this->parseblock($silence, $boolParams, $param_templatedepth, $param_th, $param_preproc);
    // b <- $r6
    if ($r6===self::$FAILED) {
      $this->currPos = $p3;
      $r1 = self::$FAILED;
      goto seq_1;
    }
    $r1 = true;
    seq_1:
    if ($r1!==self::$FAILED) {
      $this->savedPos = $p2;
      $r1 = $this->a12($r6);
    }
    // free $p3
    $cached = ['nextPos' => $this->currPos, 'result' => $r1];
      if ($saved_preproc !== $param_preproc) $cached["\$preproc"] = $param_preproc;
      if ($saved_th !== $param_th) $cached["\$th"] = $param_th;
    $this->cache[$bucket][$key] = $cached;
    return $r1;
  }
  private function parselink_text_parameterized($silence, $boolParams, $param_templatedepth, &$param_preproc, &$param_th) {
    $key = json_encode([410, $boolParams & 0x3bff, $param_templatedepth, $param_preproc, $param_th]);
    $bucket = $this->currPos;
    $cached = $this->cache[$bucket][$key] ?? null;
    if ($cached) {
      $this->currPos = $cached['nextPos'];
        if (array_key_exists("\$preproc", $cached)) $param_preproc = $cached["\$preproc"];
        if (array_key_exists("\$th", $cached)) $param_th = $cached["\$th"];
      return $cached['result'];
    }
        $saved_preproc=$param_preproc;
        $saved_th=$param_th;
    $p2 = $this->currPos;
    $r3 = [];
    for (;;) {
      // start choice_1
      // start seq_1
      $p5 = $this->currPos;
      $r6 = $this->parsesol($silence, $boolParams, $param_templatedepth, $param_preproc, $param_th);
      if ($r6===self::$FAILED) {
        $r4 = self::$FAILED;
        goto seq_1;
      }
      // start choice_2
      $r7 = $this->parseheading($silence, $boolParams, $param_templatedepth, $param_preproc, $param_th);
      if ($r7!==self::$FAILED) {
        goto choice_2;
      }
      $r7 = $this->parsehr($silence, $boolParams, $param_templatedepth, $param_preproc, $param_th);
      if ($r7!==self::$FAILED) {
        goto choice_2;
      }
      $r7 = $this->parsefull_table_in_link_caption($silence, $boolParams, $param_templatedepth, $param_preproc, $param_th);
      choice_2:
      if ($r7===self::$FAILED) {
        $this->currPos = $p5;
        $r4 = self::$FAILED;
        goto seq_1;
      }
      $r4 = [$r6,$r7];
      seq_1:
      if ($r4!==self::$FAILED) {
        goto choice_1;
      }
      // free $p5
      $r4 = $this->parseurltext($silence, $boolParams, $param_templatedepth, $param_preproc, $param_th);
      if ($r4!==self::$FAILED) {
        goto choice_1;
      }
      $p5 = $this->currPos;
      // start seq_2
      $p8 = $this->currPos;
      $p9 = $this->currPos;
      $r10 = $this->discardinline_breaks(true, $boolParams, $param_templatedepth, $param_preproc, $param_th);
      if ($r10 === self::$FAILED) {
        $r10 = false;
      } else {
        $r10 = self::$FAILED;
        $this->currPos = $p9;
        $r4 = self::$FAILED;
        goto seq_2;
      }
      // free $p9
      // start choice_3
      $r11 = $this->parseinline_element($silence, $boolParams, $param_templatedepth, $param_preproc, $param_th);
      if ($r11!==self::$FAILED) {
        goto choice_3;
      }
      // start seq_3
      $p9 = $this->currPos;
      if (($this->input[$this->currPos] ?? null) === "[") {
        $this->currPos++;
        $r12 = "[";
      } else {
        if (!$silence) {$this->fail(19);}
        $r12 = self::$FAILED;
        $r11 = self::$FAILED;
        goto seq_3;
      }
      $r13 = [];
      for (;;) {
        $r14 = $this->parsetext_char($silence);
        if ($r14!==self::$FAILED) {
          $r13[] = $r14;
        } else {
          break;
        }
      }
      if (count($r13) === 0) {
        $r13 = self::$FAILED;
      }
      if ($r13===self::$FAILED) {
        $this->currPos = $p9;
        $r11 = self::$FAILED;
        goto seq_3;
      }
      // free $r14
      if (($this->input[$this->currPos] ?? null) === "]") {
        $this->currPos++;
        $r14 = "]";
      } else {
        if (!$silence) {$this->fail(21);}
        $r14 = self::$FAILED;
        $this->currPos = $p9;
        $r11 = self::$FAILED;
        goto seq_3;
      }
      $p15 = $this->currPos;
      $p17 = $this->currPos;
      // start choice_4
      $p18 = $this->currPos;
      if (($this->input[$this->currPos] ?? null) === "]") {
        $this->currPos++;
        $r16 = "]";
      } else {
        $r16 = self::$FAILED;
      }
      if ($r16 === self::$FAILED) {
        $r16 = false;
        goto choice_4;
      } else {
        $r16 = self::$FAILED;
        $this->currPos = $p18;
      }
      // free $p18
      if ($this->currPos >= $this->inputLength ? false : substr_compare($this->input, "]]", $this->currPos, 2, false) === 0) {
        $r16 = "]]";
        $this->currPos += 2;
      } else {
        $r16 = self::$FAILED;
      }
      choice_4:
      if ($r16!==self::$FAILED) {
        $r16 = false;
        $this->currPos = $p17;
        $r16 = substr($this->input, $p15, $this->currPos - $p15);
      } else {
        $r16 = self::$FAILED;
        $this->currPos = $p9;
        $r11 = self::$FAILED;
        goto seq_3;
      }
      // free $p17
      // free $p15
      $r11 = [$r12,$r13,$r14,$r16];
      seq_3:
      if ($r11!==self::$FAILED) {
        goto choice_3;
      }
      // free $p9
      if ($this->currPos < $this->inputLength) {
        $r11 = self::consumeChar($this->input, $this->currPos);;
      } else {
        $r11 = self::$FAILED;
        if (!$silence) {$this->fail(7);}
      }
      choice_3:
      // r <- $r11
      if ($r11===self::$FAILED) {
        $this->currPos = $p8;
        $r4 = self::$FAILED;
        goto seq_2;
      }
      $r4 = true;
      seq_2:
      if ($r4!==self::$FAILED) {
        $this->savedPos = $p5;
        $r4 = $this->a20($r11);
      }
      // free $p8
      choice_1:
      if ($r4!==self::$FAILED) {
        $r3[] = $r4;
      } else {
        break;
      }
    }
    if (count($r3) === 0) {
      $r3 = self::$FAILED;
    }
    // c <- $r3
    // free $r4
    $r1 = $r3;
    if ($r1!==self::$FAILED) {
      $this->savedPos = $p2;
      $r1 = $this->a40($r3);
    }
    $cached = ['nextPos' => $this->currPos, 'result' => $r1];
      if ($saved_preproc !== $param_preproc) $cached["\$preproc"] = $param_preproc;
      if ($saved_th !== $param_th) $cached["\$th"] = $param_th;
    $this->cache[$bucket][$key] = $cached;
    return $r1;
  }
  private function parselang_variant_flags($silence, $boolParams, $param_templatedepth, &$param_preproc, &$param_th) {
    $key = json_encode([380, $boolParams & 0x1bae, $param_templatedepth, $param_preproc, $param_th]);
    $bucket = $this->currPos;
    $cached = $this->cache[$bucket][$key] ?? null;
    if ($cached) {
      $this->currPos = $cached['nextPos'];
        if (array_key_exists("\$preproc", $cached)) $param_preproc = $cached["\$preproc"];
        if (array_key_exists("\$th", $cached)) $param_th = $cached["\$th"];
      return $cached['result'];
    }
        $saved_preproc=$param_preproc;
        $saved_th=$param_th;
    // start choice_1
    $p2 = $this->currPos;
    // start seq_1
    $p3 = $this->currPos;
    $p5 = $this->currPos;
    for (;;) {
      $r6 = $this->discardspace_or_newline($silence);
      if ($r6===self::$FAILED) {
        break;
      }
    }
    // free $r6
    $r4 = true;
    // sp1 <- $r4
    if ($r4!==self::$FAILED) {
      $r4 = substr($this->input, $p5, $this->currPos - $p5);
    } else {
      $r4 = self::$FAILED;
      $r1 = self::$FAILED;
      goto seq_1;
    }
    // free $p5
    $r6 = $this->parselang_variant_flag($silence, $boolParams, $param_templatedepth, $param_preproc, $param_th);
    // f <- $r6
    if ($r6===self::$FAILED) {
      $this->currPos = $p3;
      $r1 = self::$FAILED;
      goto seq_1;
    }
    $p5 = $this->currPos;
    for (;;) {
      $r8 = $this->discardspace_or_newline($silence);
      if ($r8===self::$FAILED) {
        break;
      }
    }
    // free $r8
    $r7 = true;
    // sp2 <- $r7
    if ($r7!==self::$FAILED) {
      $r7 = substr($this->input, $p5, $this->currPos - $p5);
    } else {
      $r7 = self::$FAILED;
      $this->currPos = $p3;
      $r1 = self::$FAILED;
      goto seq_1;
    }
    // free $p5
    // start seq_2
    $p5 = $this->currPos;
    if (($this->input[$this->currPos] ?? null) === ";") {
      $this->currPos++;
      $r9 = ";";
    } else {
      if (!$silence) {$this->fail(30);}
      $r9 = self::$FAILED;
      $r8 = self::$FAILED;
      goto seq_2;
    }
    $r10 = $this->parselang_variant_flags($silence, $boolParams, $param_templatedepth, $param_preproc, $param_th);
    if ($r10===self::$FAILED) {
      $r10 = null;
    }
    $r8 = [$r9,$r10];
    seq_2:
    if ($r8===self::$FAILED) {
      $r8 = null;
    }
    // free $p5
    // more <- $r8
    $r1 = true;
    seq_1:
    if ($r1!==self::$FAILED) {
      $this->savedPos = $p2;
      $r1 = $this->a166($r4, $r6, $r7, $r8);
      goto choice_1;
    }
    // free $p3
    $p3 = $this->currPos;
    $p5 = $this->currPos;
    for (;;) {
      $r12 = $this->discardspace_or_newline($silence);
      if ($r12===self::$FAILED) {
        break;
      }
    }
    // free $r12
    $r11 = true;
    // sp <- $r11
    if ($r11!==self::$FAILED) {
      $r11 = substr($this->input, $p5, $this->currPos - $p5);
    } else {
      $r11 = self::$FAILED;
    }
    // free $p5
    $r1 = $r11;
    if ($r1!==self::$FAILED) {
      $this->savedPos = $p3;
      $r1 = $this->a167($r11);
    }
    choice_1:
    $cached = ['nextPos' => $this->currPos, 'result' => $r1];
      if ($saved_preproc !== $param_preproc) $cached["\$preproc"] = $param_preproc;
      if ($saved_th !== $param_th) $cached["\$th"] = $param_th;
    $this->cache[$bucket][$key] = $cached;
    return $r1;
  }
  private function parselang_variant_option($silence, $boolParams, $param_templatedepth, &$param_preproc, &$param_th) {
    $key = json_encode([390, $boolParams & 0x1bfe, $param_templatedepth, $param_preproc, $param_th]);
    $bucket = $this->currPos;
    $cached = $this->cache[$bucket][$key] ?? null;
    if ($cached) {
      $this->currPos = $cached['nextPos'];
        if (array_key_exists("\$preproc", $cached)) $param_preproc = $cached["\$preproc"];
        if (array_key_exists("\$th", $cached)) $param_th = $cached["\$th"];
      return $cached['result'];
    }
        $saved_preproc=$param_preproc;
        $saved_th=$param_th;
    // start choice_1
    $p2 = $this->currPos;
    // start seq_1
    $p3 = $this->currPos;
    $p5 = $this->currPos;
    for (;;) {
      $r6 = $this->discardspace_or_newline($silence);
      if ($r6===self::$FAILED) {
        break;
      }
    }
    // free $r6
    $r4 = true;
    // sp1 <- $r4
    if ($r4!==self::$FAILED) {
      $r4 = substr($this->input, $p5, $this->currPos - $p5);
    } else {
      $r4 = self::$FAILED;
      $r1 = self::$FAILED;
      goto seq_1;
    }
    // free $p5
    $r6 = $this->parselang_variant_name($silence, $boolParams, $param_templatedepth, $param_preproc, $param_th);
    // lang <- $r6
    if ($r6===self::$FAILED) {
      $this->currPos = $p3;
      $r1 = self::$FAILED;
      goto seq_1;
    }
    $p5 = $this->currPos;
    for (;;) {
      $r8 = $this->discardspace_or_newline($silence);
      if ($r8===self::$FAILED) {
        break;
      }
    }
    // free $r8
    $r7 = true;
    // sp2 <- $r7
    if ($r7!==self::$FAILED) {
      $r7 = substr($this->input, $p5, $this->currPos - $p5);
    } else {
      $r7 = self::$FAILED;
      $this->currPos = $p3;
      $r1 = self::$FAILED;
      goto seq_1;
    }
    // free $p5
    if (($this->input[$this->currPos] ?? null) === ":") {
      $this->currPos++;
      $r8 = ":";
    } else {
      if (!$silence) {$this->fail(18);}
      $r8 = self::$FAILED;
      $this->currPos = $p3;
      $r1 = self::$FAILED;
      goto seq_1;
    }
    $p5 = $this->currPos;
    for (;;) {
      $r10 = $this->discardspace_or_newline($silence);
      if ($r10===self::$FAILED) {
        break;
      }
    }
    // free $r10
    $r9 = true;
    // sp3 <- $r9
    if ($r9!==self::$FAILED) {
      $r9 = substr($this->input, $p5, $this->currPos - $p5);
    } else {
      $r9 = self::$FAILED;
      $this->currPos = $p3;
      $r1 = self::$FAILED;
      goto seq_1;
    }
    // free $p5
    // start choice_2
    $r10 = $this->parselang_variant_nowiki($silence, $boolParams, $param_templatedepth, $param_preproc, $param_th);
    if ($r10!==self::$FAILED) {
      goto choice_2;
    }
    $r10 = $this->parselang_variant_text_no_semi($silence, $boolParams, $param_templatedepth, $param_preproc, $param_th);
    choice_2:
    // lvtext <- $r10
    if ($r10===self::$FAILED) {
      $this->currPos = $p3;
      $r1 = self::$FAILED;
      goto seq_1;
    }
    $r1 = true;
    seq_1:
    if ($r1!==self::$FAILED) {
      $this->savedPos = $p2;
      $r1 = $this->a168($r4, $r6, $r7, $r9, $r10);
      goto choice_1;
    }
    // free $p3
    $p3 = $this->currPos;
    // start seq_2
    $p5 = $this->currPos;
    $p12 = $this->currPos;
    for (;;) {
      $r13 = $this->discardspace_or_newline($silence);
      if ($r13===self::$FAILED) {
        break;
      }
    }
    // free $r13
    $r11 = true;
    // sp1 <- $r11
    if ($r11!==self::$FAILED) {
      $r11 = substr($this->input, $p12, $this->currPos - $p12);
    } else {
      $r11 = self::$FAILED;
      $r1 = self::$FAILED;
      goto seq_2;
    }
    // free $p12
    // start choice_3
    $r13 = $this->parselang_variant_nowiki($silence, $boolParams, $param_templatedepth, $param_preproc, $param_th);
    if ($r13!==self::$FAILED) {
      goto choice_3;
    }
    $r13 = $this->parselang_variant_text_no_semi_or_arrow($silence, $boolParams, $param_templatedepth, $param_preproc, $param_th);
    choice_3:
    // from <- $r13
    if ($r13===self::$FAILED) {
      $this->currPos = $p5;
      $r1 = self::$FAILED;
      goto seq_2;
    }
    if ($this->currPos >= $this->inputLength ? false : substr_compare($this->input, "=>", $this->currPos, 2, false) === 0) {
      $r14 = "=>";
      $this->currPos += 2;
    } else {
      if (!$silence) {$this->fail(79);}
      $r14 = self::$FAILED;
      $this->currPos = $p5;
      $r1 = self::$FAILED;
      goto seq_2;
    }
    $p12 = $this->currPos;
    for (;;) {
      $r16 = $this->discardspace_or_newline($silence);
      if ($r16===self::$FAILED) {
        break;
      }
    }
    // free $r16
    $r15 = true;
    // sp2 <- $r15
    if ($r15!==self::$FAILED) {
      $r15 = substr($this->input, $p12, $this->currPos - $p12);
    } else {
      $r15 = self::$FAILED;
      $this->currPos = $p5;
      $r1 = self::$FAILED;
      goto seq_2;
    }
    // free $p12
    $r16 = $this->parselang_variant_name($silence, $boolParams, $param_templatedepth, $param_preproc, $param_th);
    // lang <- $r16
    if ($r16===self::$FAILED) {
      $this->currPos = $p5;
      $r1 = self::$FAILED;
      goto seq_2;
    }
    $p12 = $this->currPos;
    for (;;) {
      $r18 = $this->discardspace_or_newline($silence);
      if ($r18===self::$FAILED) {
        break;
      }
    }
    // free $r18
    $r17 = true;
    // sp3 <- $r17
    if ($r17!==self::$FAILED) {
      $r17 = substr($this->input, $p12, $this->currPos - $p12);
    } else {
      $r17 = self::$FAILED;
      $this->currPos = $p5;
      $r1 = self::$FAILED;
      goto seq_2;
    }
    // free $p12
    if (($this->input[$this->currPos] ?? null) === ":") {
      $this->currPos++;
      $r18 = ":";
    } else {
      if (!$silence) {$this->fail(18);}
      $r18 = self::$FAILED;
      $this->currPos = $p5;
      $r1 = self::$FAILED;
      goto seq_2;
    }
    $p12 = $this->currPos;
    for (;;) {
      $r20 = $this->discardspace_or_newline($silence);
      if ($r20===self::$FAILED) {
        break;
      }
    }
    // free $r20
    $r19 = true;
    // sp4 <- $r19
    if ($r19!==self::$FAILED) {
      $r19 = substr($this->input, $p12, $this->currPos - $p12);
    } else {
      $r19 = self::$FAILED;
      $this->currPos = $p5;
      $r1 = self::$FAILED;
      goto seq_2;
    }
    // free $p12
    // start choice_4
    $r20 = $this->parselang_variant_nowiki($silence, $boolParams, $param_templatedepth, $param_preproc, $param_th);
    if ($r20!==self::$FAILED) {
      goto choice_4;
    }
    $r20 = $this->parselang_variant_text_no_semi($silence, $boolParams, $param_templatedepth, $param_preproc, $param_th);
    choice_4:
    // to <- $r20
    if ($r20===self::$FAILED) {
      $this->currPos = $p5;
      $r1 = self::$FAILED;
      goto seq_2;
    }
    $r1 = true;
    seq_2:
    if ($r1!==self::$FAILED) {
      $this->savedPos = $p3;
      $r1 = $this->a169($r11, $r13, $r15, $r16, $r17, $r19, $r20);
    }
    // free $p5
    choice_1:
    $cached = ['nextPos' => $this->currPos, 'result' => $r1];
      if ($saved_preproc !== $param_preproc) $cached["\$preproc"] = $param_preproc;
      if ($saved_th !== $param_th) $cached["\$th"] = $param_th;
    $this->cache[$bucket][$key] = $cached;
    return $r1;
  }
  private function discardbogus_lang_variant_option($silence, $boolParams, $param_templatedepth, &$param_preproc, &$param_th) {
    $key = json_encode([389, $boolParams & 0x1bfe, $param_templatedepth, $param_preproc, $param_th]);
    $bucket = $this->currPos;
    $cached = $this->cache[$bucket][$key] ?? null;
    if ($cached) {
      $this->currPos = $cached['nextPos'];
        if (array_key_exists("\$preproc", $cached)) $param_preproc = $cached["\$preproc"];
        if (array_key_exists("\$th", $cached)) $param_th = $cached["\$th"];
      return $cached['result'];
    }
        $saved_preproc=$param_preproc;
        $saved_th=$param_th;
    $r1 = $this->discardlang_variant_text($silence, $boolParams, $param_templatedepth, $param_preproc, $param_th);
    if ($r1===self::$FAILED) {
      $r1 = null;
    }
    $cached = ['nextPos' => $this->currPos, 'result' => $r1];
      if ($saved_preproc !== $param_preproc) $cached["\$preproc"] = $param_preproc;
      if ($saved_th !== $param_th) $cached["\$th"] = $param_th;
    $this->cache[$bucket][$key] = $cached;
    return $r1;
  }
  private function parsetable_heading_tag($silence, $boolParams, $param_templatedepth, &$param_preproc, &$param_th) {
    $key = json_encode([484, $boolParams & 0x3bfe, $param_templatedepth, $param_preproc, $param_th]);
    $bucket = $this->currPos;
    $cached = $this->cache[$bucket][$key] ?? null;
    if ($cached) {
      $this->currPos = $cached['nextPos'];
        if (array_key_exists("\$preproc", $cached)) $param_preproc = $cached["\$preproc"];
        if (array_key_exists("\$th", $cached)) $param_th = $cached["\$th"];
      return $cached['result'];
    }
        $saved_preproc=$param_preproc;
        $saved_th=$param_th;
    $p2 = $this->currPos;
    // start seq_1
    $p3 = $this->currPos;
    $r4 = $this->parserow_syntax_table_args($silence, $boolParams, $param_templatedepth, $param_preproc, $param_th);
    if ($r4===self::$FAILED) {
      $r4 = null;
    }
    // arg <- $r4
    $p6 = $this->currPos;
    $r5 = '';
    // tagEndPos <- $r5
    if ($r5!==self::$FAILED) {
      $this->savedPos = $p6;
      $r5 = $this->a162($r4);
    } else {
      $this->currPos = $p3;
      $r1 = self::$FAILED;
      goto seq_1;
    }
    $r7 = [];
    for (;;) {
      $p9 = $this->currPos;
      // start seq_2
      $p10 = $this->currPos;
      $r11 = $this->parsenested_block_in_table($silence, $boolParams, $param_templatedepth, $param_preproc, $param_th);
      // d <- $r11
      if ($r11===self::$FAILED) {
        $this->currPos = $p10;
        $r8 = self::$FAILED;
        goto seq_2;
      }
      $r8 = true;
      seq_2:
      if ($r8!==self::$FAILED) {
        $this->savedPos = $p9;
        $r8 = $this->a170($r4, $r5, $param_th, $r11);
        $r7[] = $r8;
      } else {
        break;
      }
      // free $p10
    }
    // c <- $r7
    // free $r8
    $r1 = true;
    seq_1:
    if ($r1!==self::$FAILED) {
      $this->savedPos = $p2;
      $r1 = $this->a171($r4, $r5, $r7);
    }
    // free $p3
    $cached = ['nextPos' => $this->currPos, 'result' => $r1];
      if ($saved_preproc !== $param_preproc) $cached["\$preproc"] = $param_preproc;
      if ($saved_th !== $param_th) $cached["\$th"] = $param_th;
    $this->cache[$bucket][$key] = $cached;
    return $r1;
  }
  private function parsepipe_pipe($silence) {
    $key = 566;
    $bucket = $this->currPos;
    $cached = $this->cache[$bucket][$key] ?? null;
    if ($cached) {
      $this->currPos = $cached['nextPos'];
  
      return $cached['result'];
    }
  
    // start choice_1
    if ($this->currPos >= $this->inputLength ? false : substr_compare($this->input, "||", $this->currPos, 2, false) === 0) {
      $r1 = "||";
      $this->currPos += 2;
      goto choice_1;
    } else {
      if (!$silence) {$this->fail(80);}
      $r1 = self::$FAILED;
    }
    if ($this->currPos >= $this->inputLength ? false : substr_compare($this->input, "{{!}}{{!}}", $this->currPos, 10, false) === 0) {
      $r1 = "{{!}}{{!}}";
      $this->currPos += 10;
    } else {
      if (!$silence) {$this->fail(81);}
      $r1 = self::$FAILED;
    }
    choice_1:
    $cached = ['nextPos' => $this->currPos, 'result' => $r1];
  
    $this->cache[$bucket][$key] = $cached;
    return $r1;
  }
  private function discardrow_syntax_table_args($silence, $boolParams, $param_templatedepth, &$param_preproc, &$param_th) {
    $key = json_encode([489, $boolParams & 0x3bbe, $param_templatedepth, $param_preproc, $param_th]);
    $bucket = $this->currPos;
    $cached = $this->cache[$bucket][$key] ?? null;
    if ($cached) {
      $this->currPos = $cached['nextPos'];
        if (array_key_exists("\$preproc", $cached)) $param_preproc = $cached["\$preproc"];
        if (array_key_exists("\$th", $cached)) $param_th = $cached["\$th"];
      return $cached['result'];
    }
        $saved_preproc=$param_preproc;
        $saved_th=$param_th;
    $p2 = $this->currPos;
    // start seq_1
    $p3 = $this->currPos;
    $r4 = $this->parsetable_attributes($silence, $boolParams | 0x40, $param_templatedepth, $param_preproc, $param_th);
    // as <- $r4
    if ($r4===self::$FAILED) {
      $r1 = self::$FAILED;
      goto seq_1;
    }
    $r5 = $this->parseoptional_spaces($silence);
    // s <- $r5
    if ($r5===self::$FAILED) {
      $this->currPos = $p3;
      $r1 = self::$FAILED;
      goto seq_1;
    }
    $r6 = $this->parsepipe($silence);
    // p <- $r6
    if ($r6===self::$FAILED) {
      $this->currPos = $p3;
      $r1 = self::$FAILED;
      goto seq_1;
    }
    $p7 = $this->currPos;
    $r8 = $this->discardpipe(true);
    if ($r8 === self::$FAILED) {
      $r8 = false;
    } else {
      $r8 = self::$FAILED;
      $this->currPos = $p7;
      $this->currPos = $p3;
      $r1 = self::$FAILED;
      goto seq_1;
    }
    // free $p7
    $r1 = true;
    seq_1:
    if ($r1!==self::$FAILED) {
      $this->savedPos = $p2;
      $r1 = $this->a11($r4, $r5, $r6);
    }
    // free $p3
    $cached = ['nextPos' => $this->currPos, 'result' => $r1];
      if ($saved_preproc !== $param_preproc) $cached["\$preproc"] = $param_preproc;
      if ($saved_th !== $param_th) $cached["\$th"] = $param_th;
    $this->cache[$bucket][$key] = $cached;
    return $r1;
  }
  private function parsefull_table_in_link_caption($silence, $boolParams, $param_templatedepth, &$param_preproc, &$param_th) {
    $key = json_encode([460, $boolParams & 0x3bff, $param_templatedepth, $param_preproc, $param_th]);
    $bucket = $this->currPos;
    $cached = $this->cache[$bucket][$key] ?? null;
    if ($cached) {
      $this->currPos = $cached['nextPos'];
        if (array_key_exists("\$preproc", $cached)) $param_preproc = $cached["\$preproc"];
        if (array_key_exists("\$th", $cached)) $param_th = $cached["\$th"];
      return $cached['result'];
    }
        $saved_preproc=$param_preproc;
        $saved_th=$param_th;
    $p2 = $this->currPos;
    // start seq_1
    $p3 = $this->currPos;
    // start choice_1
    $p5 = $this->currPos;
    $r4 = $this->discardinline_breaks(true, $boolParams, $param_templatedepth, $param_preproc, $param_th);
    if ($r4 === self::$FAILED) {
      $r4 = false;
      goto choice_1;
    } else {
      $r4 = self::$FAILED;
      $this->currPos = $p5;
    }
    // free $p5
    $p5 = $this->currPos;
    if ($this->currPos >= $this->inputLength ? false : substr_compare($this->input, "{{!}}", $this->currPos, 5, false) === 0) {
      $r4 = "{{!}}";
      $this->currPos += 5;
      $r4 = false;
      $this->currPos = $p5;
    } else {
      $r4 = self::$FAILED;
    }
    // free $p5
    choice_1:
    if ($r4===self::$FAILED) {
      $r1 = self::$FAILED;
      goto seq_1;
    }
    $r6 = $this->parsefull_table_in_link_caption_parameterized($silence, ($boolParams & ~0x200) | 0x10, $param_templatedepth, $param_preproc, $param_th);
    // r <- $r6
    if ($r6===self::$FAILED) {
      $this->currPos = $p3;
      $r1 = self::$FAILED;
      goto seq_1;
    }
    $r1 = true;
    seq_1:
    if ($r1!==self::$FAILED) {
      $this->savedPos = $p2;
      $r1 = $this->a172($r6);
    }
    // free $p3
    $cached = ['nextPos' => $this->currPos, 'result' => $r1];
      if ($saved_preproc !== $param_preproc) $cached["\$preproc"] = $param_preproc;
      if ($saved_th !== $param_th) $cached["\$th"] = $param_th;
    $this->cache[$bucket][$key] = $cached;
    return $r1;
  }
  private function parsetext_char($silence) {
    $key = 490;
    $bucket = $this->currPos;
    $cached = $this->cache[$bucket][$key] ?? null;
    if ($cached) {
      $this->currPos = $cached['nextPos'];
  
      return $cached['result'];
    }
  
    if (strcspn($this->input, "-'<[{\x0a\x0d:;]}|!=", $this->currPos, 1) !== 0) {
      $r1 = self::consumeChar($this->input, $this->currPos);
    } else {
      $r1 = self::$FAILED;
      if (!$silence) {$this->fail(41);}
    }
    $cached = ['nextPos' => $this->currPos, 'result' => $r1];
  
    $this->cache[$bucket][$key] = $cached;
    return $r1;
  }
  private function parselang_variant_flag($silence, $boolParams, $param_templatedepth, &$param_preproc, &$param_th) {
    $key = json_encode([382, $boolParams & 0x1bae, $param_templatedepth, $param_preproc, $param_th]);
    $bucket = $this->currPos;
    $cached = $this->cache[$bucket][$key] ?? null;
    if ($cached) {
      $this->currPos = $cached['nextPos'];
        if (array_key_exists("\$preproc", $cached)) $param_preproc = $cached["\$preproc"];
        if (array_key_exists("\$th", $cached)) $param_th = $cached["\$th"];
      return $cached['result'];
    }
        $saved_preproc=$param_preproc;
        $saved_th=$param_th;
    // start choice_1
    $p2 = $this->currPos;
    $r3 = $this->input[$this->currPos] ?? '';
    // f <- $r3
    if (preg_match("/^[\\-+A-Z]/", $r3)) {
      $this->currPos++;
    } else {
      $r3 = self::$FAILED;
      if (!$silence) {$this->fail(82);}
    }
    $r1 = $r3;
    if ($r1!==self::$FAILED) {
      $this->savedPos = $p2;
      $r1 = $this->a173($r3);
      goto choice_1;
    }
    $p4 = $this->currPos;
    $r5 = $this->parselang_variant_name($silence, $boolParams, $param_templatedepth, $param_preproc, $param_th);
    // v <- $r5
    $r1 = $r5;
    if ($r1!==self::$FAILED) {
      $this->savedPos = $p4;
      $r1 = $this->a174($r5);
      goto choice_1;
    }
    $p6 = $this->currPos;
    $p8 = $this->currPos;
    $r7 = self::$FAILED;
    for (;;) {
      // start seq_1
      $p10 = $this->currPos;
      $p11 = $this->currPos;
      $r12 = $this->discardspace_or_newline(true);
      if ($r12 === self::$FAILED) {
        $r12 = false;
      } else {
        $r12 = self::$FAILED;
        $this->currPos = $p11;
        $r9 = self::$FAILED;
        goto seq_1;
      }
      // free $p11
      $p11 = $this->currPos;
      $r13 = $this->discardnowiki(true, $boolParams, $param_templatedepth, $param_preproc, $param_th);
      if ($r13 === self::$FAILED) {
        $r13 = false;
      } else {
        $r13 = self::$FAILED;
        $this->currPos = $p11;
        $this->currPos = $p10;
        $r9 = self::$FAILED;
        goto seq_1;
      }
      // free $p11
      if (strcspn($this->input, "{}|;", $this->currPos, 1) !== 0) {
        $r14 = self::consumeChar($this->input, $this->currPos);
      } else {
        $r14 = self::$FAILED;
        if (!$silence) {$this->fail(83);}
        $this->currPos = $p10;
        $r9 = self::$FAILED;
        goto seq_1;
      }
      $r9 = true;
      seq_1:
      if ($r9!==self::$FAILED) {
        $r7 = true;
      } else {
        break;
      }
      // free $p10
    }
    // b <- $r7
    if ($r7!==self::$FAILED) {
      $r7 = substr($this->input, $p8, $this->currPos - $p8);
    } else {
      $r7 = self::$FAILED;
    }
    // free $r9
    // free $p8
    $r1 = $r7;
    if ($r1!==self::$FAILED) {
      $this->savedPos = $p6;
      $r1 = $this->a175($r7);
    }
    choice_1:
    $cached = ['nextPos' => $this->currPos, 'result' => $r1];
      if ($saved_preproc !== $param_preproc) $cached["\$preproc"] = $param_preproc;
      if ($saved_th !== $param_th) $cached["\$th"] = $param_th;
    $this->cache[$bucket][$key] = $cached;
    return $r1;
  }
  private function parselang_variant_name($silence, $boolParams, $param_templatedepth, &$param_preproc, &$param_th) {
    $key = json_encode([384, $boolParams & 0x1bae, $param_templatedepth, $param_preproc, $param_th]);
    $bucket = $this->currPos;
    $cached = $this->cache[$bucket][$key] ?? null;
    if ($cached) {
      $this->currPos = $cached['nextPos'];
        if (array_key_exists("\$preproc", $cached)) $param_preproc = $cached["\$preproc"];
        if (array_key_exists("\$th", $cached)) $param_th = $cached["\$th"];
      return $cached['result'];
    }
        $saved_preproc=$param_preproc;
        $saved_th=$param_th;
    // start choice_1
    $p2 = $this->currPos;
    // start seq_1
    $p3 = $this->currPos;
    $r4 = $this->input[$this->currPos] ?? '';
    if (preg_match("/^[a-z]/", $r4)) {
      $this->currPos++;
    } else {
      $r4 = self::$FAILED;
      if (!$silence) {$this->fail(84);}
      $r1 = self::$FAILED;
      goto seq_1;
    }
    $r5 = self::$FAILED;
    for (;;) {
      $r6 = $this->input[$this->currPos] ?? '';
      if (preg_match("/^[\\-a-zA-Z]/", $r6)) {
        $this->currPos++;
        $r5 = true;
      } else {
        $r6 = self::$FAILED;
        if (!$silence) {$this->fail(85);}
        break;
      }
    }
    if ($r5===self::$FAILED) {
      $this->currPos = $p3;
      $r1 = self::$FAILED;
      goto seq_1;
    }
    // free $r6
    $r1 = true;
    seq_1:
    if ($r1!==self::$FAILED) {
      $r1 = substr($this->input, $p2, $this->currPos - $p2);
      goto choice_1;
    } else {
      $r1 = self::$FAILED;
    }
    // free $p3
    // free $p2
    $r1 = $this->parsenowiki_text($silence, $boolParams, $param_templatedepth, $param_preproc, $param_th);
    choice_1:
    $cached = ['nextPos' => $this->currPos, 'result' => $r1];
      if ($saved_preproc !== $param_preproc) $cached["\$preproc"] = $param_preproc;
      if ($saved_th !== $param_th) $cached["\$th"] = $param_th;
    $this->cache[$bucket][$key] = $cached;
    return $r1;
  }
  private function parselang_variant_nowiki($silence, $boolParams, $param_templatedepth, &$param_preproc, &$param_th) {
    $key = json_encode([392, $boolParams & 0x1bae, $param_templatedepth, $param_preproc, $param_th]);
    $bucket = $this->currPos;
    $cached = $this->cache[$bucket][$key] ?? null;
    if ($cached) {
      $this->currPos = $cached['nextPos'];
        if (array_key_exists("\$preproc", $cached)) $param_preproc = $cached["\$preproc"];
        if (array_key_exists("\$th", $cached)) $param_th = $cached["\$th"];
      return $cached['result'];
    }
        $saved_preproc=$param_preproc;
        $saved_th=$param_th;
    $p2 = $this->currPos;
    // start seq_1
    $p3 = $this->currPos;
    $r4 = $this->parsenowiki_text($silence, $boolParams, $param_templatedepth, $param_preproc, $param_th);
    // n <- $r4
    if ($r4===self::$FAILED) {
      $r1 = self::$FAILED;
      goto seq_1;
    }
    $p6 = $this->currPos;
    for (;;) {
      $r7 = $this->discardspace_or_newline($silence);
      if ($r7===self::$FAILED) {
        break;
      }
    }
    // free $r7
    $r5 = true;
    // sp <- $r5
    if ($r5!==self::$FAILED) {
      $r5 = substr($this->input, $p6, $this->currPos - $p6);
    } else {
      $r5 = self::$FAILED;
      $this->currPos = $p3;
      $r1 = self::$FAILED;
      goto seq_1;
    }
    // free $p6
    $r1 = true;
    seq_1:
    if ($r1!==self::$FAILED) {
      $this->savedPos = $p2;
      $r1 = $this->a176($r4, $r5);
    }
    // free $p3
    $cached = ['nextPos' => $this->currPos, 'result' => $r1];
      if ($saved_preproc !== $param_preproc) $cached["\$preproc"] = $param_preproc;
      if ($saved_th !== $param_th) $cached["\$th"] = $param_th;
    $this->cache[$bucket][$key] = $cached;
    return $r1;
  }
  private function parselang_variant_text_no_semi($silence, $boolParams, $param_templatedepth, &$param_preproc, &$param_th) {
    $key = json_encode([396, $boolParams & 0x1b7e, $param_templatedepth, $param_preproc, $param_th]);
    $bucket = $this->currPos;
    $cached = $this->cache[$bucket][$key] ?? null;
    if ($cached) {
      $this->currPos = $cached['nextPos'];
        if (array_key_exists("\$preproc", $cached)) $param_preproc = $cached["\$preproc"];
        if (array_key_exists("\$th", $cached)) $param_th = $cached["\$th"];
      return $cached['result'];
    }
        $saved_preproc=$param_preproc;
        $saved_th=$param_th;
    $r1 = $this->parselang_variant_text($silence, $boolParams | 0x80, $param_templatedepth, $param_preproc, $param_th);
    $cached = ['nextPos' => $this->currPos, 'result' => $r1];
      if ($saved_preproc !== $param_preproc) $cached["\$preproc"] = $param_preproc;
      if ($saved_th !== $param_th) $cached["\$th"] = $param_th;
    $this->cache[$bucket][$key] = $cached;
    return $r1;
  }
  private function parselang_variant_text_no_semi_or_arrow($silence, $boolParams, $param_templatedepth, &$param_preproc, &$param_th) {
    $key = json_encode([398, $boolParams & 0x1a7e, $param_templatedepth, $param_preproc, $param_th]);
    $bucket = $this->currPos;
    $cached = $this->cache[$bucket][$key] ?? null;
    if ($cached) {
      $this->currPos = $cached['nextPos'];
        if (array_key_exists("\$preproc", $cached)) $param_preproc = $cached["\$preproc"];
        if (array_key_exists("\$th", $cached)) $param_th = $cached["\$th"];
      return $cached['result'];
    }
        $saved_preproc=$param_preproc;
        $saved_th=$param_th;
    $r1 = $this->parselang_variant_text_no_semi($silence, $boolParams | 0x100, $param_templatedepth, $param_preproc, $param_th);
    $cached = ['nextPos' => $this->currPos, 'result' => $r1];
      if ($saved_preproc !== $param_preproc) $cached["\$preproc"] = $param_preproc;
      if ($saved_th !== $param_th) $cached["\$th"] = $param_th;
    $this->cache[$bucket][$key] = $cached;
    return $r1;
  }
  private function discardlang_variant_text($silence, $boolParams, $param_templatedepth, &$param_preproc, &$param_th) {
    $key = json_encode([395, $boolParams & 0x1bfe, $param_templatedepth, $param_preproc, $param_th]);
    $bucket = $this->currPos;
    $cached = $this->cache[$bucket][$key] ?? null;
    if ($cached) {
      $this->currPos = $cached['nextPos'];
        if (array_key_exists("\$preproc", $cached)) $param_preproc = $cached["\$preproc"];
        if (array_key_exists("\$th", $cached)) $param_th = $cached["\$th"];
      return $cached['result'];
    }
        $saved_preproc=$param_preproc;
        $saved_th=$param_th;
    $p2 = $this->currPos;
    $r3 = [];
    for (;;) {
      // start choice_1
      $r4 = $this->parseinlineline($silence, $boolParams, $param_templatedepth, $param_preproc, $param_th);
      if ($r4!==self::$FAILED) {
        goto choice_1;
      }
      if (($this->input[$this->currPos] ?? null) === "|") {
        $this->currPos++;
        $r4 = "|";
      } else {
        if (!$silence) {$this->fail(13);}
        $r4 = self::$FAILED;
      }
      choice_1:
      if ($r4!==self::$FAILED) {
        $r3[] = $r4;
      } else {
        break;
      }
    }
    // tokens <- $r3
    // free $r4
    $r1 = $r3;
    if ($r1!==self::$FAILED) {
      $this->savedPos = $p2;
      $r1 = $this->a156($r3);
    }
    $cached = ['nextPos' => $this->currPos, 'result' => $r1];
      if ($saved_preproc !== $param_preproc) $cached["\$preproc"] = $param_preproc;
      if ($saved_th !== $param_th) $cached["\$th"] = $param_th;
    $this->cache[$bucket][$key] = $cached;
    return $r1;
  }
  private function parsefull_table_in_link_caption_parameterized($silence, $boolParams, $param_templatedepth, &$param_preproc, &$param_th) {
    $key = json_encode([462, $boolParams & 0x3bff, $param_templatedepth, $param_preproc, $param_th]);
    $bucket = $this->currPos;
    $cached = $this->cache[$bucket][$key] ?? null;
    if ($cached) {
      $this->currPos = $cached['nextPos'];
        if (array_key_exists("\$preproc", $cached)) $param_preproc = $cached["\$preproc"];
        if (array_key_exists("\$th", $cached)) $param_th = $cached["\$th"];
      return $cached['result'];
    }
        $saved_preproc=$param_preproc;
        $saved_th=$param_th;
    // start seq_1
    $p1 = $this->currPos;
    $r3 = $this->parsetable_start_tag($silence, $boolParams, $param_templatedepth, $param_preproc, $param_th);
    if ($r3===self::$FAILED) {
      $r2 = self::$FAILED;
      goto seq_1;
    }
    $r4 = $this->parseoptionalNewlines($silence);
    if ($r4===self::$FAILED) {
      $this->currPos = $p1;
      $r2 = self::$FAILED;
      goto seq_1;
    }
    $r5 = [];
    for (;;) {
      // start seq_2
      $p7 = $this->currPos;
      $r8 = [];
      for (;;) {
        // start seq_3
        $p10 = $this->currPos;
        $r11 = $this->parsesol($silence, $boolParams, $param_templatedepth, $param_preproc, $param_th);
        if ($r11===self::$FAILED) {
          $r9 = self::$FAILED;
          goto seq_3;
        }
        // start choice_1
        $r12 = $this->parsetable_content_line($silence, $boolParams, $param_templatedepth, $param_preproc, $param_th);
        if ($r12!==self::$FAILED) {
          goto choice_1;
        }
        $r12 = $this->parsetplarg_or_template($silence, $boolParams, $param_templatedepth, $param_th, $param_preproc);
        choice_1:
        if ($r12===self::$FAILED) {
          $this->currPos = $p10;
          $r9 = self::$FAILED;
          goto seq_3;
        }
        $r13 = $this->parseoptionalNewlines($silence);
        if ($r13===self::$FAILED) {
          $this->currPos = $p10;
          $r9 = self::$FAILED;
          goto seq_3;
        }
        $r9 = [$r11,$r12,$r13];
        seq_3:
        if ($r9!==self::$FAILED) {
          $r8[] = $r9;
        } else {
          break;
        }
        // free $p10
      }
      // free $r9
      $r9 = $this->parsesol($silence, $boolParams, $param_templatedepth, $param_preproc, $param_th);
      if ($r9===self::$FAILED) {
        $this->currPos = $p7;
        $r6 = self::$FAILED;
        goto seq_2;
      }
      $r14 = $this->parsetable_end_tag($silence);
      if ($r14===self::$FAILED) {
        $this->currPos = $p7;
        $r6 = self::$FAILED;
        goto seq_2;
      }
      $r6 = [$r8,$r9,$r14];
      seq_2:
      if ($r6!==self::$FAILED) {
        $r5[] = $r6;
      } else {
        break;
      }
      // free $p7
    }
    if (count($r5) === 0) {
      $r5 = self::$FAILED;
    }
    if ($r5===self::$FAILED) {
      $this->currPos = $p1;
      $r2 = self::$FAILED;
      goto seq_1;
    }
    // free $r6
    $r2 = [$r3,$r4,$r5];
    seq_1:
    // free $r2,$p1
    $cached = ['nextPos' => $this->currPos, 'result' => $r2];
      if ($saved_preproc !== $param_preproc) $cached["\$preproc"] = $param_preproc;
      if ($saved_th !== $param_th) $cached["\$th"] = $param_th;
    $this->cache[$bucket][$key] = $cached;
    return $r2;
  }
  private function discardnowiki($silence, $boolParams, $param_templatedepth, &$param_preproc, &$param_th) {
    $key = json_encode([417, $boolParams & 0x1bae, $param_templatedepth, $param_preproc, $param_th]);
    $bucket = $this->currPos;
    $cached = $this->cache[$bucket][$key] ?? null;
    if ($cached) {
      $this->currPos = $cached['nextPos'];
        if (array_key_exists("\$preproc", $cached)) $param_preproc = $cached["\$preproc"];
        if (array_key_exists("\$th", $cached)) $param_th = $cached["\$th"];
      return $cached['result'];
    }
        $saved_preproc=$param_preproc;
        $saved_th=$param_th;
    $p2 = $this->currPos;
    // start seq_1
    $p3 = $this->currPos;
    $r4 = $this->parseextension_tag($silence, $boolParams, $param_templatedepth, $param_preproc, $param_th);
    // extToken <- $r4
    if ($r4===self::$FAILED) {
      $r1 = self::$FAILED;
      goto seq_1;
    }
    $this->savedPos = $this->currPos;
    $r5 = $this->a177($r4);
    if ($r5) {
      $r5 = false;
    } else {
      $r5 = self::$FAILED;
      $this->currPos = $p3;
      $r1 = self::$FAILED;
      goto seq_1;
    }
    $r1 = true;
    seq_1:
    if ($r1!==self::$FAILED) {
      $this->savedPos = $p2;
      $r1 = $this->a178($r4);
    }
    // free $p3
    $cached = ['nextPos' => $this->currPos, 'result' => $r1];
      if ($saved_preproc !== $param_preproc) $cached["\$preproc"] = $param_preproc;
      if ($saved_th !== $param_th) $cached["\$th"] = $param_th;
    $this->cache[$bucket][$key] = $cached;
    return $r1;
  }
  private function parsenowiki_text($silence, $boolParams, $param_templatedepth, &$param_preproc, &$param_th) {
    $key = json_encode([418, $boolParams & 0x1bae, $param_templatedepth, $param_preproc, $param_th]);
    $bucket = $this->currPos;
    $cached = $this->cache[$bucket][$key] ?? null;
    if ($cached) {
      $this->currPos = $cached['nextPos'];
        if (array_key_exists("\$preproc", $cached)) $param_preproc = $cached["\$preproc"];
        if (array_key_exists("\$th", $cached)) $param_th = $cached["\$th"];
      return $cached['result'];
    }
        $saved_preproc=$param_preproc;
        $saved_th=$param_th;
    $p2 = $this->currPos;
    $r3 = $this->parsenowiki($silence, $boolParams, $param_templatedepth, $param_preproc, $param_th);
    // extToken <- $r3
    $r1 = $r3;
    if ($r1!==self::$FAILED) {
      $this->savedPos = $p2;
      $r1 = $this->a179($r3);
    }
    $cached = ['nextPos' => $this->currPos, 'result' => $r1];
      if ($saved_preproc !== $param_preproc) $cached["\$preproc"] = $param_preproc;
      if ($saved_th !== $param_th) $cached["\$th"] = $param_th;
    $this->cache[$bucket][$key] = $cached;
    return $r1;
  }
  private function parsenowiki($silence, $boolParams, $param_templatedepth, &$param_preproc, &$param_th) {
    $key = json_encode([416, $boolParams & 0x1bae, $param_templatedepth, $param_preproc, $param_th]);
    $bucket = $this->currPos;
    $cached = $this->cache[$bucket][$key] ?? null;
    if ($cached) {
      $this->currPos = $cached['nextPos'];
        if (array_key_exists("\$preproc", $cached)) $param_preproc = $cached["\$preproc"];
        if (array_key_exists("\$th", $cached)) $param_th = $cached["\$th"];
      return $cached['result'];
    }
        $saved_preproc=$param_preproc;
        $saved_th=$param_th;
    $p2 = $this->currPos;
    // start seq_1
    $p3 = $this->currPos;
    $r4 = $this->parseextension_tag($silence, $boolParams, $param_templatedepth, $param_preproc, $param_th);
    // extToken <- $r4
    if ($r4===self::$FAILED) {
      $r1 = self::$FAILED;
      goto seq_1;
    }
    $this->savedPos = $this->currPos;
    $r5 = $this->a177($r4);
    if ($r5) {
      $r5 = false;
    } else {
      $r5 = self::$FAILED;
      $this->currPos = $p3;
      $r1 = self::$FAILED;
      goto seq_1;
    }
    $r1 = true;
    seq_1:
    if ($r1!==self::$FAILED) {
      $this->savedPos = $p2;
      $r1 = $this->a178($r4);
    }
    // free $p3
    $cached = ['nextPos' => $this->currPos, 'result' => $r1];
      if ($saved_preproc !== $param_preproc) $cached["\$preproc"] = $param_preproc;
      if ($saved_th !== $param_th) $cached["\$th"] = $param_th;
    $this->cache[$bucket][$key] = $cached;
    return $r1;
  }

  public function parse($input, $options = []) {
    $this->initInternal($input, $options);
    $startRule = $options['startRule'] ?? '(DEFAULT)';
    $result = null;

    if (!empty($options['stream'])) {
      switch ($startRule) {
        case '(DEFAULT)':
        case "start_async":
          return $this->streamstart_async(false, self::newRef(null));
          break;
        default:
          throw new \WikiPEG\InternalError("Can't stream rule $startRule.");
      }
    } else {
      switch ($startRule) {
        case '(DEFAULT)':
        case "start":
          $result = $this->parsestart(false, self::newRef(null));
          break;
        
        case "table_start_tag":
          $result = $this->parsetable_start_tag(false, 0, 0, self::newRef(null), self::newRef(null));
          break;
        
        case "url":
          $result = $this->parseurl(false, self::newRef(null));
          break;
        
        case "row_syntax_table_args":
          $result = $this->parserow_syntax_table_args(false, 0, 0, self::newRef(null), self::newRef(null));
          break;
        
        case "table_attributes":
          $result = $this->parsetable_attributes(false, 0, 0, self::newRef(null), self::newRef(null));
          break;
        
        case "generic_newline_attributes":
          $result = $this->parsegeneric_newline_attributes(false, 0, 0, self::newRef(null), self::newRef(null));
          break;
        
        case "tplarg_or_template_or_bust":
          $result = $this->parsetplarg_or_template_or_bust(false, self::newRef(null));
          break;
        
        case "extlink":
          $result = $this->parseextlink(false, 0, 0, self::newRef(null), self::newRef(null));
          break;
        default:
          throw new \WikiPEG\InternalError("Can't start parsing from rule $startRule.");
      }
    }

    if ($result !== self::$FAILED && $this->currPos === $this->inputLength) {
      return $result;
    } else {
      if ($result !== self::$FAILED && $this->currPos < $this->inputLength) {
        $this->fail(0);
      }
      throw $this->buildParseException();
    }
  }
}
