UPDATE /*_*/flow_topic_list
	SET topic_list_id = substr( topic_list_id, 1, 11 ),
			topic_id = substr( topic_id, 1, 11 );

UPDATE /*_*/flow_workflow
   SET workflow_id = substr( workflow_id, 1, 11 );

UPDATE /*_*/flow_tree_revision
	SET tree_rev_descendant_id = substr( tree_rev_descendant_id, 1, 11 ),
			tree_rev_id = substr( tree_rev_id, 1, 11 ),
			tree_parent_id = substr( tree_parent_id, 1, 11 );

UPDATE /*_*/flow_revision
	SET rev_id = substr( rev_id, 1, 11 ),
			rev_parent_id = substr( rev_parent_id, 1, 11 ),
			rev_last_edit_id = substr( rev_last_edit_id, 1, 11 );

UPDATE /*_*/flow_tree_node
	SET tree_ancestor_id = substr( tree_ancestor_id, 1, 11 ),
			tree_descendant_id = substr( tree_descendant_id, 1, 11 );
