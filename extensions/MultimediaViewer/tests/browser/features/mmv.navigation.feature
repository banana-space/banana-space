@chrome @en.wikipedia.beta.wmflabs.org @firefox @integration @test2.wikipedia.org
Feature: Navigation

  Background:
    Given I am viewing an image using MMV

  Scenario: Clicking the next arrow takes me to the next image
    When I click the next arrow
    Then the image and metadata of the next image should appear

  Scenario: Clicking the previous arrow takes me to the previous image
    When I click the previous arrow
    Then the image and metadata of the previous image should appear

  Scenario: Closing MMV restores the scroll position
    When I close MMV
    Then I should be navigated back to the original wiki article
      And the wiki article should be scrolled to the same position as before opening MMV

  Scenario: Browsing back to close MMV restores the scroll position
    When I press the browser back button
    Then I should be navigated back to the original wiki article
      And the wiki article should be scrolled to the same position as before opening MMV
