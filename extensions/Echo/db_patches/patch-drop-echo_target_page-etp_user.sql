-- Patch to drop unused etp_user

-- Drop indexes depending on etp_user
DROP INDEX /*i*/echo_target_page_user_event ON /*_*/echo_target_page;
DROP INDEX /*i*/echo_target_page_user_page_event ON /*_*/echo_target_page;

-- Drop etp_user column
ALTER TABLE /*_*/echo_target_page DROP etp_user;

-- Add index on etp_event
CREATE INDEX /*i*/echo_target_page_event ON /*_*/echo_target_page (etp_event);
