#
# This file is subject to the license terms in the COPYING file found in the
# CirrusSearch top-level directory and at
# https://phabricator.wikimedia.org/diffusion/ECIR/browse/master/COPYING. No part of
# CirrusSearch, including this file, may be copied, modified, propagated, or
# distributed except according to the terms contained in the COPYING file.
#
# Copyright 2012-2014 by the Mediawiki developers. See the CREDITS file in the
# CirrusSearch top-level directory and at
# https://phabricator.wikimedia.org/diffusion/ECIR/browse/master/CREDITS
#
@clean @firefox @test2.wikipedia.org @phantomjs @smoke
Feature: Smoke test

  @en.wikipedia.beta.wmflabs.org
  Scenario: Search suggestions
    Given I am at a random page
    When I type main p into the search box
    Then suggestions should appear
    And Main Page is the first suggestion

  Scenario: Fill in search term and click search
    Given I am at a random page
    When I type ma into the search box
    And I click the search button
    Then I am on a page titled Search results

  @en.wikipedia.beta.wmflabs.org
  Scenario: Search with accent yields result page with accent
    Given I am at a random page
    When I search for África
    Then I am on a page titled África
