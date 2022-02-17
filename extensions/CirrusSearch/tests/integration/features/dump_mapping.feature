@clean @dump_mapping @phantomjs
Feature: You can dump the mapping CirrusSearch set on Elasticsearch's indexes
  Scenario: You can dump the mapping CirrusSearch set on Elasticsearch's indexes
    When I dump the cirrus mapping
    Then A valid mapping dump is produced
