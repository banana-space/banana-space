<?php

class SubpageHandler {
    /**
     * Gets the ID of the parent page.
     * @return int|false
     */
    public static function getParentId( Title $title ) {
        if ($title->getNamespace() !== NS_NOTES) return false;

        $isRoot = strpos($title->getText(), '/') === false;
        if ($isRoot) return false;

        $namespace = $title->getNamespace();
        $titleText = $title->getDBkey();
        $titleText = preg_replace('#(^[^/]+)/preamble$#', '$1', $titleText);

        $dbr = wfGetDB( DB_REPLICA );

        $result = $dbr->select(
            'banana_subpage',
            'parent_id',
            [
                'page_namespace' => $namespace,
                'page_title' => $titleText
            ]
        );
        foreach ($result as $row) return $row->parent_id;
        return false;
    }

    public static function getLabels( Title $title ) {
        $id = $title->getArticleID();
        $parentId = self::getParentId( $title );
        if ($parentId === false) $parentId = $title->getArticleID();

        $dbr = wfGetDB( DB_REPLICA );
        $labels = [];
        // TODO: is this possible with $dbr->select() ?
        $result = $dbr->query(
            "SELECT
                banana_label.page_id,
                banana_label.label_name,
                banana_label.label_target,
                banana_label.label_text
            FROM banana_subpage 
            INNER JOIN page
                ON page.page_namespace = banana_subpage.page_namespace
                AND page.page_title = banana_subpage.page_title 
            INNER JOIN banana_label
                ON banana_label.page_id = page.page_id
            WHERE
                parent_id = $parentId;"
        );

        foreach ($result as $row) {
            if ($row->page_id !== $id) {
                $labels[$row->label_name] = [
                    'page_id' => $row->page_id,
                    'target' => $row->label_target,
                    'text' => $row->label_text
                ];
            }
        }

        return $labels;
    }

    /**
     * @return array|false
     */
    public static function getSubpageInfo( Title $title ) {
        $parentId = self::getParentId($title);
        if ($parentId === false) return false;

        $dbr = wfGetDB( DB_REPLICA );
        $result = $dbr->select(
            'banana_subpage',
            [
                'page_title',
                'subpage_order',
                'subpage_number',
                'display_title'
            ],
            [
                'parent_id' => $parentId
            ]
        );

        $ordered = [];
        $order = -1;
        $text = $title->getDBkey();
        foreach ($result as $row) {
            $ordered[$row->subpage_order] = $row;
            if ($row->page_title === $text) $order = $row->subpage_order;
        }

        if ($order === -1) return [
            'parent_title' => $ordered[0]->page_title,
            'parent_display' => $ordered[0]->display_title,
            'rows' => $ordered
        ];

        return [
            'title' => $ordered[$order]->page_title,
            'number' => $ordered[$order]->subpage_number,
            'prefix' => self::numberToPrefixHTML($ordered[$order]->subpage_number),
            'display' => $ordered[$order]->display_title,
            'prev_title' => $order == 1 ? null : $ordered[$order - 1]->page_title,
            'prev_number' => $order == 1 ? null : $ordered[$order - 1]->subpage_number,
            'prev_prefix' => $order == 1 ? null : self::numberToPrefixHTML($ordered[$order - 1]->subpage_number),
            'prev_display' => $order == 1 ? null : $ordered[$order - 1]->display_title,
            'next_title' => !isset($ordered[$order + 1]) ? null : $ordered[$order + 1]->page_title,
            'next_number' => !isset($ordered[$order + 1]) ? null : $ordered[$order + 1]->subpage_number,
            'next_prefix' => !isset($ordered[$order + 1]) ? null : self::numberToPrefixHTML($ordered[$order + 1]->subpage_number),
            'next_display' => !isset($ordered[$order + 1]) ? null : $ordered[$order + 1]->display_title,
            'parent_title' => $ordered[0]->page_title,
            'parent_display' => $ordered[0]->display_title,
            'rows' => $ordered
        ];
    }

    /**
     * @return string
     */
    public static function getPagePrefix( Title $title ) {
		$subpageInfo = SubpageHandler::getSubpageInfo($title);
        return $subpageInfo === false ? '' : $subpageInfo['prefix'];
    }

    private static function numberToPrefixHTML( $number ) {
        if ($number !== '') $number .= '.';
        return htmlentities($number, null, 'UTF-8');
    }

    /**
     * Update subpages and labels in database.
     * @param Title $title Title of the page.
     * @param string $data JSON string which comes from btex output.
     */
    public static function updatePageData( Title $title, ParserOutput $output ) {
        $namespace = $title->getNamespace();
        $dbw = wfGetDB( DB_MASTER );

        if ($namespace === NS_NOTES) {
            $data = $output->getExtensionData('btex-data');
            $json = json_decode($data ?? '{}') ?? [];

            $isRoot = strpos($title->getText(), '/') === false;
            $titleText = $title->getDBkey();
            $id = $title->getArticleID();

            $pagePrefix = self::getPagePrefix( $title );

            if ($isRoot) {
                $dbw->delete('banana_subpage', [ 'parent_id' => $id ]);
            }
            $dbw->delete('banana_label', [ 'page_id' => $id ]);

            if ($isRoot && isset($json->subpages)) {
                $rows = [];
                $rows[] = [
                    'page_namespace' => $namespace,
                    'page_title' =>  $titleText,
                    'parent_id' => $id,
                    'subpage_order' => 0,
                    'subpage_number' => '',
                    'display_title' => preg_replace('#^讲义:\s*#', '', $output->getDisplayTitle())
                ];

                $subpageTitles = [];

                $order = 1;
                foreach ($json->subpages as $subpage) {
                    $subpageTitle = $titleText . str_replace(' ', '_', self::normalizeSubpageTitle(substr($subpage->title, 1)));
                    if (!in_array( $subpageTitle, $subpageTitles )) {
                        $rows[] = [
                            'page_namespace' => $namespace,
                            'page_title' => $subpageTitle,
                            'parent_id' => $id,
                            'subpage_order' => $order,
                            'subpage_number' => $subpage->number,
                            'display_title' => $subpage->displayTitle
                        ];
                        $subpageTitles[] = $subpageTitle;
                        $order++;
                    }
                }

                $dbw->insert('banana_subpage', $rows);
            }

            if (isset($json->labels)) {
                $rows = [];
                foreach ($json->labels as $key => $label) {
                    $rows[] = [
                        'page_id' => $id,
                        'label_name' => $key,
                        'label_target' => $label->id,
                        'label_text' => str_replace('~~', $pagePrefix, $label->html)
                    ];
                }

                $dbw->insert('banana_label', $rows);
            }
        }

        $lang = $output->getExtensionData('btex-page-lang');
        if (!in_array( $lang, ['en', 'en-us', 'en-gb', 'fr', 'fr-fr', 'de', 'de-de', 'zh', 'zh-hans', 'zh-hant'] ))
            $lang = null;
        $dbw->update(
            'page',
            [ 'page_lang' => $lang ],
            [ 'page_id' => $title->getArticleID() ]
        );
    }

    private static function normalizeSubpageTitle($text) {
        $text = preg_replace('#\s+#', ' ', $text);
        $text = preg_replace('# $#', '', $text);
        return $text;
    }
}
