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
        $titleText = $title->getText();
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
        $text = $title->getText();
        foreach ($result as $row) {
            $ordered[$row->subpage_order] = $row;
            if ($row->page_title === $text) $order = $row->subpage_order;
        }

        if ($order === -1) return [
            'parent_title' => $ordered[0]->page_title,
            'parent_display' => $ordered[0]->display_title
        ];

        error_log('DEBUG ' . $ordered[$order]->subpage_number);

        return [
            'title' => $ordered[$order]->page_title,
            'number' => $ordered[$order]->subpage_number,
            'prefix' => self::numberToPrefix($ordered[$order]->subpage_number),
            'display' => $ordered[$order]->display_title,
            'prev_title' => $order == 1 ? null : $ordered[$order - 1]->page_title,
            'prev_number' => $order == 1 ? null : $ordered[$order - 1]->subpage_number,
            'prev_prefix' => $order == 1 ? null : self::numberToPrefix($ordered[$order - 1]->subpage_number),
            'prev_display' => $order == 1 ? null : $ordered[$order - 1]->display_title,
            'next_title' => !isset($ordered[$order + 1]) ? null : $ordered[$order + 1]->page_title,
            'next_number' => !isset($ordered[$order + 1]) ? null : $ordered[$order + 1]->subpage_number,
            'next_prefix' => !isset($ordered[$order + 1]) ? null : self::numberToPrefix($ordered[$order + 1]->subpage_number),
            'next_display' => !isset($ordered[$order + 1]) ? null : $ordered[$order + 1]->display_title,
            'parent_title' => $ordered[0]->page_title,
            'parent_display' => $ordered[0]->display_title
        ];
    }

    /**
     * @return string
     */
    public static function getPagePrefix( Title $title ) {
		$subpageInfo = SubpageHandler::getSubpageInfo($title);
        return $subpageInfo === false ? '' : $subpageInfo['prefix'];
    }

    private static function numberToPrefix( $number ) {
        if ($number !== '') $number .= '.';
        return $number;
    }

    /**
     * Update subpages and labels in database.
     * @param Title $title Title of the page.
     * @param string $data JSON string which comes from btex output.
     */
    public static function updatePageData( Title $title, string $data ) {
        $json = json_decode($data) ?? [];
        $isRoot = $title->getRootText() === $title->getText();
        $namespace = $title->getNamespace();
        $titleText = $title->getText();
        $id = $title->getArticleID();

        $dbw = wfGetDB( DB_MASTER );
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
                'display_title' => null
            ];

            $order = 1;
            foreach ($json->subpages as $subpage) {
                $rows[] = [
                    'page_namespace' => $namespace,
                    'page_title' =>  $titleText . substr($subpage->title, 1),
                    'parent_id' => $id,
                    'subpage_order' => $order,
                    'subpage_number' => $subpage->number,
                    'display_title' => $subpage->displayTitle
                ];
                $order++;
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
}
