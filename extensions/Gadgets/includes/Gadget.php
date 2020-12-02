<?php
/**
 * Gadgets extension - lets users select custom javascript gadgets
 *
 * For more info see https://www.mediawiki.org/wiki/Extension:Gadgets
 *
 * @file
 * @ingroup Extensions
 * @author Daniel Kinzler, brightbyte.de
 * @copyright © 2007 Daniel Kinzler
 * @license GPL-2.0-or-later
 */

/**
 * Wrapper for one gadget.
 */
class Gadget {
	/**
	 * Increment this when changing class structure
	 */
	public const GADGET_CLASS_VERSION = 9;

	public const CACHE_TTL = 86400;

	private $scripts = [],
			$styles = [],
			$dependencies = [],
			$peers = [],
			$messages = [],
			$name,
			$definition,
			$resourceLoaded = false,
			$requiredRights = [],
			$requiredSkins = [],
			$targets = [ 'desktop' ],
			$onByDefault = false,
			$hidden = false,
			$type = '',
			$category;

	public function __construct( array $options ) {
		foreach ( $options as $member => $option ) {
			switch ( $member ) {
				case 'scripts':
				case 'styles':
				case 'dependencies':
				case 'peers':
				case 'messages':
				case 'name':
				case 'definition':
				case 'resourceLoaded':
				case 'requiredRights':
				case 'requiredSkins':
				case 'targets':
				case 'onByDefault':
				case 'type':
				case 'hidden':
				case 'category':
					$this->{$member} = $option;
					break;
				default:
					throw new InvalidArgumentException( "Unrecognized '$member' parameter" );
			}
		}
	}

	/**
	 * Create a object based on the metadata in a GadgetDefinitionContent object
	 *
	 * @param string $id
	 * @param GadgetDefinitionContent $content
	 * @return Gadget
	 */
	public static function newFromDefinitionContent( $id, GadgetDefinitionContent $content ) {
		$data = $content->getAssocArray();
		$prefixGadgetNs = function ( $page ) {
			return 'Gadget:' . $page;
		};
		$info = [
			'name' => $id,
			'resourceLoaded' => true,
			'requiredRights' => $data['settings']['rights'],
			'onByDefault' => $data['settings']['default'],
			'hidden' => $data['settings']['hidden'],
			'requiredSkins' => $data['settings']['skins'],
			'category' => $data['settings']['category'],
			'scripts' => array_map( $prefixGadgetNs, $data['module']['scripts'] ),
			'styles' => array_map( $prefixGadgetNs, $data['module']['styles'] ),
			'dependencies' => $data['module']['dependencies'],
			'peers' => $data['module']['peers'],
			'messages' => $data['module']['messages'],
			'type' => $data['module']['type'],
		];

		return new self( $info );
	}

	/**
	 * Get a placeholder object to use if a gadget doesn't exist
	 *
	 * @param string $id name
	 * @return Gadget
	 */
	public static function newEmptyGadget( $id ) {
		return new self( [ 'name' => $id ] );
	}

	/**
	 * Whether the provided gadget id is valid
	 *
	 * @param string $id
	 * @return bool
	 */
	public static function isValidGadgetID( $id ) {
		return strlen( $id ) > 0 && ResourceLoader::isValidModuleName( self::getModuleName( $id ) );
	}

	/**
	 * @return string Gadget name
	 */
	public function getName() {
		return $this->name;
	}

	/**
	 * @return string Gadget description parsed into HTML
	 */
	public function getDescription() {
		return wfMessage( "gadget-{$this->getName()}" )->parse();
	}

	/**
	 * @return string Wikitext of gadget description
	 */
	public function getRawDescription() {
		return wfMessage( "gadget-{$this->getName()}" )->plain();
	}

	/**
	 * @return string Name of category (aka section) our gadget belongs to. Empty string if none.
	 */
	public function getCategory() {
		return $this->category;
	}

	/**
	 * @param string $id Name of gadget
	 * @return string Name of ResourceLoader module for the gadget
	 */
	public static function getModuleName( $id ) {
		return "ext.gadget.{$id}";
	}

	/**
	 * Checks whether this gadget is enabled for given user
	 *
	 * @param User $user user to check against
	 * @return bool
	 */
	public function isEnabled( $user ) {
		return (bool)$user->getOption( "gadget-{$this->name}", $this->onByDefault );
	}

	/**
	 * Checks whether given user has permissions to use this gadget
	 *
	 * @param User $user The user to check against
	 * @return bool
	 */
	public function isAllowed( User $user ) {
		return $user->isAllowedAll( ...$this->requiredRights );
	}

	/**
	 * @return bool Whether this gadget is on by default for everyone
	 *  (but can be disabled in preferences)
	 */
	public function isOnByDefault() {
		return $this->onByDefault;
	}

	/**
	 * @return bool
	 */
	public function isHidden() {
		return $this->hidden;
	}

	/**
	 * Check if this gadget is compatible with a skin
	 *
	 * @param Skin $skin The skin to check against
	 * @return bool
	 */
	public function isSkinSupported( Skin $skin ) {
		return ( count( $this->requiredSkins ) === 0
				|| in_array( $skin->getSkinName(), $this->requiredSkins )
			);
	}

	/**
	 * @return bool Whether all of this gadget's JS components support ResourceLoader
	 */
	public function supportsResourceLoader() {
		return $this->resourceLoaded;
	}

	/**
	 * @return bool Whether this gadget has resources that can be loaded via ResourceLoader
	 */
	public function hasModule() {
		return count( $this->styles )
			+ ( $this->supportsResourceLoader() ? count( $this->scripts ) : 0 )
				> 0;
	}

	/**
	 * @return string Definition for this gadget from MediaWiki:gadgets-definition
	 */
	public function getDefinition() {
		return $this->definition;
	}

	/**
	 * @return array Array of pages with JS (including namespace)
	 */
	public function getScripts() {
		return $this->scripts;
	}

	/**
	 * @return array Array of pages with CSS (including namespace)
	 */
	public function getStyles() {
		return $this->styles;
	}

	/**
	 * @return array Array of all of this gadget's resources
	 */
	public function getScriptsAndStyles() {
		return array_merge( $this->scripts, $this->styles );
	}

	/**
	 * @return array
	 */
	public function getTargets() {
		return $this->targets;
	}

	/**
	 * Returns list of scripts that don't support ResourceLoader
	 * @return string[]
	 */
	public function getLegacyScripts() {
		if ( $this->supportsResourceLoader() ) {
			return [];
		}
		return $this->scripts;
	}

	/**
	 * Returns names of resources this gadget depends on
	 * @return string[]
	 */
	public function getDependencies() {
		return $this->dependencies;
	}

	/**
	 * Get list of extra modules that should be loaded when this gadget is enabled
	 *
	 * Primary use case is to allow a Gadget that includes JavaScript to also load
	 * a (usually, hidden) styles-type module to be applied to the page. Dependencies
	 * don't work for this use case as those would not be part of page rendering.
	 *
	 * @return string[]
	 */
	public function getPeers() {
		return $this->peers;
	}

	/**
	 * @return array
	 */
	public function getMessages() {
		return $this->messages;
	}

	/**
	 * Returns array of permissions required by this gadget
	 * @return string[]
	 */
	public function getRequiredRights() {
		return $this->requiredRights;
	}

	/**
	 * Returns array of skins where this gadget works
	 * @return string[]
	 */
	public function getRequiredSkins() {
		return $this->requiredSkins;
	}

	/**
	 * Returns the load type of this Gadget's ResourceLoader module
	 * @return string 'styles' or 'general'
	 */
	public function getType() {
		if ( $this->type === 'styles' || $this->type === 'general' ) {
			return $this->type;
		}
		// Similar to ResourceLoaderWikiModule default
		if ( $this->styles && !$this->scripts && !$this->dependencies ) {
			return 'styles';
		} else {
			return 'general';
		}
	}
}
