@clean @api @prefix @redirect @accent_squashing @accented_namespace @suggest
Feature: Prefix search via api
# @suggest needs to be at the end because it will update the completion suggester index
  Scenario: Suggestions don't appear when you search for a string that is too long
    When I get api suggestions for 贵州省瞬时速度团头鲂身体c实施ysstsstsg说tyttxy以推销员会同香港推广系统在同他讨厌她团体淘汰>赛系统大选于它拥有一天天用于与体育学院国ttxzyttxtxytdttyyyztdsytstsstxtttd天天体育系统的摄像头听到他他偷笑>偷笑太阳团体杏眼桃腮他要tttxx y贵州省瞬时速度团头鲂身体c实施ysstsstsg说tyttxy以推销员会同香港推广系统在同他讨厌她团体淘汰>赛系统大选于它拥有一天天用于与体育学院国ttxzyttxtxytdttyyyztdsytstsstxtttd天天体育系统的摄像头听到他他偷笑>偷笑太阳团体杏眼桃腮他要tttxx y
#    Then the api warns Prefix search request was longer than the maximum allowed length. (288 > 255)
     Then the api returns error code request_too_long

  Scenario: Prefix search lists page name if both redirect and page name match
    When I get api suggestions for Redirecttest Y using the classic profile
    Then Redirecttest Yay is the first api suggestion
      And Redirecttest Yikes is not in the api suggestions
    When I get api suggestions for Redirecttest Y using the fuzzy profile
    Then Redirecttest Yay is the first api suggestion
      And Redirecttest Yikes is not in the api suggestions

  Scenario: Prefix search ranks redirects under title matches
    When I get api suggestions for PrefixRedirectRanking using the classic profile
    Then PrefixRedirectRanking 1 is the first api suggestion
      And PrefixRedirectRanking 2 is the second api suggestion
    When I get api suggestions for PrefixRedirectRanking using the fuzzy profile
    Then PrefixRedirectRanking 1 is the first api suggestion
      And PrefixRedirectRanking 2 is the second api suggestion

  Scenario: Prefix search with classic profile is stricter than the fuzzy profile
    When I get api suggestions for PrefixRedirectRankng using the classic profile
    Then the API should produce list of length 0
    When I get api suggestions for PrefixRedirectRankng using the fuzzy profile
    Then PrefixRedirectRanking 1 is the first api suggestion
      And PrefixRedirectRanking 2 is the second api suggestion

  Scenario Outline: Search suggestions with accents
    When I get api suggestions for <term> using the classic profile
    Then <first_suggestion> is the first api suggestion
      And <second_suggestion> is the second api suggestion
    When I get api suggestions for <term> using the fuzzy profile
    Then <first_suggestion> is the first api suggestion
      And <second_suggestion> is the second api suggestion
  Examples:
    |      term      | first_suggestion | second_suggestion |
    | Áccent Sorting | Áccent Sorting   | Accent Sorting    |
    | áccent Sorting | Áccent Sorting   | Accent Sorting    |
    | Accent Sorting | Accent Sorting   | Áccent Sorting    |
    | accent Sorting | Accent Sorting   | Áccent Sorting    |

  Scenario: Searching for a bare namespace finds everything in the namespace
    Given a page named Template talk:Foo exists
    When I get api suggestions for template talk:
    Then Template talk:Foo is in the api suggestions

  Scenario Outline: Search suggestions
    When I get api suggestions for <term> using the classic profile
    Then <first_result> is the first api suggestion
      And the api should offer to search for pages containing <term>
    When I get api suggestions for <term> using the fuzzy profile
    Then <first_result> is the first api suggestion
      And the api should offer to search for pages containing <term>
    When I get api near matches for <term>
      Then <title> is the first api search result
  Examples:
    | term                   | first_result           | title                  |
# Note that there are more links to catapult then to any other page that starts with the
# word "catapult" so it should be first
    | catapult               | Catapult               | Catapult               |
    | catapul                | Catapult               | none                   |
    | two words              | Two Words              | Two Words              |
#   | ~catapult              | none                   | none                   |
    | Template:Template Test | Template:Template Test | Template:Template Test |
    | l'or                   | L'Oréal                | none                   |
    | l or                   | L'Oréal                | none                   |
    | L'orea                 | L'Oréal                | none                   |
    | L'Oréal                | L'Oréal                | L'Oréal                |
    | L’Oréal                | L'Oréal                | L'Oréal                |
    | L Oréal                | L'Oréal                | L'Oréal                |
    | Jean-Yves Le Drian     | Jean-Yves Le Drian     | Jean-Yves Le Drian     |
    | Jean Yves Le Drian     | Jean-Yves Le Drian     | Jean-Yves Le Drian     |

  Scenario: Prefix search includes redirects
    When I get api suggestions for SEO Redirecttest using the classic profile
    Then SEO Redirecttest is the first api suggestion
    When I get api near matches for SEO Redirecttest
    Then SEO Redirecttest is the first api search result
    When I get api suggestions for SEO Redirecttest using the fuzzy profile
    Then SEO Redirecttest is the first api suggestion
    When I get api near matches for SEO Redirecttest
    Then SEO Redirecttest is the first api search result

  Scenario: Prefix search includes redirects for pages outside the main namespace
    When I get api suggestions for User_talk:SEO Redirecttest using the classic profile
    Then User talk:SEO Redirecttest is the first api suggestion
    When I get api near matches for User_talk:SEO Redirecttest
    Then User talk:SEO Redirecttest is the first api search result
    When I get api suggestions for User_talk:SEO Redirecttest using the fuzzy profile
    Then User talk:SEO Redirecttest is the first api suggestion
    When I get api near matches for User_talk:SEO Redirecttest
    Then User talk:SEO Redirecttest is the first api search result

  Scenario Outline: Search suggestions with accents
    When I get api suggestions for <term> using the classic profile
    Then <first_result> is the first api suggestion
      And the api should offer to search for pages containing <term>
    When I get api suggestions for <term> using the fuzzy profile
    Then <first_result> is the first api suggestion
      And the api should offer to search for pages containing <term>
    When I get api near matches for <term>
    Then <title> is the first api search result
  Examples:
    | term                   | first_result           | title                  |
    | África                 | África                 | África                 |
    | Africa                 | África                 | África                 |
    | AlphaBeta              | AlphaBeta              | AlphaBeta              |
    | ÁlphaBeta              | AlphaBeta              | AlphaBeta              |
    | Mó:Test                | Mó:Test                | Mó:Test                |
    | Mo:Test                | Mó:Test                | Mó:Test                |
    | file:Mo:Test           | none                   | none                   |

  Scenario Outline: Search suggestions with various profiles
    When I get api suggestions for <term> using the <profile> profile
    Then <result>
      And the api should offer to search for pages containing <term>
  Examples:
    | term      | profile           | result                                               |
    | África    | strict            | África is the first api suggestion                   |
    | Africa    | strict            | the API should produce list of length 0              |
    | Agrica    | strict            | the API should produce list of length 0              |
    | África    | normal            | África is the first api suggestion                   |
    | Africa    | normal            | África is the first api suggestion                   |
    | Agrica    | normal            | the API should produce list of length 0              |
    | África    | classic           | África is the first api suggestion                   |
    | Africa    | classic           | África is the first api suggestion                   |
    | Agrica    | classic           | the API should produce list of length 0              |
    | África    | fuzzy             | África is the first api suggestion                   |
    | Africa    | fuzzy             | África is the first api suggestion                   |
    | Agrica    | fuzzy             | África is the first api suggestion                   |
    | doors     | strict            | the API should produce list of length 0              |
    | doors     | classic           | the API should produce list of length 0              |
    | doors     | normal            | The Doors is the first api suggestion                |
    | the doors | normal            | The Doors is the first api suggestion                |
    | thedoors  | normal            | the API should produce list of length 0              |
    | doors     | fuzzy             | The Doors is the first api suggestion                |
    | the doors | fuzzy             | The Doors is the first api suggestion                |
    | thedoors  | fuzzy             | The Doors is the first api suggestion                |
    | endym     | classic           | the API should produce list of length 0              |
    | endym     | normal            | the API should produce list of length 0              |
    | endym     | fuzzy             | the API should produce list of length 0              |
    | endym     | normal-subphrases | Hyperion Cantos/Endymion is the first api suggestion |
    | endym     | fuzzy-subphrases  | Hyperion Cantos/Endymion is the first api suggestion |
    | endimion  | normal-subphrases | the API should produce list of length 0              |
    | endimion  | fuzzy-subphrases  | Hyperion Cantos/Endymion is the first api suggestion |
  # Just take too long to run on a regular basis
  # @redirect @huge
  # Scenario: Prefix search on pages with tons of redirects is reasonably fast
  #   Given a page named IHaveTonsOfRedirects exists
  #     And there are 1000 redirects to IHaveTonsOfRedirects of the form TonsOfRedirects%s
  #   When I type TonsOfRedirects into the search box
  #   Then suggestions should appear

  Scenario: Search suggestions
    When I ask suggestion API for main
     Then the API should produce list containing Main Page

  Scenario: Created pages suggestions
    When I ask suggestion API for x-m
      Then the API should produce list containing X-Men

  Scenario: Nothing to suggest
    When I ask suggestion API for jabberwocky
      Then the API should produce empty list

  Scenario: Ordering
    When I ask suggestion API for x-m
      Then the API should produce list starting with X-Men

  Scenario: Fuzzy
    When I ask suggestion API for xmen
      Then the API should produce list starting with X-Men

  Scenario: Empty tokens
    When I ask suggestion API for はー
      Then the API should produce list starting with はーい
      And I ask suggestion API for はい
      Then the API should produce list starting with はーい

  Scenario Outline: Search redirects shows the best redirect
    When I ask suggestion API for <term>
      Then the API should produce list containing <suggested>
  Examples:
    |   term      |    suggested      |
    | eise        | Eisenhardt, Max   |
    | max         | Max Eisenhardt    |
    | magnetu     | Magneto           |

  Scenario Outline: Search prefers exact match over fuzzy match and ascii folded
    When I ask suggestion API for <term>
      Then the API should produce list starting with <suggested>
  Examples:
    |   term      |    suggested      |
    | max         | Max Eisenhardt    |
    | main p      | Main Page         |
    | eis         | Eisenhardt, Max   |
    | ele         | Elektra           |
    | éle         | Électricité       |

  Scenario Outline: Search prefers exact db match over partial prefix match
    When I ask suggestion API at most 2 items for <term>
      Then the API should produce list starting with <first>
      And the API should produce list containing <other>
  Examples:
    |   term      |   first  | other  |
    | Ic          |  Iceman  |  Ice   |
    | Ice         |   Ice    | Iceman |

  Scenario: Ordering & limit
    When I ask suggestion API at most 1 item for x-m
      Then the API should produce list starting with X-Men
      And the API should produce list of length 1

  Scenario Outline: Search fallback to prefix search if namespace is provided
    When I ask suggestion API for <term>
      Then the API should produce list starting with <suggested>
  Examples:
    |   term      |    suggested        |
    | Special:    | Special:ActiveUsers |
    | Special:Act | Special:ActiveUsers |

  Scenario Outline: Search prefers main namespace over crossns redirects
    When I ask suggestion API for <term>
      Then the API should produce list starting with <suggested>
  Examples:
    |   term      |    suggested      |
    | V           | Venom             |
    | V:          | V:N               |
    | Z           | Zam Wilson        |
    | Z:          | Z:Navigation      |

  Scenario: Default sort can be used as search input
    When I ask suggestion API for Wilson
      Then the API should produce list starting with Sam Wilson

  Scenario Outline: Completion and prefixsearch both allow to search over multiple namespaces
    When I get api suggestions for <term> using the <profile> profile on namespaces <namespaces>
      Then <result>
      And the api should offer to search for pages containing <term>
  Examples:
    |   term      |   profile   | namespaces |          result                            |
    | Magnet      | strict      |     0,12   | Magneto is in the api suggestions          |
    | Magnet      | strict      |     0,12   | Help:Magneto is in the api suggestions     |
    | Magnet      | fuzzy       |     0,12   | Magneto is in the api suggestions          |
    | Magnet      | fuzzy       |     0,12   | Help:Magneto is in the api suggestions     |
    | Magnet      | normal      |     0,12   | Magneto is in the api suggestions          |
    | Magnet      | normal      |     0,12   | Help:Magneto is in the api suggestions     |
    | Magnet      | classic     |     0,12   | Magneto is in the api suggestions          |
    | Magnet      | classic     |     0,12   | Help:Magneto is in the api suggestions     |
    | Magnet      | strict      |     0      | Help:Magneto is not in the api suggestions |
    | Magnet      | fuzzy       |     0      | Help:Magneto is not in the api suggestions |
    | Magnet      | normal      |     0      | Help:Magneto is not in the api suggestions |
    | Magnet      | classic     |     0      | Help:Magneto is not in the api suggestions |
