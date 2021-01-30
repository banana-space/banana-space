-- Patch to add the "moderated reason" field to revision table

ALTER TABLE /*_*/flow_revision ADD COLUMN rev_mod_reason varchar(255) binary;