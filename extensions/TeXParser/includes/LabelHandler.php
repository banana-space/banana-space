<?php

/**
 * Labels are defined by \label{...} and used by \ref{...}.
 * This class reads and writes labels from/to the database.
 */

use MediaWiki\MediaWikiServices;

class LabelHandler {
    public static function getLabels( Title $title ) {
        $lb = MediaWikiServices::getInstance()->getDBLoadBalancer();
        $dbr = $lb->getConnectionRef( DB_REPLICA );

        // TODO: $dbr->select( ... );

        return [];
    }

    public static function setLabels( Title $title, $labels ) {
        $lb = MediaWikiServices::getInstance()->getDBLoadBalancer();
        $dbw = $lb->getConnectionRef( DB_MASTER );
        
        // TODO: ...
    }
}
