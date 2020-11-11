BEGIN;
SET client_min_messages = 'ERROR';

DROP SEQUENCE IF EXISTS oathauth_users_id_seq CASCADE;

CREATE SEQUENCE oathauth_users_id_seq MINVALUE 0 START WITH 0;
CREATE TABLE oathauth_users (
	-- User ID
	id INTEGER NOT NULL PRIMARY KEY DEFAULT nextval('oathauth_users_id_seq'),

	-- Secret key
	secret TEXT NULL,

	-- Scratch tokens
	scratch_tokens TEXT NULL

);

COMMIT;