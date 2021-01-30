-- This patch doesn't need to be SQLite compatible (or re-implemented
-- for SQLite) since SQLite doesn't care about column widths anyway.
ALTER TABLE /*_*/flow_workflow MODIFY workflow_wiki varchar(64) binary not null;

ALTER TABLE /*_*/flow_tree_revision MODIFY tree_orig_user_wiki varchar(64) binary not null;

ALTER TABLE /*_*/flow_revision MODIFY rev_user_wiki varchar(64) binary not null,
                               MODIFY rev_mod_user_wiki varchar(64) binary default null,
                               MODIFY rev_edit_user_wiki varchar(64) binary default null;
