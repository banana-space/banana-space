define mw_prefix='{$wgDBprefix}';

CREATE SEQUENCE oathauth_users_id_seq;
CREATE TABLE &mw_prefix.oathauth_users (
	-- User ID
	id NUMBER NOT NULL,

	-- Secret key
	secret VARCHAR2(255) NULL,

	-- Scratch tokens
	scratch_tokens varbinary(511) NULL

);
ALTER TABLE &mw_prefix.oathauth_users ADD CONSTRAINT &mw_prefix.oathauth_users_pk PRIMARY KEY (id);