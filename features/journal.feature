# Journal entries — narrative text tied to a phase, optionally linked to a book
# of the same game. Content is required and sanitized; cross-game links are denied.

Feature: Journal
  As a player writing my story
  I save journal entries during the game
  So that my narrative is preserved alongside the mechanics

  Background:
    Given I am an authenticated player

  Scenario: Saving an entry succeeds
    Given a game positioned at phase "chapter_1"
    When I save a journal entry "The rain fell hard on the city."
    Then the response status code should be 201

  Scenario: An empty entry is rejected
    Given a game positioned at phase "chapter_1"
    When I save an empty journal entry
    Then the response status code should be 422

  Scenario: Entry content is sanitized of HTML
    Given a game positioned at phase "chapter_1"
    When I save a journal entry "<b>Bold</b> and plain"
    Then the response status code should be 201
    And the JSON response key "content" should equal "Bold and plain"

  Scenario: An entry can be linked to a book of the same game
    Given a game positioned at phase "chapter_1"
    And the game has a book
    When I save a journal entry "Notes on the tome" linked to the book
    Then the response status code should be 201

  Scenario: Linking to a non-existent book is not found
    Given a game positioned at phase "chapter_1"
    When I save a journal entry "Notes" linked to book 999999
    Then the response status code should be 404

  Scenario: Linking to a book from another game is forbidden
    Given a game positioned at phase "chapter_1"
    And another game has a book
    When I save a journal entry "Notes" linked to the foreign book
    Then the response status code should be 403

  Scenario: Entries are listed in chronological order
    Given a game positioned at phase "chapter_1"
    When I save a journal entry "First"
    And I save a journal entry "Second"
    And I list the journal
    Then the response status code should be 200
    And I see 2 journal entries
    And the journal entries are in chronological order

  Scenario: Writing to another player's game is forbidden
    Given a game owned by another player
    When I save a journal entry "Sneaky note"
    Then the response status code should be 403
