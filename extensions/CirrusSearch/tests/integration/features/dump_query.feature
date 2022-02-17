@clean @dump_quer @phantomjs
Feature: Can dump the query syntax
  Scenario: Can dump the query syntax
    Given I request a query dump for main page
     Then A valid query dump for main page is produced
