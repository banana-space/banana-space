-- updates suppression terminology, which used to be called 'censor'

UPDATE /*_*/flow_revision SET rev_change_type = 'suppress-post' WHERE rev_change_type = 'censor-post' AND rev_type = 'post';
UPDATE /*_*/flow_revision SET rev_change_type = 'suppress-topic' WHERE rev_change_type = 'censor-topic' AND rev_type = 'post';

UPDATE /*_*/logging SET log_action = 'flow-suppress-post' WHERE log_action = 'flow-censor-post' AND log_type = 'suppress';
UPDATE /*_*/logging SET log_action = 'flow-suppress-topic' WHERE log_action = 'flow-censor-topic' AND log_type = 'suppress';

-- recentchanges: this query is expensive & the code has fallbacks in place
-- don't execute unless you only have few Flow data
UPDATE /*_*/recentchanges SET rc_params = REPLACE(rc_params, 's:11:"censor-post"', 's:13:"suppress-post"') WHERE (rc_type = 142 OR rc_source = 'flow') AND rc_params LIKE '%s:11:"censor-post"%';
UPDATE /*_*/recentchanges SET rc_params = REPLACE(rc_params, 's:12:"censor-topic"', 's:14:"suppress-topic"') WHERE (rc_type = 142 OR rc_source = 'flow') AND rc_params LIKE '%s:12:"censor-topic"%';
