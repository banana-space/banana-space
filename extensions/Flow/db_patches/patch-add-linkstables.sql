CREATE TABLE /*_*/flow_wiki_ref (
	ref_src_object_id binary(11) not null,
	ref_src_object_type varbinary(32) not null,
	ref_src_workflow_id binary(11) not null,
	ref_src_namespace int not null,
	ref_src_title varbinary(255) not null,
	ref_target_namespace int not null,
	ref_target_title varbinary(255) not null,
	ref_type varbinary(16) not null
);

CREATE UNIQUE INDEX /*i*/flow_wiki_ref_pk ON /*_*/flow_wiki_ref
	(ref_src_namespace, ref_src_title, ref_type, ref_target_namespace, ref_target_title, ref_src_object_type, ref_src_object_id);

CREATE UNIQUE INDEX /*i*/flow_wiki_ref_revision ON /*_*/flow_wiki_ref
	(ref_src_namespace, ref_src_title, ref_src_object_type, ref_src_object_id, ref_type, ref_target_namespace, ref_target_title);

CREATE TABLE /*_*/flow_ext_ref (
	ref_src_object_id binary(11) not null,
	ref_src_object_type varbinary(32) not null,
	ref_src_workflow_id binary(11) not null,
	ref_src_namespace int not null,
	ref_src_title varbinary(255) not null,
	ref_target varbinary(255) not null,
	ref_type varbinary(16) not null
);

CREATE UNIQUE INDEX /*i*/flow_ext_ref_pk ON /*_*/flow_ext_ref
	(ref_src_namespace, ref_src_title, ref_type, ref_target, ref_src_object_type, ref_src_object_id);

CREATE UNIQUE INDEX /*i*/flow_ext_ref_revision ON /*_*/flow_ext_ref
	(ref_src_namespace, ref_src_title, ref_src_object_type, ref_src_object_id, ref_type, ref_target);
