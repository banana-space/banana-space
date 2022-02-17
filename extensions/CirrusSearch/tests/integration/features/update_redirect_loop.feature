@clean @phantomjs @update @redirect_loop
Feature: Search backend updates containing redirect loops
  Scenario: Pages that redirect to themself don't throw errors
    Then I don't wait for a page named IAmABad RedirectSelf%{epoch} to exist with contents #REDIRECT [[IAmABad RedirectSelf%{epoch}]]

  # The actual creation of the pages will fails if redirect loops fails
  Scenario: Pages that form a redirect chain don't throw errors
    When I don't wait for a page named IAmABad RedirectChain%{epoch} A to exist with contents #REDIRECT [[IAmABad RedirectChain%{epoch} B]]
      And I don't wait for a page named IAmABad RedirectChain%{epoch} B to exist with contents #REDIRECT [[IAmABad RedirectChain%{epoch} C]]
      And I don't wait for a page named IAmABad RedirectChain%{epoch} C to exist with contents #REDIRECT [[IAmABad RedirectChain%{epoch} D]]
    Then I don't wait for a page named IAmABad RedirectChain%{epoch} D to exist with contents #REDIRECT [[IAmABad RedirectChain%{epoch} A]]
      And I don't wait for a page named IAmABad RedirectChain%{epoch} B to exist with contents #REDIRECT [[IAmABad RedirectChain%{epoch} D]]
