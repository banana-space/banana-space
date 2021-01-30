ALTER TABLE /*_*/flow_workflow
    CHANGE workflow_user_id workflow_user_id bigint unsigned default null,
    CHANGE workflow_user_wiki workflow_user_wiki varchar(32) binary default null;

