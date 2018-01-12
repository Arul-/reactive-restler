@example11 @versioning
Feature: Testing Access Control

  Scenario: Access public api without a key
    When I request "/examples/_011_versioning/bmi?height=190"
    Then the response status code should be 200
    And the response is JSON
    And the type is "array"
    And the response has a "bmi" property
    And the "message" property equals "Normal weight"
    And the "metric.height" property equals "190 centimeters"

  Scenario: Access public api without a key
    When I request "v1/examples/_011_versioning/bmi?height=190"
    Then the response status code should be 200
    And the response is JSON
    And the type is "array"
    And the response has a "bmi" property
    And the "message" property equals "Normal weight"
    And the "metric.height" property equals "190 centimeters"

  Scenario: Access public api without a key
    When I request "v2/examples/_011_versioning/bmi?height=190"
    Then the response status code should be 400
    And the response is JSON
    And the type is "array"
    And the "error.message" property equals "Bad Request: invalid height unit"

  Scenario: Access public api without a key
    When I request "v2/examples/_011_versioning/bmi?height=190cm"
    Then the response status code should be 200
    And the response is JSON
    And the type is "array"
    And the response has a "bmi" property
    And the "message" property equals "Normal weight"
    And the "metric.height" property equals "190 centimeters"