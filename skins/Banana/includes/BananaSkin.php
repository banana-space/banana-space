<?php

class BananaSkin extends SkinMustache136 {
    public static function initBananaSkin() {
        // Merge notices with alerts
        global $wgEchoNotifications;
        foreach ( $wgEchoNotifications as &$data ) {
            $data['section'] = 'alert';
        }
    }

    public function getTemplateData() {
        $data = parent::getTemplateData();
        $portlets = &$data['data-portlets'];
        $personal = &$portlets['data-user-menu'];

        // Move alerts from data-user-menu to data-notifications,
        // and remove notices (merged into alerts)
        $matches = [];
        if (preg_match(
            '#<li id="pt-notifications-alert">.*?</li>#',
            $personal['html-items'],
            $matches
        )) {
            $personal['html-items'] = preg_replace(
                '#<li id="pt-notifications-alert">.*?</li>#',
                '',
                $personal['html-items']
            );
            $personal['html-items'] = preg_replace(
                '#<li id="pt-notifications-notice">.*?</li>#',
                '',
                $personal['html-items']
            );

            $notifications = &$portlets['data-notifications'];
            $notifications['html-items'] = $matches[0];
            $notifications['class'] = preg_replace(
                '# emptyPortlet#',
                '',
                $notifications['class']
            );
        }

        // Make navigation links (Explore, Create, ...)
        $explore = $this->getPortletData( 'explore', [
            'notes' => [
                'href' => Skin::makeUrl( $this->msg( 'banana-portal-notes-title' )->text() ),
                'text' => $this->msg( 'banana-portal-notes-text' )->text()
            ]
        ] );
        $explore['label'] = $this->msg( 'banana-nav-explore' )->text();
        $portlets['data-explore'] = $explore;

        $create = $this->getPortletData( 'create', [
            'page' => [
                'href' => Skin::makeSpecialUrl( 'CreatePage' ),
                'text' => $this->msg( 'banana-create-page-text' )->text()
            ],
            'notes' => [
                'href' => Skin::makeSpecialUrl( 'CreatePage' ),
                'text' => $this->msg( 'banana-create-notes-text' )->text()
            ]
        ] );
        $create['label'] = $this->msg( 'banana-nav-create' )->text();
        $portlets['data-create'] = $create;

        // Remove MW icon
        unset($data['data-footer']['data-icons']);

        // Remove page actions if empty (for ease with CSS)
        if ($portlets['data-actions']['html-items'] === '') {
            unset($portlets['data-actions']);
        }

        return $data;
    }
}
