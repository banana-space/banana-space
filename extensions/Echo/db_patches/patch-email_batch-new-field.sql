ALTER TABLE /*_*/echo_email_batch ADD COLUMN eeb_event_hash varchar(32) binary not null default '';

DROP INDEX /*i*/echo_email_batch_user_priority_event ON /*_*/echo_email_batch;

CREATE INDEX /*i*/echo_email_batch_user_hash_priority ON /*_*/echo_email_batch (eeb_user_id, eeb_event_hash, eeb_event_priority);


