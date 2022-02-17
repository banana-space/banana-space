@frozen
Feature: Mutations to frozen indexes are properly delayed
  Scenario: Updates to frozen indexes are delayed
   Given I delete FrozenTest
     And a page named FrozenTest exists with contents foobarbaz
     And I api search for foobarbaz
     And FrozenTest is the first api search result
     And I globally freeze indexing
     And I don't wait for a page named FrozenTest to exist with contents superduperfrozen
     And I wait 10 seconds
     And I api search for superduperfrozen
     And FrozenTest is not in the api search results
    When I globally thaw indexing
     And I wait 10 seconds
    Then I api search for superduperfrozen yields FrozenTest as the first result

  Scenario: Deletes to frozen indexes are delayed
   Given a page named FrozenDeleteTest exists with contents bazbarfoo
     And I api search for bazbarfoo
     And FrozenDeleteTest is the first api search result
     And I globally freeze indexing
     And I delete FrozenDeleteTest without waiting
     And I don't wait for a page named FrozenDeleteTest to exist with contents mrfreeze recreated this page to work around mediawiki's behavior of not showing deleted pages in search results.  mrfreeze is surprisingly helpful.
     And I wait 10 seconds
     And I api search for bazbarfoo
     And FrozenDeleteTest is the first api search result
    When I globally thaw indexing
     And I wait 10 seconds
    Then I api search for bazbarfoo yields no results
