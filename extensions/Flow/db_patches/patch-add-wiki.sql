ALTER TABLE /*_*/flow_tree_revision ADD tree_orig_user_wiki varchar(32) binary not null;

ALTER TABLE /*_*/flow_revision ADD rev_user_wiki varchar(32) binary not null;

ALTER TABLE /*_*/flow_revision ADD rev_mod_user_wiki varchar(32) binary default null;

ALTER TABLE /*_*/flow_revision ADD rev_edit_user_wiki varchar(32) binary default null;
