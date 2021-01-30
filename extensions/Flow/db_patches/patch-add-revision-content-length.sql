ALTER TABLE flow_revision
  ADD rev_content_length int NOT NULL DEFAULT 0,
  ADD rev_previous_content_length int NOT NULL DEFAULT 0;
