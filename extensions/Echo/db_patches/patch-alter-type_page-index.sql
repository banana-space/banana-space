DROP INDEX /*i*/type_page ON /*_*/echo_event;

CREATE INDEX /*i*/event_type ON /*_*/echo_event (event_type);
