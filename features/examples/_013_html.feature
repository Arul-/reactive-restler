@example13 @html
Feature: Testing Html

  Scenario: Getting Html response
    When I request "examples/_013_html/tasks.html"
    Then the response status code should be 200
    And the response is HTML

  Scenario: Getting Json response
    When I request "examples/_013_html/tasks.json"
    Then the response status code should be 200
    And the response is JSON
