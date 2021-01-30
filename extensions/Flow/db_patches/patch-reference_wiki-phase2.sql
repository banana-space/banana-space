CREATE INDEX /*i*/flow_wiki_ref_workflow_id_idx_tmp ON /*_*/flow_wiki_ref
       (ref_src_workflow_id, ref_src_wiki);

CREATE INDEX /*i*/flow_ext_ref_workflow_id_idx_tmp ON /*_*/flow_ext_ref
       (ref_src_workflow_id, ref_src_wiki);
