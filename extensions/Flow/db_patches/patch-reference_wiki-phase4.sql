-- After phase 3 is complete (re-running does not affect any more rows),
-- this should be run.

-- Mark field as not null
ALTER TABLE /*_*/flow_wiki_ref MODIFY ref_src_wiki varchar(16) binary not null;
ALTER TABLE /*_*/flow_ext_ref MODIFY ref_src_wiki varchar(16) binary not null;
