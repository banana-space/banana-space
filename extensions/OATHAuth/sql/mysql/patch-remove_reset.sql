ALTER TABLE /*_*/oathauth_users
  DROP COLUMN secret_reset;

ALTER TABLE /*_*/oathauth_users
  DROP COLUMN scratch_tokens_reset;

ALTER TABLE /*_*/oathauth_users
  DROP COLUMN is_validated;
