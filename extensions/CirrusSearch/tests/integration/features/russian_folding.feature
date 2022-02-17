@clean @api @ru
Feature: Searches with Russian accents
  Scenario: Searching for ё when text has е
    When I api search on ru for чёрная дыра
    Then Черная дыра is the first api search result

  Scenario: Searching for е when text has ё
    When I api search on ru for черный
    Then Саша Чёрный is the first api search result

  Scenario: Searching for no accent and lowercase
    When I api search on ru for гликберг
    Then Саша Чёрный is the first api search result

  Scenario: Searching with insource allows to find exact matches
    When I api search on ru for insource:гликберг
    Then there are no api search results
    And I api search on ru for insource:гли́кберг
    Then Саша Чёрный is the first api search result

  Scenario: Searching for with accent
    When I api search on ru for Бра́зер
    Then Бразер is the first api search result

  Scenario: Searching for й when text has й
    When I api search on ru for чёрный
    Then Саша Чёрный is the first api search result

  Scenario: Searching for й when text has и
    When I api search on ru for чёрныи
    Then there are no api search results
