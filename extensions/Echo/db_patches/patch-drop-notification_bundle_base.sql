-- Drop unused field notification_bundle_base and the indexes that contain it
DROP INDEX /*i*/echo_notification_user_base_read_timestamp ON /*_*/echo_notification;
DROP INDEX /*i*/echo_notification_user_base_timestamp ON /*_*/echo_notification;
DROP INDEX /*i*/echo_notification_user_hash_base_timestamp ON /*_*/echo_notification;
ALTER TABLE /*_*/echo_notification DROP notification_bundle_base;
