ALTER TABLE /*_*/flow_workflow RENAME TO /*_*/temp_flow_workflow_default_null;

CREATE TABLE /*_*/flow_workflow (
        workflow_id binary(11) not null,
        workflow_wiki varchar(16) binary not null,
        workflow_namespace int not null,
        workflow_page_id int unsigned not null,
        workflow_title_text varchar(255) binary not null,
    workflow_name varchar(255) binary not null,
        workflow_last_update_timestamp binary(14) not null,
        -- TODO: check what the new global user ids need for storage
        workflow_user_id bigint unsigned default null,
        workflow_user_ip varbinary(39) default null,
        workflow_user_wiki varchar(32) binary default null,
        -- TODO: is this usefull as a bitfield?  may be premature optimization, a string
        -- or list of strings may be simpler and use only a little more space.
        workflow_lock_state int unsigned not null,
        workflow_type varbinary(16) not null,
        PRIMARY KEY (workflow_id)
) /*$wgDBTableOptions*/;

INSERT INTO /*_*/flow_workflow
    (workflow_id, workflow_wiki, workflow_namespace, workflow_page_id, workflow_title_text, workflow_name, workflow_last_update_timestamp, workflow_user_id, workflow_user_ip, workflow_user_wiki, workflow_lock_state, workflow_type )
    SELECT workflow_id, workflow_wiki, workflow_namespace, workflow_page_id, workflow_title_text, workflow_name, workflow_last_update_timestamp, workflow_user_id, workflow_user_ip, workflow_user_wiki, workflow_lock_state, workflow_type
    FROM /*_*/temp_flow_workflow_default_null;

DROP TABLE /*_*/temp_flow_workflow_default_null;

CREATE INDEX /*i*/flow_workflow_lookup ON /*_*/flow_workflow (workflow_wiki, workflow_namespace, workflow_title_text);

