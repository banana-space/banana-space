ALTER TABLE /*_*/flow_workflow
	CHANGE workflow_id workflow_id binary(11) not null;

ALTER TABLE /*_*/flow_topic_list
	CHANGE topic_list_id topic_list_id binary(11) not null,
	CHANGE topic_id topic_id binary(11) not null;

ALTER TABLE /*_*/flow_tree_revision
	CHANGE tree_rev_descendant_id tree_rev_descendant_id binary(11) not null,
	CHANGE tree_rev_id tree_rev_id binary(11) not null,
	CHANGE tree_parent_id tree_parent_id binary(11) default null;

ALTER TABLE /*_*/flow_revision
	CHANGE rev_id rev_id binary(11) not null,
	CHANGE rev_parent_id rev_parent_id binary(11) default null,
	CHANGE rev_last_edit_id rev_last_edit_id binary(11) default null;

ALTER TABLE /*_*/flow_tree_node
	CHANGE tree_ancestor_id tree_ancestor_id binary(11) not null,
	CHANGE tree_descendant_id tree_descendant_id binary(11) not null;
