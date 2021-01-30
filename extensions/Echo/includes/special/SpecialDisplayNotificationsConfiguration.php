<?php

class SpecialDisplayNotificationsConfiguration extends UnlistedSpecialPage {
	/**
	 * EchoAttributeManager to access notification configuration
	 *
	 * @var EchoAttributeManager $attributeManager;
	 */
	protected $attributeManager;

	/**
	 * Notification controller
	 *
	 * @var EchoNotificationController $notificationController;
	 */
	protected $notificationController;

	/**
	 * Category names, mapping internal name to HTML-formatted name
	 *
	 * @var string[] $categoryNames
	 */
	protected $categoryNames;

	// Should be one mapping text (friendly) name to internal name, but there
	// is no friendly name
	/**
	 * Notification type names.  Mapping HTML-formatted internal name to internal name
	 *
	 * @var string[] $notificationTypeNames
	 */
	protected $notificationTypeNames;

	/**
	 * Notify types, mapping internal name to HTML-formatted name
	 *
	 * @var string[] $notifyTypes
	 */
	protected $notifyTypes;

	// Due to how HTMLForm works, it's convenient to have both directions
	/**
	 * Category names, mapping HTML-formatted name to internal name
	 *
	 * @var string[] $flippedCategoryNames
	 */
	protected $flippedCategoryNames;

	/**
	 * Notify types, mapping HTML-formatted name to internal name
	 *
	 * @var string[] $flippedNotifyTypes
	 */
	protected $flippedNotifyTypes;

	public function __construct() {
		parent::__construct( 'DisplayNotificationsConfiguration' );

		$this->attributeManager = EchoAttributeManager::newFromGlobalVars();
		$this->notificationController = new EchoNotificationController();
	}

	public function execute( $subPage ) {
		global $wgEchoNotifiers, $wgEchoNotifications;

		$this->setHeaders();
		$this->checkPermissions();

		$internalCategoryNames = $this->attributeManager->getInternalCategoryNames();
		$this->categoryNames = [];

		foreach ( $internalCategoryNames as $internalCategoryName ) {
			$formattedFriendlyCategoryName = Html::element(
				'strong',
				[],
				$this->msg( 'echo-category-title-' . $internalCategoryName )->numParams( 1 )->text()
			);

			$formattedInternalCategoryName = $this->msg( 'parentheses' )->rawParams(
				Html::element(
					'em',
					[],
					$internalCategoryName
				)
			)->parse();

			$this->categoryNames[$internalCategoryName] = $formattedFriendlyCategoryName . ' '
				. $formattedInternalCategoryName;
		}

		$this->flippedCategoryNames = array_flip( $this->categoryNames );

		$this->notifyTypes = [];
		foreach ( $wgEchoNotifiers as $notifyType => $notifier ) {
			$this->notifyTypes[$notifyType] = $this->msg( 'echo-pref-' . $notifyType )->escaped();
		}

		$this->flippedNotifyTypes = array_flip( $this->notifyTypes );

		$notificationTypes = array_keys( $wgEchoNotifications );
		$this->notificationTypeNames = array_combine(
			array_map( 'htmlspecialchars', $notificationTypes ),
			$notificationTypes
		);

		$this->getOutput()->setPageTitle( $this->msg( 'echo-displaynotificationsconfiguration' )->text() );
		$this->outputHeader( 'echo-displaynotificationsconfiguration-summary' );
		$this->outputConfiguration();
	}

	/**
	 * Outputs the Echo configuration
	 */
	protected function outputConfiguration() {
		$this->outputNotificationsInCategories();
		$this->outputNotificationsInSections();
		$this->outputAvailability();
		$this->outputMandatory();
		$this->outputEnabledDefault();
	}

	/**
	 * Displays a checkbox matrix, using an HTMLForm
	 *
	 * @param string $id Arbitrary ID
	 * @param string $legendMsgKey Message key for an explanatory legend.  For example,
	 *   "We wrote this feature because in the days of yore, there was but one notification badge"
	 * @param array $rowLabelMapping Associative array mapping label to tag
	 * @param array $columnLabelMapping Associative array mapping label to tag
	 * @param array $value Array consisting of strings in the format '$columnTag-$rowTag'
	 */
	protected function outputCheckMatrix(
		$id,
		$legendMsgKey,
		array $rowLabelMapping,
		array $columnLabelMapping,
		array $value
	) {
		$form = new HTMLForm(
			[
				$id => [
					'type' => 'checkmatrix',
					'rows' => $rowLabelMapping,
					'columns' => $columnLabelMapping,
					'default' => $value,
					'disabled' => true,
				]
			],
			$this->getContext()
		);

		$form->setTitle( $this->getPageTitle() )
			->prepareForm()
			->suppressDefaultSubmit()
			->setWrapperLegendMsg( $legendMsgKey )
			->displayForm( false );
	}

	/**
	 * Outputs the notification types in each category
	 */
	protected function outputNotificationsInCategories() {
		$notificationsByCategory = $this->attributeManager->getEventsByCategory();

		$out = $this->getOutput();
		$out->addHTML( Html::element(
			'h2',
			[ 'id' => 'mw-echo-displaynotificationsconfiguration-notifications-by-category' ],
			$this->msg( 'echo-displaynotificationsconfiguration-notifications-by-category-header' )->text()
		) );

		$out->addHTML( Html::openElement( 'ul' ) );
		foreach ( $notificationsByCategory as $categoryName => $notificationTypes ) {
			$implodedTypes = Html::element(
				'span',
				[],
				implode( $this->msg( 'comma-separator' )->text(), $notificationTypes )
			);

			$out->addHTML(
				Html::rawElement(
					'li',
					[],
					$this->categoryNames[$categoryName] . $this->msg( 'colon-separator' )->escaped() . ' '
						. $implodedTypes
				)
			);
		}
		$out->addHTML( Html::closeElement( 'ul' ) );
	}

	/**
	 * Output the notification types in each section (alert/message)
	 */
	protected function outputNotificationsInSections() {
		$this->getOutput()->addHTML( Html::element(
			'h2',
			[ 'id' => 'mw-echo-displaynotificationsconfiguration-sorting-by-section' ],
			$this->msg( 'echo-displaynotificationsconfiguration-sorting-by-section-header' )->text()
		) );

		$bySectionValue = [];

		$flippedSectionNames = [];

		foreach ( EchoAttributeManager::$sections as $section ) {
			$types = $this->attributeManager->getEventsForSection( $section );
			// echo-notification-alert-text-only, echo-notification-notice-text-only
			$msgSection = $section == 'message' ? 'notice' : $section;
			$flippedSectionNames[$this->msg( 'echo-notification-' . $msgSection . '-text-only' )->escaped()]
				= $section;
			foreach ( $types as $type ) {
				$bySectionValue[] = "$section-$type";
			}
		}

		$this->outputCheckMatrix(
			'type-by-section',
			'echo-displaynotificationsconfiguration-sorting-by-section-legend',
			$this->notificationTypeNames,
			$flippedSectionNames,
			$bySectionValue
		);
	}

	/**
	 * Output which notify types are available for each category
	 */
	protected function outputAvailability() {
		$this->getOutput()->addHTML( Html::element(
			'h2',
			[ 'id' => 'mw-echo-displaynotificationsconfiguration-available-notification-methods' ],
			$this->msg( 'echo-displaynotificationsconfiguration-available-notification-methods-header' )->text()
		) );

		$byCategoryValue = [];

		foreach ( $this->notifyTypes as $notifyType => $displayNotifyType ) {
			foreach ( $this->categoryNames as $category => $displayCategory ) {
				if ( $this->attributeManager->isNotifyTypeAvailableForCategory( $category, $notifyType ) ) {
					$byCategoryValue[] = "$notifyType-$category";
				}
			}
		}

		$this->outputCheckMatrix(
			'availability-by-category',
			'echo-displaynotificationsconfiguration-available-notification-methods-by-category-legend',
			$this->flippedCategoryNames,
			$this->flippedNotifyTypes,
			$byCategoryValue
		);
	}

	/**
	 * Output which notification categories are turned on by default, for each notify type
	 */
	protected function outputEnabledDefault() {
		$this->getOutput()->addHTML( Html::element(
			'h2',
			[ 'id' => 'mw-echo-displaynotificationsconfiguration-enabled-default' ],
			$this->msg( 'echo-displaynotificationsconfiguration-enabled-default-header' )->text()
		) );

		// In reality, anon users are not relevant to Echo, but this lets us easily query default options.
		$anonUser = new User;

		$byCategoryValueExisting = [];
		foreach ( $this->notifyTypes as $notifyType => $displayNotifyType ) {
			foreach ( $this->categoryNames as $category => $displayCategory ) {
				$tag = "$notifyType-$category";
				if ( $anonUser->getOption( "echo-subscriptions-$tag" ) ) {
					$byCategoryValueExisting[] = "$notifyType-$category";
				}
			}
		}

		$this->outputCheckMatrix(
			'enabled-by-default-generic',
			'echo-displaynotificationsconfiguration-enabled-default-existing-users-legend',
			$this->flippedCategoryNames,
			$this->flippedNotifyTypes,
			$byCategoryValueExisting
		);

		$loggedInUser = new User;
		// This might not catch if there are other hooks that do similar.
		// We can't run the actual hook, to avoid side effects.
		$overrides = EchoHooks::getNewUserPreferenceOverrides();
		foreach ( $overrides as $prefKey => $value ) {
			$loggedInUser->setOption( $prefKey, $value );
		}

		$byCategoryValueNew = [];
		foreach ( $this->notifyTypes as $notifyType => $displayNotifyType ) {
			foreach ( $this->categoryNames as $category => $displayCategory ) {
				$tag = "$notifyType-$category";
				if ( $loggedInUser->getOption( "echo-subscriptions-$tag" ) ) {
					$byCategoryValueNew[] = "$notifyType-$category";
				}
			}
		}

		$this->outputCheckMatrix(
			'enabled-by-default-new',
			'echo-displaynotificationsconfiguration-enabled-default-new-users-legend',
			$this->flippedCategoryNames,
			$this->flippedNotifyTypes,
			$byCategoryValueNew
		);
	}

	/**
	 * Output which notify types are mandatory for each category
	 */
	protected function outputMandatory() {
		$byCategoryValue = [];

		$this->getOutput()->addHTML( Html::element(
			'h2',
			[ 'id' => 'mw-echo-displaynotificationsconfiguration-mandatory-notification-methods' ],
			$this->msg( 'echo-displaynotificationsconfiguration-mandatory-notification-methods-header' )->text()
		) );

		foreach ( $this->notifyTypes as $notifyType => $displayNotifyType ) {
			foreach ( $this->categoryNames as $category => $displayCategory ) {
				if ( !$this->attributeManager->isNotifyTypeDismissableForCategory( $category, $notifyType ) ) {
					$byCategoryValue[] = "$notifyType-$category";
				}
			}
		}

		$this->outputCheckMatrix(
			'mandatory',
			'echo-displaynotificationsconfiguration-mandatory-notification-methods-by-category-legend',
			$this->flippedCategoryNames,
			$this->flippedNotifyTypes,
			$byCategoryValue
		);
	}
}
