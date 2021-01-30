

ALTER TABLE /*_*/flow_revision RENAME TO /*_*/temp_flow_revision_change_type;

CREATE TABLE /*_*/flow_revision (
    -- UID::newTimestampedUID128()
    rev_id binary(16) not null,
    -- What kind of revision is this: tree/summary/etc.
    rev_type varchar(16) binary not null,
    -- user id creating the revision
    rev_user_id bigint unsigned not null,
    -- name of user creating the revision, or ip address if anon
    -- TODO: global user logins will obviate the need for this, but a round trip
    --       will be needed to map from rev_user_id -> user name
    rev_user_text varchar(255) binary not null default '',
    -- rev_id of parent or null if no previous revision
    rev_parent_id binary(16),
    -- comma separated set of ascii flags.
    rev_flags tinyblob not null,
    -- content of the revision
    rev_content mediumblob not null,
    -- the type of change that was made. MW message key.
    -- formerly rev_comment
    rev_change_type varbinary(255) null,
    -- current moderation state
    rev_mod_state varchar(32) binary not null,
    -- moderated by who?
    rev_mod_user_id bigint unsigned,
    rev_mod_user_text varchar(255) binary,
    rev_mod_timestamp varchar(14) binary,

    -- track who made the most recent content edit
    rev_last_edit_id binary(16) null,
    rev_edit_user_id bigint unsigned,
    rev_edit_user_text varchar(255) binary,

    PRIMARY KEY (rev_id)
) /*$wgDBTableOptions*/;

INSERT INTO /*_*/flow_revision
	(rev_id, rev_type, rev_user_id, rev_user_text, rev_parent_id, rev_flags, rev_content, rev_change_type, rev_mod_state, rev_mod_user_id, rev_mod_user_text, rev_mod_timestamp, rev_last_edit_id, rev_edit_user_id, rev_edit_user_text )
SELECT
	rev_id, rev_type, rev_user_id, rev_user_text, rev_parent_id, rev_flags, rev_content, rev_comment, rev_mod_state, rev_mod_user_id, rev_mod_user_text, rev_mod_timestamp, rev_last_edit_id, rev_edit_user_id, rev_edit_user_text
FROM
	/*_*/temp_flow_revision_change_type;

DROP TABLE /*_*/temp_flow_revision_change_type;

CREATE UNIQUE INDEX /*i*/flow_revision_unique_parent ON
    /*_*/flow_revision (rev_parent_id);
