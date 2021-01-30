<?php

namespace Flow;

use Closure;
use Flow\Exception\FlowException;
use Flow\Model\UUID;
use Html;
use LightnCandy\LightnCandy;
use LightnCandy\SafeString;
use MWTimestamp;
use OOUI\IconWidget;
use RequestContext;
use Title;

class TemplateHelper {

	/**
	 * @var string
	 */
	protected $templateDir;

	/**
	 * @var callable[]
	 */
	protected $renderers;

	/**
	 * @var bool Always compile template files
	 */
	protected $forceRecompile = false;

	/**
	 * @param string $templateDir
	 * @param bool $forceRecompile
	 */
	public function __construct( $templateDir, $forceRecompile = false ) {
		$this->templateDir = $templateDir;
		$this->forceRecompile = $forceRecompile;
	}

	/**
	 * Constructs the location of the source handlebars template
	 * and the compiled php code that goes with it.
	 *
	 * @param string $templateName
	 *
	 * @return string[]
	 * @throws FlowException Disallows upwards directory traversal via $templateName
	 */
	public function getTemplateFilenames( $templateName ) {
		// Prevent upwards directory traversal using same methods as Title::secureAndSplit,
		// which is implemented in MediaWikiTitleCodec::splitTitleString.
		if (
			strpos( $templateName, '.' ) !== false &&
			(
				$templateName === '.' || $templateName === '..' ||
				strpos( $templateName, './' ) === 0 ||
				strpos( $templateName, '../' ) === 0 ||
				strpos( $templateName, '/./' ) !== false ||
				strpos( $templateName, '/../' ) !== false ||
				substr( $templateName, -2 ) === '/.' ||
				substr( $templateName, -3 ) === '/..'
			)
		) {
			throw new FlowException( "Malformed \$templateName: $templateName" );
		}

		return [
			'template' => "{$this->templateDir}/{$templateName}.handlebars",
			'compiled' => "{$this->templateDir}/compiled/{$templateName}.handlebars.php",
		];
	}

	/**
	 * Returns a given template function if found, otherwise throws an exception.
	 *
	 * @param string $templateName
	 *
	 * @return callable
	 * @throws FlowException
	 * @throws \Exception
	 */
	public function getTemplate( $templateName ) {
		if ( isset( $this->renderers[$templateName] ) ) {
			return $this->renderers[$templateName];
		}

		$filenames = $this->getTemplateFilenames( $templateName );

		if ( $this->forceRecompile ) {
			if ( !file_exists( $filenames['template'] ) ) {
				throw new FlowException( "Could not locate template: {$filenames['template']}" );
			}

			$code = self::compile( file_get_contents( $filenames['template'] ), $this->templateDir );

			if ( !$code ) {
				throw new FlowException( "Failed to compile template '$templateName'." );
			}
			$success = file_put_contents( $filenames['compiled'], '<?php ' . $code );

			// failed to recompile template (OS permissions?); unless the
			// content hasn't changes, throw an exception!
			if ( !$success && file_get_contents( $filenames['compiled'] ) !== $code ) {
				throw new FlowException( "Failed to save updated compiled template '$templateName'" );
			}
		}

		/** @var callable $renderer */
		$renderer = require $filenames['compiled'];
		$this->renderers[$templateName] = function ( $args, array $scopes = [] ) use ( $templateName, $renderer ) {
			return $renderer( $args, $scopes );
		};
		return $this->renderers[$templateName];
	}

	/**
	 * @param string $code Handlebars code
	 * @param string $templateDir Directory templates are stored in
	 *
	 * @return string PHP code
	 * @suppress PhanTypeMismatchArgument
	 */
	public static function compile( $code, $templateDir ) {
		return LightnCandy::compile(
			$code,
			[
				'flags' => LightnCandy::FLAG_ERROR_EXCEPTION
					| LightnCandy::FLAG_EXTHELPER
					| LightnCandy::FLAG_SPVARS
					| LightnCandy::FLAG_HANDLEBARS
					| LightnCandy::FLAG_RUNTIMEPARTIAL,
				'partialresolver' => function ( $context, $name ) use ( $templateDir ) {
					$filename = "$templateDir/$name.partial.handlebars";
					if ( file_exists( $filename ) ) {
						return file_get_contents( $filename );
					}
					return null;
				},
				'helpers' => [
					'l10n' => 'Flow\TemplateHelper::l10n',
					'uuidTimestamp' => 'Flow\TemplateHelper::uuidTimestamp',
					'timestamp' => 'Flow\TemplateHelper::timestampHelper',
					'html' => 'Flow\TemplateHelper::htmlHelper',
					'block' => 'Flow\TemplateHelper::block',
					'post' => 'Flow\TemplateHelper::post',
					'historyTimestamp' => 'Flow\TemplateHelper::historyTimestamp',
					'historyDescription' => 'Flow\TemplateHelper::historyDescription',
					'showCharacterDifference' => 'Flow\TemplateHelper::showCharacterDifference',
					'l10nParse' => 'Flow\TemplateHelper::l10nParse',
					'diffRevision' => 'Flow\TemplateHelper::diffRevision',
					'diffUndo' => 'Flow\TemplateHelper::diffUndo',
					'moderationAction' => 'Flow\TemplateHelper::moderationAction',
					'concat' => 'Flow\TemplateHelper::concat',
					'linkWithReturnTo' => 'Flow\TemplateHelper::linkWithReturnTo',
					'escapeContent' => 'Flow\TemplateHelper::escapeContent',
					'enablePatrollingLink' => 'Flow\TemplateHelper::enablePatrollingLink',
					'oouify' => 'Flow\TemplateHelper::oouify',
					'getSaveOrPublishMessage' => 'Flow\TemplateHelper::getSaveOrPublishMessage',
					'eachPost' => 'Flow\TemplateHelper::eachPost',
					'ifAnonymous' => 'Flow\TemplateHelper::ifAnonymous',
					'ifCond' => 'Flow\TemplateHelper::ifCond',
					'tooltip' => 'Flow\TemplateHelper::tooltip',
					'progressiveEnhancement' => 'Flow\TemplateHelper::progressiveEnhancement',
				],
			]
		);
	}

	/**
	 * Returns HTML for a given template by calling the template function with the given args.
	 *
	 * @param string $templateName
	 * @param array $args
	 * @param array $scopes
	 *
	 * @return string
	 */
	public static function processTemplate( $templateName, array $args, array $scopes = [] ) {
		// Undesirable, but lightncandy helpers have to be static methods
		/** @var TemplateHelper $lightncandy */
		$lightncandy = Container::get( 'lightncandy' );
		$template = $lightncandy->getTemplate( $templateName );
		// @todo ugly hack...remove someday.  Requires switching to newest version
		// of lightncandy which supports recursive partial templates.
		if ( !array_key_exists( 'rootBlock', $args ) ) {
			$args['rootBlock'] = $args;
		}
		return $template( $args, $scopes );
	}

	// Helpers

	/**
	 * Generates a timestamp using the UUID, then calls the timestamp helper with it.
	 *
	 * @param string $uuid
	 *
	 * @return SafeString|null
	 */
	public static function uuidTimestamp( $uuid ) {
		$obj = UUID::create( $uuid );
		if ( !$obj ) {
			return null;
		}

		// timestamp helper expects ms timestamp
		$timestamp = (int)$obj->getTimestampObj()->getTimestamp() * 1000;
		return self::timestamp( $timestamp );
	}

	/**
	 * @param string $timestamp
	 *
	 * @return SafeString|null
	 */
	public static function timestampHelper( $timestamp ) {
		return self::timestamp( (int)$timestamp );
	}

	/**
	 * @param int $timestamp milliseconds since the unix epoch
	 *
	 * @return SafeString|null
	 */
	protected static function timestamp( $timestamp ) {
		global $wgLang, $wgUser;

		if ( !$timestamp ) {
			return null;
		}

		// source timestamps are in ms
		$timestamp /= 1000;
		$ts = new MWTimestamp( $timestamp );

		return new SafeString( self::processTemplate(
			'timestamp',
			[
				'time_iso' => $timestamp,
				'time_ago' => $wgLang->getHumanTimestamp( $ts ),
				'time_readable' => $wgLang->userTimeAndDate( $timestamp, $wgUser ),
				'guid' => null, // generated client-side
			]
		) );
	}

	/**
	 * @param string $html
	 *
	 * @return SafeString
	 */
	public static function htmlHelper( $html ) {
		return new SafeString( $html ?? 'undefined' );
	}

	/**
	 * @param array $block
	 *
	 * @return SafeString
	 */
	public static function block( $block ) {
		$template = "flow_block_" . $block['type'];
		if ( $block['block-action-template'] ) {
			$template .= '_' . $block['block-action-template'];
		}
		return new SafeString( self::processTemplate(
			$template,
			$block
		) );
	}

	/**
	 * @param array $context The 'this' value of the calling context
	 * @param mixed $postIds List of ids (roots)
	 * @param array $options blockhelper specific invocation options
	 *
	 * @return null|string HTML
	 * @throws FlowException When callbacks are not Closure instances
	 */
	public static function eachPost( array $context, $postIds, array $options ) {
		/** @var callable $inverse */
		$inverse = $options['inverse'] ?? null;
		/** @var callable $fn */
		$fn = $options['fn'];

		if ( $postIds && !is_array( $postIds ) ) {
			$postIds = [ $postIds ];
		} elseif ( $postIds === [] ) {
			// Failure callback, if any
			if ( !$inverse ) {
				return null;
			}
			if ( !$inverse instanceof Closure ) {
				throw new FlowException( 'Invalid inverse callback, expected Closure' );
			}
			return $inverse( $options['cx'], [] );
		} else {
			return null;
		}

		if ( !$fn instanceof Closure ) {
			throw new FlowException( 'Invalid callback, expected Closure' );
		}
		$html = [];
		foreach ( $postIds as $id ) {
			$revId = $context['posts'][$id][0] ?? '';

			if ( !$revId || !isset( $context['revisions'][$revId] ) ) {
				throw new FlowException( "Revision not available: $revId. Post ID: $id" );
			}

			// $fn is always safe return value, it's the inner template content.
			$html[] = $fn( $context['revisions'][$revId] );
		}

		// Return the resulting HTML
		return implode( '', $html );
	}

	/**
	 * Required to prevent recursion loop rendering nested posts
	 *
	 * @param array $rootBlock
	 * @param array $revision
	 *
	 * @return SafeString
	 */
	public static function post( $rootBlock, $revision ) {
		return new SafeString( self::processTemplate( 'flow_post', [
			'revision' => $revision,
			'rootBlock' => $rootBlock,
		] ) );
	}

	/**
	 * @param array $revision
	 *
	 * @return SafeString
	 */
	public static function historyTimestamp( $revision ) {
		$raw = false;
		$formattedTime = $revision['dateFormats']['timeAndDate'];
		$formattedTimeOutput = '';
		$linkKeys = [ 'header-revision', 'topic-revision', 'post-revision', 'summary-revision' ];
		foreach ( $linkKeys as $linkKey ) {
			if ( isset( $revision['links'][$linkKey] ) ) {
				$link = $revision['links'][$linkKey];
				$formattedTimeOutput = Html::element(
					'a',
					[
						'href' => $link['url'],
						'title' => $link['title'],
					],
					$formattedTime
				);
				$raw = true;
				break;
			}
		}

		if ( $raw === false ) {
			$formattedTimeOutput = htmlspecialchars( $formattedTime );
		}

		$class = [ 'mw-changeslist-date' ];
		if ( $revision['isModeratedNotLocked'] ) {
			$class[] = 'history-deleted';
		}

		// @phan-suppress-next-line SecurityCheck-DoubleEscaped
		return new SafeString(
			'<span class="plainlinks">'
			. Html::rawElement( 'span', [ 'class' => $class ], $formattedTimeOutput )
			. '</span>'
		);
	}

	/**
	 * @param array $revision
	 *
	 * @return SafeString|null
	 */
	public static function historyDescription( $revision ) {
		if ( !isset( $revision['properties']['_key'] ) ) {
			return null;
		}

		$i18nKey = $revision['properties']['_key'];
		unset( $revision['properties']['_key'] );

		// $revision['properties'] contains the params for the i18n message, which are named,
		// so we need array_values() to strip the names. They are in the correct order because
		// RevisionFormatter::getDescriptionParams() uses a foreach loop to build this array
		// from the i18n-params definition in FlowActions.php.
		// A variety of the i18n history messages contain wikitext and require ->parse().
		// @phan-suppress-next-line SecurityCheck-DoubleEscaped
		return new SafeString( wfMessage( $i18nKey, array_values( $revision['properties'] ) )->parse() );
	}

	/**
	 * @param string $old
	 * @param string $new
	 *
	 * @return SafeString
	 */
	public static function showCharacterDifference( $old, $new ) {
		// @phan-suppress-next-line SecurityCheck-DoubleEscaped
		return new SafeString( \ChangesList::showCharacterDifference( (int)$old, (int)$new ) );
	}

	/**
	 * Creates a special script tag to be processed client-side. This contains extra template HTML, which allows
	 * the front-end to "progressively enhance" the page with more content which isn't needed in a non-JS state.
	 *
	 * @see FlowHandlebars.prototype.progressiveEnhancement in flow-handlebars.js for more details.
	 *
	 * @param array $options
	 *
	 * @return SafeString
	 */
	public static function progressiveEnhancement( array $options ) {
		$fn = $options['fn'];
		$input = $options['hash'];
		$insertionType = empty( $input['type'] ) ? 'insert' : htmlspecialchars( $input['type'] );
		$target = empty( $input['target'] ) ? '' : 'data-target="' . htmlspecialchars( $input['target'] ) . '"';
		$sectionId = empty( $input['id'] ) ? '' : 'id="' . htmlspecialchars( $input['id'] ) . '"';

		// @phan-suppress-next-line SecurityCheck-DoubleEscaped
		return new SafeString(
			'<script name="handlebars-template-progressive-enhancement"' .
				' type="text/x-handlebars-template-progressive-enhancement"' .
				' data-type="' . $insertionType . '"' .
				' ' . $target .
				' ' . $sectionId .
			'>' .
				// Replace the nested script tag with a placeholder tag for recursive progressiveEnhancement
				str_replace( '</script>', '</flowprogressivescript>', $fn() ) .
			'</script>'
		);
	}

	/**
	 * A helper to output OOUI widgets.
	 *
	 * @param array ...$args one or more arguments, i18n key and parameters
	 * @return \OOUI\Widget|null
	 */
	public static function oouify( ...$args ) {
		$options = array_pop( $args );
		$named = $options['hash'];

		$widgetType = $named[ 'type' ];
		$data = [];

		$classes = [];
		if ( isset( $named['classes'] ) ) {
			$classes = explode( ' ', $named[ 'classes' ] );
		}

		// Push raw arguments
		$data['args'] = $args;
		$baseConfig = [
			// 'infusable' => true,
			'id' => $named[ 'name' ] ?? null,
			'classes' => $classes,
			'data' => $data
		];
		$widget = null;
		switch ( $widgetType ) {
			case 'BoardDescriptionWidget':
				$dataArgs = [
					'infusable' => false,
					'description' => $args[0],
					'editLink' => $args[1]
				];
				$widget = new OOUI\BoardDescriptionWidget( $baseConfig + $dataArgs );
				break;
			case 'IconWidget':
				$dataArgs = [
					'icon' => $args[0],
				];
				$widget = new IconWidget( $baseConfig + $dataArgs );
				break;
		}

		return $widget;
	}

	/**
	 * @param array ...$args one or more arguments, i18n key and parameters
	 *
	 * @return string Message output, using the 'text' format
	 */
	public static function l10n( ...$args ) {
		$options = array_pop( $args );
		$str = array_shift( $args );

		return wfMessage( $str )->params( $args )->text();
	}

	/**
	 * @param array ...$args one or more arguments, i18n key and parameters
	 *
	 * @return SafeString HTML
	 */
	public static function l10nParse( ...$args ) {
		$options = array_pop( $args );
		$str = array_shift( $args );
		// @phan-suppress-next-line SecurityCheck-DoubleEscaped
		return new SafeString( wfMessage( $str, $args )->parse() );
	}

	/**
	 * A helper to output whether a wiki is publish wiki or not
	 *
	 * @param array $options
	 * @return string Translated message string for either 'save' or 'publish'
	 *  version
	 */
	public static function getSaveOrPublishMessage( array $options ) {
		global $wgEditSubmitButtonLabelPublish;
		$named = $options['hash'];

		if ( !$named['save'] || !$named['publish'] ) {
			throw new FlowException( "Missing an argument. Expected two message keys for 'save' and 'post'" );
		}

		$msg = $wgEditSubmitButtonLabelPublish ? $named['publish'] : $named['save'];

		return wfMessage( $msg )->text();
	}

	/**
	 * @param array $data RevisionDiffViewFormatter::formatApi return value
	 *
	 * @return SafeString
	 */
	public static function diffRevision( $data ) {
		$differenceEngine = new \DifferenceEngine();
		$notice = '';
		if ( $data['diff_content'] === '' ) {
			$notice .= '<div class="mw-diff-empty">' .
				wfMessage( 'diff-empty' )->parse() .
				"</div>\n";
		}
		// Work around exception in DifferenceEngine::showDiffStyle() (T202454)
		$out = RequestContext::getMain()->getOutput();
		$out->addModuleStyles( 'mediawiki.diff.styles' );

		$renderer = Container::get( 'lightncandy' )->getTemplate( 'flow_revision_diff_header' );

		// @phan-suppress-next-line SecurityCheck-DoubleEscaped
		return new SafeString( $differenceEngine->addHeader(
			$data['diff_content'],
			$renderer( [
				'old' => true,
				'revision' => $data['old'],
				'links' => $data['links'],
			] ),
			$renderer( [
				'new' => true,
				'revision' => $data['new'],
				'links' => $data['links'],
			] ),
			// FIXME we should be passing in a multinotice for multi-rev diffs here
			'',
			$notice
		) );
	}

	public static function diffUndo( $diffContent ) {
		$differenceEngine = new \DifferenceEngine();
		$notice = '';
		if ( $diffContent === '' ) {
			$notice = '<div class="mw-diff-empty">' .
				wfMessage( 'diff-empty' )->parse() .
				"</div>\n";
		}
		// Work around exception in DifferenceEngine::showDiffStyle() (T202454)
		$out = RequestContext::getMain()->getOutput();
		$out->addModuleStyles( 'mediawiki.diff.styles' );

		// @phan-suppress-next-line SecurityCheck-DoubleEscaped
		return new SafeString( $differenceEngine->addHeader(
			$diffContent,
			wfMessage( 'flow-undo-latest-revision' )->parse(),
			wfMessage( 'flow-undo-your-text' )->parse(),
			// FIXME we should be passing in a multinotice for multi-rev diffs here
			'',
			$notice
		) );
	}

	/**
	 * @param array $actions
	 * @param string $moderationState
	 *
	 * @return string
	 */
	public static function moderationAction( $actions, $moderationState ) {
		return isset( $actions[$moderationState] ) ? $actions[$moderationState]['url'] : '';
	}

	/**
	 * @param string ...$args Expects one or more strings to join
	 *
	 * @return string all unnamed arguments joined together
	 */
	public static function concat( ...$args ) {
		$options = array_pop( $args );
		return implode( '', $args );
	}

	/**
	 * Runs a callback when user is anonymous
	 *
	 * @param array $options which must contain fn and inverse key mapping to functions.
	 *
	 * @return mixed result of callback
	 * @throws FlowException Fails when callbacks are not Closure instances
	 */
	public static function ifAnonymous( $options ) {
		if ( RequestContext::getMain()->getUser()->isAnon() ) {
			$fn = $options['fn'];
			if ( !$fn instanceof Closure ) {
				throw new FlowException( 'Expected callback to be Closuire instance' );
			}
		} elseif ( isset( $options['inverse'] ) ) {
			$fn = $options['inverse'];
			if ( !$fn instanceof Closure ) {
				throw new FlowException( 'Expected inverse callback to be Closuire instance' );
			}
		} else {
			return '';
		}

		return $fn();
	}

	/**
	 * Adds returnto parameter pointing to current page to existing URL
	 *
	 * @param string $url to modify
	 *
	 * @return string modified url
	 */
	protected static function addReturnTo( $url ) {
		$ctx = RequestContext::getMain();
		$returnTo = $ctx->getTitle();
		if ( !$returnTo ) {
			return $url;
		}
		// We can't get only the query parameters from
		$returnToQuery = $ctx->getRequest()->getQueryValues();

		unset( $returnToQuery['title'] );

		$args = [
			'returnto' => $returnTo->getPrefixedURL(),
		];
		if ( $returnToQuery ) {
			$args['returntoquery'] = wfArrayToCgi( $returnToQuery );
		}
		return wfAppendQuery( $url, wfArrayToCgi( $args ) );
	}

	/**
	 * Adds returnto parameter pointing to given Title to an existing URL
	 *
	 * @param string $title
	 *
	 * @return string modified url
	 */
	public static function linkWithReturnTo( $title ) {
		$title = Title::newFromText( $title );
		if ( !$title ) {
			return '';
		}
		// FIXME: This should use local url to avoid redirects on mobile. See bug 66746.
		$url = $title->getFullURL();

		return self::addReturnTo( $url );
	}

	/**
	 * Accepts the contentType and content properties returned from the api
	 * for individual revisions and ensures that content is included in the
	 * final html page in an xss safe maner.
	 *
	 * It is expected that all content with contentType of html has been
	 * processed by parsoid and is safe for direct output into the document.
	 *
	 * @param string $contentType
	 * @param string $content
	 *
	 * @return string|SafeString
	 */
	public static function escapeContent( $contentType, $content ) {
		return in_array( $contentType, [ 'html', 'fixed-html', 'topic-title-html' ] ) ?
			new SafeString( $content ) :
			$content;
	}

	/**
	 * Only perform action when conditions match
	 *
	 * @param string $value
	 * @param string $operator e.g. 'or'
	 * @param string $value2 to compare with
	 * @param array $options lightncandy hbhelper options
	 *
	 * @return mixed result of callback
	 * @throws FlowException Fails when callbacks are not Closure instances
	 */
	public static function ifCond( $value, $operator, $value2, array $options ) {
		$doCallback = false;

		// Perform operator
		// FIXME: Rename to || to be consistent with other operators
		if ( $operator === 'or' ) {
			if ( $value || $value2 ) {
				$doCallback = true;
			}
		} elseif ( $operator === '===' ) {
			if ( $value === $value2 ) {
				$doCallback = true;
			}
		} elseif ( $operator === '!==' ) {
			if ( $value !== $value2 ) {
				$doCallback = true;
			}
		} else {
			return '';
		}

		if ( $doCallback ) {
			$fn = $options['fn'];
			if ( !$fn instanceof Closure ) {
				throw new FlowException( 'Expected callback to be Closure instance' );
			}
			return $fn();
		} elseif ( isset( $options['inverse'] ) ) {
			$inverse = $options['inverse'];
			if ( !$inverse instanceof Closure ) {
				throw new FlowException( 'Expected inverse callback to be Closure instance' );
			}
			return $inverse();
		} else {
			return '';
		}
	}

	/**
	 * @param array $options
	 *
	 * @return string tooltip
	 */
	public static function tooltip( $options ) {
		$fn = $options['fn'];
		$params = $options['hash'];

		return (
			self::processTemplate( 'flow_tooltip', [
				'positionClass' => $params['positionClass'] ? 'flow-ui-tooltip-' . $params['positionClass'] : null,
				'contextClass' => $params['contextClass'] ? 'mw-ui-' . $params['contextClass'] : null,
				'extraClass' => $params['extraClass'] ?: '',
				'blockClass' => $params['isBlock'] ? 'flow-ui-tooltip-block' : null,
				'content' => $fn(),
			] )
		);
	}

	/**
	 * Enhance the patrolling link and protect it.
	 */
	public static function enablePatrollingLink() {
		$outputPage = RequestContext::getMain()->getOutput();

		// Enhance the patrol link with ajax
		// FIXME: This duplicates DifferenceEngine::markPatrolledLink.
		$outputPage->preventClickjacking();
		$outputPage->addModules( 'mediawiki.misc-authed-curate' );
	}
}
