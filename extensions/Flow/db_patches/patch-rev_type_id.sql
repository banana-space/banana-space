
ALTER TABLE /*_*/flow_revision ADD rev_type_id binary(11) not null default '';

CREATE INDEX /*i*/flow_revision_type_id ON /*_*/flow_revision (rev_type, rev_type_id);
