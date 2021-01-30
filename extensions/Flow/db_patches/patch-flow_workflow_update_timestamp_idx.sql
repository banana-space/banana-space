-- FlowForceSearchIndex.php maintenance script will request workflows between timestamps
CREATE INDEX /*i*/flow_workflow_update_timestamp ON /*_*/flow_workflow (workflow_last_update_timestamp);
