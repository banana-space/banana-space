CREATE TABLE /*_*/echo_unread_wikis (
	# Primary key
	euw_id INT UNSIGNED NOT NULL PRIMARY KEY AUTO_INCREMENT,
	# Global user id
	euw_user INT UNSIGNED NOT NULL,
	# Name of wiki
	euw_wiki VARCHAR(64) NOT NULL,
	# unread alerts count on that wiki
	euw_alerts INT UNSIGNED NOT NULL,
	# Timestamp of the most recent unread alert
	euw_alerts_ts BINARY(14) NOT NULL,
	# unread messages count on that wiki
	euw_messages INT UNSIGNED NOT NULL,
	# Timestamp of the most recent unread message
	euw_messages_ts BINARY(14) NOT NULL
) /*$wgDBTableOptions*/;

CREATE UNIQUE INDEX /*i*/echo_unread_wikis_user_wiki ON /*_*/echo_unread_wikis (euw_user,euw_wiki);
