-- Increase varchar size to 64
ALTER TABLE /*_*/echo_unread_wikis MODIFY euw_wiki VARCHAR(64) NOT NULL;
