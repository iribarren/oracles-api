# Oracle tables — public lookup data with a database-first read and a hardcoded
# fallback. In the test database the oracle tables are empty, so the fallback
# constants are served unless a scenario seeds a category.

Feature: Oracles
  As a visitor building a setting
  I read the oracle tables without authenticating
  So that I can pick a genre, epoch and book traits

  Scenario: The tables fall back to the built-in constants when the database is empty
    When I request the oracle tables
    Then the response status code should be 200
    And the oracle table "color" has 6 entries
    And the oracle table "color" contains "Negro"
    And the oracle table "genre" has 6 entries
    And the oracle table "genre" contains "Fantasía"

  Scenario: The random setting returns a genre and an epoch
    When I request a random setting
    Then the response status code should be 200
    And the random setting includes a genre and an epoch

  Scenario: A seeded category is served from the database, replacing the fallback
    Given the oracle category "color" is seeded with value "ColorDesdeBD"
    When I request the oracle tables
    Then the response status code should be 200
    And the oracle table "color" has 1 entries
    And the oracle table "color" contains "ColorDesdeBD"
