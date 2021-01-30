
ALTER TABLE /*_*/flow_workflow ADD workflow_type varbinary(16);

-- this is very inefficient, but sqlite will not allow an update
-- against multiple tables.

UPDATE /*_*/flow_workflow
   SET workflow_type = ( SELECT definition_type
                           FROM /*_*/flow_definition
                          WHERE workflow_definition_id = definition_id );
