<?php

namespace OOUI;

/**
 * Element with an accesskey.
 *
 * Accesskeys allow an user to go to a specific element by using
 * a shortcut combination of a browser specific keys + the key
 * set to the field.
 *
 * @abstract
 */
trait AccessKeyedElement {

	/**
	 * Accesskey
	 *
	 * @var string
	 */
	protected $accessKey = null;

	/**
	 * @var Tag
	 */
	protected $accessKeyed;

	/**
	 * @param array $config Configuration options
	 * @param string $config['accessKey'] AccessKey. If not provided, no accesskey will be added
	 */
	public function initializeAccessKeyedElement( array $config = [] ) {
		// Properties
		$this->accessKeyed = isset( $config['accessKeyed'] ) ? $config['accessKeyed'] : $element;

		// Initialization
		$this->setAccessKey(
			isset( $config['accessKey'] ) ? $config['accessKey'] : null
		);
		$this->registerConfigCallback( function ( &$config ) {
			if ( $this->accessKey !== null ) {
				$config['accessKey'] = $this->accessKey;
			}
		} );
	}

	/**
	 * Set access key.
	 *
	 * @param string $accessKey Tag's access key, use empty string to remove
	 * @return $this
	 */
	public function setAccessKey( $accessKey ) {
		$accessKey = is_string( $accessKey ) && strlen( $accessKey ) ? $accessKey : null;

		if ( $this->accessKey !== $accessKey ) {
			if ( $accessKey !== null ) {
				$this->accessKeyed->setAttributes( [ 'accesskey' => $accessKey ] );
			} else {
				$this->accessKeyed->removeAttributes( [ 'accesskey' ] );
			}
			$this->accessKey = $accessKey;

			// Only if this is a TitledElement
			if ( method_exists( $this, 'updateTitle' ) ) {
				$this->updateTitle();
			}
		}

		return $this;
	}

	/**
	 * Get AccessKey.
	 *
	 * @return string Accesskey string
	 */
	public function getAccessKey() {
		return $this->accessKey;
	}

	/**
	 * Add information about the access key to the element's tooltip label.
	 * (This is only public for hacky usage in FieldLayout.)
	 *
	 * @param string $title Tooltip label for `title` attribute
	 * @return string
	 */
	public function formatTitleWithAccessKey( $title ) {
		$accessKey = $this->getAccessKey();
		if ( $accessKey ) {
			$title .= " [$accessKey]";
		}
		return $title;
	}
}
