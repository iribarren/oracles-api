Feature: API is reachable

  Scenario: Health check returns 200
    When I send a GET request to "/api/health"
    Then the response status code should be 200
    And the response should be valid JSON
    And the JSON response should have key "status"

  Scenario: Authenticated endpoint requires JWT
    When I send a GET request to "/api/game/00000000-0000-0000-0000-000000000000"
    Then the response status code should be 401
