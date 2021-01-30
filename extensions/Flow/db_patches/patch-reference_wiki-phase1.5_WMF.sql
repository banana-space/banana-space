-- An earlier version of phase 1 was run in production.  This schema
-- change brings production from the current state to the state
-- required after phase 1.

-- Create new indexes
CREATE INDEX /*i*/flow_wiki_ref_idx_v2 ON /*_*/flow_wiki_ref
	(ref_src_wiki, ref_src_namespace, ref_src_title, ref_type, ref_target_namespace, ref_target_title, ref_src_object_type, ref_src_object_id);
CREATE INDEX /*i*/flow_wiki_ref_revision_v2 ON /*_*/flow_wiki_ref
	(ref_src_wiki, ref_src_namespace, ref_src_title, ref_src_object_type, ref_src_object_id, ref_type, ref_target_namespace, ref_target_title);

CREATE INDEX /*i*/flow_ext_ref_idx_v2 ON /*_*/flow_ext_ref
	(ref_src_wiki, ref_src_namespace, ref_src_title, ref_type, ref_target(255), ref_src_object_type, ref_src_object_id);

CREATE INDEX /*i*/flow_ext_ref_revision_v2 ON /*_*/flow_ext_ref
	(ref_src_wiki, ref_src_namespace, ref_src_title, ref_src_object_type, ref_src_object_id, ref_type, ref_target(255));
