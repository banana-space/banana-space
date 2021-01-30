DROP INDEX /*i*/user_event ON /*_*/echo_notification;

ALTER TABLE /*_*/echo_notification ADD PRIMARY KEY (notification_user, notification_event);
