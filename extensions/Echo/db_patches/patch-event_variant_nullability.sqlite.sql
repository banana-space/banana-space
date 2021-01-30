-- Sqlites alter table statement can NOT change existing columns.  The only
-- option since we need to change the nullability of event_variant is to
-- recreate the table and copy the data over.

-- Rename current table to temporary name
ALTER TABLE /*_*/echo_event RENAME TO /*_*/temp_echo_event_variant_nullability;

-- Recreate table using the proper nullability constraint for event_variant
CREATE TABLE /*_*/echo_event (
    event_id int unsigned not null primary key auto_increment,
    event_type varchar(64) binary not null,
    event_variant varchar(64) binary null,
    event_agent_id int unsigned null, -- The user who triggered it, if any
    event_agent_ip varchar(39) binary null, -- IP address who triggered it, if any
    event_extra BLOB NULL,
    event_page_id int unsigned null,
    event_deleted tinyint unsigned not null default 0
) /*$wgDBTableOptions*/;

-- Copy over all the old data into the new table
INSERT INTO /*_*/echo_event 
	(event_id, event_type, event_variant, event_agent_id, event_agent_ip, event_extra, event_page_id, event_deleted)
SELECT
	event_id, event_type, event_variant, event_agent_id, event_agent_ip, event_extra, event_page_id, event_deleted
FROM
	/*_*/temp_echo_event_variant_nullability;

-- Drop the original table
DROP TABLE /*_*/temp_echo_event_variant_nullability;

-- recreate indexes
CREATE INDEX /*i*/echo_event_type ON /*_*/echo_event (event_type);

