ALTER TABLE /*_*/flow_wiki_ref CHANGE ref_id ref_id BINARY(11) NOT NULL;
ALTER TABLE /*_*/flow_ext_ref CHANGE ref_id ref_id BINARY(11) NOT NULL;

ALTER TABLE /*_*/flow_wiki_ref ADD PRIMARY KEY (ref_id);
ALTER TABLE /*_*/flow_ext_ref ADD PRIMARY KEY (ref_id);
