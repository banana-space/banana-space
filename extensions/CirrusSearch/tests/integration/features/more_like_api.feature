@clean @more_like_this @api
Feature: More like an article
  Scenario: Searching for morelike:<page that doesn't exist> returns no results
    When I api search for morelike:IDontExist
    Then there are no api search results

  Scenario: Searching for morelike:<page> returns pages that are "like" that page
    When I api search for morelike:More Like Me 1
    Then More Like Me is in the first api search result
      But More Like Me 1 is not in the api search results

  Scenario: Searching for morelike:<redirect> returns pages that are "like" the page that it is a redirect to
    When I api search for morelike:More Like Me Rdir
    Then More Like Me is in the first api search result
      But More Like Me 1 is not in the api search results

  @redirect_loop
  Scenario: Searching for morelike:<redirect in a loop> returns no results
    When I api search for morelike:Redirect Loop
    Then there are no api search results

  Scenario: Searching for morelike:<page>|<page>|<page> returns pages that are "like" all those pages
    When I api search for morelike:More Like Me 1|More Like Me Set 2 Page 1|More Like Me Set 3 Page 1
    Then More Like Me is part of the api search result
      And More Like Me Set 2 is part of the api search result
      And More Like Me Set 3 is part of the api search result
      But More Like Me 1 is not in the api search results
      And More Like Me Set 2 Page 1 is not in the api search results
      And More Like Me Set 3 Page 1 is not in the api search results

  Scenario: Morelikethis can be combined
    When I api search for morelikethis:"More Like Me 1|More Like Me Set 2 Page 1|More Like Me Set 3 Page 1" intitle:2
    Then More Like Me Set 2 is part of the api search result
      But More Like Me Set 3 is not in the api search results

  Scenario: Searching for morelike:<page> with the title field and filtering with the word length
    When I set More Like This Options to title field, word length to 3 and I api search for morelike:More Like Me 1
    Then More Like Me 2 is part of the api search result
    And More Like Me 3 is part of the api search result
    And More Like Me 4 is part of the api search result
    And More Like Me 5 is part of the api search result
    And More Like Me Set 3 Page 3 is part of the api search result
    But More Like Me 1 is not part of the api search result
    And This is Me is not is not part of the api search result

  Scenario: Searching for morelike:<page> with the title field and filtering with the percent terms to match
    When I set More Like This Options to title field, percent terms to match to -30% and I api search for morelike:More Like Me 1
    Then More Like Me 2 is part of the api search result
    And More Like Me 3 is part of the api search result
    And More Like Me 4 is part of the api search result
    And More Like Me 5 is part of the api search result
    And More Like Me Set 3 Page 3 is part of the api search result
    But More Like Me 1 is not part of the api search result
    And This is Me is not part of the api search result

  Scenario: Searching for morelike:<page> with the title field and bad settings give no results
    When I set More Like This Options to bad settings and I api search for morelike:More Like Me 1
    Then there are no api search results

  Scenario: Searching for morelike:<page> with the title field and settings with poor precision
    When I set More Like This Options to title field, word length to 2 and I api search for morelike:More Like Me 1
    Then This is Me is part of the api search result
