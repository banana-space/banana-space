@clean @go @phantomjs
Feature: Go Search
  @from_core
  Scenario: I can "go" to a user's page whether it is there or not
    When I go search for User:DoesntExist
    Then I am on a page titled User:DoesntExist

  @options
  Scenario Outline: When I near match more than one page but one is exact (case, modulo case, or converted to title case) I go to that page
    When I go search for <term> Nearmatchflattentest
    Then I am on a page titled <title> Nearmatchflattentest
  Examples:
    |      term      |      title      |
    | bach           | Johann Sebastian Bach |
    | Søn Redirectnoncompete | Blah Redirectnoncompete |
    | Soñ Redirectnoncompete | Blah Redirectnoncompete |

  Scenario: Searching for a string that is a valid mediawiki title but longer than the max prefix search does not fail
    When I go search for vdyējūyeyafqhrqtwtfmvvbv不顾要死不活的姑娘风景如小D3：n t q h ra r n q r n q n r q r n w t n ran s g是否能Z或者 Ru 人也不发达噶分湖人奴嗯也能一年时光啊郭德纲）n蜂蜜犹如的还是创始人发布A大股东
    Then there are no search results
