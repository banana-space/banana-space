@clean @api @redirect @update
Feature: Updating a page from or to a redirect
  Scenario: Turning a page into a redirect removes it from the search index
    Given a page named RedirectTarget exists
     When a page named ToBeRedirect%{epoch} exists
      And I api search for ToBeRedirect%{epoch}
     Then ToBeRedirect%{epoch} is the first api search result
     When a page named ToBeRedirect%{epoch} exists with contents #REDIRECT [[RedirectTarget]]
      And I api search for ToBeRedirect%{epoch}
     Then RedirectTarget is the first api search result
      And ToBeRedirect%{epoch} is not in the api search results

  Scenario: Turning a page from a redirect to a regular page puts it in the index
    Given a page named RedirectTarget exists
     When a page named StartsAsRedirect%{epoch} exists with contents #REDIRECT [[RedirectTarget]]
      And I api search for StartsAsRedirect%{epoch}
     Then RedirectTarget is the first api search result
     When a page named StartsAsRedirect%{epoch} exists
      And I wait for RedirectTarget to not include StartsAsRedirect%{epoch} in redirects
      # Waiting for the redirect to become not-a-redirect doesn't seem to reliably wait. This
      # is still not reliable ... but hopefully it helps.
      And I wait 2 seconds
      And I api search for StartsAsRedirect%{epoch}
     Then StartsAsRedirect%{epoch} is the first api search result
      And RedirectTarget is not in the api search results
