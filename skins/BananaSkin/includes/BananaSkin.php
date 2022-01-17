<?php

class BananaSkin extends SkinMustache136 {
        /** @inheritDoc */
        public function __construct( $options ) {
            global $wgVersion;
            if ( version_compare( $wgVersion, '1.38', '<' ) ) {
                    $options['templateDirectory'] = 'skins/BananaSkin/templates/';
            }
            parent::__construct( $options );
    }

    public static function initBananaSkin() {
        // Merge notices with alerts
        global $wgEchoNotifications;
        foreach ( $wgEchoNotifications as &$data ) {
            $data['section'] = 'alert';
        }
    }

    public static function makeUrl( $name ) {
        $title = Title::newFromText( $name );
        self::checkTitle( $title, $name );
        return $title->getLocalURL();
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
                'href' => BananaSkin::makeUrl( $this->msg( 'banana-portal-notes-title' )->text() ),
                'text' => $this->msg( 'banana-portal-notes-text' )->text()
            ],
            'discussion' => [
                'href' => BananaSkin::makeUrl( $this->msg( 'banana-portal-discussion-title' )->text() ),
                'text' => $this->msg( 'banana-portal-discussion-text' )->text()
            ]
        ] );
        $explore['label'] = $this->msg( 'banana-nav-explore' )->text();
        $portlets['data-explore'] = $explore;

        $create = $this->getPortletData( 'create', [
            'create-page' => [
                'href' => BananaSkin::makeUrl( $this->msg( 'banana-create-page-title' )->text() ),
                'text' => $this->msg( 'banana-create-page-text' )->text()
            ],
            'plan' => [
                'href' => BananaSkin::makeUrl( $this->msg( 'banana-plan-title' )->text() ),
                'text' => $this->msg( 'banana-plan-text' )->text()
            ],
            'list-stubs' => [
                'href' => BananaSkin::makeUrl( $this->msg( 'banana-list-stubs-title' )->text() ),
                'text' => $this->msg( 'banana-list-stubs-text' )->text()
            ],
            'file' => [
                'href' => Skin::makeSpecialUrl( 'Upload' ),
                'text' => $this->msg( 'banana-upload-file-text' )->text()
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

        // Search link (for small screens)
        $data['html-search-link'] = '<a href="' . Skin::makeSpecialUrl('Search') . '">' .
            $this->msg( 'banana-search-link-text' ) .'</a>';

        // Before-header section
        if ($this->canUseWikiPage()) {
            $wikiPage = $this->getWikiPage();
            $options = $wikiPage->makeParserOptions('canonical');
            $output = $wikiPage->getParserOutput($options);
            if ($output) {
                $before = $output->getExtensionData('btex-before') ?? '';
                $data['html-before-header'] = $before;
            }
        }

        return $data;
    }
}
