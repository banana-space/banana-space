@clean @filters @intitle @api
Feature: Searches with the intitle filter
  Scenario: intitle: can be combined with other text
    When I api search for intitle:catapult amazing
    Then Amazing Catapult is the first api search result
      And Two Words is not in the api search results

  @wildcards
  Scenario: intitle: can take a wildcard
    When I api search for intitle:catapul*
    Then Catapult is in the api search results

  @wildcards @setup_main
  Scenario: intitle: can take a wildcard and combine it with a regular wildcard
    When I api search for intitle:catapul* amaz*
    Then Amazing Catapult is the first api search result

  Scenario: intitle: will accept a space after its : with quoted titles
    When I api search for intitle: "amazing catapult"
    Then Amazing Catapult is the first api search result
      And Two Words is not in the api search results

  Scenario: intitle: with quoted titles performs an exact phrase search
    When I api search for intitle:"links to catapult"
    Then Links To Catapult is the first api search result

  Scenario: intitle: with quoted titles performs an exact phrase search
    When I api search for intitle:"links catapult"
    Then Links To Catapult is not in the api search results

  Scenario: intitle: with quoted titles performs an exact phrase search even with escaped quotes
    When I api search for intitle:"\"links to catapult\""
    Then Links To Catapult is the first api search result

  Scenario: intitle: with quoted titles performs an exact phrase search even with escaped quote
    When I api search for intitle:"\"links catapult\""
    Then Links To Catapult is not in the api search results

  Scenario: intitle: only includes pages with the title
    When I api search for intitle:catapult
    Then Catapult is in the api search results
      And Amazing Catapult is in the api search results
      But Two Words is not in the api search results

  Scenario: -intitle: excludes pages with part of the title
    When I api search for -intitle:amazing intitle:catapult
    Then Catapult is the first api search result
      And Amazing Catapult is not in the api search results

  Scenario: -intitle: doesn't highlight excluded title
    When I api search for -intitle:catapult two words
    Then Two Words is the first api search result
      And ffnonesenseword catapult pickles anotherword is the highlighted snippet of the first api search result

  Scenario: intitle: will accept a space after its :
    When I api search for intitle: catapult
    Then Catapult is in the api search results
      And Amazing Catapult is in the api search results
      But Two Words is not in the api search results

  Scenario Outline: intitle: will accept multiple spaces between clauses
    When I api search for intitle:catapult<spaces>intitle:catapult
    Then Catapult is in the api search results
      And Amazing Catapult is in the api search results
      But Two Words is not in the api search results
  Examples:
    |       spaces       |
    |%{\u0020}%%{\u0020}%|
    |%{\u0020}%%{\u0009}%|
    |%{\u3000}%%{\u3000}%|
