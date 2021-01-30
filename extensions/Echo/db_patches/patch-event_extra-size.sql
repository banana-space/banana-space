-- Patch to add extra space to event_extra
alter table /*_*/echo_event change column event_extra event_extra BLOB NULL;