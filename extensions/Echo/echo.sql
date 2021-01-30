-- Database Schema for Echo notification system

-- An event is a thing that happened that caused one or more users to be notified.
-- For every notified user, there is a corresponding row in the echo_notification table.
CREATE TABLE /*_*/echo_event (
	-- Unique auto-increment ID
	event_id int unsigned not null primary key auto_increment,
	-- Event type; one of the keys in $wgEchoNotifications
	event_type varchar(64) binary not null,
	-- Unused, always null
	event_variant varchar(64) binary null,
	-- The agent (user who triggered the event), if any. If the agent is a logged-in user,
	-- event_agent_id contains their user ID and event_agent_ip is null. If the agent is
	-- an anonymous user , event_agent_ip contains their IP address and event_agent_id is null.
	-- If the event doesn't have an agent, both fields are null.
	event_agent_id int unsigned null,
	event_agent_ip varchar(39) binary null,
	-- JSON blob with additional information about the event
	event_extra BLOB NULL,
	-- Page ID of the page the event happened on, if any (key to page_id)
	event_page_id int unsigned null,
	-- Whether the event pertains to a deleted page and should be hidden. Events are marked as
	-- deleted when the related page is deleted, and unmarked as deleted when the related page
	-- is undeleted
	event_deleted tinyint unsigned not null default 0
) /*$wgDBTableOptions*/;

-- Index to get only "alert" types or only "message" types
CREATE INDEX /*i*/echo_event_type ON /*_*/echo_event (event_type);
-- Index to find events for a specific page
CREATE INDEX /*i*/echo_event_page_id ON /*_*/echo_event (event_page_id);

-- A notification is a user being notified about a certain event. Multiple users can be notified
-- about the same event.
CREATE TABLE /*_*/echo_notification (
	-- Key to event_id
	notification_event int unsigned not null,
	-- Key to user_id
	notification_user int unsigned not null,
	-- Timestamp when the notification was created
	notification_timestamp binary(14) not null,
	-- Timestamp when the user read the notification, or null if unread
	notification_read_timestamp binary(14) null,
	-- Hash for bundling together similar notifications. Notifications that can be bundled together
	-- will have the same hash
	notification_bundle_hash varchar(32) binary not null,
	PRIMARY KEY (notification_user, notification_event)
) /*$wgDBTableOptions*/;

-- Index to get a user's notifications in chronological order
CREATE INDEX /*i*/echo_user_timestamp ON /*_*/echo_notification (notification_user,notification_timestamp);
-- Used to get all notifications for a given event
CREATE INDEX /*i*/echo_notification_event ON /*_*/echo_notification (notification_event);
-- Used to get read/unread notifications for a user
CREATE INDEX /*i*/echo_notification_user_read_timestamp ON /*_*/echo_notification (notification_user, notification_read_timestamp);

-- Table gathering events for batch emails
-- If a user asks to receive batch emails, events are gathered in this table until it's time to
-- send an email. Once a user has been emailed about an event, it's deleted from this table.
CREATE TABLE /*_*/echo_email_batch (
	-- Unique auto-increment ID
	eeb_id int unsigned not null primary key auto_increment,
	-- Key to user_id
	eeb_user_id int unsigned not null,
	-- Priority of the event as defined in $wgEchoNotifications; events with lower numbers are listed first
	eeb_event_priority tinyint unsigned not null default 10,
	-- Key to event_id
	eeb_event_id int unsigned not null,
	-- Same value as notification_bundle_hash, or a unique value if notification_bundle_hash is empty
	eeb_event_hash varchar(32) binary not null
) /*$wgDBTableOptions*/;

-- Used to delete events once they have been processed, and to identify users with events to process
CREATE UNIQUE INDEX /*i*/echo_email_batch_user_event ON /*_*/echo_email_batch (eeb_user_id,eeb_event_id);
-- Used to get a list of events for a user, grouping events with the same hash and ordering by priority
CREATE INDEX /*i*/echo_email_batch_user_hash_priority ON /*_*/echo_email_batch (eeb_user_id, eeb_event_hash, eeb_event_priority);

-- A "target page" of an event is a page that, when the user visits it, causes the event to be
-- marked as read. Typically this is the same as the event's event_page_id, but some events
-- have multiple target pages, and many events don't set a target page at all. An event's
-- target pages are derived from the 'target-page' key in event_extra.
-- This table is also used for moderating events when the related page is deleted,
-- but this should use event_page_id instead (T217452).
CREATE TABLE /*_*/echo_target_page (
	-- Unique auto-increment ID
	etp_id int unsigned not null primary key auto_increment,
	-- Key to page_id
	etp_page int unsigned not null default 0,
	-- Key to event_id
	etp_event int unsigned not null default 0
) /*$wgDBTableOptions*/;

-- Not currently used
CREATE INDEX /*i*/echo_target_page_event ON /*_*/echo_target_page (etp_event);
-- Used to get the events associated with a given page
CREATE INDEX /*i*/echo_target_page_page_event ON /*_*/echo_target_page (etp_page, etp_event);
