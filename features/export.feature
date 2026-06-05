# Export — a printable journal document aggregating character, entries, rolls and
# attributes. Restricted to the session owner.

Feature: Export
  As a player
  I export my finished journal
  So that I can keep a printable record of the story

  Scenario: Exporting returns the assembled document
    Given I am an authenticated player
    And a game positioned at phase "chapter_1"
    When I export the game
    Then the response status code should be 200
    And the JSON response key "title" should equal "La Biblioteca"
    And the JSON response should have key "overcome_score"
    And the JSON response should have key "entries"
    And the JSON response should have key "attributes"

  Scenario: Exporting another player's game is forbidden
    Given I am an authenticated player
    And a game owned by me
    And another authenticated player "intruder@biblioteca.test"
    When another player exports the game
    Then the response status code should be 403
