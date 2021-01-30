ALTER TABLE /*_*/echo_target_page ADD etp_id int unsigned not null primary key auto_increment;
DROP INDEX /*i*/echo_target_page_user_event ON /*_*/echo_target_page;
CREATE INDEX /*i*/echo_target_page_user_event ON /*_*/echo_target_page (etp_user, etp_event);
