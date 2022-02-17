@clean @non_existent @api @update
Feature: Search backend updates that reference nonexistent pages
  Scenario: Pages that link to nonexistent pages still get their search index updated
    Given I delete IDontExist
      And a page named ILinkToNonExistentPages%{epoch} exists with contents [[IDontExist]]
      And I api search for ILinkToNonExistentPages%{epoch}
     Then ILinkToNonExistentPages%{epoch} is the first api search result

  Scenario: Pages that redirect to nonexistent pages don't throw errors
    Given I delete IDontExist
     When I don't wait for a page named IRedirectToNonExistentPages%{epoch} to exist with contents #REDIRECT [[IDontExist]]

  Scenario: Linking to a nonexistent page doesn't add it to the search index with an [INVALID] word count
    Given a page named ILinkToNonExistentPages%{epoch} exists with contents [[IDontExistLink%{epoch}]]
      And I api search for IDontExistLink%{epoch}
     Then ILinkToNonExistentPages%{epoch} is the first api search result
      And there are no api search results with [INVALID] words in the data
     When a page named IDontExistLink%{epoch} exists
      And I api search for IDontExistLink%{epoch}
     Then IDontExistLink%{epoch} is the first api search result
      And there are no api search results with [INVALID] words in the data

  Scenario: Redirecting to a non-existing page doesn't add it to the search index with an [INVALID] word count
    Given I don't wait for a page named IRedirectToNonExistentPages%{epoch} to exist with contents #REDIRECT [[IDontExistRdir%{epoch}]]
      And I wait 5 seconds
      And I api search for IDontExistRdir%{epoch}
      And there are no api search results with [INVALID] words in the data
      And a page named IDontExistRdir%{epoch} exists
      And I api search for IDontExistRdir%{epoch}
     Then IDontExistRdir%{epoch} is the first api search result
      And there are no api search results with [INVALID] words in the data

  Scenario: Linking to a page that redirects to a non-existing page doesn't add it to the search index with an [INVALID] word count
    Given I don't wait for a page named IRedirectToNonExistentPagesLinked%{epoch} to exist with contents #REDIRECT [[IDontExistRdirLinked%{epoch}]]
      And I wait 5 seconds
      And a page named ILinkIRedirectToNonExistentPages%{epoch} exists with contents [[IRedirectToNonExistentPagesLinked%{epoch}]]
      And I api search for IDontExistRdir%{epoch}
      And there are no api search results with [INVALID] words in the data
     When a page named IDontExistRdirLinked%{epoch} exists
      And I api search for IDontExistRdirLinked%{epoch}
     Then IDontExistRdirLinked%{epoch} is the first api search result
      And there are no api search results with [INVALID] words in the data
