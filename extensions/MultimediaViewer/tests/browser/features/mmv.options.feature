@chrome @en.wikipedia.beta.wmflabs.org @firefox @integration @test2.wikipedia.org
Feature: Options

  Background:
    Given I am viewing an image using MMV

  Scenario: Clicking the X icon on the enable confirmation closes the options menu
    Given I reenable MMV
    When I click the enable X icon
    Then the options menu should disappear

  Scenario: Clicking the X icon on the disable confirmation closes the options menu
    Given I disable MMV
    When I click the disable X icon
    Then the options menu should disappear

  Scenario: Clicking the image closes the options menu
    Given I click the options icon
    When I click the image
    Then the options menu should disappear

  Scenario: Clicking cancel closes the options menu
    Given I click the options icon
    When I click the disable cancel button
    Then the options menu should disappear

  Scenario: Clicking the options icon brings up the options menu
    When I click the options icon
    Then the options menu should appear with the prompt to disable

  Scenario: Clicking enable shows the confirmation
    Given I click the options icon with MMV disabled
    When I click the enable button
    Then the enable confirmation should appear

  Scenario: Clicking disable shows the confirmation
    Given I click the options icon
    When I click the disable button
    Then the disable confirmation should appear

  Scenario: Disabling media viewer makes the next thumbnail click go to the file page
    Given I disable and close MMV
    When I click on the first image in the article
    Then I am taken to the file page
