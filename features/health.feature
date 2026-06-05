# Health & connectivity — public diagnostics used by infrastructure and the frontend.

Feature: Health and connectivity
  As an operator or the frontend
  I probe the public diagnostics
  So that I can confirm the API and its database are reachable

  Scenario: The health endpoint reports a healthy database
    When I check the health endpoint
    Then the response status code should be 200
    And the JSON response key "status" should equal "healthy"
    And the JSON path "checks.database.status" should equal "up"

  Scenario: The test endpoint confirms the API is running
    When I call the test endpoint
    Then the response status code should be 200
    And the JSON response key "status" should equal "ok"
    And the JSON response key "message" should equal "La Biblioteca API is running"
