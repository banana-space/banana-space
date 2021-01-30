-- Increase varchar size to 64
ALTER TABLE /*_*/flow_wiki_ref MODIFY ref_src_wiki varchar(64) binary not null;
