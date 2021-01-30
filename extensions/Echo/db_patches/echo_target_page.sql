CREATE TABLE /*_*/echo_target_page (
	etp_user int unsigned not null default 0,
	etp_page int unsigned not null default 0,
	etp_event int unsigned not null default 0
) /*$wgDBTableOptions*/;

CREATE UNIQUE INDEX /*i*/echo_target_page_user_event ON /*_*/echo_target_page (etp_user, etp_event);
CREATE INDEX /*i*/echo_target_page_user_page_event ON /*_*/echo_target_page (etp_user, etp_page, etp_event);
