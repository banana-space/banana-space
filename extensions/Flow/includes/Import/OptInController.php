<?php

namespace Flow\Import;

use DateTime;
use DateTimeZone;
use DeferredUpdates;
use DerivativeContext;
use Exception;
use Flow\Block\AbstractBlock;
use Flow\Collection\HeaderCollection;
use Flow\Container;
use Flow\Content\BoardContent;
use Flow\Conversion\Utils;
use Flow\DbFactory;
use Flow\Exception\InvalidDataException;
use Flow\Notifications\Controller;
use Flow\OccupationController;
use Flow\WorkflowLoader;
use Flow\WorkflowLoaderFactory;
use FormatJson;
use IContextSource;
use MediaWiki\Logger\LoggerFactory;
use MediaWiki\MediaWikiServices;
use MediaWiki\Revision\RevisionRecord;
use MediaWiki\Revision\SlotRecord;
use MovePage;
use ParserOptions;
use Psr\Log\LoggerInterface;
use RequestContext;
use Title;
use User;
use WikiPage;
use WikitextContent;

/**
 * Entry point for enabling Flow on a page.
 */
class OptInController {
	public static $ENABLE = 'enable';
	public static $DISABLE = 'disable';

	/**
	 * @var OccupationController
	 */
	private $occupationController;

	/**
	 * @var Controller
	 */
	private $notificationController;

	/**
	 * @var ArchiveNameHelper
	 */
	private $archiveNameHelper;

	/**
	 * @var DbFactory
	 */
	private $dbFactory;

	/**
	 * @var LoggerInterface
	 */
	private $logger;

	/**
	 * @var IContextSource
	 */
	private $context;

	/**
	 * @var User
	 */
	private $user;

	/**
	 * @param OccupationController $occupationController
	 * @param Controller $notificationController
	 * @param ArchiveNameHelper $archiveNameHelper
	 * @param DbFactory $dbFactory
	 * @param LoggerInterface $logger Logger for errors and exceptions
	 * @param User $scriptUser User that takes actions, such as creating the board or
	 *   editing descriptions
	 */
	public function __construct(
		OccupationController $occupationController,
		Controller $notificationController,
		ArchiveNameHelper $archiveNameHelper,
		DbFactory $dbFactory,
		LoggerInterface $logger,
		User $scriptUser
	) {
		$this->occupationController = $occupationController;
		$this->notificationController = $notificationController;
		$this->archiveNameHelper = $archiveNameHelper;
		$this->dbFactory = $dbFactory;
		$this->logger = $logger;
		$this->user = $scriptUser;
		$this->context = new DerivativeContext( RequestContext::getMain() );
		$this->context->setUser( $this->user );
	}

	/**
	 * @param string $action Action to take, self::ENABLE or self::DISABLE
	 * @param Title $talkpage Title of user's talk page
	 * @param User $user User that owns the talk page
	 */
	public function initiateChange( $action, Title $talkpage, User $user ) {
		$outerMethod = __METHOD__;
		$logger = $this->logger;

		DeferredUpdates::addCallableUpdate(
			function () use ( $logger, $outerMethod, $action, $talkpage, $user ) {
				try {
					if ( $action === self::$ENABLE ) {
						$this->enable( $talkpage, $user );
					} elseif ( $action === self::$DISABLE ) {
						$this->disable( $talkpage );
					} else {
						$logger->error( $outerMethod . ': unrecognized action: ' . $action );
					}
				} catch ( Exception $exception ) {
					$logger->error(
						$outerMethod . ' failed to {action} Flow on \'{talkpage}\' for user \'{user}\'. {message} {trace}',
						[
							'action' => $action,
							'talkpage' => $talkpage->getPrefixedText(),
							'user' => $user->getName(),
							'message' => $exception->getMessage(),
							'trace' => $exception->getTraceAsString(),
						]
					);

					// Rollback both Flow and Core DBs.
					MediaWikiServices::getInstance()->getDBLoadBalancerFactory()
						->rollbackMasterChanges( $outerMethod );
				}
			},
			DeferredUpdates::POSTSEND
		);
	}

	/**
	 * @param Title $title
	 * @param User $user
	 */
	public function enable( Title $title, User $user ) {
		if ( $this->isFlowBoard( $title ) ) {
			// already a Flow board
			return;
		}

		// archive existing wikitext talk page
		$currentTemplate = null;
		$templatesFromTalkpage = null;
		if ( $title->exists( Title::GAID_FOR_UPDATE ) ) {
			$templatesFromTalkpage = $this->extractTemplatesAboveFirstSection( $title );
			$wikitextTalkpageArchiveTitle = $this->archiveExistingTalkpage( $title );
			$currentTemplate = $this->getFormattedCurrentTemplate( $wikitextTalkpageArchiveTitle );
		}

		// create or restore flow board
		$archivedFlowPage = $this->findLatestFlowArchive( $title );
		if ( $archivedFlowPage ) {
			$this->restoreExistingFlowBoard( $archivedFlowPage, $title, $currentTemplate );
		} else {
			$this->createFlowBoard( $title, $templatesFromTalkpage . "\n\n" . $currentTemplate );
			$this->notificationController->notifyFlowEnabledOnTalkpage( $user );
		}
	}

	/**
	 * @param Title $title
	 */
	public function disable( Title $title ) {
		if ( !$this->isFlowBoard( $title ) ) {
			return;
		}

		// archive the flow board
		$flowArchiveTitle = $this->archiveFlowBoard( $title );

		// restore the original wikitext talk page
		$archivedTalkpage = $this->findLatestArchive( $title );
		if ( $archivedTalkpage ) {
			$this->removeArchiveTemplateFromWikitextTalkpage( $archivedTalkpage );
			$this->addCurrentTemplate( $archivedTalkpage, $flowArchiveTitle );
			$restoreReason = wfMessage( 'flow-optin-restore-wikitext' )->inContentLanguage()->text();
			$this->movePage( $archivedTalkpage, $title, $restoreReason );
		}
	}

	/**
	 * Check whether the current user has a flow board archived already.
	 *
	 * @param User $user
	 * @return bool Flow board archive exists
	 */
	public function hasFlowBoardArchive( User $user ) {
		return $this->findLatestFlowArchive( $user->getTalkPage() ) !== false;
	}

	/**
	 * @param Title $title
	 * @return bool
	 */
	private function isFlowBoard( Title $title ) {
		return $title->getContentModel( Title::GAID_FOR_UPDATE ) === CONTENT_MODEL_FLOW_BOARD;
	}

	/**
	 * @param Title $from
	 * @param Title $to
	 * @param string $reason
	 */
	private function movePage( Title $from, Title $to, $reason = '' ) {
		$this->occupationController->forceAllowCreation( $to );
		$mp = new MovePage( $from, $to );
		$mp->move( $this->user, $reason, false );

		/*
		 * Article IDs are cached inside title objects. Since we'll be
		 * reusing these objects, we have to make sure they reflect the
		 * correct IDs.
		 * We could just Title::GAID_FOR_UPDATE everywhere, but that would
		 * result in a lot of unneeded calls to master.
		 * If these IDs are wrong, we could end up associating workflows
		 * with an incorrect page (that was just moved)
		 *
		 * Anyway, the page has just been moved without redirect, so that
		 * page is no longer valid.
		 */
		$from->resetArticleID( 0 );
		$linkCache = MediaWikiServices::getInstance()->getLinkCache();
		$linkCache->addBadLinkObj( $from );

		/*
		 * Force id cached inside $title to be updated, as well as info
		 * inside LinkCache.
		 */
		$to->getArticleID( Title::GAID_FOR_UPDATE );
	}

	/**
	 * @param string $msgKey
	 * @param mixed $args
	 * @throws ImportException
	 */
	private function fatal( $msgKey, $args = [] ) {
		throw new ImportException( wfMessage( $msgKey, $args )->inContentLanguage()->text() );
	}

	/**
	 * @param string $str
	 * @return string[]
	 */
	private function fromNewlineSeparated( $str ) {
		return explode( "\n", $str );
	}

	/**
	 * @param Title $title
	 * @return Title|false
	 */
	private function findLatestArchive( Title $title ) {
		$archiveFormats = $this->fromNewlineSeparated(
			wfMessage( 'flow-conversion-archive-page-name-format' )->inContentLanguage()->plain() );
		return $this->archiveNameHelper->findLatestArchiveTitle( $title, $archiveFormats );
	}

	/**
	 * @param Title $title
	 * @return Title
	 * @throws ImportException
	 */
	private function findNextArchive( Title $title ) {
		$archiveFormats = $this->fromNewlineSeparated(
			wfMessage( 'flow-conversion-archive-page-name-format' )->inContentLanguage()->plain() );
		return $this->archiveNameHelper->decideArchiveTitle( $title, $archiveFormats );
	}

	/**
	 * @param Title $title
	 * @return Title|false
	 */
	private function findLatestFlowArchive( Title $title ) {
		$archiveFormats = $this->fromNewlineSeparated(
			wfMessage( 'flow-conversion-archive-flow-page-name-format' )->inContentLanguage()->plain() );
		return $this->archiveNameHelper->findLatestArchiveTitle( $title, $archiveFormats );
	}

	/**
	 * @param Title $title
	 * @return Title
	 * @throws ImportException
	 */
	private function findNextFlowArchive( Title $title ) {
		$archiveFormats = $this->fromNewlineSeparated(
			wfMessage( 'flow-conversion-archive-flow-page-name-format' )->inContentLanguage()->plain() );
		return $this->archiveNameHelper->decideArchiveTitle( $title, $archiveFormats );
	}

	/**
	 * @param Title $title
	 * @param string $contentText
	 * @param string $summary
	 * @throws ImportException
	 * @throws \MWException
	 * @param-taint escapes_escaped $contentText
	 */
	private function createRevision( Title $title, $contentText, $summary ) {
		$page = WikiPage::factory( $title );
		$newContent = new WikitextContent( $contentText );
		$status = $page->doEditContent(
			$newContent,
			$summary,
			EDIT_FORCE_BOT | EDIT_SUPPRESS_RC,
			false,
			$this->user
		);

		if ( !$status->isGood() ) {
			throw new ImportException( "Failed creating revision at {$title}" );
		}
	}

	/**
	 * @param Title $title
	 * @param string $boardDescription
	 * @throws ImportException
	 * @throws \Flow\Exception\CrossWikiException
	 * @throws \Flow\Exception\InvalidInputException
	 */
	private function createFlowBoard( Title $title, $boardDescription ) {
		/** @var WorkflowLoaderFactory $loaderFactory */
		$loaderFactory = Container::get( 'factory.loader.workflow' );
		$page = $title->getPrefixedText();

		$creationStatus = $this->occupationController->safeAllowCreation( $title, $this->user, false );
		if ( !$creationStatus->isGood() ) {
			$this->fatal( 'flow-special-enableflow-board-creation-not-allowed', $page );
		}

		$loader = $loaderFactory->createWorkflowLoader( $title );
		$blocks = $loader->getBlocks();
		$this->logBlockErrors( $blocks );

		if ( !$boardDescription ) {
			$boardDescription = ' ';
		}

		$action = 'edit-header';
		$params = [
			'header' => [
				'content' => $boardDescription,
				'format' => 'wikitext',
			],
		];

		$blocksToCommit = $loader->handleSubmit(
			$this->context,
			$action,
			$params
		);

		foreach ( $blocks as $block ) {
			if ( $block->hasErrors() ) {
				$errors = $block->getErrors();

				foreach ( $errors as $errorKey ) {
					$this->fatal( $block->getErrorMessage( $errorKey ) );
				}
			}
		}

		$loader->commit( $blocksToCommit );
	}

	/**
	 * @param Title $title
	 * @return Title
	 */
	private function archiveExistingTalkpage( Title $title ) {
		$archiveTitle = $this->findNextArchive( $title );
		$archiveReason = wfMessage( 'flow-optin-archive-wikitext' )->inContentLanguage()->text();
		$this->movePage( $title, $archiveTitle, $archiveReason );

		$content = $this->getContent( $archiveTitle );
		$content = $this->removeCurrentTemplateFromWikitext( $content, $archiveTitle );
		$content = $this->getFormattedArchiveTemplate( $title ) . "\n\n" . $content;

		$addTemplateReason = wfMessage( 'flow-beta-feature-add-archive-template-edit-summary' )->inContentLanguage()->plain();
		$this->createRevision(
			$archiveTitle,
			$content,
			$addTemplateReason
		);

		return $archiveTitle;
	}

	/**
	 * @param Title $archivedFlowPage
	 * @param Title $title
	 * @param string|null $currentTemplate
	 */
	private function restoreExistingFlowBoard( Title $archivedFlowPage, Title $title, $currentTemplate = null ) {
		$this->editBoardDescription(
			$archivedFlowPage,
			function ( $content ) use ( $currentTemplate, $archivedFlowPage ) {
				$templateName = wfMessage( 'flow-importer-wt-converted-archive-template' )->inContentLanguage()->plain();
				$content = TemplateHelper::removeFromHtml( $content, $templateName );
				if ( $currentTemplate ) {
					$content = Utils::convert( 'wikitext', 'html', $currentTemplate, $archivedFlowPage ) . "<br/><br/>" . $content;
				}
				return $content;
			},
			'html'
		);

		$restoreReason = wfMessage( 'flow-optin-restore-flow-board' )->inContentLanguage()->text();
		$this->movePage( $archivedFlowPage, $title, $restoreReason );
	}

	/**
	 * @param Title $title
	 * @return string
	 * @throws \MWException
	 */
	private function getContent( Title $title ) {
		$page = WikiPage::factory( $title );
		$page->loadPageData( WikiPage::READ_LATEST );
		$revision = $page->getRevisionRecord();
		if ( $revision ) {
			$content = $revision->getContent( SlotRecord::MAIN, RevisionRecord::FOR_PUBLIC );
			if ( $content instanceof WikitextContent ) {
				return $content->getNativeData();
			}
		}

		return '';
	}

	/**
	 * @param Title $archiveTitle
	 * @return string
	 */
	private function getFormattedCurrentTemplate( Title $archiveTitle ) {
		$now = new DateTime( "now", new DateTimeZone( "GMT" ) );
		$arguments = [
			'archive' => $archiveTitle->getPrefixedText(),
			'date' => $now->format( 'Y-m-d' ),
		];
		$template = wfMessage( 'flow-importer-wt-converted-template' )->inContentLanguage()->plain();
		return $this->formatTemplate( $template, $arguments );
	}

	/**
	 * @param string $name
	 * @param array $args
	 * @return string
	 */
	private function formatTemplate( $name, array $args ) {
		$arguments = implode( '|',
			array_map(
				function ( $key, $value ) {
					return "$key=$value";
				},
				array_keys( $args ),
				array_values( $args ) )
		);
		return "{{{$name}|$arguments}}";
	}

	/**
	 * @param Title $title
	 * @param callable $newDescriptionCallback
	 * @param string $format
	 * @throws ImportException
	 * @throws InvalidDataException
	 */
	private function editBoardDescription( Title $title, callable $newDescriptionCallback, $format = 'html' ) {
		/*
		 * We could use WorkflowLoaderFactory::createWorkflowLoader
		 * to get to the workflow ID, but that uses WikiPage::factory
		 * to build the wikipage & get the content. For most requests,
		 * that'll be better (it reads from replicas), but we really
		 * need to read from master here.
		 * We'll need WorkflowLoader further down anyway, but we'll
		 * then have the correct workflow ID to initialize it with!
		 *
		 * $title->getLatestRevId() should be fine, it'll be read from
		 * LinkCache, which has been updated.
		 * RevisionLookup::getRevisionById will try replica first.
		 * If it can't find the id, it'll try to find it on master.
		 */
		$revId = $title->getLatestRevID();
		$revRecord = MediaWikiServices::getInstance()
			->getRevisionLookup()
			->getRevisionById( $revId );
		$content = $revRecord->getContent( SlotRecord::MAIN );
		if ( !$content instanceof BoardContent ) {
			throw new InvalidDataException(
				'Could not find board page for ' . $title->getPrefixedDBkey() . ' (id: ' . $title->getArticleID() . ').' .
				'Found content: ' . var_export( $content, true )
			);
		}
		$workflowId = $content->getWorkflowId();

		$collection = HeaderCollection::newFromId( $workflowId );
		$revision = $collection->getLastRevision();

		/*
		 * We could just do $revision->getContent( $format ), but that
		 * may need to find $title in order to convert.
		 * We already know $title (and don't want to risk it being used
		 * in a way it stores lagging replica data), so let's just
		 * manually convert the content.
		 */
		$content = $revision->getContentRaw();
		$content = Utils::convert( $revision->getContentFormat(), $format, $content, $title );

		$newDescription = $newDescriptionCallback( $content );

		$action = 'edit-header';
		$params = [
			'header' => [
				'content' => $newDescription,
				'format' => $format,
				'prev_revision' => $revision->getRevisionId()->getAlphadecimal()
			],
		];

		/** @var WorkflowLoaderFactory $factory */
		$factory = Container::get( 'factory.loader.workflow' );

		/** @var WorkflowLoader $loader */
		$loader = $factory->createWorkflowLoader( $title, $workflowId );

		$blocks = $loader->getBlocks();
		$this->logBlockErrors( $blocks );

		$blocksToCommit = $loader->handleSubmit(
			$this->context,
			$action,
			$params
		);

		foreach ( $blocks as $block ) {
			if ( $block->hasErrors() ) {
				$errors = $block->getErrors();

				foreach ( $errors as $errorKey ) {
					$this->fatal( $block->getErrorMessage( $errorKey ) );
				}
			}
		}

		$loader->commit( $blocksToCommit );
	}

	/**
	 * @param Title $current
	 * @return string
	 */
	private function getFormattedArchiveTemplate( Title $current ) {
		$templateName = wfMessage( 'flow-importer-wt-converted-archive-template' )->inContentLanguage()->plain();
		$now = new DateTime( "now", new DateTimeZone( "GMT" ) );
		return $this->formatTemplate( $templateName, [
			'from' => $current->getPrefixedText(),
			'date' => $now->format( 'Y-m-d' ),
		] );
	}

	/**
	 * @param Title $title
	 * @throws ImportException
	 */
	private function removeArchiveTemplateFromWikitextTalkpage( Title $title ) {
		$wtContent = $this->getContent( $title );
		if ( !$wtContent ) {
			return;
		}

		$content = Utils::convert( 'wikitext', 'html', $wtContent, $title );
		$templateName = wfMessage( 'flow-importer-wt-converted-archive-template' )->inContentLanguage()->plain();

		$newContent = TemplateHelper::removeFromHtml( $content, $templateName );

		$this->createRevision(
			$title,
			Utils::convert( 'html', 'wikitext', $newContent, $title ),
			wfMessage( 'flow-beta-feature-remove-archive-template-edit-summary' )->inContentLanguage()->plain() );
	}

	/**
	 * @param string $wikitextContent
	 * @param Title $title
	 * @return string
	 */
	private function removeCurrentTemplateFromWikitext( $wikitextContent, Title $title ) {
		$templateName = wfMessage( 'flow-importer-wt-converted-template' )->inContentLanguage()->plain();
		$contentAsHtml = Utils::convert( 'wikitext', 'html', $wikitextContent, $title );
		$contentWithoutTemplate = TemplateHelper::removeFromHtml( $contentAsHtml, $templateName );
		return Utils::convert( 'html', 'wikitext', $contentWithoutTemplate, $title );
	}

	/**
	 * @param Title $title
	 * @return string
	 */
	private function extractTemplatesAboveFirstSection( Title $title ) {
		$content = $this->getContent( $title );
		if ( !$content ) {
			return '';
		}

		$parser = MediaWikiServices::getInstance()->getParserFactory()->create();
		$output = $parser->parse( $content, $title, new ParserOptions( $this->user ) );
		$sections = $output->getSections();
		if ( $sections ) {
			$content = substr( $content, 0, $sections[0]['byteoffset'] );
		}
		return TemplateHelper::extractTemplates( $content, $title );
	}

	/**
	 * @param Title $title
	 * @param string $reason
	 * @param callable $newDescriptionCallback
	 * @param string $format
	 * @throws ImportException
	 * @throws InvalidDataException
	 */
	private function editWikitextContent( Title $title, $reason, callable $newDescriptionCallback, $format = 'html' ) {
		$content = Utils::convert( 'wikitext', $format, $this->getContent( $title ), $title );
		$newContent = $newDescriptionCallback( $content );
		$this->createRevision(
			$title,
			Utils::convert( $format, 'wikitext', $newContent, $title ),
			$reason
		);
	}

	/**
	 * Add the "current" template to the page considered the current talkpage
	 * and link to the archived talkpage.
	 *
	 * @param Title $currentTalkpageTitle
	 * @param Title $archivedTalkpageTitle
	 */
	private function addCurrentTemplate( Title $currentTalkpageTitle, Title $archivedTalkpageTitle ) {
		$template = $this->getFormattedCurrentTemplate( $archivedTalkpageTitle );
		$this->editWikitextContent(
			$currentTalkpageTitle,
			wfMessage( 'flow-beta-feature-add-current-template-edit-summary' )->inContentLanguage()->plain(),
			function ( $content ) use ( $template ) {
				return $template . "\n\n" . $content;
			},
			'wikitext'
		);
	}

	/**
	 * @param Title $title
	 * @return Title
	 * @throws InvalidDataException
	 */
	private function archiveFlowBoard( Title $title ) {
		$flowArchiveTitle = $this->findNextFlowArchive( $title );
		$archiveReason = wfMessage( 'flow-optin-archive-flow-board' )->inContentLanguage()->text();
		$this->movePage( $title, $flowArchiveTitle, $archiveReason );

		$template = $this->getFormattedArchiveTemplate( $title );
		$template = Utils::convert( 'wikitext', 'html', $template, $title );

		$this->editBoardDescription(
			$flowArchiveTitle,
			function ( $content ) use ( $template ) {
				$templateName = wfMessage( 'flow-importer-wt-converted-template' )->inContentLanguage()->plain();
				$content = TemplateHelper::removeFromHtml( $content, $templateName );
				return $template . "<br/><br/>" . $content;
			},
			'html' );

		return $flowArchiveTitle;
	}

	/**
	 * @param array $blocks
	 */
	private function logBlockErrors( array $blocks ) {
		$errors = [];
		/** @var AbstractBlock $block */
		foreach ( $blocks as $block ) {
			if ( $block->hasErrors() ) {
				$blockErrors = $block->getErrors();
				foreach ( $blockErrors as $blockErrorType ) {
					$errors[ $block->getName() ] = [
						'type' => $blockErrorType,
						'message' => $block->getErrorMessage( $blockErrorType ),
						'extra' => $block->getErrorExtra( $blockErrorType )
					];
				}
			}
		}
		if ( $errors ) {
			LoggerFactory::getInstance( 'Flow' )->error(
				'Found {count} block errors for user {user_id}',
				[
					'count' => count( $errors ),
					'user_id' => RequestContext::getMain()->getUser()->getId(),
					'errors' => FormatJson::encode( $errors )
				]
			);
		}
	}
}
