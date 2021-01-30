CREATE INDEX /*i*/echo_user_timestamp ON /*_*/echo_notification (notification_user, notification_timestamp);
DROP INDEX /*i*/user_timestamp ON /*_*/echo_notification;
