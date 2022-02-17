@clean @dump_settings @phantomjs
Feature: You can dump the settings CirrusSearch set on Elasticsearch's indexes
  Scenario: You can dump the settings CirrusSearch set on Elasticsearch's indexes
    When I dump the cirrus settings
    Then A valid settings dump is produced
