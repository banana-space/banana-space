<?php
/**
 * Temporary action for restoring multi-content revisions
 * @file
 * @ingroup Actions
 */

/**
 * Temporary action for restoring multi-content revisions.
 *
 * This is intended to go away when real MCR support is added to EditPage and
 * the standard revert-by-edit behavior can be implemented there instead.
 *
 * @ingroup Actions
 * @since 1.32
 * @deprecated since 1.32
 */
class McrRestoreAction extends McrUndoAction {

	public function getName() {
		return 'mcrrestore';
	}

	public function getDescription() {
		return '';
	}

	protected function initFromParameters() {
		$curRev = $this->getWikiPage()->getRevisionRecord();
		if ( !$curRev ) {
			throw new ErrorPageError( 'mcrundofailed', 'nopagetext' );
		}
		$this->curRev = $curRev;
		$this->cur = $this->getRequest()->getInt( 'cur', $this->curRev->getId() );

		$this->undo = $this->cur;
		$this->undoafter = $this->getRequest()->getInt( 'restore' );

		if ( $this->undo == 0 || $this->undoafter == 0 ) {
			throw new ErrorPageError( 'mcrundofailed', 'mcrundo-missingparam' );
		}
	}

	protected function addStatePropagationFields( HTMLForm $form ) {
		$form->addHiddenField( 'restore', $this->undoafter );
		$form->addHiddenField( 'cur', $this->curRev->getId() );
	}

	protected function alterForm( HTMLForm $form ) {
		parent::alterForm( $form );

		$form->setWrapperLegendMsg( 'confirm-mcrrestore-title' );
	}

}
