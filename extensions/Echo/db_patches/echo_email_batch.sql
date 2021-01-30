CREATE TABLE /*_*/echo_email_batch (
	eeb_id int unsigned not null primary key auto_increment,
	eeb_user_id int unsigned not null,
	eeb_event_priority tinyint unsigned not null default 10, -- event priority
	eeb_event_id int unsigned not null
) /*$wgDBTableOptions*/;

CREATE UNIQUE INDEX /*i*/echo_email_batch_user_event ON /*_*/echo_email_batch (eeb_user_id,eeb_event_id);
CREATE UNIQUE INDEX /*i*/echo_email_batch_user_priority_event ON /*_*/echo_email_batch (eeb_user_id,eeb_event_priority,eeb_event_id);
