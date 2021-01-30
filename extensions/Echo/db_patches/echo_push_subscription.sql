-- Stores push subscriptions associated with wiki users.
CREATE TABLE /*_*/echo_push_subscription (
	eps_id INT UNSIGNED NOT NULL PRIMARY KEY auto_increment,
	-- central user ID
	eps_user INT UNSIGNED NOT NULL,
	-- platform-provided push subscription token
	eps_token TEXT NOT NULL,
	-- SHA256 digest of the push subscription token (to be used as a uniqueness constraint, since
	-- the tokens themselves may be large)
	eps_token_sha256 CHAR(64) NOT NULL UNIQUE,
	-- push provider ID, expected to reference values 'fcm' or 'apns'
	eps_provider TINYINT UNSIGNED NOT NULL,
	-- last updated timestamp
	eps_updated TIMESTAMP NOT NULL,
	-- push subscription metadata (e.g APNS notification topic)
	eps_data BLOB,
	FOREIGN KEY (eps_provider) REFERENCES /*_*/echo_push_provider(epp_id)
) /*$wgDBTableOptions*/;

CREATE INDEX /*i*/echo_push_subscription_user_id ON /*_*/echo_push_subscription (eps_user);
