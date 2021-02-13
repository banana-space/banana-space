<?php

class BananaParsoid {
    /**
     * Gets the cached btex output from the database.
     * @return string|null
     */
    public static function getFromDatabase( Title $title, string $code, string $preamble = '' ) {
        $md5 = md5($preamble . ' ' . $code);

        $dbr = wfGetDB( DB_REPLICA );
        $rows = $dbr->select(
            'banana_cache',
            [
                'result'
            ],
            [
                'page_id' => $title->getArticleID(),
                'md5' => $md5
            ]
        );

        foreach ($rows as $row) {
            return $row->result;
        }

        return null;
    }

    /**
     * Writes btex output to the database.
     * @return string|null
     */
    public static function writeToDatabase( Title $title, string $result, string $code, string $preamble = '' ) {
        $dbw = wfGetDB( DB_MASTER );
        $id = $title->getArticleID();

        $dbw->delete(
            'banana_cache',
            [
                'page_id' => $id
            ]
        );

        $dbw->insert(
            'banana_cache',
            [
                [
                    'page_id' => $id,
                    'md5' => md5($preamble . ' ' . $code),
                    'result' => $result
                ]
            ]
        );
    }
}
