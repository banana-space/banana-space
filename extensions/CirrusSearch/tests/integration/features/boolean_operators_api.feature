@boolean_operators @clean @api @setup_main
Feature: Searches with boolean operators
  Scenario Outline: -, !, and NOT prohibit words in search results
    When I api search for <query>
    Then Catapult is the first api search result
      But Amazing Catapult is not in the api search results
  Examples:
  |        query         |
  | catapult -amazing    |
  | -amazing catapult    |
  | catapult !amazing    |
  | !amazing catapult    |
  | catapult NOT amazing |
  | NOT amazing catapult |

  Scenario Outline: +, &&, and AND require matches but since that is the default they don't look like they do anything
    When I api search for <query>
    Then Amazing Catapult is the first api search result
      But Catapult is not in the api search results
  Examples:
  |         query         |
  | +catapult amazing     |
  | amazing +catapult     |
  | +amazing +catapult    |
  | catapult AND amazing  |

  Scenario Outline: OR and || matches docs with either set
    When I api search for <query>
    Then Catapult is in the api search results
      And Two Words is in the api search results
  Examples:
  |          query         |
  | catapult OR África     |
  | África \|\| catapult   |
  | catapult OR "África"   |
  | catapult \|\| "África" |
  | "África" OR catapult   |
  | "África" \|\| catapult |
