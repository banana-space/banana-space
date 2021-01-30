-- 2012-05-06: Split event_agent field to allow anonymous agents.

ALTER TABLE echo_event CHANGE COLUMN event_agent event_agent_id int unsigned null;
ALTER TABLE echo_event ADD COLUMN event_agent_ip varchar(255) binary null;