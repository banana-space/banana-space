-- Sqlite can't add a primary key to an existing table

-- give current table temporary name
ALTER TABLE /*_*/echo_target_page RENAME TO /*_*/temp_echo_target_page;

-- recreate table with our new setup
CREATE TABLE /*_*/echo_target_page (
    etp_id int unsigned not null primary key auto_increment,
    etp_user int unsigned not null default 0,
    etp_page int unsigned not null default 0,
    etp_event int unsigned not null default 0
) /*$wgDBTableOptions*/;

-- copy over old data into new table
INSERT INTO /*_*/echo_target_page
	(etp_user, etp_page, etp_event)
SELECT
	etp_user, etp_page, etp_event
FROM
	/*_*/temp_echo_target_page;

-- drop the original table
DROP TABLE /*_*/temp_echo_target_page;

-- recreate indexes
CREATE INDEX /*i*/echo_target_page_user_event ON /*_*/echo_target_page (etp_user, etp_event);
CREATE INDEX /*i*/echo_target_page_user_page_event ON /*_*/echo_target_page (etp_user, etp_page, etp_event);
