-- drop unique constraint & recreate index
DROP INDEX /*i*/flow_wiki_ref_pk ON /*_*/flow_wiki_ref;
DROP INDEX /*i*/flow_wiki_ref_revision ON /*_*/flow_wiki_ref;

CREATE INDEX /*i*/flow_wiki_ref_idx ON /*_*/flow_wiki_ref ( ref_src_namespace, ref_src_title, ref_type, ref_target_namespace, ref_target_title, ref_src_object_type, ref_src_object_id );
CREATE INDEX /*i*/flow_wiki_ref_revision ON /*_*/flow_wiki_ref ( ref_src_namespace, ref_src_title, ref_src_object_type, ref_src_object_id, ref_type, ref_target_namespace, ref_target_title );

-- drop unique constraint, change url column to blob & recreate index
DROP INDEX /*i*/flow_ext_ref_pk ON /*_*/flow_ext_ref;
DROP INDEX /*i*/flow_ext_ref_revision ON /*_*/flow_ext_ref;

ALTER TABLE /*_*/flow_ext_ref CHANGE ref_target ref_target BLOB NOT NULL;

CREATE INDEX /*i*/flow_ext_ref_idx ON /*_*/flow_ext_ref ( ref_src_namespace, ref_src_title, ref_type, ref_target(255), ref_src_object_type, ref_src_object_id );
CREATE INDEX /*i*/flow_ext_ref_revision ON /*_*/flow_ext_ref ( ref_src_namespace, ref_src_title, ref_src_object_type, ref_src_object_id, ref_type, ref_target(255) );
