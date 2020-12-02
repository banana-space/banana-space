<?php

namespace OOUI;

/**
 * Use together with ItemWidget to make disabled state inheritable.
 *
 * @abstract
 */
trait GroupWidget {
	use GroupElement;

	public function setDisabled( $state ) {
		// @phan-suppress-next-line PhanTraitParentReference
		parent::setDisabled( $state );
		$modifiedItems = [];
		$items = $this->getItems();
		/** @var Widget $item */
		foreach ( $items as $item ) {
			$modifiedItems[] = $item->setDisabled( $state );
		}
		$this->clearItems();
		$this->addItems( $modifiedItems );
	}
}
