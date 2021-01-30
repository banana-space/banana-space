-- Patch to add infomation about the last content edit to flow revisions
ALTER TABLE /*_*/flow_revision
	ADD rev_last_edit_id binary(16) null,
	ADD rev_edit_user_id bigint unsigned,
	ADD rev_edit_user_text varchar(255) binary,
	CHANGE rev_user_id rev_user_id bigint unsigned not null;

