<?php

/**
 * A formatter for Special:Notifications
 *
 * This formatter uses OOUI libraries. Any calls to this formatter must
 * also call OutputPage::enableOOUI() before calling this formatter.
 */
class SpecialNotificationsFormatter extends EchoEventFormatter {
	protected function formatModel( EchoEventPresentationModel $model ) {
		$markReadSpecialPage = new SpecialNotificationsMarkRead();
		$id = $model->getEventId();

		$icon = Html::element(
			'img',
			[
				'class' => 'mw-echo-icon',
				'src' => $this->getIconURL( $model ),
			]
		);

		OutputPage::setupOOUI();

		$markAsReadIcon = new OOUI\IconWidget( [
			'icon' => 'close',
			'title' => wfMessage( 'echo-notification-markasread' )->text(),
		] );

		$markAsReadForm = $markReadSpecialPage->getMinimalForm(
			$id,
			$this->msg( 'echo-notification-markasread' )->text(),
			false,
			$markAsReadIcon->toString()
		);

		$markAsReadButton = Html::rawElement(
			'div',
			[ 'class' => 'mw-echo-markAsReadButton' ],
			$markAsReadForm->prepareForm()->getHTML( /* First submission attempt */ false )
		);

		$html = Xml::tags(
				'div',
				[ 'class' => 'mw-echo-title' ],
				$model->getHeaderMessage()->parse()
			) . "\n";

		$body = $model->getBodyMessage();
		if ( $body ) {
			$html .= Xml::tags(
					'div',
					[ 'class' => 'mw-echo-payload' ],
					$body->escaped()
				) . "\n";
		}

		$ts = $this->language->getHumanTimestamp(
			new MWTimestamp( $model->getTimestamp() ),
			null,
			$this->user
		);

		$footerItems = [ Html::element( 'span', [ 'class' => 'mw-echo-notification-footer-element' ], $ts ) ];

		// Add links to the footer, primary goes first, then secondary ones
		$links = [];
		$primaryLink = $model->getPrimaryLinkWithMarkAsRead();
		if ( $primaryLink !== false ) {
			$links[] = $primaryLink;
		}
		$links = array_merge( $links, array_filter( $model->getSecondaryLinks() ) );
		foreach ( $links as $link ) {
			$footerAttributes = [
				'href' => $link['url'],
				'class' => 'mw-echo-notification-footer-element',
			];

			if ( isset( $link['tooltip'] ) ) {
				$footerAttributes['title'] = $link['tooltip'];
			}

			$footerItems[] = Html::element(
				'a',
				$footerAttributes,
				$link['label']
			);
		}

		$pipe = wfMessage( 'pipe-separator' )->inLanguage( $this->language )->text();
		$html .= Xml::tags(
			'div',
			[ 'class' => 'mw-echo-notification-footer' ],
			implode(
				Html::element( 'span', [ 'class' => 'mw-echo-notification-footer-element' ], $pipe ),
				$footerItems
			)
		) . "\n";

		// Wrap everything in mw-echo-content class
		$html = Xml::tags( 'div', [ 'class' => 'mw-echo-content' ], $html );

		// And then add the mark as read button
		// and the icon in front and wrap with
		// mw-echo-state class.
		$html = Xml::tags( 'div', [ 'class' => 'mw-echo-state' ], $markAsReadButton . $icon . $html );

		return $html;
	}

	private function getIconURL( EchoEventPresentationModel $model ) {
		return EchoIcon::getUrl(
				$model->getIconType(),
				$this->language->getDir()
		);
	}
}
