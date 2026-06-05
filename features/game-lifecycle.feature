# Game lifecycle & ownership — who may create, view and list sessions.
# Ownerless sessions are public; owned sessions are private to their owner,
# enforced by the GameSessionVoter.

Feature: Game lifecycle and ownership
  As a player
  My sessions are private to me, while anonymous play stays open
  So that progress is protected without blocking guests

  Scenario: A guest creates an ownerless session
    When I create a game
    Then the response status code should be 201
    And the created game has no owner

  Scenario: An authenticated player owns the sessions they create
    Given I am an authenticated player
    When I create a game
    Then the response status code should be 201
    And the created game is owned by me

  Scenario: The owner can view their session
    Given I am an authenticated player
    And a game owned by me
    When the owner views the game
    Then access is granted

  Scenario: Another player cannot view an owned session
    Given I am an authenticated player
    And a game owned by me
    And another authenticated player "intruder@biblioteca.test"
    When another player views the game
    Then access is forbidden

  Scenario: A guest cannot view an owned session
    Given I am an authenticated player
    And a game owned by me
    When a guest views the game
    Then access is forbidden

  Scenario: Anyone can view an ownerless session
    Given an ownerless game
    When a guest views the game
    Then access is granted

  Scenario: Player sessions list only the player's own games
    Given I am an authenticated player
    And a game owned by me
    And a game owned by me
    And a game owned by another player
    When I list my sessions
    Then the response status code should be 200
    And I see 2 sessions

  Scenario: Listing player sessions requires authentication
    When a guest lists player sessions
    Then the response status code should be 401
