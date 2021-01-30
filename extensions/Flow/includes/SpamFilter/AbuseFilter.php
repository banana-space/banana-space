<?php

namespace Flow\SpamFilter;

use ExtensionRegistry;
use Flow\Model\AbstractRevision;
use IContextSource;
use MediaWiki\Extension\AbuseFilter\VariableGenerator\VariableGenerator;
use Status;
use Title;

class AbuseFilter implements SpamFilter {
	/**
	 * @var string
	 */
	protected $group;

	/**
	 * @param string $group The abuse filter group to use
	 */
	public function __construct( $group ) {
		$this->group = $group;
	}

	/**
	 * Set up AbuseFilter for Flow extension
	 *
	 * @param array $emergencyDisable optional AbuseFilter emergency disable values
	 */
	public function setup( array $emergencyDisable = [] ) {
		global
		$wgAbuseFilterValidGroups,
		$wgAbuseFilterEmergencyDisableThreshold,
		$wgAbuseFilterEmergencyDisableCount,
		$wgAbuseFilterEmergencyDisableAge;

		if ( !$this->enabled() ) {
			return;
		}

		// if no Flow-specific emergency disable threshold given, use defaults
		$emergencyDisable += [
			'threshold' => $wgAbuseFilterEmergencyDisableThreshold['default'],
			'count' => $wgAbuseFilterEmergencyDisableCount['default'],
			'age' => $wgAbuseFilterEmergencyDisableAge['default'],
		];

		// register Flow's AbuseFilter filter group
		if ( !in_array( $this->group, $wgAbuseFilterValidGroups ) ) {
			$wgAbuseFilterValidGroups[] = $this->group;

			// AbuseFilter emergency disable values for Flow
			$wgAbuseFilterEmergencyDisableThreshold[$this->group] = $emergencyDisable['threshold'];
			$wgAbuseFilterEmergencyDisableCount[$this->group] = $emergencyDisable['count'];
			$wgAbuseFilterEmergencyDisableAge[$this->group] = $emergencyDisable['age'];
		}
	}

	/**
	 * @param IContextSource $context
	 * @param AbstractRevision $newRevision
	 * @param AbstractRevision|null $oldRevision
	 * @param Title $title
	 * @param Title $ownerTitle
	 * @return Status
	 */
	public function validate(
		IContextSource $context,
		AbstractRevision $newRevision,
		?AbstractRevision $oldRevision,
		Title $title,
		Title $ownerTitle
	) {
		$gen = new VariableGenerator( new \AbuseFilterVariableHolder );
		$vars = $gen
			->addEditVars( $title )
			->addUserVars( $context->getUser() )
			->addTitleVars( $title, 'page' )
			->addTitleVars( $ownerTitle, 'board' )
			->getVariableHolder();

		$vars->setVar( 'ACTION', $newRevision->getChangeType() );

		/*
		 * This should not roundtrip to Parsoid; AbuseFilter checks will be
		 * performed upon submitting new content, and content is always
		 * submitted in wikitext. It will only be transformed once it's being
		 * saved to DB.
		 */
		$vars->setLazyLoadVar( 'new_wikitext', 'FlowRevisionContent', [ 'revision' => $newRevision ] );
		$vars->setLazyLoadVar( 'new_size', 'length', [ 'length-var' => 'new_wikitext' ] );

		/*
		 * This may roundtrip to Parsoid if content is stored in HTML.
		 * Since the variable is lazy-loaded, it will not roundtrip unless the
		 * variable is actually used.
		 */
		$vars->setLazyLoadVar( 'old_wikitext', 'FlowRevisionContent', [ 'revision' => $oldRevision ] );
		$vars->setLazyLoadVar( 'old_size', 'length', [ 'length-var' => 'old_wikitext' ] );

		return \AbuseFilter::filterAction( $vars, $title, $this->group, $context->getUser() );
	}

	/**
	 * Checks if AbuseFilter is installed.
	 *
	 * @return bool
	 */
	public function enabled() {
		return ExtensionRegistry::getInstance()->isLoaded( 'Abuse Filter' ) && (bool)$this->group;
	}

	/**
	 * Additional lazy-load methods for dealing with AbstractRevision objects,
	 * to delay processing data until/if variables are actually used.
	 *
	 * @return array
	 */
	public function lazyLoadMethods() {
		return [
			/**
			 * @param \AbuseFilterVariableHolder $vars
			 * @param array $parameters Parameters with data to compute the value
			 */
			'FlowRevisionContent' => function ( \AbuseFilterVariableHolder $vars, array $parameters ) {
				if ( !isset( $parameters['revision'] ) ) {
					return '';
				}
				$revision = $parameters['revision'];
				if ( $revision instanceof AbstractRevision ) {
					return $revision->getContentInWikitext();
				} else {
					return '';
				}
			}
		];
	}
}
