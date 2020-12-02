@chrome @en.wikipedia.beta.wmflabs.org @firefox @integration @safari @test2.wikipedia.org
Feature: Download menu

  Background:
    Given I am viewing an image using MMV

  Scenario: Download menu can be opened
    When I click the download icon
    Then the download menu should appear

  Scenario: Clicking the image closes the download menu
    When I click the download icon
      And the download menu appears
      And I click the image
    Then the download menu should disappear

  Scenario: Image size defaults to original
    When I click the download icon
    Then the original beginning download image size label should be "4000 × 3000 px jpg"
      And the download links should be the original image

  Scenario: Attribution area is collapsed by default
    When I click the download icon
    Then the attribution area should be collapsed

  Scenario: Attribution area can be opened
    When I click the download icon
      And I click on the attribution area
    Then the attribution area should be open

  Scenario: Attribution area can be closed
    When I click the download icon
      And I click on the attribution area
      And I click on the attribution area close icon
    Then the attribution area should be collapsed

  Scenario: The small download option has the correct information
    When I open the download dropdown
      And the download size options appear
      And I click the small download size
      And the download size options disappears
    Then the download image size label should be "640 × 480 px jpg"
      And the download links should be the 640 thumbnail

  Scenario: The medium download option has the correct information
    When I open the download dropdown
    And the download size options appear
      And I click the medium download size
      And the download size options disappears
    Then the download image size label should be "960 × 720 px jpg"
      And the download links should be the 960 thumbnail

  Scenario: The large download option has the correct information
    When I open the download dropdown
    And the download size options appear
      And I click the large download size
      And the download size options disappears
    Then the download image size label should be "1440 × 1080 px jpg"
      And the download links should be the 1440 thumbnail

  Scenario: The extra large download option has the correct information
    When I open the download dropdown
    And the download size options appear
      And I click the extra large download size
      And the download size options disappears
    Then the download image size label should be "2880 × 2160 px jpg"
      And the download links should be the 2880 thumbnail
