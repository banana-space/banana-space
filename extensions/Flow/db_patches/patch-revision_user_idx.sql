-- Special:Contributions can do queries based on user id/ip
CREATE INDEX /*i*/flow_revision_user ON /*_*/flow_revision (rev_user_id, rev_user_ip, rev_user_wiki);
