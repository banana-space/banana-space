-- Renames "summaries" to "headers"
ALTER TABLE /*_*/flow_summary_revision
	RENAME TO flow_header_revision,
	DROP PRIMARY KEY,
	CHANGE COLUMN summary_workflow_id header_workflow_id binary(16) not null,
	CHANGE COLUMN summary_rev_id header_rev_id binary(16) not null,
	ADD PRIMARY KEY ( header_workflow_id, header_rev_id );

UPDATE /*_*/flow_revision SET rev_type='header' WHERE rev_type='summary';