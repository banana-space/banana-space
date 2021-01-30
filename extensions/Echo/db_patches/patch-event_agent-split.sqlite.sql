-- Split event_agent field to allow anonymous agents 
ALTER TABLE echo_event ADD COLUMN event_agent_id int unsigned null;
ALTER TABLE echo_event ADD COLUMN event_agent_ip varchar binary null;
UPDATE echo_event SET event_agent_id = event_agent;

-- Rename current table to temporary name
ALTER TABLE /*_*/echo_event RENAME TO /*_*/temp_echo_event_split_event_agent;

-- Recreate table using the proper nullability constraint for event_variant
CREATE TABLE /*_*/echo_event (
    event_id int unsigned not null primary key auto_increment,
    event_type varchar(64) binary not null,
    event_variant varchar(64) binary null,
    event_agent_id int unsigned null, -- The user who triggered it, if any
    event_agent_ip varchar(39) binary null, -- IP address who triggered it, if any
    event_page_title varchar(255) binary null,
    event_extra BLOB NULL
) /*$wgDBTableOptions*/;

-- Copy over all the old data into the new table
INSERT INTO /*_*/echo_event
    (event_id, event_type, event_variant, event_agent_id, event_page_title, event_extra)
SELECT
    event_id, event_type, event_variant, event_agent, event_page_title, event_extra
FROM
    /*_*/temp_echo_event_split_event_agent;

-- Drop the original table
DROP TABLE /*_*/temp_echo_event_split_event_agent;

-- recreate indexes
CREATE INDEX /*i*/echo_event_type ON /*_*/echo_event (event_type);


