
ALTER TABLE /*_*/flow_workflow ADD workflow_type varbinary(16);

UPDATE /*_*/flow_workflow, /*_*/flow_definition
   SET workflow_type = definition_type
 WHERE workflow_definition_id = definition_id;
