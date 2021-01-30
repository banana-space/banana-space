-- Adds a ref_src_wiki field to reference tables


-- Add field to wiki_ref table
ALTER TABLE /*_*/flow_wiki_ref ADD COLUMN ref_src_wiki varchar(16) binary not null;

-- Drop indexes for adjustment
DROP INDEX /*i*/flow_wiki_ref_idx ON /*_*/flow_wiki_ref;
DROP INDEX /*i*/flow_wiki_ref_revision ON /*_*/flow_wiki_ref;

-- Populate wiki references with the appropriate wiki
UPDATE /*_*/flow_wiki_ref, /*_*/flow_workflow
	SET ref_src_wiki = workflow_wiki
	WHERE
		ref_src_workflow_id = workflow_id;

-- Recreate new indexes
CREATE INDEX /*i*/flow_wiki_ref_idx_v2 ON /*_*/flow_wiki_ref
	(ref_src_wiki, ref_src_namespace, ref_src_title, ref_type, ref_target_namespace, ref_target_title, ref_src_object_type, ref_src_object_id);
CREATE INDEX /*i*/flow_wiki_ref_revision_v2 ON /*_*/flow_wiki_ref
	(ref_src_wiki, ref_src_namespace, ref_src_title, ref_src_object_type, ref_src_object_id, ref_type, ref_target_namespace, ref_target_title);


-- Add field to ext_ref table
ALTER TABLE /*_*/flow_ext_ref ADD COLUMN ref_src_wiki varchar(16) binary not null;

-- Drop indexes
DROP INDEX /*i*/flow_ext_ref_idx ON /*_*/flow_ext_ref;
DROP INDEX /*i*/flow_ext_ref_revision ON /*_*/flow_ext_ref;

-- Populate external references with the appropriate wiki
UPDATE /*_*/flow_ext_ref, /*_*/flow_workflow
	SET ref_src_wiki = workflow_wiki
	WHERE
		ref_src_workflow_id = workflow_id;

-- Recreate new indexes
CREATE INDEX /*i*/flow_ext_ref_idx_v2 ON /*_*/flow_ext_ref
	(ref_src_wiki, ref_src_namespace, ref_src_title, ref_type, ref_target(255), ref_src_object_type, ref_src_object_id);

CREATE INDEX /*i*/flow_ext_ref_revision_v2 ON /*_*/flow_ext_ref
	(ref_src_wiki, ref_src_namespace, ref_src_title, ref_src_object_type, ref_src_object_id, ref_type, ref_target(255));
