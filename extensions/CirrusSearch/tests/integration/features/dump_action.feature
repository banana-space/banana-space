@clean @dump_action @phantomjs
Feature: Cirrus dump
  Scenario: Can dump pages
    When I dump the cirrus data for Main Page
    Then the page text contains Main Page
      And the page text contains template
      And the page text contains namespace
      And the page text contains version
      And the page text contains _id
