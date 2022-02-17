@clean @exact_quotes @api
Feature: Searches that contain quotes
  Scenario: Searching for a word in quotes disbles stemming (can still find plural with exact match)
    When I api search for "pickles"
    Then Two Words is the first api search result

  Scenario: Searching for a phrase in quotes disbles stemming (can't find plural with singular)
    When I api search for "catapult pickle"
    Then there are no api search results

  Scenario: Searching for a phrase in quotes disbles stemming (can still find plural with exact match)
    When I api search for "catapult pickles"
    Then Two Words is the first api search result

  Scenario: Quoted phrases have a default slop of 0
    When I api search for "ffnonesenseword pickles"
    Then none is the first api search result
    When I api search for "ffnonesenseword pickles"~1
    Then Two Words is the first api search result

  Scenario: Quoted phrases match stop words
    When I api search for "Contains A Stop Word"
    Then Contains A Stop Word is the first api search result

  Scenario: Adding a ~ to a phrase keeps stemming enabled
    When I api search for "catapult pickle"~
    Then Two Words is the first api search result

  Scenario: Adding a ~ to a phrase switches the default slop to 0
    When I api search for "ffnonesenseword pickle"~
    Then none is the first api search result
    When I api search for "ffnonesenseword pickle"~1~
    Then Two Words is the first api search result

  Scenario: Adding a ~ to a phrase stops it from matching stop words so long as there is enough slop
    When I api search for "doesn't actually Contain A Stop Words"~1~
    Then Doesn't Actually Contain Stop Words is the first api search result

  Scenario: Adding a ~<a number>~ to a phrase keeps stemming enabled
    When I api search for "catapult pickle"~0~
    Then Two Words is the first api search result

  Scenario: Adding a ~<a number> to a phrase turns off because it is a proximity search
    When I api search for "catapult pickle"~0
    Then there are no api search results

  Scenario: Searching for a quoted * actually searches for a *
    When I api search with query independent profile empty for "pick*"
    Then Pick* is the first api search result

  Scenario Outline: Searching for "<word> <word>"~<number> activates a proximity search
    When I api search for "ffnonesenseword anotherword"~<proximity>
    Then <result> is the first api search result
  Examples:
    | proximity | result    |
    | 0         | none      |
    | 1         | none      |
    | 2         | Two Words |
    | 3         | Two Words |
    | 77        | Two Words |

  Scenario Outline: Prefixing a quoted phrase with - or ! or NOT negates it
    When I api search for catapult <negation>"two words"<suffix>
    Then Catapult is in the api search results
      And Two Words is not in the api search results
  Examples:
    |    negation    | suffix |
    | -              |        |
    | !              |        |
    | NOT            |        |
    | -              | ~      |
    | !              | ~      |
    | NOT            | ~      |
    | -              | ~1     |
    | !              | ~1     |
    | NOT            | ~1     |
    | -              | ~7~    |
    | !              | ~7~    |
    | NOT            | ~7~    |

  Scenario: Can combine positive and negative phrase search
    When I api search for catapult "catapult" -"two words" -"some stuff"
    Then Catapult is in the api search results
      And Two Words is not in the api search results

  Scenario: Can combine positive and negative phrase search (backwards)
    When I api search for catapult -"asdf" "two words"
    Then Two Words is in the api search results
      And Catapult is not in the api search results

  @setup_main
  Scenario: Searching for a word in quotes disbles stemming (can't find plural with singular)
    When I api search for "pickle"
    Then there are no api search results
