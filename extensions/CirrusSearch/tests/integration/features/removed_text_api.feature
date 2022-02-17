@clean @api @setup_main @removed_text
Feature: Removed text
  Scenario: Searching fox text that is inside <video> and <audio> tags doesn't find it
    When I api search for "JavaScript disabled"
    Then there are no api search results

  Scenario: Searching fox text that is inside autocollapse tags doesn't find it
    When I api search for inside autocollapse
    Then there are no api search results
