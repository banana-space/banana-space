@clean @go @api
Feature: Go Search
  Scenario: I can "go" to a page with mixed capital and lower case name by the name all lower cased
    When I get api near matches for mixedcapsandlowercase
    Then MixedCapsAndLowerCase is the first api search result

  Scenario: I can "go" to a page with mixed capital and lower case name by the name with totally wrong case cased
    When I get api near matches for miXEdcapsandlowercASe
    Then MixedCapsAndLowerCase is the first api search result

  Scenario: I can "go" to a page with an accented character without the accent
    When I get api near matches for africa
    Then África is the first api search result

  @accented_namespace
  Scenario: I can "go" to a page in a namespace with an accented character without the accent
    When I get api near matches for mo:test
    Then Mó:Test is the first api search result

  @accented_namespace
  Scenario: When I "go" to a page with the namespace specified twice I end up on the search results page
    When I get api near matches for file:mo:test
    Then there are no api search results

  @from_core
  Scenario: I can "go" to a page with mixed capital and lower case name by the name all lower cased and quoted
    When I get api near matches for "mixedcapsandlowercase"
    Then MixedCapsAndLowerCase is the first api search result

  @options
  Scenario Outline: When I near match just one page I go to that page
    When I get api near matches for <term> Nearmatchflattentest
    Then <title> Nearmatchflattentest is the first api search result
  Examples:
    |      term      |      title      |
    | soñ onlyaccent | Soñ Onlyaccent  |
    | son onlyaccent | Soñ Onlyaccent  |
    | Søn Redirecttoomany | Søn Redirecttoomany |
    | Són Redirecttoomany | Són Redirecttoomany |

  @options
  Scenario Outline: When I near match more than one page but one is exact (case, modulo case, or converted to title case) I go to that page
    When I get api near matches for <term> Nearmatchflattentest
    Then <title> Nearmatchflattentest is the first api search result
  Examples:
    |      term      |      title      |
    | son            | son             |
    | Son            | Son             |
    | SON            | SON             |
    | soñ            | soñ             |
    | Son Nolower    | Son Nolower     |
    | son Nolower    | Son Nolower     |
    | SON Nolower    | SON Nolower     |
    | soñ Nolower    | Soñ Nolower     |
    | son Titlecase  | Son Titlecase   |
    | Son Titlecase  | Son Titlecase   |
    | soñ Titlecase  | Soñ Titlecase   |
    | SON Titlecase  | Son Titlecase   |
    | soñ twoaccents | Soñ Twoaccents  |
    | són twoaccents | Són Twoaccents  |
    | bach           | Bach            |
    | koan           | Koan            |
    | son redirect   | Son Redirect    |
    | Son Redirectnotbetter | Són Redirectnotbetter |
    | Søn Redirectnoncompete | Søn Redirectnoncompete |
    | Soñ Redirectnoncompete | Soñ Redirectnoncompete |

  @options
  Scenario Outline: When I near match more than one page but none of them are exact then I go to the search results page
    When I get api near matches for <term> Nearmatchflattentest
    Then  there are no api search results
  Examples:
    |       term      |
    | son twoaccents  |
    | Son Double      |
    | Son Redirecttoomany |

  @redirect
  Scenario: When I near match a redirect and a page then the redirect is chosen if it is a better match
    When I get api near matches for SEO Redirecttest
    Then SEO Redirecttest is the first api search result
