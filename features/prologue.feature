# Prologue rules — character creation and the entry into chapter 1.
# These scenarios pin the behaviour of GameEngine::completePrologue and the
# initial state produced by GameEngine::createGame.

Feature: Prologue
  As a player starting a new journal
  I create my character in the prologue
  So that the story can begin in chapter 1

  Background:
    Given I am an authenticated player

  Scenario: A new game starts in the prologue with three fresh attributes
    Given a new game
    Then the game phase is "prologue"
    And the "body" attribute background is 0
    And the "body" attribute support is 0
    And the "mind" attribute background is 0
    And the "social" attribute support is 0

  Scenario: Completing the prologue records the character and advances to chapter 1
    Given a new game
    When I complete the prologue with name "Aria" genre "Investigación" epoch "Victoriana"
    Then the game phase is "chapter_1"

  Scenario: The prologue cannot be completed once it is over
    Given a game positioned at phase "chapter_1"
    When I complete the prologue with name "Aria" genre "Investigación" epoch "Victoriana"
    Then the request is rejected

  Scenario: The character name is required
    Given a new game
    When I complete the prologue without a name
    Then the request is rejected
