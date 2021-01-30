-- we used to store the username in there, when user was logged in
-- now we need it blank to reliably query for Special:Contributions
UPDATE /*_*/flow_revision SET rev_user_ip = NULL WHERE rev_user_id != 0;
