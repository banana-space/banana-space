-- Changes rev_comment to rev_change_type
ALTER TABLE /*_*/flow_revision CHANGE rev_comment rev_change_type varbinary(255) null;
