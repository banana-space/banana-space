-- Patch to update ip size from varbinary(255) to varbinary(39)
ALTER TABLE /*_*/echo_event CHANGE COLUMN event_agent_ip event_agent_ip varchar(39) binary NULL;
