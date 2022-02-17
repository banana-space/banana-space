@clean @filters @incategory @api
Feature: Searches with the incategory filter

  Scenario: incategory: only includes pages with the category
    When I api search for incategory:weaponry
    Then Catapult is in the api search results
      And Amazing Catapult is in the api search results
      But Two Words is not in the api search results

  Scenario: incategory: splits on | to create an OR query
    When I api search for incategory:weaponry|nothing
    Then Catapult is in the api search results
      And Amazing Catapult is in the api search results
      But Two Words is not in the api search results

  Scenario Outline: incategory: does not fail when the category is unknown
    When I api search for incategory:<category>
    Then there are no api search results
  Examples:
    |          category           |
    | doesnotexistatleastihopenot |
    | id:2147483600               |

  Scenario: incategory: finds categories by page id
    When I locate the page id of Category:Weaponry and store it as %weaponry_id%
     And I api search for incategory:id:%weaponry_id%
    Then Catapult is in the api search results
      And Amazing Catapult is in the api search results
      But Two Words is not in the api search results

  Scenario: incategory: works on categories from templates
    When I api search for incategory:templatetagged incategory:twowords
    Then Two Words is the first api search result

  Scenario: incategory works with multi word categories
    When I api search for incategory:"Categorywith Twowords"
    Then Two Words is the first api search result

  Scenario: incategory can find categories containing quotes if the quote is escaped
    When I api search for incategory:"Categorywith \" Quote"
    Then Two Words is the first api search result

  Scenario: incategory can be repeated
    When I api search for incategory:"Categorywith \" Quote" incategory:"Categorywith Twowords"
    Then Two Words is the first api search result

  Scenario: incategory works with can find two word categories with spaces
    When I api search for incategory:Categorywith_Twowords
    Then Two Words is the first api search result

  Scenario: incategory: when passed a quoted category that doesn't exist finds nothing even though there is a category that matches one of the words
    When I api search for incategory:"Dontfindme Weaponry"
    Then there are no api search results

  Scenario: incategory when passed a single word category doesn't find a two word category that contains that word
    When I api search for incategory:ASpace
    Then there are no api search results

  Scenario: incategory: finds a multiword category when it is surrounded by quotes
    When I api search for incategory:"CategoryWith ASpace"
    Then IHaveATwoWordCategory is the first api search result

  Scenario: incategory: can be combined with other text
    When I api search for incategory:weaponry amazing
    Then Amazing Catapult is the first api search result

  Scenario: -incategory: excludes pages with the category
    When I api search for -incategory:weaponry incategory:twowords
    Then Two Words is the first api search result

  Scenario: incategory: can handle a space after the :
    When I api search for incategory: weaponry
    Then Catapult is in the api search results
      And Amazing Catapult is in the api search results
      But Two Words is not in the api search results

  Scenario Outline: incategory: can handle multiple spaces between clauses
    When I api search for incategory:weaponry<spaces>incategory:weaponry
    Then Catapult is in the api search results
      And Amazing Catapult is in the api search results
      And Two Words is not in the api search results
  Examples:
    |       spaces       |
    |%{\u0020}%%{\u0020}%|
    |%{\u0020}%%{\u0009}%|
    |%{\u3000}%%{\u3000}%|
