@clean @api
Feature: Full text search
  @headings
  Scenario: Pages can be found by their headings
    When I api search for incategory:HeadingsTest "I am a heading"
    Then HasHeadings is the first api search result

  @headings
  Scenario: Ignored headings aren't searched so text with the same word is wins
    When I api search for incategory:HeadingsTest References
    Then HasReferencesInText is the first api search result

 @setup_main
  Scenario: Searching for a page using its title and another word not in the page's text doesn't find the page
    When I api search for DontExistWord Two Words
    Then there are no api search results

  @setup_main
  Scenario: Searching for a page using its title and another word in the page's text does find it
    When I api search for catapult Two Words
    Then Two Words is the first api search result

  @setup_phrase_rescore
  Scenario: Searching for an unquoted phrase finds the phrase first
    When I api search for Words Test Rescore
    Then Rescore Test Words Chaff is the first api search result

  @setup_phrase_rescore
  Scenario: Searching for a quoted phrase finds higher scored matches before the whole query interpreted as a phrase
    When I api search for Rescore "Test Words"
    Then Test Words Rescore Rescore Test Words is the first api search result

  # Note that other tests will catch this situation as well but this test should be pretty specific
  @setup_phrase_rescore
  Scenario: Searching for an unquoted phrase still prioritizes titles over text
    When I api search for Rescore Test TextContent
    Then Rescore Test TextContent is the first api search result

  @setup_phrase_rescore
  Scenario: Searching with a quoted word just treats the word as though it didn't have quotes
    When I api search for "Rescore" Words Test
    Then Test Words Rescore Rescore Test Words is the first api search result

  @programmer_friendly
  Scenario Outline: Programmer friendly searches
    When I api search for <term>
    Then <page> is the first api search result
  Examples:
    |        term         |        page         |
    | namespace aliases   | $wgNamespaceAliases |
    | namespaceAliases    | $wgNamespaceAliases |
    | $wgNamespaceAliases | $wgNamespaceAliases |
    | namespace_aliases   | $wgNamespaceAliases |
    | NamespaceAliases    | $wgNamespaceAliases |
    | wgnamespacealiases  | $wgNamespaceAliases |
    | snake case          | PFSC                |
    | snakeCase           | PFSC                |
    | snake_case          | PFSC                |
    | SnakeCase           | PFSC                |
    | Pascal Case         | PascalCase          |
    | pascalCase          | PascalCase          |
    | pascal_case         | PascalCase          |
    | PascalCase          | PascalCase          |
    | pascalcase          | PascalCase          |
    | numeric 7           | NumericCase7        |
    | numericcase7        | NumericCase7        |
    | numericCase         | NumericCase7        |
    | getInitial          | this.getInitial     |
    | reftoolbarbase js   | RefToolbarBase.js   |
    | this.iscamelcased   | PFTest Paren        |

  @stemmer
  Scenario Outline: Stemming works as expected
    When I api search for StemmerTest <term>
    Then <first_result> is the first api search result
      And <second_result> is the second api search result
  Examples:
    |    term    |      first_result      |    second_result    |
    | aliases    | StemmerTest Aliases    | StemmerTest Alias   |
    | alias      | StemmerTest Alias      | StemmerTest Aliases |
    | used       | StemmerTest Used       | none                |
    | uses       | StemmerTest Used       | none                |
    | use        | StemmerTest Used       | none                |
    | us         | none                   | none                |
    | guideline  | StemmerTest Guidelines | none                |

  @match_stopwords
  Scenario: When you search for a stopword you find pages with that stopword
    When I api search for to -intitle:Manyredirectstarget
    Then To is the first api search result

  @many_redirects
  Scenario: When you search for a page by redirects having more unrelated redirects doesn't penalize the score
    When I api search for incategory:ManyRedirectsTest Many Redirects Test
    Then Manyredirectstarget is the first api search result

  @fallback_finder
  Scenario: I can find things that Elasticsearch typically thinks of as word breaks in the title
    When I api search for $US
    Then $US is the first api search result

  @fallback_finder
  Scenario: I can find things that Elaticsearch typically thinks of as word breaks in redirect title
    When I api search for ¢
    Then Cent (currency) is the first api search result

  @accent_squashing
  Scenario Outline: Searching with accents
    When I api search for "<term>"
    Then <first_result> is the first api search result
  Examples:
    | term                   | first_result           |
    | África                 | África                 |
    | Africa                 | África                 |
    | AlphaBeta              | AlphaBeta              |
    | ÁlphaBeta              | none                   |

  @unicode_normalization
  Scenario Outline: Searching for similar unicode characters finds all variants
    When I api search for <term>
    Then there are 4 api search results
  Examples:
    | term |
    | वाङ्मय |
    | वाङ्‍मय |
    | वाङ‍्मय |
    | वाङ्‌मय |

  Scenario Outline: Searching without accents finds results with accents
    When I api search for <term>
    Then <result>
  Examples:
    | term        | result |
    | ανθρωπος    | Page with non ascii letters is the first api search result |
    | ἄνθρωπος    | Page with non ascii letters is the first api search result |

  @accented_namespace
  Scenario: Searching for an accented namespace without the accent finds things in it
    When I api search for mo:some text
    Then Mó:Test is the first api search result

  @accented_namespace
  Scenario: If the search started with a namespace it doesn't pick up the accented namespace
    When I api search for file:mo:some text
    Then Mó:Test is not in the api search results

  Scenario: Zero result queries are rewritten with suggestions
    When I api search with rewrites enabled for mani page
    Then Main Page is the first api search result
