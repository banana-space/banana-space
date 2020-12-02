<?php
declare( strict_types = 1 );

namespace Wikimedia\Parsoid\Wt2Html;

use Closure;
use DateTime;
use DOMDocument;
use DOMElement;
use Generator;
use Wikimedia\ObjectFactory;
use Wikimedia\Parsoid\Config\Env;
use Wikimedia\Parsoid\Ext\DOMProcessor as ExtDOMProcessor;
use Wikimedia\Parsoid\Ext\ParsoidExtensionAPI;
use Wikimedia\Parsoid\Tokens\SourceRange;
use Wikimedia\Parsoid\Utils\ContentUtils;
use Wikimedia\Parsoid\Utils\DOMCompat;
use Wikimedia\Parsoid\Utils\DOMDataUtils;
use Wikimedia\Parsoid\Utils\DOMTraverser;
use Wikimedia\Parsoid\Utils\DOMUtils;
use Wikimedia\Parsoid\Utils\PHPUtils;
use Wikimedia\Parsoid\Utils\Title;
use Wikimedia\Parsoid\Utils\Utils;
use Wikimedia\Parsoid\Wt2Html\PP\Handlers\CleanUp;
use Wikimedia\Parsoid\Wt2Html\PP\Handlers\DedupeStyles;
use Wikimedia\Parsoid\Wt2Html\PP\Handlers\DisplaySpace;
use Wikimedia\Parsoid\Wt2Html\PP\Handlers\HandleLinkNeighbours;
use Wikimedia\Parsoid\Wt2Html\PP\Handlers\Headings;
use Wikimedia\Parsoid\Wt2Html\PP\Handlers\LiFixups;
use Wikimedia\Parsoid\Wt2Html\PP\Handlers\TableFixups;
use Wikimedia\Parsoid\Wt2Html\PP\Handlers\UnpackDOMFragments;
use Wikimedia\Parsoid\Wt2Html\PP\Processors\AddExtLinkClasses;
use Wikimedia\Parsoid\Wt2Html\PP\Processors\AddMediaInfo;
use Wikimedia\Parsoid\Wt2Html\PP\Processors\AddRedLinks;
use Wikimedia\Parsoid\Wt2Html\PP\Processors\ComputeDSR;
use Wikimedia\Parsoid\Wt2Html\PP\Processors\ConvertOffsets;
use Wikimedia\Parsoid\Wt2Html\PP\Processors\LangConverter;
use Wikimedia\Parsoid\Wt2Html\PP\Processors\Linter;
use Wikimedia\Parsoid\Wt2Html\PP\Processors\MarkFosteredContent;
use Wikimedia\Parsoid\Wt2Html\PP\Processors\MigrateTemplateMarkerMetas;
use Wikimedia\Parsoid\Wt2Html\PP\Processors\MigrateTrailingNLs;
use Wikimedia\Parsoid\Wt2Html\PP\Processors\Normalize;
use Wikimedia\Parsoid\Wt2Html\PP\Processors\ProcessTreeBuilderFixups;
use Wikimedia\Parsoid\Wt2Html\PP\Processors\PWrap;
use Wikimedia\Parsoid\Wt2Html\PP\Processors\WrapSections;
use Wikimedia\Parsoid\Wt2Html\PP\Processors\WrapTemplates;

/**
 * Perform post-processing steps on an already-built HTML DOM.
 */
class DOMPostProcessor extends PipelineStage {
	/** @var array */
	private $options;

	/** @var array */
	private $seenIds;

	/** @var array */
	private $processors;

	/** @var ParsoidExtensionAPI Provides post-processing support to extensions */
	private $extApi;

	/** @var array */
	private $metadataMap;

	/** @var bool */
	private $atTopLevel = false;

	/**
	 * @param Env $env
	 * @param array $options
	 * @param string $stageId
	 * @param PipelineStage|null $prevStage
	 */
	public function __construct(
		Env $env, array $options = [], string $stageId = "", $prevStage = null
	) {
		parent::__construct( $env, $prevStage );

		$this->options = $options + [ 'frame' => $env->topFrame ];
		$this->seenIds = [];
		$this->processors = [];
		$this->extApi = new ParsoidExtensionAPI( $env, [] );

		// map from mediawiki metadata names to RDFa property names
		$this->metadataMap = [
			'ns' => [
				'property' => 'mw:pageNamespace',
				'content' => '%d',
			],
			'id' => [
				'property' => 'mw:pageId',
				'content' => '%d',
			],

			// DO NOT ADD rev_user, rev_userid, and rev_comment (See T125266)

			// 'rev_revid' is used to set the overall subject of the document, we don't
			// need to add a specific <meta> or <link> element for it.

			'rev_parentid' => [
				'rel' => 'dc:replaces',
				'resource' => 'mwr:revision/%d',
			],
			'rev_timestamp' => [
				'property' => 'dc:modified',
				'content' => function ( $m ) {
					# Convert from TS_MW ("mediawiki timestamp") format
					$dt = DateTime::createFromFormat( 'YmdHis', $m['rev_timestamp'] );
					# Note that DateTime::ISO8601 is not actually ISO8601, alas.
					return $dt->format( 'Y-m-d\TH:i:s.000\Z' );
				},
			],
			'rev_sha1' => [
				'property' => 'mw:revisionSHA1',
				'content' => '%s',
			]
		];
	}

	/**
	 * @param ?array $processors
	 */
	public function registerProcessors( ?array $processors ): void {
		if ( empty( $processors ) ) {
			$processors = $this->getDefaultProcessors();
		}

		foreach ( $processors as $p ) {
			if ( !empty( $p['omit'] ) ) {
				continue;
			}
			if ( empty( $p['name'] ) ) {
				$p['name'] = Utils::stripNamespace( $p['Processor'] );
			}
			if ( empty( $p['shortcut'] ) ) {
				$p['shortcut'] = $p['name'];
			}
			if ( !empty( $p['isTraverser'] ) ) {
				$t = new DOMTraverser();
				foreach ( $p['handlers'] as $h ) {
					$t->addHandler( $h['nodeName'], $h['action'] );
				}
				$p['proc'] = function ( ...$args ) use ( $t ) {
					$args[] = null;
					return $t->traverse( $this->env, ...$args );
				};
			} else {
				$classNameOrSpec = $p['Processor'];
				if ( empty( $p['isExtPP'] ) ) {
					// Internal processor w/ ::run() method, class name given
					// @phan-suppress-next-line PhanNonClassMethodCall
					$c = new $classNameOrSpec();
					$p['proc'] = function ( ...$args ) use ( $c ) {
						return $c->run( $this->env, ...$args );
					};
				} else {
					// Extension post processor, object factory spec given
					$c = ObjectFactory::getObjectFromSpec( $classNameOrSpec, [
						'allowClassName' => true,
						'assertClass' => ExtDOMProcessor::class,
					] );
					$p['proc'] = function ( ...$args ) use ( $c ) {
						return $c->wtPostprocess( $this->extApi, ...$args );
					};
				}
			}
			$this->processors[] = $p;
		}
	}

	/**
	 * @return array
	 */
	public function getDefaultProcessors(): array {
		$env = $this->env;
		$options = $this->options;
		$seenIds = &$this->seenIds;
		$usedIdIndex = [];

		$tableFixer = new TableFixups( $env );

		/* ---------------------------------------------------------------------------
		 * FIXME:
		 * 1. PipelineFactory caches pipelines per env
		 * 2. PipelineFactory.parse uses a default cache key
		 * 3. ParserTests uses a shared/global env object for all tests.
		 * 4. ParserTests also uses PipelineFactory.parse (via env.getContentHandler())
		 *    => the pipeline constructed for the first test that runs wt2html
		 *       is used for all subsequent wt2html tests
		 * 5. If we are selectively turning on/off options on a per-test basis
		 *    in parser tests, those options won't work if those options are
		 *    also used to configure pipeline construction (including which DOM passes
		 *    are enabled).
		 *
		 *    Ex: if (env.wrapSections) { addPP('wrapSections', wrapSections); }
		 *
		 *    This won't do what you expect it to do. This is primarily a
		 *    parser tests script issue -- but given the abstraction layers that
		 *    are on top of the parser pipeline construction, fixing that is
		 *    not straightforward right now. So, this note is a warning to future
		 *    developers to pay attention to how they construct pipelines.
		 * --------------------------------------------------------------------------- */

		$processors = [
			// Common post processing
			[
				'Processor' => MarkFosteredContent::class,
				'shortcut' => 'fostered'
			],
			[
				'Processor' => ProcessTreeBuilderFixups::class,
				'shortcut' => 'process-fixups'
			],
			[
				'Processor' => Normalize::class
			],
			[
				'Processor' => PWrap::class,
				'shortcut' => 'pwrap',
				'skipNested' => true
			],
			// Run this after 'ProcessTreeBuilderFixups' because the mw:StartTag
			// and mw:EndTag metas would otherwise interfere with the
			// firstChild/lastChild check that this pass does.
			[
				'Processor' => MigrateTemplateMarkerMetas::class,
				'shortcut' => 'migrate-metas'
			],
			[
				'Processor' => MigrateTrailingNLs::class,
				'shortcut' => 'migrate-nls'
			],
			// dsr computation and tpl encap are only relevant for top-level content
			[
				'Processor' => ComputeDSR::class,
				'shortcut' => 'dsr',
				'omit' => !empty( $options['inTemplate'] )
			],
			[
				'Processor' => WrapTemplates::class,
				'shortcut' => 'tplwrap',
				'omit' => !empty( $options['inTemplate'] )
			],
			// 1. Link prefixes and suffixes
			// 2. Unpack DOM fragments
			[
				'name' => 'HandleLinkNeighbours,UnpackDOMFragments',
				'shortcut' => 'dom-unpack',
				'isTraverser' => true,
				'handlers' => [
					[
						'nodeName' => 'a',
						'action' => [ HandleLinkNeighbours::class, 'handler' ]
					],
					[
						'nodeName' => null,
						'action' => [ UnpackDOMFragments::class, 'handler' ]
					]
				]
			]
		];

		/**
		 * FIXME: There are two potential ordering problems here.
		 *
		 * 1. unpackDOMFragment should always run immediately
		 *    before these extensionPostProcessors, which we do currently.
		 *    This ensures packed content get processed correctly by extensions
		 *    before additional transformations are run on the DOM.
		 *
		 * This ordering issue is handled through documentation.
		 *
		 * 2. This has existed all along (in the PHP parser as well as Parsoid
		 *    which is probably how the ref-in-ref hack works - because of how
		 *    parser functions and extension tags are procesed, #tag:ref doesn't
		 *    see a nested ref anymore) and this patch only exposes that problem
		 *    more clearly with the sealFragment property.
		 *
		 * * Consider the set of extensions that
		 *   (a) process wikitext
		 *   (b) provide an extensionPostProcessor
		 *   (c) run the extensionPostProcessor only on the top-level
		 *   As of today, there is exactly one extension (Cite) that has all
		 *   these properties, so the problem below is a speculative problem
		 *   for today. But, this could potentially be a problem in the future.
		 *
		 * * Let us say there are at least two of them, E1 and E2 that
		 *   support extension tags <e1> and <e2> respectively.
		 *
		 * * Let us say in an instance of <e1> on the page, <e2> is present
		 *   and in another instance of <e2> on the page, <e1> is present.
		 *
		 * * In what order should E1's and E2's extensionPostProcessors be
		 *   run on the top-level? Depending on what these handlers do, you
		 *   could get potentially different results. You can see this quite
		 *   starkly with the sealFragment flag.
		 *
		 * * The ideal solution to this problem is to require that every extension's
		 *   extensionPostProcessor be idempotent which lets us run these
		 *   post processors repeatedly till the DOM stabilizes. But, this
		 *   still doesn't necessarily guarantee that ordering doesn't matter.
		 *   It just guarantees that with the sealFragment flag set on
		 *   multiple extensions, all sealed fragments get fully processed.
		 *   So, we still need to worry about that problem.
		 *
		 *   But, idempotence *could* potentially be a sufficient property in most cases.
		 *   To see this, consider that there is a Footnotes extension which is similar
		 *   to the Cite extension in that they both extract inline content in the
		 *   page source to a separate section of output and leave behind pointers to
		 *   the global section in the output DOM. Given this, the Cite and Footnote
		 *   extension post processors would essentially walk the dom and
		 *   move any existing inline content into that global section till it is
		 *   done. So, even if a <footnote> has a <ref> and a <ref> has a <footnote>,
		 *   we ultimately end up with all footnote content in the footnotes section
		 *   and all ref content in the references section and the DOM stabilizes.
		 *   Ordering is irrelevant here.
		 *
		 *   So, perhaps one way of catching these problems would be in code review
		 *   by analyzing what the DOM postprocessor does and see if it introduces
		 *   potential ordering issues.
		 */
		foreach ( $env->getSiteConfig()->getExtDOMProcessors() as $extName => $domProcs ) {
			foreach ( $domProcs as $i => $domProcSpec ) {
				$processors[] = [
					'isExtPP' => true, // This is an extension DOM post processor
					'name' => "pp:$extName:$i",
					'Processor' => $domProcSpec,
				];
			}
		}

		$processors = array_merge( $processors, [
			[
				'name' => 'LiFixups,TableFixups,DedupeStyles',
				'shortcut' => 'fixups',
				'isTraverser' => true,
				'skipNested' => true,
				'handlers' => [
					// 1. Deal with <li>-hack and move trailing categories in <li>s out of the list
					[
						'nodeName' => 'li',
						'action' => [ LiFixups::class, 'handleLIHack' ],
					],
					[
						'nodeName' => 'li',
						'action' => [ LiFixups::class, 'migrateTrailingCategories' ]
					],
					[
						'nodeName' => 'dt',
						'action' => [ LiFixups::class, 'migrateTrailingCategories' ]
					],
					[
						'nodeName' => 'dd',
						'action' => [ LiFixups::class, 'migrateTrailingCategories' ]
					],
					// 2. Fix up issues from templated table cells and table cell attributes
					[
						'nodeName' => 'td',
						'action' => function ( $node, $env, $options ) use ( &$tableFixer ) {
							return $tableFixer->stripDoubleTDs( $node, $options['frame'] );
						}
					],
					[
						'nodeName' => 'td',
						'action' => function ( $node, $env, $options ) use ( &$tableFixer ) {
							return $tableFixer->handleTableCellTemplates( $node, $options['frame'] );
						}
					],
					[
						'nodeName' => 'th',
						'action' => function ( $node, $env, $options ) use ( &$tableFixer ) {
							return $tableFixer->handleTableCellTemplates( $node, $options['frame'] );
						}
					],
					// 3. Deduplicate template styles
					// (should run after dom-fragment expansion + after extension post-processors)
					[
						'nodeName' => 'style',
						'action' => [ DedupeStyles::class, 'dedupe' ]
					]
				]
			],
			// This is run at all levels since, for now, we don't have a generic
			// solution to running top level passes on HTML stashed in data-mw.
			// See T214994 for that.
			//
			// Also, the gallery extension's "packed" mode would otherwise need a
			// post-processing pass to scale media after it has been fetched.  That
			// introduces an ordering dependency that may or may not complicate things.
			[
				'Processor' => AddMediaInfo::class,
				'shortcut' => 'media'
			],
			// Benefits from running after determining which media are redlinks
			[
				'name' => 'Headings-genAnchors',
				'shortcut' => 'heading-ids',
				'isTraverser' => true,
				'skipNested' => true,
				'handlers' => [
					[
						'nodeName' => null,
						'action' => [ Headings::class, 'genAnchors' ]
					],
					[
						'nodeName' => null,
						'action' => function ( $node, $env ) use ( &$seenIds ) {
							return Headings::dedupeHeadingIds( $seenIds, $node );
						}
					]
				]
			],
			[
				'Processor' => Linter::class,
				'omit' => !$env->getSiteConfig()->linting(),
				'skipNested' => true
			],
			// Strip marker metas -- removes left over marker metas (ex: metas
			// nested in expanded tpl/extension output).
			[
				'name' => 'CleanUp-stripMarkerMetas',
				'shortcut' => 'strip-metas',
				'isTraverser' => true,
				'handlers' => [
					[
						'nodeName' => 'meta',
						'action' => [ CleanUp::class, 'stripMarkerMetas' ]
					]
				]
			],
			// Language conversion
			[
				'Processor' => LangConverter::class,
				'shortcut' => 'lang-converter',
				'skipNested' => true
			],
			[
				'name' => 'DisplaySpace',
				'shortcut' => 'displayspace',
				'skipNested' => true,
				'isTraverser' => true,
				'handlers' => [
					[
						'nodeName' => '#text',
						'action' => [ DisplaySpace::class, 'leftHandler' ]
					],
					[
						'nodeName' => '#text',
						'action' => [ DisplaySpace::class, 'rightHandler' ]
					],
				]
			],
			[
				'Processor' => AddExtLinkClasses::class,
				'shortcut' => 'linkclasses',
				'skipNested' => true
			],
			// Add <section> wrappers around sections
			[
				'Processor' => WrapSections::class,
				'shortcut' => 'sections',
				'skipNested' => true
			],
			[
				'Processor' => ConvertOffsets::class,
				'shortcut' => 'convertoffsets',
				'skipNested' => true,
			],
			[
				'name' => 'CleanUp-handleEmptyElts,CleanUp-cleanupAndSaveDataParsoid',
				'shortcut' => 'cleanup',
				'isTraverser' => true,
				'handlers' => [
					// Strip empty elements from template content
					[
						'nodeName' => null,
						'action' => [ CleanUp::class, 'handleEmptyElements' ]
					],
					// Save data.parsoid into data-parsoid html attribute.
					// Make this its own thing so that any changes to the DOM
					// don't affect other handlers that run alongside it.
					[
						'nodeName' => null,
						'action' => function ( $node, $env, $options, $atTopLevel, $tplInfo ) use ( &$usedIdIndex ) {
							if ( DOMUtils::isBody( $node ) ) {
								$usedIdIndex = DOMDataUtils::usedIdIndex( $node );
							}
							return CleanUp::cleanupAndSaveDataParsoid(
								$usedIdIndex, $node, $env, $atTopLevel, $tplInfo );
						}
					]
				]
			],
			[
				'Processor' => AddRedLinks::class,
				'shortcut' => 'redlinks',
				'skipNested' => true,
				'omit' => $env->noDataAccess(),
			],
		] );

		return $processors;
	}

	/**
	 * @inheritDoc
	 */
	public function setSourceOffsets( SourceRange $so ): void {
		$this->options['sourceOffsets'] = $so;
	}

	/**
	 * @inheritDoc
	 */
	public function setFrame(
		?Frame $parentFrame, ?Title $title, array $args, string $srcText
	): void {
		if ( !$parentFrame ) {
			$this->options['frame'] = $this->env->topFrame->newChild(
				$title, $args, $srcText
			);
		} elseif ( !$title ) {
			$this->options['frame'] = $parentFrame->newChild(
				$parentFrame->getTitle(), $parentFrame->getArgs()->args, $srcText
			);
		} else {
			$this->options['frame'] = $parentFrame->newChild(
				$title, $args, $srcText
			);
		}
	}

	/**
	 * @param array $opts
	 */
	public function resetState( array $opts ): void {
		$this->atTopLevel = $opts['toplevel'] ?? false;
		// $this->env->getPageConfig()->meta->displayTitle = null;
		$this->seenIds = [];
	}

	/**
	 * Create an element in the document.head with the given attrs.
	 *
	 * @param DOMDocument $document
	 * @param string $tagName
	 * @param array $attrs
	 */
	public function appendToHead( DOMDocument $document, string $tagName, array $attrs = [] ): void {
		$elt = $document->createElement( $tagName );
		DOMUtils::addAttributes( $elt, $attrs );
		( DOMCompat::getHead( $document ) )->appendChild( $elt );
	}

	/**
	 * FIXME: consider moving to DOMUtils or Env.
	 *
	 * @param Env $env
	 * @param DOMDocument $document
	 */
	public function addMetaData( Env $env, DOMDocument $document ): void {
		// add <head> element if it was missing
		if ( !( DOMCompat::getHead( $document ) instanceof DOMElement ) ) {
			$document->documentElement->insertBefore(
				$document->createElement( 'head' ),
				DOMCompat::getBody( $document )
			);
		}

		// add mw: and mwr: RDFa prefixes
		$prefixes = [
			'dc: http://purl.org/dc/terms/',
			'mw: http://mediawiki.org/rdf/'
		];
		$document->documentElement->setAttribute( 'prefix', implode( ' ', $prefixes ) );

		// (From wfParseUrl in core:)
		// Protocol-relative URLs are handled really badly by parse_url().
		// It's so bad that the easiest way to handle them is to just prepend
		// 'https:' and strip the protocol out later.
		$baseURI = $env->getSiteConfig()->baseURI();
		$wasRelative = substr( $baseURI, 0, 2 ) == '//';
		if ( $wasRelative ) {
			$baseURI = "https:$baseURI";
		}
		// add 'https://' to baseURI if it was missing
		$pu = parse_url( $baseURI );
		$mwrPrefix = ( !empty( $pu['scheme'] ) ? '' : 'https://' ) .
			$baseURI . 'Special:Redirect/';

		( DOMCompat::getHead( $document ) )->setAttribute( 'prefix', 'mwr: ' . $mwrPrefix );

		// add <head> content based on page meta data:

		// Set the charset first.
		$this->appendToHead( $document, 'meta', [ 'charset' => 'utf-8' ] );

		// Add page / revision metadata to the <head>
		// PORT-FIXME: We will need to do some refactoring to eliminate
		// this hardcoding. Probably even merge thi sinto metadataMap
		$pageConfig = $env->getPageConfig();
		$revProps = [
			'id' => $pageConfig->getPageId(),
			'ns' => $pageConfig->getNs(),
			'rev_parentid' => $pageConfig->getParentRevisionId(),
			'rev_revid' => $pageConfig->getRevisionId(),
			'rev_sha1' => $pageConfig->getRevisionSha1(),
			'rev_timestamp' => $pageConfig->getRevisionTimestamp()
		];
		foreach ( $revProps as $key => $value ) {
			// generate proper attributes for the <meta> or <link> tag
			if ( $value === null || $value === '' || !isset( $this->metadataMap[$key] ) ) {
				continue;
			}

			$attrs = [];
			$mdm = $this->metadataMap[$key];

			/** FIXME: The JS side has a bunch of other checks here */

			foreach ( $mdm as $k => $v ) {
				// evaluate a function, or perform sprintf-style formatting, or
				// use string directly, depending on value in metadataMap
				if ( $v instanceof Closure ) {
					$v = $v( $revProps );
				} elseif ( strpos( $v, '%' ) !== false ) {
					// @phan-suppress-next-line PhanPluginPrintfVariableFormatString
					$v = sprintf( $v, $value );
				}
				$attrs[$k] = $v;
			}

			// <link> is used if there's a resource or href attribute.
			$this->appendToHead( $document,
				isset( $attrs['resource'] ) || isset( $attrs['href'] ) ? 'link' : 'meta',
				$attrs
			);
		}

		if ( $revProps['rev_revid'] ) {
			$document->documentElement->setAttribute(
				'about', $mwrPrefix . 'revision/' . $revProps['rev_revid']
			);
		}

		// Normalize before comparison
		if (
			preg_replace( '/_/', ' ', $env->getSiteConfig()->mainpage() ) ===
			preg_replace( '/_/', ' ', $env->getPageConfig()->getTitle() )
		) {
			$this->appendToHead( $document, 'meta', [
				'property' => 'isMainPage',
				'content' => 'true' /* HTML attribute values should be strings */
			] );
		}

		// Set the parsoid content-type strings
		// FIXME: Should we be using http-equiv for this?
		$this->appendToHead( $document, 'meta', [
				'property' => 'mw:html:version',
				'content' => $env->getOutputContentVersion()
			]
		);

		$expTitle = strtr( $env->getPageConfig()->getTitle(), ' ', '_' );
		$expTitle = explode( '/', $expTitle );
		$expTitle = array_map( function ( $comp ) {
			return PHPUtils::encodeURIComponent( $comp );
		}, $expTitle );

		$this->appendToHead( $document, 'link', [
			'rel' => 'dc:isVersionOf',
			'href' => $env->getSiteConfig()->baseURI() . implode( '/', $expTitle )
		] );

		DOMCompat::setTitle(
			$document,
			// PORT-FIXME: There isn't a place anywhere yet for displayTitle
			/* $env->getPageConfig()->meta->displayTitle || */
			$env->getPageConfig()->getTitle()
		);

		// Add base href pointing to the wiki root
		$this->appendToHead( $document, 'base', [
			'href' => $env->getSiteConfig()->baseURI()
		] );

		// Hack: link styles
		$modules = [
			'mediawiki.skinning.content.parsoid',
			// Use the base styles that apioutput and fallback skin use.
			'mediawiki.skinning.interface',
			// Make sure to include contents of user generated styles
			// e.g. MediaWiki:Common.css / MediaWiki:Mobile.css
			'site.styles'
		];

		// Styles from native extensions
		foreach ( $env->getSiteConfig()->getExtStyles() as $style ) {
			$modules[] = $style;
		}

		// Styles from modules returned from preprocessor / parse requests
		$outputProps = $env->getOutputProperties();
		if ( isset( $outputProps['modulestyles'] ) ) {
			foreach ( $outputProps['modulestyles'] as $mo ) {
				$modules[] = $mo;
			}
		}

		$modulesBaseURI = $env->getSiteConfig()->getModulesLoadURI();
		$styleURI = $modulesBaseURI .
			'?modules=' .
			PHPUtils::encodeURIComponent( implode( '|', $modules ) ) .
			'&only=styles&skin=vector';
		$this->appendToHead( $document, 'link', [ 'rel' => 'stylesheet', 'href' => $styleURI ] );

		// Stick data attributes in the head
		if ( $env->pageBundle ) {
			DOMDataUtils::injectPageBundle( $document, DOMDataUtils::getPageBundle( $document ) );
		}

		// html5shiv
		$shiv = $document->createElement( 'script' );
		$src = $modulesBaseURI . '?modules=html5shiv&only=scripts&skin=vector&sync=1';
		$shiv->setAttribute( 'src', $src );
		$fi = $document->createElement( 'script' );
		$fi->appendChild( $document->createTextNode( "html5.addElements('figure-inline');" ) );
		$comment = $document->createComment(
			'[if lt IE 9]>' . DOMCompat::getOuterHTML( $shiv ) .
			DOMCompat::getOuterHTML( $fi ) . '<![endif]'
		);
		DOMCompat::getHead( $document )->appendChild( $comment );

		$lang = $env->getPageConfig()->getPageLanguage() ?:
			$env->getSiteConfig()->lang() ?: 'en';
		$dir = $env->getPageConfig()->getPageLanguageDir() ?:
			( ( $env->getSiteConfig()->rtl() ) ? 'rtl' : 'ltr' );

		// Indicate whether LanguageConverter is enabled, so that downstream
		// caches can split on variant (if necessary)
		$this->appendToHead( $document, 'meta', [
				'http-equiv' => 'content-language',
				'content' => $env->htmlContentLanguage()
			]
		);
		$this->appendToHead( $document, 'meta', [
				'http-equiv' => 'vary',
				'content' => $env->htmlVary()
			]
		);

		$body = DOMCompat::getBody( $document );
		$bodyCL = DOMCompat::getClassList( $body );

		$body->setAttribute( 'lang', Utils::bcp47n( $lang ) );
		$bodyCL->add( 'mw-content-' . $dir );
		$bodyCL->add( 'sitedir-' . $dir );
		$bodyCL->add( $dir );
		$body->setAttribute( 'dir', $dir );

		// Set 'mw-body-content' directly on the body.
		// This is the designated successor for #bodyContent in core skins.
		$bodyCL->add( 'mw-body-content' );
		// Set 'parsoid-body' to add the desired layout styling from Vector.
		$bodyCL->add( 'parsoid-body' );
		// Also, add the 'mediawiki' class.
		// Some Mediawiki:Common.css seem to target this selector.
		$bodyCL->add( 'mediawiki' );
		// Set 'mw-parser-output' directly on the body.
		// Templates target this class as part of the TemplateStyles RFC
		$bodyCL->add( 'mw-parser-output' );
	}

	/**
	 * @param DOMDocument $document
	 */
	public function doPostProcess( DOMDocument $document ): void {
		$env = $this->env;

		$hasDumpFlags = $env->hasDumpFlags();

		$body = DOMCompat::getBody( $document );

		if ( $hasDumpFlags && $env->hasDumpFlag( 'dom:post-builder' ) ) {
			$opts = [];
			ContentUtils::dumpDOM( $body, 'DOM: after tree builder', $opts );
		}

		$tracePP = $env->hasTraceFlag( 'time/dompp' ) || $env->hasTraceFlag( 'time' );

		$startTime = null;
		$endTime = null;
		$prefix = null;
		$logLevel = null;
		$resourceCategory = null;

		if ( $tracePP ) {
			if ( $this->atTopLevel ) {
				$prefix = 'TOP';
				// Turn off DOM pass timing tracing on non-top-level documents
				$logLevel = 'trace/time/dompp';
				$resourceCategory = 'DOMPasses:TOP';
			} else {
				$prefix = '---';
				$logLevel = 'debug/time/dompp';
				$resourceCategory = 'DOMPasses:NESTED';
			}
			$startTime = PHPUtils::getStartHRTime();
			$env->log( $logLevel, $prefix . '; start=' . $startTime );
		}

		for ( $i = 0;  $i < count( $this->processors );  $i++ ) {
			$pp = $this->processors[$i];
			if ( !empty( $pp['skipNested'] ) && !$this->atTopLevel ) {
				continue;
			}

			$ppName = null;
			$ppStart = null;

			// Trace
			if ( $tracePP ) {
				$ppName = $pp['name'] . str_repeat(
					" ",
					( strlen( $pp['name'] ) < 30 ) ? 30 - strlen( $pp['name'] ) : 0
				);
				$ppStart = PHPUtils::getStartHRTime();
				$env->log( $logLevel, $prefix . '; ' . $ppName . ' start' );
			}

			$opts = null;
			if ( $hasDumpFlags ) {
				$opts = [
					'env' => $env,
					'dumpFragmentMap' => $this->atTopLevel,
					'keepTmp' => true
				];

				if ( $env->hasDumpFlag( 'dom:pre-' . $pp['shortcut'] ) ) {
					ContentUtils::dumpDOM( $body, 'DOM: pre-' . $pp['shortcut'], $opts );
				}
			}

			$pp['proc']( $body, $this->options, $this->atTopLevel );

			if ( $hasDumpFlags && $env->hasDumpFlag( 'dom:post-' . $pp['shortcut'] ) ) {
				ContentUtils::dumpDOM( $body, 'DOM: post-' . $pp['shortcut'], $opts );
			}

			if ( $tracePP ) {
				$ppElapsed = PHPUtils::getHRTimeDifferential( $ppStart );
				$env->log(
					$logLevel,
					$prefix . '; ' . $ppName . ' end; time = ' . number_format( $ppElapsed, 5 )
				);
				$env->bumpTimeUse( $resourceCategory, $ppElapsed, 'DOM' );
			}
		}

		if ( $tracePP ) {
			$endTime = PHPUtils::getStartHRTime();
			$env->log(
				$logLevel,
				$prefix . '; end=' . number_format( $endTime, 5 ) . '; time = ' .
				number_format( PHPUtils::getHRTimeDifferential( $startTime ), 5 )
			);
		}

		// For sub-pipeline documents, we are done.
		// For the top-level document, we generate <head> and add it.
		if ( $this->atTopLevel ) {
			self::addMetaData( $env, $document );
			// @phan-suppress-next-line PhanPluginEmptyStatementIf
			if ( $env->hasTraceFlag( 'time' ) ) {
				// $env->printTimeProfile();
			}
			// @phan-suppress-next-line PhanPluginEmptyStatementIf
			if ( $env->hasDumpFlag( 'wt2html:limits' ) ) {
				/*
				$env->printWt2HtmlResourceUsage( [
					'HTML Size' => strlen( DOMCompat::getOuterHTML( $document->documentElement ) )
				] );
				*/
			}
		}
	}

	/**
	 * @inheritDoc
	 */
	public function process( $doc, array $opts = null ) {
		'@phan-var DOMDocument $doc'; // @var DOMDocument $doc
		$this->doPostProcess( $doc );
		return $doc;
	}

	/**
	 * @inheritDoc
	 */
	public function processChunkily( $input, ?array $options ): Generator {
		if ( $this->prevStage ) {
			// The previous stage will yield a DOM.
			// FIXME: Should we change the signature of that to return a DOM
			// If we do so, a pipeline stage returns either a generator or
			// concrete output (in this case, a DOM).
			$dom = $this->prevStage->processChunkily( $input, $options )->current();
		} else {
			$dom = $input;
		}
		$this->process( $dom );
		yield $dom;
	}
}
