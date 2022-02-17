@clean @api @prefer_recent
Feature: Searches with prefer-recent
  Scenario Outline: Simple smoke test for prefer-recent (make sure it returns results)
    When I api search for prefer-recent:<options> PreferRecent First OR Second OR Third
    Then PreferRecent First is in the api search results
    Then PreferRecent Second is in the api search results
    Then PreferRecent Third is in the api search results
  Examples:
    |   options   |
    | 1,.001      |
    | 1,0.001     |
    | 1,.0001     |
    | .99,.0001   |
    | .99,.001    |
    | 1         |
    | 1,1       |
    | 1,.2      |
