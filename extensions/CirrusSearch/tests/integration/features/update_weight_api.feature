@clean @api @update @weight
Feature: Page updates trigger appropriate weight updates in newly linked and unlinked articles
  # Note that these tests can be a bit flakey if you don't use Redis and checkDelay because they count using
  # Elasticsearch which delays all updates for around a second.  So if the jobs run too fast they won't work.
  # Redis and checkDelay fix this by forcing a delay.
  Scenario: Pages weights are updated when new pages link to them
    Given I don't wait for a page named WeightedLink%{epoch} 1 to exist
      And I don't wait for a page named WeightedLink%{epoch} 2/1 to exist with contents [[WeightedLink%{epoch} 2]]
      And I don't wait for a page named WeightedLink%{epoch} 2 to exist
      And I wait for WeightedLink%{epoch} 2 to have incoming_links of 1
      And I api search for WeightedLink%{epoch}
     Then WeightedLink%{epoch} 2 is the first api search result
     When I don't wait for a page named WeightedLink%{epoch} 1/1 to exist with contents [[WeightedLink%{epoch} 1]]
      And I don't wait for a page named WeightedLink%{epoch} 1/2 to exist with contents [[WeightedLink%{epoch} 1]]
      And I wait for WeightedLink%{epoch} 1 to have incoming_links of 2
      And I api search for WeightedLink%{epoch}
     Then WeightedLink%{epoch} 1 is the first api search result

  @expect_failure
  Scenario: Pages weights are updated when links are removed from them
    Given I don't wait for a page named WeightedLinkRemoveUpdate%{epoch} 1/1 to exist with contents [[WeightedLinkRemoveUpdate%{epoch} 1]]
      And I don't wait for a page named WeightedLinkRemoveUpdate%{epoch} 1/2 to exist with contents [[WeightedLinkRemoveUpdate%{epoch} 1]]
      And I don't wait for a page named WeightedLinkRemoveUpdate%{epoch} 1 to exist
      And I don't wait for a page named WeightedLinkRemoveUpdate%{epoch} 2/1 to exist with contents [[WeightedLinkRemoveUpdate%{epoch} 2]]
      And I don't wait for a page named WeightedLinkRemoveUpdate%{epoch} 2 to exist
      And I wait for WeightedLinkRemoveUpdate%{epoch} 1 to have incoming_links of 2
      And I wait for WeightedLinkRemoveUpdate%{epoch} 2 to have incoming_links of 1
      And I api search for WeightedLinkRemoveUpdate%{epoch}
     Then WeightedLinkRemoveUpdate%{epoch} 1 is the first api search result
     When I don't wait for a page named WeightedLinkRemoveUpdate%{epoch} 1/1 to exist with contents [[Junk]]
      And I don't wait for a page named WeightedLinkRemoveUpdate%{epoch} 1/2 to exist with contents [[Junk]]
      And I wait for WeightedLinkRemoveUpdate%{epoch} 1 to have incoming_links of 0
      And I api search for WeightedLinkRemoveUpdate%{epoch}
     Then WeightedLinkRemoveUpdate%{epoch} 2 is the first api search result

  Scenario: Pages weights are updated when new pages link to their redirects
    Given I don't wait for a page named WeightedLinkRdir%{epoch} 1/Redirect to exist with contents #REDIRECT [[WeightedLinkRdir%{epoch} 1]]
      And I don't wait for a page named WeightedLinkRdir%{epoch} 1 to exist
      And I don't wait for a page named WeightedLinkRdir%{epoch} 2/Redirect to exist with contents #REDIRECT [[WeightedLinkRdir%{epoch} 2]]
      And I don't wait for a page named WeightedLinkRdir%{epoch} 2/1 to exist with contents [[WeightedLinkRdir%{epoch} 2/Redirect]]
      And I don't wait for a page named WeightedLinkRdir%{epoch} 2 to exist
      And I wait for WeightedLinkRdir%{epoch} 1 to have incoming_links of 1
      And I wait for WeightedLinkRdir%{epoch} 2 to have incoming_links of 2
      And I api search for WeightedLinkRdir%{epoch}
     Then WeightedLinkRdir%{epoch} 2 is the first api search result
     When I don't wait for a page named WeightedLinkRdir%{epoch} 1/1 to exist with contents [[WeightedLinkRdir%{epoch} 1/Redirect]]
      And I don't wait for a page named WeightedLinkRdir%{epoch} 1/2 to exist with contents [[WeightedLinkRdir%{epoch} 1/Redirect]]
      And I wait for WeightedLinkRdir%{epoch} 1 to have incoming_links of 3
      And I api search for WeightedLinkRdir%{epoch}
     Then WeightedLinkRdir%{epoch} 1 is the first api search result

  Scenario: Pages weights are updated when links are removed from their redirects
    Given I don't wait for a page named WLRURdir%{epoch} 1/1 to exist with contents [[WLRURdir%{epoch} 1/Redirect]]
      And I don't wait for a page named WLRURdir%{epoch} 1/2 to exist with contents [[WLRURdir%{epoch} 1/Redirect]]
      And I don't wait for a page named WLRURdir%{epoch} 1/Redirect to exist with contents #REDIRECT [[WLRURdir%{epoch} 1]]
      And I don't wait for a page named WLRURdir%{epoch} 1 to exist
      And I don't wait for a page named WLRURdir%{epoch} 2/Redirect to exist with contents #REDIRECT [[WLRURdir%{epoch} 2]]
      And I don't wait for a page named WLRURdir%{epoch} 2/1 to exist with contents [[WLRURdir%{epoch} 2/Redirect]]
      And I don't wait for a page named WLRURdir%{epoch} 2 to exist
      And I wait for WLRURdir%{epoch} 1 to have incoming_links of 3
      And I wait for WLRURdir%{epoch} 2 to have incoming_links of 2
      And I api search for WLRURdir%{epoch}
     Then WLRURdir%{epoch} 1 is the first api search result
     When I don't wait for a page named WLRURdir%{epoch} 1/1 to exist with contents [[Junk]]
      And I don't wait for a page named WLRURdir%{epoch} 1/2 to exist with contents [[Junk]]
      And I wait for WLRURdir%{epoch} 1 to have incoming_links of 1
      And I api search for WLRURdir%{epoch}
     Then WLRURdir%{epoch} 2 is the first api search result

  Scenario: Redirects to redirects don't count in the score
    Given I don't wait for a page named WLDoubleRdir%{epoch} 1/Redirect to exist with contents #REDIRECT [[WLDoubleRdir%{epoch} 1]]
      And I don't wait for a page named WLDoubleRdir%{epoch} 1/Redirect Redirect to exist with contents #REDIRECT [[WLDoubleRdir%{epoch} 1/Redirect]]
      And I don't wait for a page named WLDoubleRdir%{epoch} 1/1 to exist with contents [[WLDoubleRdir%{epoch} 1/Redirect Redirect]]
      And I don't wait for a page named WLDoubleRdir%{epoch} 1/2 to exist with contents [[WLDoubleRdir%{epoch} 1/Redirect Redirect]]
      And I don't wait for a page named WLDoubleRdir%{epoch} 1 to exist
      And I don't wait for a page named WLDoubleRdir%{epoch} 2/Redirect to exist with contents #REDIRECT [[WLDoubleRdir%{epoch} 2]]
      And I don't wait for a page named WLDoubleRdir%{epoch} 2/1 to exist with contents [[WLDoubleRdir%{epoch} 2/Redirect]]
      And I don't wait for a page named WLDoubleRdir%{epoch} 2/2 to exist with contents [[WLDoubleRdir%{epoch} 2/Redirect]]
      And I don't wait for a page named WLDoubleRdir%{epoch} 2 to exist
      And I wait for WLDoubleRdir%{epoch} 1 to have incoming_links of 1
      And I wait for WLDoubleRdir%{epoch} 2 to have incoming_links of 3
      And I api search for WLDoubleRdir%{epoch}
     Then WLDoubleRdir%{epoch} 2 is the first api search result
