@clean @phantomjs @setup_main @setup_namespaces
Feature: Full text search advanced features
  Background:
    Given I am at the search results page

  Scenario Outline: Main search with non-advanced clicky features
    When I click the <filter> link
      And I search for <term>
    Then I am on a page titled Search results
      And <first_result> is the first search result
  Examples:
    | filter                 | term         | first_result     |
    | Content pages          | catapult     | Catapult         |
    | Content pages          | smoosh       | none             |
    | Content pages          | nothingasdf  | none             |
    | Multimedia             | catapult     | none             |
    | Multimedia             | smoosh       | none             |
    | Multimedia             | nothingasdf  | File:Nothingasdf |
    | Everything             | catapult     | Catapult         |
    | Everything             | smoosh       | Help:Smoosh      |
    | Everything             | nothingasdf  | File:Nothingasdf |

  Scenario Outline: Main search with advanced clicky features
    When I click the Advanced link
      And I click the (Main) or (Article) label
      And I click the <filters> labels
      And I search for <term>
    Then I am on a page titled Search results
      And <first_result> is the first search result
  Examples:
    | filters             | term     | first_result   |
    | Talk, Help          | catapult | Talk:Two Words |
    | Help, Help talk     | catapult | none           |
    | (Main) or (Article) | catapult | Catapult       |
