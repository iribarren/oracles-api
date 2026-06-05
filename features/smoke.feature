Feature: API is reachable

  Scenario: Health check returns 200
    When I send a GET request to "/api/health"
    Then the response status code should be 200
    And the response should be valid JSON
    And the JSON response should have key "status"

  # Game endpoints are public (the GameSessionVoter enforces ownership), so a
  # missing session is a plain 404 rather than an auth challenge.
  Scenario: A missing game session is not found
    When I send a GET request to "/api/game/00000000-0000-0000-0000-000000000000"
    Then the response status code should be 404
