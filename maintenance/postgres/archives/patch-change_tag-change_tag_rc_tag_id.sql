-- T193874: Add new indexes to change_tag table using ct_tag_id instead of ct_tag

CREATE UNIQUE INDEX /*i*/change_tag_rc_tag_id ON /*_*/change_tag (ct_rc_id,ct_tag_id);
CREATE UNIQUE INDEX /*i*/change_tag_log_tag_id ON /*_*/change_tag (ct_log_id,ct_tag_id);
CREATE UNIQUE INDEX /*i*/change_tag_rev_tag_id ON /*_*/change_tag (ct_rev_id,ct_tag_id);

CREATE INDEX /*i*/change_tag_rc_tag_nonuniq ON /*_*/change_tag (ct_rc_id,ct_tag);
CREATE INDEX /*i*/change_tag_log_tag_nonuniq ON /*_*/change_tag (ct_log_id,ct_tag);
CREATE INDEX /*i*/change_tag_rev_tag_nonuniq ON /*_*/change_tag (ct_rev_id,ct_tag);

DROP INDEX /*i*/change_tag_rc_tag;
DROP INDEX /*i*/change_tag_log_tag;
DROP INDEX /*i*/change_tag_rev_tag;

ALTER TABLE /*_*/change_tag
  ALTER COLUMN ct_tag SET DEFAULT '';
