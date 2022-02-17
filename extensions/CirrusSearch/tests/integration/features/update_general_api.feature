@clean @api @update
Feature: Search backend updates
  Scenario: Deleted pages are removed from the index
    Given a page named DeleteMe exists
      And I api search for DeleteMe
      And DeleteMe is the first api search result
     When I delete DeleteMe
      And I api search for DeleteMe
     Then there are no api search results

  Scenario: Deleted redirects are removed from the index
    Given a page named DeleteMe exists
      And a page named DeleteMeRedirect exists with contents #REDIRECT [[DeleteMe]]
      And I api search for DeleteMeRedirect
      And DeleteMe is the first api search result
     When I delete DeleteMeRedirect
     Then I api search for DeleteMeRedirect
      And there are no api search results

  Scenario: Altered pages are updated in the index
   Given I edit ChangeMe to add superduperchangedme
     And I api search for superduperchangedme
    Then ChangeMe is the first api search result

  # TODO: Templates don't seem to be propogating (even outside cirrus) in my MWV...
  #Scenario: Pages containing altered template are updated in the index
  #  Given a page named Template:ChangeMe exists with contents foo
  #    And a page named ChangeMyTemplate exists with contents {{Template:ChangeMe}}
  #   When I edit Template:ChangeMe to add superduperultrachangedme
  #        # TODO: How to wait for template to propgate? Might have to re-add within...
  #    And I wait 5 seconds
  #    And I api search for superduperultrachangedme
  #   Then ChangeMyTemplate is the first api search result

  Scenario: Really really long links don't break updates
    When a page named ReallyLongLink%{epoch} exists with contents @really_long_link.txt
     And I api search for ReallyLongLink%{epoch}
    Then ReallyLongLink%{epoch} is the first api search result

  # This test doesn't rely on our paranoid revision delete handling logic, rather, it verifies what should work with the
  # logic with a similar degree of paranoia
  Scenario: When a revision is deleted the page is updated regardless of if the revision is current
    Given a page named RevDelTest exists with contents first
      And a page named RevDelTest exists with contents delete this revision
      And I api search for intitle:RevDelTest "delete this revision"
     Then RevDelTest is the first api search result
      And a page named RevDelTest exists with contents current revision
     When I delete the second most recent revision via api of RevDelTest
      And I api search for intitle:RevDelTest "delete this revision"
     Then there are no api search results
     When I api search for intitle:RevDelTest current revision
     Then RevDelTest is the first api search result

  @move
  Scenario: Moved pages that leave a redirect are updated in the index
    Given a page named Move%{epoch} From2 exists with contents move me
      And I api search for Move%{epoch} From2
     Then Move%{epoch} From2 is the first api search result
     When I move Move%{epoch} From2 to Move%{epoch} To2 and do not leave a redirect via api
      And I api search for Move%{epoch} From2
     Then there are no api search results
      And I api search for Move%{epoch} To2
     Then Move%{epoch} To2 is the first api search result

  @move
  Scenario: Moved pages that switch indexes are removed from their old index if they leave a redirect
    Given a page named Move%{epoch} From3 exists with contents move me
      And I api search for Move%{epoch} From3
     Then Move%{epoch} From3 is the first api search result
     When I move Move%{epoch} From3 to User:Move%{epoch} To3 and leave a redirect via api
      And I api search for User:Move%{epoch} To3
     Then User:Move%{epoch} To3 is the first api search result
      And I api search for Move%{epoch} From3
     Then there are no api search results

  @move
  Scenario: Moved pages that switch indexes are removed from their old index if they don't leave a redirect
    Given a page named Move%{epoch} From4 exists with contents move me
      And I api search for Move%{epoch} From4
     Then Move%{epoch} From4 is the first api search result
     When I move Move%{epoch} From4 to User:Move%{epoch} To4 and do not leave a redirect via api
      And I api search for User:Move%{epoch} To4
     Then User:Move%{epoch} To4 is the first api search result
      And I api search for Move%{epoch} To4
     Then there are no api search results

  Scenario: Deleted pages are added to archive index
    Given a page named DeleteMeTest exists
      And I api search for DeleteMeTest
     Then DeleteMeTest is the first api search result
     When I delete DeleteMeTest
      # For some reason this is done in the browser
      And I search deleted pages for deletemetest
      And deleted page search returns DeleteMeTest as first result
