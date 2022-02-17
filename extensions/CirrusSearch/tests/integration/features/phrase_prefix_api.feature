@clean @api @phrase_prefix
Feature: Searches with a phrase prefix term
  Scenario: Simple quoted prefix phrases get results
    When I api search for functional p*
    Then Functional programming is the first api search result
      And *Functional* *programming* is referential transparency. is the highlighted snippet of the first api search result
