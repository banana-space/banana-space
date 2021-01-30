DROP INDEX /*i*/event_type ON /*_*/echo_event;

CREATE INDEX /*i*/echo_event_type ON /*_*/echo_event (event_type);
