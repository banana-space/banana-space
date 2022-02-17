@clean @dump_config @phantomjs
Feature: You can dump CirrusSearch's configuration
  Scenario: You can dump CirrusSearch's configuration
    When I dump the cirrus config
     Then the config dump contains CirrusSearchNamespaceWeights
     And the config dump text does not contain Password
     And the config dump text does not contain password
