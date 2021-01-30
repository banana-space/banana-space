<?php

/**
 * The `SkinMustache` class from MediaWiki 1.36.
 */

class SkinMustache136 extends SkinMustache {

    /**
     * @inheritDoc
     */
	public function getTemplateData() {
        $out = $this->getOutput();
         $printSource = Html::rawElement( 'div', [ 'class' => 'printfooter' ], $this->printSource() );
         $bodyContent = $out->getHTML() . "\n" . $printSource;
  
         $newTalksHtml = $this->getNewtalks() ?: null;
  
         $data = [
             'data-logos' => $this->getLogoData(),
             // Array objects
             'array-indicators' => $this->getIndicatorsData( $out->getIndicators() ),
             // Data objects
             'data-search-box' => $this->buildSearchProps(),
             // HTML strings
             'html-site-notice' => $this->getSiteNotice() ?: null,
             'html-user-message' => $newTalksHtml ?
                 Html::rawElement( 'div', [ 'class' => 'usermessage' ], $newTalksHtml ) : null,
             'html-title' => $out->getPageTitle(),
             'html-subtitle' => $this->prepareSubtitle(),
             'html-body-content' => $this->wrapHTML( $out->getTitle(), $bodyContent ),
             'html-categories' => $this->getCategories(),
             'html-after-content' => $this->afterContentHook(),
             'html-undelete-link' => $this->prepareUndeleteLink(),
             'html-user-language-attributes' => $this->prepareUserLanguageAttributes(),
  
             // links
             'link-mainpage' => Title::newMainPage()->getLocalUrl(),
         ];
  
         foreach ( $this->options['messages'] ?? [] as $message ) {
             $data["msg-{$message}"] = $this->msg( $message )->text();
         }
         return $data + $this->getPortletsTemplateData() + $this->getFooterTemplateData();
    }

    /**
     * @inheritDoc
     */
    protected function getPortletData( $name, array $items ) {
        // Monobook and Vector historically render this portal as an element with ID p-cactions
        // This inconsistency is regretful from a code point of view
        // However this ensures compatibility with gadgets.
        // In future we should port p-#cactions to #p-actions and drop this rename.
        if ( $name === 'actions' ) {
            $name = 'cactions';
        }
 
        // user-menu is the new personal tools, without the notifications.
        // A lot of user code and gadgets relies on it being named personal.
        // This allows it to function as a drop-in replacement.
        if ( $name === 'user-menu' ) {
            $name = 'personal';
        }
 
        $id = Sanitizer::escapeIdForAttribute( "p-$name" );
        $data = [
            'id' => $id,
            'class' => 'mw-portlet ' . Sanitizer::escapeClass( "mw-portlet-$name" ),
            'html-tooltip' => Linker::tooltip( $id ),
            'html-items' => '',
            // Will be populated by SkinAfterPortlet hook.
            'html-after-portal' => '',
        ];
        // Run the SkinAfterPortlet
        // hook and if content is added appends it to the html-after-portal
        // for output.
        // Currently in production this supports the wikibase 'edit' link.
        $content = $this->getAfterPortlet( $name );
        if ( $content !== '' ) {
            $data['html-after-portal'] = Html::rawElement(
                'div',
                [
                    'class' => [
                        'after-portlet',
                        Sanitizer::escapeClass( "after-portlet-$name" ),
                    ],
                ],
                $content
            );
        }
 
        foreach ( $items as $key => $item ) {
            $data['html-items'] .= $this->makeListItem( $key, $item );
        }
 
        $data['label'] = $this->getPortletLabel( $name );
        $data['class'] .= ( count( $items ) === 0 && $content === '' )
            ? ' emptyPortlet' : '';
        return $data;
    }
 
    /**
     * @inheritDoc
     */
    private function getPortletLabel( $name ) {
        // For historic reasons for some menu items,
        // there is no language key corresponding with its menu key.
        $mappings = [
            'tb' => 'toolbox',
            'personal' => 'personaltools',
            'lang' => 'otherlanguages',
        ];
        $msgObj = $this->msg( $mappings[ $name ] ?? $name );
        // If no message exists fallback to plain text (T252727)
        $labelText = $msgObj->exists() ? $msgObj->text() : $name;
        return $labelText;
    }
    
    /**
     * @inheritDoc
     */
    private function getPortletsTemplateData() {
        $portlets = [];
        $contentNavigation = $this->buildContentNavigationUrls() + [
            'user-menu' => $this->buildPersonalUrls( false ),
            'notifications' => []
        ];
        $sidebar = [];
        $sidebarData = $this->buildSidebar();
        foreach ( $sidebarData as $name => $items ) {
            if ( is_array( $items ) ) {
                // Numeric strings gets an integer when set as key, cast back - T73639
                $name = (string)$name;
                switch ( $name ) {
                    // ignore search
                    case 'SEARCH':
                        break;
                    // Map toolbox to `tb` id.
                    case 'TOOLBOX':
                        $sidebar[] = $this->getPortletData( 'tb', $items );
                        break;
                    // Languages is no longer be tied to sidebar
                    case 'LANGUAGES':
                        // The language portal will be added provided either
                        // languages exist or there is a value in html-after-portal
                        // for example to show the add language wikidata link (T252800)
                        $portal = $this->getPortletData( 'lang', $items );
                        if ( count( $items ) || $portal['html-after-portal'] ) {
                            $portlets['data-languages'] = $portal;
                        }
                        break;
                    default:
                        $sidebar[] = $this->getPortletData( $name, $items );
                        break;
                }
            }
        }
 
        foreach ( $contentNavigation as $name => $items ) {
            if ( $name === 'user-menu' ) {
                $items = $this->getPersonalToolsForMakeListItem( $items );
            }
 
            $portlets['data-' . $name] = $this->getPortletData( $name, $items );
        }
 
        // A menu that includes the notifications. This will be deprecated in future versions
        // of the skin API spec.
        $portlets['data-personal'] = $this->getPortletData(
            'personal',
            $this->getPersonalToolsForMakeListItem(
                $contentNavigation['user-menu']
            )
        );
 
        return [
            'data-portlets' => $portlets,
            'data-portlets-sidebar' => [
                'data-portlets-first' => $sidebar[0] ?? null,
                'array-portlets-rest' => array_slice( $sidebar, 1 ),
            ],
        ];
    }
  
    /**
     * @inheritDoc
     */
    private function getFooterTemplateData() : array {
        $data = [];
        foreach ( $this->getFooterLinks() as $category => $links ) {
            $items = [];
            $rowId = "footer-$category";
 
            foreach ( $links as $key => $link ) {
                // Link may be null. If so don't include it.
                if ( $link ) {
                    $items[] = [
                        // Monobook uses name rather than id.
                        // We may want to change monobook to adhere to the same contract however.
                        'name' => $key,
                        'id' => "$rowId-$key",
                        'html' => $link,
                    ];
                }
            }
 
            $data['data-' . $category] = [
                'id' => $rowId,
                'className' => null,
                'array-items' => $items
            ];
        }
 
        // If footer icons are enabled append to the end of the rows
        $footerIcons = $this->getFooterIcons();
 
        if ( count( $footerIcons ) > 0 ) {
            $icons = [];
            foreach ( $footerIcons as $blockName => $blockIcons ) {
                $html = '';
                foreach ( $blockIcons as $key => $icon ) {
                    $html .= $this->makeFooterIcon( $icon );
                }
                // For historic reasons this mimics the `icononly` option
                // for BaseTemplate::getFooterIcons. Empty rows should not be output.
                if ( $html ) {
                    $block = htmlspecialchars( $blockName );
                    $icons[] = [
                        'name' => $block,
                        'id' => 'footer-' . $block . 'ico',
                        'html' => $html,
                    ];
                }
            }
 
            // Empty rows should not be output.
            // This is how Vector has behaved historically but we can revisit later if necessary.
            if ( count( $icons ) > 0 ) {
                $data['data-icons'] = [
                    'id' => 'footer-icons',
                    'className' => 'noprint',
                    'array-items' => $icons,
                ];
            }
        }
 
        return [
            'data-footer' => $data,
        ];
    }

    /**
     * @inheritDoc
     */
    private function getLogoData() : array {
        $logoData = ResourceLoaderSkinModule::getAvailableLogos( $this->getConfig() );
        // check if the logo supports variants
        $variantsLogos = $logoData['variants'] ?? null;
        if ( $variantsLogos ) {
            $preferred = $this->getOutput()->getTitle()
                ->getPageViewLanguage()->getCode();
            $variantOverrides = $variantsLogos[$preferred] ?? null;
            // Overrides the logo
            if ( $variantOverrides ) {
                foreach ( $variantOverrides as $key => $val ) {
                    $logoData[$key] = $val;
                }
            }
        }
        return $logoData;
    }
  
    /**
     * @inheritDoc
     */
    private function buildSearchProps() : array {
        $config = $this->getConfig();
 
        $props = [
            'form-action' => $config->get( 'Script' ),
            'html-button-search-fallback' => $this->makeSearchButton(
                'fulltext',
                [ 'id' => 'mw-searchButton', 'class' => 'searchButton mw-fallbackSearchButton' ]
            ),
            'html-button-search' => $this->makeSearchButton(
                'go',
                [ 'id' => 'searchButton', 'class' => 'searchButton' ]
            ),
            'html-input' => $this->makeSearchInput( [ 'id' => 'searchInput' ] ),
            'msg-search' => $this->msg( 'search' )->text(),
            'page-title' => SpecialPage::getTitleFor( 'Search' )->getPrefixedDBkey(),
        ];
 
        return $props;
    }
}
