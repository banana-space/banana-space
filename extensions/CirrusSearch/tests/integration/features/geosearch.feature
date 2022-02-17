#*  neartitle:Shanghai
#*  neartitle:50km,Seoul
#*  nearcoord:1.2345,-5.4321
#*  nearcoord:17km,54.321,-12.345
#*  boost-neartitle:"San Francisco"
#*  boost-neartitle:50km,Kampala
#*  boost-nearcoord:-12.345,87.654
#*  boost-nearcoord:77km,34.567,76.543

@clean @api @geo
Feature: Geographical searches
  Scenario: neartitle: with title
    When I api search for neartitle:10km,San_Jose
    Then Santa Clara is the first api search result

  Scenario: neartitle: with title and default radius
    When I api search for neartitle:Cupertino
    Then there are no api search results

  Scenario Outline: nearcoord: with coordinate
    When I api search for nearcoord:<coordinate>
    Then <city> is the first api search result
  Examples:
    | coordinate           | city        |
    |37.333333,-121.9      | San Jose    |
    |37.354444,-121.969167 | Santa Clara |
    |37.3175,-122.041944   | Cupertino   |

  Scenario Outline: nearcoord: with coordinate and radius
    When I api search for nearcoord:10km,<coordinate>
    Then there are <count> api search results
    Examples:
      | coordinate           | count |
      |37.333333,-121.9      | 2     |
      |37.354444,-121.969167 | 3     |
      |37.3175,-122.041944   | 2     |


  Scenario Outline: Title proximity boost
    When I api search for boost-neartitle:<distance>km,Cupertino nice city
    Then Cupertino is the first api search result
    And there are 3 api search results
  Examples:
    | distance |
    | 1        |
    | 5        |
    | 10       |
    | 100      |

  Scenario Outline: Coordinate proximity boost
    When I api search for boost-nearcoord:<coordinate> nice city
    Then <city> is the first api search result
    And there are 3 api search results
    Examples:
      | coordinate           | city        |
      |37.333333,-121.9      | San Jose    |
      |37.354444,-121.969167 | Santa Clara |
      |37.3175,-122.041944   | Cupertino   |
