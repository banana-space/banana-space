@clean @filters @api
Feature: Searches with combined filters
  Scenario Outline: Filters can be combined
    When I api search for <term>
    Then <first_result> is the first api search result
  Examples:
    |                  term                   | first_result |
    | incategory:twowords intitle:catapult    | none         |
    | incategory:twowords intitle:"Two Words" | Two Words    |
    | incategory:alpha incategory:beta        | AlphaBeta    |
