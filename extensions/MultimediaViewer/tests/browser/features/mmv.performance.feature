@en.wikipedia.beta.wmflabs.org @firefox @www.mediawiki.org @test2.wikipedia.org
Feature: Multimedia Viewer performance

  Background:
    Given I am using a custom user agent
      And I am at a wiki article with at least two embedded pictures

  Scenario: Commons with warm cache
    Given I visit an unrelated Commons page to warm up the browser cache
      And I visit the Commons page
    Then the File: page image is loaded

  Scenario: MMV with warm cache and small browser window
    Given I have a small browser window
    When I click on an unrelated image in the article to warm up the browser cache
      And I close MMV
      And I click on the first image in the article
    Then the MMV image is loaded in 125 percent of the time with a warm cache and an average browser window

  Scenario: MMV with cold cache and average browser window
    Given I have an average browser window
    When I click on the first image in the article
    Then the MMV image is loaded in 210 percent of the time with a cold cache and an average browser window

  Scenario: MMV with warm cache and average browser window
    Given I have an average browser window
    When I click on an unrelated image in the article to warm up the browser cache
      And I close MMV
      And I click on the first image in the article
    Then the MMV image is loaded in 125 percent of the time with a warm cache and an average browser window

  Scenario: MMV with cold cache and large browser window
    Given I have a large browser window
    When I click on the first image in the article
    Then the MMV image is loaded in 240 percent of the time with a cold cache and a large browser window

  Scenario: MMV with warm cache and large browser window
    Given I have a large browser window
    When I click on an unrelated image in the article to warm up the browser cache
      And I close MMV
      And I click on the first image in the article
    Then the MMV image is loaded in 125 percent of the time with a warm cache and a large browser window
