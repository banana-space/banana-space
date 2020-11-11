CREATE TABLE /*_*/oathauth_users (
	-- User ID
	id int not null primary key,

	-- Secret key
	secret varbinary(255) null,

	-- Scratch tokens
	scratch_tokens varbinary(511) null

) /*$wgDBTableOptions*/;
