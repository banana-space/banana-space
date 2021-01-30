-- Sqlites alter table statement can NOT change existing columns. The only
-- option since we want to rename the table and its columns is to recreate
-- the table and copy the data over


CREATE TABLE /*_*/flow_header_revision (
	header_workflow_id binary(16) not null,
	header_rev_id binary(16) not null,
	PRIMARY KEY (header_workflow_id, header_rev_id)
);

INSERT INTO /*_*/flow_header_revision
	(header_workflow_id, header_rev_id)
SELECT
	summary_workflow_id, summary_rev_id
FROM
	/*_*/flow_summary_revision;

DROP TABLE /*_*/flow_summary_revision;

UPDATE /*_*/flow_revision SET rev_type='header' WHERE rev_type='summary';
