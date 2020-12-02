<?php

namespace OOUI;

/**
 * Element with a title.
 *
 * Titles are rendered by the browser and are made visible when hovering the element. Titles are
 * not visible on touch devices.
 *
 * @abstract
 */
trait TitledElement {
	/**
	 * Title text.
	 *
	 * @var string
	 */
	protected $title = null;

	/**
	 * @var Element
	 */
	protected $titled;

	/**
	 * @param array $config Configuration options
	 *      - string $config['title'] Title. If not provided, the static property 'title' is used.
	 */
	public function initializeTitledElement( array $config = [] ) {
		// Properties
		$this->titled = $config['titled'] ?? $this;

		// Initialization
		$this->setTitle( $config['title'] ?? null );

		$this->registerConfigCallback( function ( &$config ) {
			if ( $this->title !== null ) {
				$config['title'] = $this->title;
			}
		} );
	}

	/**
	 * Set title.
	 *
	 * @param string|null $title Title text or null for browser default title, which is no title for
	 *   most elements.
	 * @return $this
	 */
	public function setTitle( $title ) {
		if ( $this->title !== $title ) {
			$this->title = $title;
			$this->updateTitle();
		}

		return $this;
	}

	/**
	 * Update the title attribute, in case of changes to title or accessKey.
	 *
	 * @return $this
	 */
	protected function updateTitle() {
		$title = $this->getTitle();
		if ( $title !== null ) {
			// Only if this is an AccessKeyedElement
			if ( method_exists( $this, 'formatTitleWithAccessKey' ) ) {
				$title = $this->formatTitleWithAccessKey( $title );
			}
			$this->titled->setAttributes( [ 'title' => $title ] );
		} else {
			$this->titled->removeAttributes( [ 'title' ] );
		}
		return $this;
	}

	/**
	 * Get title.
	 *
	 * @return string Title string
	 */
	public function getTitle() {
		return $this->title;
	}
}
