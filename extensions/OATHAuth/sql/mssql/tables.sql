CREATE TABLE /*_*/oathauth_users (
	-- User ID
	id INT NOT NULL PRIMARY KEY IDENTITY(0,1),

	-- Secret key
	secret NVARCHAR(255) NULL DEFAULT NULL,

	-- Scratch tokens
	scratch_tokens varbinary(511) NULL DEFAULT NULL
);
