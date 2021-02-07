<?php

use MediaWiki\MediaWikiServices;

class SubpageHandler {
    public static function getLabels( Title $title ) {
        $isRoot = strpos($title->getText(), '/') === false;
        $namespace = $title->getNamespace();
        $titleText = $title->getText();
        $id = $title->getArticleID();

        $dbr = wfGetDB( DB_REPLICA );

        $parentId = $title->getArticleID();
        if (!$isRoot) {
            $result = $dbr->select(
                'banana_subpage',
                'parent_id',
                [
                    'page_namespace' => $namespace,
                    'page_title' => $titleText
                ]
            );
            foreach ($result as $row) $parentId = $row->parent_id;
        }

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

    public static function getPagePrefix( Title $title ) {
        $isRoot = strpos($title->getText(), '/') === false;
        if ($isRoot) return '';

        $namespace = $title->getNamespace();
        $titleText = $title->getText();

        $dbr = wfGetDB( DB_REPLICA );

        $pageNumber = '';
        if (!$isRoot) {
            $result = $dbr->select(
                'banana_subpage',
                'subpage_number',
                [
                    'page_namespace' => $namespace,
                    'page_title' => $titleText
                ]
            );
            foreach ($result as $row) $pageNumber = $row->subpage_number;
        }

        return $pageNumber === '' ? '' : $pageNumber . '.';
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
                'subpage_level' => 0,
                'subpage_number' => ''
            ];

            $order = 1;
            foreach ($json->subpages as $subpage) {
                $rows[] = [
                    'page_namespace' => $namespace,
                    'page_title' =>  $titleText . substr($subpage->title, 1),
                    'parent_id' => $id,
                    'subpage_order' => $order,
                    'subpage_level' => $subpage->level,
                    'subpage_number' => $subpage->number
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
