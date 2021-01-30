-- Updates Flow's recentchanges entries to new rc_source column
-- values for rc_source & rc_type are respectively RC_FLOW &
-- Flow\Data\RecentChanges::SRC_FLOW, as defined in Flow.php
UPDATE /*_*/recentchanges SET rc_source = "flow" WHERE rc_type = 142;
