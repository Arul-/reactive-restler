@param @type
Feature: Validation

  Scenario: Consider "true" string as true
    When I request "/tests/param/type/boolean?value=true"
    Then the response status code should be 200
    And the response is JSON
    And the type is "bool"
    And the response equals true

  Scenario: Consider "false" string as false
    When I request "/tests/param/type/boolean?value=true"
    Then the response status code should be 200
    And the response is JSON
    And the type is "bool"
    And the response equals true

  Scenario: Consider number 1 as true
    When I request "/tests/param/type/boolean?value=1"
    Then the response status code should be 200
    And the response is JSON
    And the type is "bool"
    And the response equals true

  Scenario: Consider number 0 as false
    When I request "/tests/param/type/boolean?value=0"
    Then the response status code should be 200
    And the response is JSON
    And the type is "bool"
    And the response equals false

  Scenario: Don't accept any number for a boolean
    When I request "/tests/param/type/boolean?value=30873"
    Then the response status code should be 400

  Scenario: Don't accept fractional number for a boolean
    When I request "/tests/param/type/boolean?value=0.234"
    Then the response status code should be 400

  Scenario: Don't accept any string for a boolean
    When I request "/tests/param/type/boolean?value=not_boolean"
    Then the response status code should be 400

  Scenario: Fix "true" string as true
    When I request "/tests/param/type/boolfix?value=true"
    Then the response status code should be 200
    And the response is JSON
    And the type is "bool"
    And the response equals true

  Scenario: Fix "false" string as false
    When I request "/tests/param/type/boolfix?value=true"
    Then the response status code should be 200
    And the response is JSON
    And the type is "bool"
    And the response equals true

  Scenario: Fix number 1 as true
    When I request "/tests/param/type/boolfix?value=1"
    Then the response status code should be 200
    And the response is JSON
    And the type is "bool"
    And the response equals true

  Scenario: Fix number 0 as false
    When I request "/tests/param/type/boolfix?value=0"
    Then the response status code should be 200
    And the response is JSON
    And the type is "bool"
    And the response equals false

  Scenario: Fix positive numbers as a boolean true
    When I request "/tests/param/type/boolfix?value=30873"
    Then the response status code should be 200
    And the response is JSON
    And the type is "bool"
    And the response equals true

  Scenario: Fix negative numbers as a boolean true
    When I request "/tests/param/type/boolfix?value=-23"
    Then the response status code should be 200
    And the response is JSON
    And the type is "bool"
    And the response equals true

  Scenario: Fix fractional numbers as a boolean true
    When I request "/tests/param/type/boolfix?value=0.3"
    Then the response status code should be 200
    And the response is JSON
    And the type is "bool"
    And the response equals true

  Scenario: Fix non empty string as a boolean true
    When I request "/tests/param/type/boolfix?value=not_empty"
    Then the response status code should be 200
    And the response is JSON
    And the type is "bool"
    And the response equals true

  Scenario: Fix empty string as a boolean false
    When I request "/tests/param/type/boolfix?value="
    Then the response status code should be 200
    And the response is JSON
    And the type is "bool"
    And the response equals false
    
  Scenario Outline: Valid Password
    Given that I send {"password":<password>}
    And the request is sent as JSON
    When I request "/tests/param/validation/pattern"
    Then the response status code should be 200
    And the response is JSON
    And the type is "string"
    And the response equals <password>

  Examples:
    | password |
    | "1a"     |
    | "b2"     |
    | "some1"  |

  Scenario Outline: Invalid Password
    Given that I send {"password":<password>}
    And the request is sent as JSON
    When I request "/tests/param/validation/pattern"
    Then the response status code should be 400
    And the response is JSON
    And the type is "string"
    And the response contains "Bad Request: Strong password with at least one alpha and one numeric character is required"

  Examples:
    | password   |
    | "arul"     |
    | "12345678" |
    | "ONEtwo"   |