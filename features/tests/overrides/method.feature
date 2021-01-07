@restler
Feature: Ability to override http methods

  Scenario: Making put request from post should work
    When I request "POST tests/overrides/method?_method=PUT"
    Then the response status code should be 200
    And the response equals "Method::put"

  Scenario: Making put request from get should fail
    When I request "GET tests/overrides/method?_method=PUT"
    Then the response status code should be 200
    And the response equals "Method::get"

  Scenario: Making delete request from post should work
    Given that "X-HTTP-Method-Override" header is set to "DELETE"
    When I request "POST tests/overrides/method"
    Then the response status code should be 200
    And the response equals "Method::delete"

  Scenario: Making delete request from get should fail
    Given that "X-HTTP-Method-Override" header is set to "DELETE"
    When I request "GET tests/overrides/method"
    Then the response status code should be 200
    And the response equals "Method::get"
