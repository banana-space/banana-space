ALTER TABLE /*_*/echo_notification ADD COLUMN notification_bundle_base boolean not null default 1;
ALTER TABLE /*_*/echo_notification ADD COLUMN notification_bundle_hash varchar(32) binary not null default '';
ALTER TABLE /*_*/echo_notification ADD COLUMN notification_bundle_display_hash varchar(32) binary not null default '';

CREATE INDEX /*i*/echo_notification_user_base_read_timestamp ON /*_*/echo_notification (notification_user, notification_bundle_base, notification_read_timestamp);
CREATE INDEX /*i*/echo_notification_user_base_timestamp ON /*_*/echo_notification (notification_user, notification_bundle_base, notification_timestamp, notification_event);
CREATE INDEX /*i*/echo_notification_user_hash_timestamp ON /*_*/echo_notification (notification_user, notification_bundle_hash, notification_timestamp);
CREATE INDEX /*i*/echo_notification_user_hash_base_timestamp ON /*_*/echo_notification (notification_user, notification_bundle_display_hash, notification_bundle_base, notification_timestamp);
