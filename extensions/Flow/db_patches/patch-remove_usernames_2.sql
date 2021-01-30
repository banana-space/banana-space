ALTER TABLE /*_*/flow_workflow DROP workflow_user_text;

ALTER TABLE /*_*/flow_tree_revision DROP tree_orig_user_text;

ALTER TABLE /*_*/flow_revision
	DROP rev_user_text,
	DROP rev_mod_user_text,
	DROP rev_edit_user_text;
