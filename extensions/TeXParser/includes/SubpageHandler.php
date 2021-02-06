<?php

use MediaWiki\MediaWikiServices;

class SubpageHandler {
    public static function getLabels( Title $title ) {
        $lb = MediaWikiServices::getInstance()->getDBLoadBalancer();
        $dbr = $lb->getConnectionRef( DB_REPLICA );

        // TODO: $dbr->select( ... );

        return [];
    }

    /**
     * Update subpages and labels in database.
     * @param Title $title Title of the page.
     * @param string $data JSON string which comes from btex output.
     */
    public static function updatePageData( Title $title, string $data ) {
        $json = json_decode($data) ?? [];
        $isRoot = $title->getRootText() === $title->getText();
        $titleText = $title->getPrefixedText();
        $id = $title->getArticleID();

        $lb = MediaWikiServices::getInstance()->getDBLoadBalancer();
        $dbw = $lb->getConnectionRef( DB_MASTER );

        if ($isRoot) {
            $dbw->delete('banana_subpage', [ 'parent_id' => $id ]);
        }
        $dbw->delete('banana_label', [ 'page_id' => $id ]);

        if ($isRoot && isset($json->subpages)) {
            $rows = [];
            $order = 0;
            foreach ($json->subpages as $subpage) {
                $rows[] = [
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
                    'label_text' => $label->html
                ];
            }

            $dbw->insert('banana_label', $rows);
        }
    }
}
