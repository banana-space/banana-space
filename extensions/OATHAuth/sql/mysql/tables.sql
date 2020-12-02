CREATE TABLE /*_*/oathauth_users (
	-- User ID
	id int not null primary key,

	-- Module user has selected
	module varchar(255) not null,

	-- Data
	data blob null

) /*$wgDBTableOptions*/;
