@clean @filters @hastemplate @api
Feature: Searches with the hastemplate filter
  Scenario: hastemplate: finds pages with matching templates with namespace specified
    When I api search for hastemplate:"Template:Template Test"
    Then Two Words is the first api search result

  Scenario: hastemplate: finds pages with matching templates that aren't in the template namespace if you prefix them with the namespace
    When I api search for hastemplate:"Talk:TalkTemplate"
    Then HasTTemplate is the first api search result

  Scenario: hastemplate: finds pages which contain a template in the main namespace if they are prefixed with : (which is how you'd transclude them)
    When I api search for hastemplate::MainNamespaceTemplate
    Then HasMainNSTemplate is the first api search result

  Scenario: hastemplate: doesn't find pages which contain a template in the main namespace if you don't prefix the name with : (that is for the Template namespace)
    When I api search for hastemplate:MainNamespaceTemplate
    Then HasMainNSTemplate is not in the api search results

  Scenario: -hastemplate removes pages with matching templates
    When I api search for -hastemplate:"Template Test" catapult
    Then Two Words is not in the api search results

  Scenario: hastemplate: finds pages with matching templates (when you don't specify a namespace, Template is assumed)
    When I api search for hastemplate:"Template Test"
    Then Two Words is the first api search result

  Scenario: hastemplate: with quotes find templates that match with the exact case
    When I api search for hastemplate:"casechecktemplate"
    Then CaseCheckTemplate is not in the api search results
