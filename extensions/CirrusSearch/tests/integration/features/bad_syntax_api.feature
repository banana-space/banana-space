@bad_syntax @clean @api
Feature: Searches with syntax errors
  @setup_main
  Scenario: Searching for <text>~<text> treats the tilde like a space except that the whole "word" (including tilde) makes a phrase search
    When I api search for ffnonesenseword~catapult
    Then Two Words is the first api search result

 @setup_main
  Scenario: Searching for <text>~<text> treats the tilde like a space (not finding any results if a fuzzy search was needed)
    When I api search for ffnonesensewor~catapult
    Then none is the first api search result

  @balance_quotes
  Scenario Outline: Searching for for a phrase with a hanging quote adds the quote automatically
    When I api search for <term>
    Then Two Words is the first api search result
   Examples:
    |                      term                     |
    | "two words                                    |
    | "two words" "ffnonesenseword catapult         |
    | "two words" "ffnonesenseword catapult pickles |
    | "two words" pickles "ffnonesenseword catapult |

  @balance_quotes
  Scenario Outline: Searching for a phrase containing /, :, and \" find the page as expected
    Given a page named <title> exists
    When I api search for <term>
    Then <title> is the first api search result
  Examples:
    |                        term                       |                   title                   |
    | "10.1093/acprof:oso/9780195314250.003.0001"       | 10.1093/acprof:oso/9780195314250.003.0001 |
    | "10.5194/os-8-1071-2012"                          | 10.5194/os-8-1071-2012                    |
    | "10.7227/rie.86.2"                                | 10.7227/rie.86.2                          |
    | "10.7227\"yay"                                    | 10.7227"yay                               |
    | intitle:"1911 Encyclopædia Britannica/Dionysius"' | 1911 Encyclopædia Britannica/Dionysius    |

  Scenario: searching for NOT something will not crash (technically it should bring up the most linked document, but this isn't worth checking)
    When I api search for NOT catapult
    Then there is an api search result

  Scenario Outline: searching for less than and greater than doesn't find tons and tons of tokens
    When I api search for <query>
    Then none is the first api search result
  Examples:
    | query |
    | <}    |
    | <=}   |
    | >.    |
    | >=.   |
    | >     |
    | <     |
    | >>    |
    | <>    |
    | <>=   |
    | >>>   |
    | <<<   |
    | <<<~  |

  @filters
  Scenario Outline: Empty filters work like terms but aren't in test data so aren't found
    When I api search for <term>
	Then none is the first api search result
  Examples:
    |         term           |
    | intitle:"" catapult    |
    | incategory:"" catapult |
    | intitle:               |
    | intitle:""             |
    | incategory:            |
    | incategory:""          |
    | hastemplate:           |
    | hastemplate:""         |

  Scenario Outline: Searching with a / doesn't cause a degraded search result
    When I api search for main <term>
    Then Main Page is the first api search result
  Examples:
    |      term      |
    | intitle:/page  |
    | Main/Page      |

  @exact_quotes @setup_main
  Scenario: Searching for "<word> <word>"~<not a numer> treats the ~ as a space
    When I api search for "ffnonesenseword catapult"~anotherword
      And Two Words is the first api search result

  Scenario Outline: Searching for special whitespaces returns no result
    When I api search for <specialwhitespaces>
    Then none is the first api search result
  Examples:
    |     specialwhitespaces     |
    | %{\u3000}%                 |
    | %{\u0009}%%{\u3000}%       |
    | %{\u0009}% %{\u3000}%      |
    | %ideographic_whitespace%   |

  @boolean_operators
  Scenario Outline: ORs and ANDs around phrase prefixes finds the search terms
    When I api search for "test catapul*" <operator> "test catapul*" <operator> "test catapul*"
    Then there are no errors reported by the api
  Examples:
    | operator |
    | AND      |
    | OR       |
