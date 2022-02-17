@clean @filters @insource @api
Feature: Searches with the insource filter
  Scenario: insource: only includes pages with the source
    When I api search for all:insource:pickles
    Then Template:Template Test is in the api search results
      But Two Words is not in the api search results

  Scenario: insource: can be combined with other text
    When I api search for all:insource:catapult two words
    Then Two Words is the first api search result
      But Template:Template Test is not in the api search results

  Scenario: -insource: excludes pages with that in the source
    When I api search for all:-insource:pickles pickles
    Then Two Words is the first api search result
      But Template:Template Test is not in the api search results

  Scenario: insource with quotes performs an exact phrase query
    When I api search for insource:"catapult Template_Test anotherword"
    Then Two Words is the first api search result

  Scenario: insource with quotes performs an exact phrase query
    When I api search for insource:"catapult anotherword"
    Then Two Words is not in the api search results

  Scenario: insource with quotes performs an exact phrase query even with escaped quotes
    When I api search for insource:"\"catapult Template_Test anotherword\""
    Then Two Words is the first api search result

  Scenario: insource with quotes performs an exact phrase query even with escaped quotes
    When I api search for insource:"\"catapult anotherword\""
    Then Two Words is not in the api search results

  Scenario: insource splits on semicolon (T145023)
    When I api search for insource:somebug
    Then Wikitext is the first api search result

  @wildcards
  Scenario: insource: can take a wildcard
    When I api search for all:insource:pickl*
    Then Template:Template Test is the first api search result

  @regex
  Scenario: insource:// executes a regular expression
    When I api search for all:insource:/kles \[\[Ca/
    Then Template:Template Test is the first api search result

  @regex
  Scenario: insource:// can be combined with other filters
    When I api search for asdf insource:/\[\[Category/
    Then Catapult is the first api search result
    When I api search for insource:/\[\[Category/ asdf
    Then Catapult is the first api search result

  @regex
  Scenario: insource:// finds text inside of template calls
    When I api search for insource:/year_end.*=.*661/
    Then Rashidun Caliphate is the first api search result

  @regex
  Scenario: insource:// can find escaped forward slashes
    When I api search for insource:/a\/b/
    Then RegexEscapedForwardSlash is the first api search result

  @regex
  Scenario: insource:// can find escaped backslash
    When I api search for insource:/a\\b/
    Then RegexEscapedBackslash is the first api search result

  @regex
  Scenario: insource:// can find escaped dots
    When I api search for insource:/a\.b/
    Then RegexEscapedDot is the first api search result

  @regex
  Scenario: insource:// can contain spaces
    When I api search for RegexSpaces insource:/a b c/
    Then RegexSpaces is the first api search result

  @regex
  Scenario: insource:// can find a url
    When I api search for all:insource:/show_bug.cgi\?id=52908/
    Then File:Savepage-greyed.png is the first api search result

  @regex
  Scenario: insource:// is case sensitive by default but can be made case insensitive
    When I api search for insource:/a\.B/
    Then there are no api search results
    When I api search for insource:/a\.B/i
    Then RegexEscapedDot is the first api search result

  @regex
  Scenario: insource:// reports errors sanely
    When I api search for all:insource:/[ /
    Then this error is reported by api: Regular expression syntax error at 2: expected ']'

  @regex
  Scenario: insource:// doesn't break other clauses
    When I api search for insource:/b c/ insource:/a b c/
    Then RegexSpaces is the first api search result

  @regex
  Scenario: insource:// for some complex regexes fails entirely
    When I api search for all:insource:/[ab]*a[ab]{50,80}/
    Then this error is reported by api: Regular expression is too complex. Learn more about simplifying it [[mw:Special:MyLanguage/Help:CirrusSearch/RegexTooComplex|here]].

  @regex
  Scenario: insource:// for other complex regexes finds answers and doesn't spin forever
    When I api search for all:insource:/[ab]*a[cd]{50,80}/
    Then RegexComplexResult is the first api search result

  @regex
  Scenario Outline: insource:// accepts multiple spaces between clauses
    When I api search for insource:/b c/<spaces>insource:/a b c/
    Then RegexSpaces is the first api search result
  Examples:
    |       spaces       |
    |%{\u0020}%%{\u0020}%|
    |%{\u0020}%%{\u0009}%|
    |%{\u3000}%%{\u3000}%|

  @regex
  Scenario: intitle:// matches titles
    When I api search for intitle:/in Pa/
    Then Main Page is the first api search result
     And Ma*in Pa*ge is the highlighted title of the first api search result

  @regex @redirect
  Scenario: intitle:// matches redirects
    When I api search for intitle:/EO Redir/
    Then Search Engine Optimization Redirecttest is the first api search result
     And S*EO Redir*ecttest is the highlighted redirectsnippet of the first api search result

  @regex @redirect
  Scenario: intitle:// and insource:// can be combined
    When I api search for intitle:/EO Redir/ insource:/rch Eng/
    Then Search Engine Optimization Redirecttest is the first api search result
     And S*EO Redir*ecttest is the highlighted redirectsnippet of the first api search result
     And Sea*rch Eng*ine Optimization Redirecttest is the highlighted snippet of the first api search result
