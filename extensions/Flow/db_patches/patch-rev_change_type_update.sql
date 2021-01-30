-- Updates older change_type values to match with action names

UPDATE /*_*/flow_revision SET rev_change_type = 'edit-title' WHERE rev_change_type IN('flow-rev-message-edit-title', 'flow-edit-title') AND rev_type = 'post';

UPDATE /*_*/flow_revision SET rev_change_type = 'new-post' WHERE rev_change_type IN('flow-rev-message-new-post', 'flow-new-post') AND rev_type = 'post';
UPDATE /*_*/flow_revision SET rev_change_type = 'edit-post' WHERE rev_change_type IN('flow-rev-message-edit-post', 'flow-edit-post') AND rev_type = 'post';
UPDATE /*_*/flow_revision SET rev_change_type = 'reply' WHERE rev_change_type IN('flow-rev-message-reply', 'flow-reply') AND rev_type = 'post';
UPDATE /*_*/flow_revision SET rev_change_type = 'restore-post' WHERE rev_change_type IN('flow-rev-message-restored-post', 'flow-post-restored') AND rev_type = 'post';
UPDATE /*_*/flow_revision SET rev_change_type = 'hide-post' WHERE rev_change_type IN('flow-rev-message-hid-post', 'flow-post-hidden') AND rev_type = 'post';
UPDATE /*_*/flow_revision SET rev_change_type = 'delete-post' WHERE rev_change_type IN('flow-rev-message-deleted-post', 'flow-post-deleted') AND rev_type = 'post';
UPDATE /*_*/flow_revision SET rev_change_type = 'censor-post' WHERE rev_change_type IN('flow-rev-message-censored-post', 'flow-post-censored') AND rev_type = 'post';

UPDATE /*_*/flow_revision SET rev_change_type = 'edit-header' WHERE rev_change_type IN ('flow-rev-message-edit-header', 'flow-edit-summary') AND rev_type = 'header';
UPDATE /*_*/flow_revision SET rev_change_type = 'create-header' WHERE rev_change_type IS NULL OR rev_change_type IN ('flow-rev-message-create-header', 'flow-create-summary', 'flow-create-header') AND rev_type = 'header';
