@boost_template @clean @api
Feature: Searches with the boost-template operator
  Scenario: Searching for a page without template boosts doesn't use them
    When I api search for BoostTemplateTest
    Then NoTemplates BoostTemplateTest is the first api search result

  Scenario: Adding a single template boost is recognized
    When I api search for boost-templates:"Template:BoostTemplateLow|10000%" BoostTemplateTest
    Then LowTemplate is the first api search result

  Scenario: Adding two template boosts is also recognized
    When I api search for boost-templates:"Template:BoostTemplateLow|10000% Template:BoostTemplateHigh|100000%" BoostTemplateTest
    Then HighTemplate is the first api search result

  Scenario: Four templates is just fine (though I'm only actually using two of them)
    When I api search for boost-templates:"Template:BoostTemplateFake|10% Template:BoostTemplateLow|10000% Template:BoostTemplateFake2|1000000% Template:BoostTemplateHigh|100000%" BoostTemplateTest
    Then HighTemplate is the first api search result

  Scenario: Template boosts can also lower the score of a template
    When I api search for boost-templates:"Template:BoostTemplateLow|1%" BoostTemplateTest -intitle:"BoostTemplateTest"
    Then HighTemplate is the first api search result

  Scenario: Template boosts can also lower the score of another template (we didn't get lucky with the first one)
    When I api search for boost-templates:"Template:BoostTemplateHigh|1%" BoostTemplateTest -intitle:"BoostTemplateTest"
    Then LowTemplate is the first api search result
