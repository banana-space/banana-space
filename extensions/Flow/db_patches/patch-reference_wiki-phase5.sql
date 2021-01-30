-- Drop old indexes
DROP INDEX /*i*/flow_wiki_ref_idx ON /*_*/flow_wiki_ref;
DROP INDEX /*i*/flow_wiki_ref_revision ON /*_*/flow_wiki_ref;

DROP INDEX /*i*/flow_ext_ref_idx ON /*_*/flow_ext_ref;
DROP INDEX /*i*/flow_ext_ref_revision ON /*_*/flow_ext_ref;

-- Temporary migration leftovers. These have already been removed from officewiki.
DROP INDEX /*i*/flow_wiki_ref_workflow_id_idx_tmp ON /*_*/flow_wiki_ref;
DROP INDEX /*i*/flow_ext_ref_workflow_id_idx_tmp ON /*_*/flow_ext_ref;
