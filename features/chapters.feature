# Chapter rules — the dice mechanic and how outcomes reshape an attribute.
# Each roll is 1d6 + (base + background + support) versus 2d10. The dice are
# fixed by the scenario so the outcome and its effect can be asserted exactly.

Feature: Chapters
  As a player working through the three chapters
  I roll my attributes against the challenge dice
  So that hits, weak hits and misses reshape my character

  Background:
    Given I am an authenticated player

  Scenario: A hit raises the attribute background by one
    Given a game positioned at phase "chapter_1"
    And the next action roll is a hit
    When I resolve the chapter using "mind"
    Then the roll outcome is "hit"
    And the "mind" attribute background is 1

  Scenario: A weak hit raises the attribute support by one
    Given a game positioned at phase "chapter_1"
    And the next action roll is a weak_hit
    When I resolve the chapter using "social"
    Then the roll outcome is "weak_hit"
    And the "social" attribute support is 1

  Scenario: A miss lowers the attribute background by one
    Given a game positioned at phase "chapter_1"
    And the next action roll is a miss
    When I resolve the chapter using "body"
    Then the roll outcome is "miss"
    And the "body" attribute background is -1

  Scenario: The roll modifier sums base, background and support
    Given a game positioned at phase "chapter_1"
    And the "mind" attribute has background 2 and support 1
    And the next action roll is action die 5 and challenge dice 9 and 9
    When I resolve the chapter using "mind"
    Then the roll modifier is 4
    And the roll outcome is "hit"

  Scenario: Resolving a chapter does not advance the phase
    Given a game positioned at phase "chapter_1"
    And the next action roll is a hit
    When I resolve the chapter using "mind"
    Then the game phase is "chapter_1"

  Scenario: A chapter cannot be advanced before it is resolved
    Given a game positioned at phase "chapter_1"
    When I advance the chapter
    Then the request is rejected

  Scenario: Advancing after a resolved roll moves to the next chapter
    Given a game positioned at phase "chapter_1"
    And the next action roll is a hit
    When I resolve the chapter using "mind"
    And I advance the chapter
    Then the game phase is "chapter_2"

  Scenario: An attribute cannot be used twice across the chapters
    Given a game positioned at phase "chapter_1"
    And the next action roll is a hit
    When I resolve the chapter using "mind"
    And I advance the chapter
    And the next action roll is a hit
    And I resolve the chapter using "mind"
    Then the request is rejected

  Scenario: A support title can be set after a weak hit
    Given a game positioned at phase "chapter_1"
    And the next action roll is a weak_hit
    When I resolve the chapter using "social"
    And I set the support title "Police informant" for "social"
    Then the "social" attribute support title is "Police informant"

  Scenario: A support title longer than fifty characters is rejected
    Given a game positioned at phase "chapter_1"
    And the next action roll is a weak_hit
    When I resolve the chapter using "social"
    And I set the support title "This support description is definitely longer than fifty characters" for "social"
    Then the request is rejected

  Scenario: Generating a chapter book returns the four book traits
    Given a game positioned at phase "chapter_1"
    When I generate the chapter book
    Then the response status code should be 200
    And the JSON response should have key "color"
    And the JSON response should have key "binding"
    And the JSON response should have key "smell"
    And the JSON response should have key "interior"
