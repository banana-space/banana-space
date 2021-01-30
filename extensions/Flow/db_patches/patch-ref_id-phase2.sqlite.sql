-- SQLite won't allow us to just alter the columns, so we'll move this table
-- out of the way, create it anew the way we want it, and then copy the
-- data over before dropping the old table
ALTER TABLE /*_*/flow_wiki_ref RENAME TO /*_*/temp_flow_wiki_ref;
ALTER TABLE /*_*/flow_ext_ref RENAME TO /*_*/temp_flow_ext_ref;

-- SQLite also doesn't allow multiple indexes with the same name (even if
-- they're on separate tables.
-- Since we're going to recreate these same tables with the same indexes, we
-- first need to drop them from the other table.
DROP INDEX /*i*/flow_wiki_ref_idx_v2;
DROP INDEX /*i*/flow_wiki_ref_revision_v2;
DROP INDEX /*i*/flow_ext_ref_idx_v2;
DROP INDEX /*i*/flow_ext_ref_revision_v2;

CREATE TABLE /*_*/flow_wiki_ref (
	ref_id binary(11) not null,
	ref_src_wiki varchar(16) binary not null,
	ref_src_object_id binary(11) not null,
	ref_src_object_type varbinary(32) not null,
	ref_src_workflow_id binary(11) not null,
	ref_src_namespace int not null,
	ref_src_title varbinary(255) not null,
	ref_target_namespace int not null,
	ref_target_title varbinary(255) not null,
	ref_type varbinary(16) not null,

	PRIMARY KEY (ref_id)
) /*$wgDBTableOptions*/;

CREATE INDEX /*i*/flow_wiki_ref_idx_v2 ON /*_*/flow_wiki_ref
	(ref_src_wiki, ref_src_namespace, ref_src_title, ref_type, ref_target_namespace, ref_target_title, ref_src_object_type, ref_src_object_id);

CREATE INDEX /*i*/flow_wiki_ref_revision_v2 ON /*_*/flow_wiki_ref
	(ref_src_wiki, ref_src_namespace, ref_src_title, ref_src_object_type, ref_src_object_id, ref_type, ref_target_namespace, ref_target_title);

CREATE TABLE /*_*/flow_ext_ref (
	ref_id binary(11) not null,
	ref_src_wiki varchar(16) binary not null,
	ref_src_object_id binary(11) not null,
	ref_src_object_type varbinary(32) not null,
	ref_src_workflow_id binary(11) not null,
	ref_src_namespace int not null,
	ref_src_title varbinary(255) not null,
	ref_target blob not null,
	ref_type varbinary(16) not null,

	PRIMARY KEY (ref_id)
) /*$wgDBTableOptions*/;

CREATE INDEX /*i*/flow_ext_ref_idx_v2 ON /*_*/flow_ext_ref
	(ref_src_wiki, ref_src_namespace, ref_src_title, ref_type, ref_target(255), ref_src_object_type, ref_src_object_id);

CREATE INDEX /*i*/flow_ext_ref_revision_v2 ON /*_*/flow_ext_ref
	(ref_src_wiki, ref_src_namespace, ref_src_title, ref_src_object_type, ref_src_object_id, ref_type, ref_target(255));


INSERT INTO /*_*/flow_wiki_ref
	(ref_id, ref_src_wiki, ref_src_object_id, ref_src_object_type, ref_src_workflow_id, ref_src_namespace, ref_src_title, ref_target_namespace, ref_target_title, ref_type)
SELECT
	ref_id, ref_src_wiki, ref_src_object_id, ref_src_object_type, ref_src_workflow_id, ref_src_namespace, ref_src_title, ref_target_namespace, ref_target_title, ref_type
FROM
	/*_*/temp_flow_wiki_ref;

INSERT INTO /*_*/flow_ext_ref
	(ref_id, ref_src_wiki, ref_src_object_id, ref_src_object_type, ref_src_workflow_id, ref_src_namespace, ref_src_title, ref_target, ref_type)
SELECT
	ref_id, ref_src_wiki, ref_src_object_id, ref_src_object_type, ref_src_workflow_id, ref_src_namespace, ref_src_title, ref_target, ref_type
FROM
	/*_*/temp_flow_ext_ref;

DROP TABLE /*_*/temp_flow_wiki_ref;
DROP TABLE /*_*/temp_flow_ext_ref;
