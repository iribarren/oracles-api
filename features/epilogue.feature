# Epilogue rules — the epilogue opens with a book step (its own phase), then three
# actions accumulate an overcome score, the optional support bonus can be spent
# exactly once, and the final roll compares that score against 2d10 to end the game.
# Like chapters, an action roll does NOT advance the phase: advancing is a separate
# step, so each journal entry stays tagged with the phase it was written in.

Feature: Epilogue
  As a player resolving the ending
  I discover the epilogue book, take three actions and a final roll
  So that my accumulated score decides how the story closes

  Background:
    Given I am an authenticated player

  Scenario: Generating the epilogue book returns the four book traits
    Given a game positioned at phase "epilogue_book"
    When I generate the epilogue book
    Then the response status code should be 200
    And the JSON response should have key "color"
    And the JSON response should have key "binding"
    And the JSON response should have key "smell"
    And the JSON response should have key "interior"

  Scenario: Advancing from the epilogue book moves to the first action
    Given a game positioned at phase "epilogue_book"
    And I generate the epilogue book
    When I advance the epilogue
    Then the game phase is "epilogue_action_1"

  Scenario: The epilogue cannot be advanced from the book before it is generated
    Given a game positioned at phase "epilogue_book"
    When I advance the epilogue
    Then the request is rejected

  Scenario: A hit in an epilogue action adds three to the overcome score
    Given a game positioned at phase "epilogue_action_1"
    And the next action roll is a hit
    When I resolve the epilogue action using "body"
    Then the roll outcome is "hit"
    And the overcome score is 3

  Scenario: A weak hit in an epilogue action adds two to the overcome score
    Given a game positioned at phase "epilogue_action_1"
    And the next action roll is a weak_hit
    When I resolve the epilogue action using "body"
    Then the overcome score is 2

  Scenario: A miss in an epilogue action still adds one to the overcome score
    Given a game positioned at phase "epilogue_action_1"
    And the next action roll is a miss
    When I resolve the epilogue action using "body"
    Then the overcome score is 1

  Scenario: Resolving an epilogue action does not advance the phase
    Given a game positioned at phase "epilogue_action_1"
    And the next action roll is a hit
    When I resolve the epilogue action using "body"
    Then the game phase is "epilogue_action_1"

  Scenario: Advancing after a resolved action moves to the next action
    Given a game positioned at phase "epilogue_action_1"
    And the next action roll is a hit
    When I resolve the epilogue action using "body"
    And I advance the epilogue
    Then the game phase is "epilogue_action_2"

  Scenario: An epilogue action cannot be advanced before it is resolved
    Given a game positioned at phase "epilogue_action_1"
    When I advance the epilogue
    Then the request is rejected

  Scenario: Spending support adds its value to the modifier and marks it used
    Given a game positioned at phase "epilogue_action_1"
    And the "social" attribute has background 0 and support 2
    And the next action roll is action die 6 and challenge dice 1 and 1
    When I resolve the epilogue action using "body" with support "social"
    Then the roll modifier is 3
    And support has been used

  Scenario: Support can only be spent once across the epilogue
    Given a game positioned at phase "epilogue_action_1"
    And the "social" attribute has background 0 and support 2
    And the next action roll is a hit
    And I resolve the epilogue action using "body" with support "social"
    And I advance the epilogue
    And the next action roll is a hit
    When I resolve the epilogue action using "mind" with support "social"
    Then the request is rejected

  Scenario: An attribute cannot be used twice across the epilogue actions
    Given a game positioned at phase "epilogue_action_1"
    And the next action roll is a hit
    And I resolve the epilogue action using "body"
    And I advance the epilogue
    And the next action roll is a hit
    When I resolve the epilogue action using "body"
    Then the request is rejected

  Scenario: The final roll wins when the overcome score beats both dice
    Given a game positioned at phase "epilogue_final"
    And the game overcome score is 8
    And the next final roll challenge dice are 5 and 7
    When I roll the final outcome
    Then the roll outcome is "hit"
    And the game phase is "completed"

  Scenario: The final roll is a weak hit when the score beats only one die
    Given a game positioned at phase "epilogue_final"
    And the game overcome score is 6
    And the next final roll challenge dice are 5 and 7
    When I roll the final outcome
    Then the roll outcome is "weak_hit"
    And the game phase is "completed"

  Scenario: The final roll loses when the score beats neither die
    Given a game positioned at phase "epilogue_final"
    And the game overcome score is 3
    And the next final roll challenge dice are 5 and 7
    When I roll the final outcome
    Then the roll outcome is "miss"
    And the game phase is "completed"

  Scenario: A full playthrough runs from the prologue to a completed game
    Given a new game
    When I complete the prologue with name "Aria" genre "Investigación" epoch "Victoriana"
    And the next action roll is a hit
    And I resolve the chapter using "body"
    And I advance the chapter
    And the next action roll is a hit
    And I resolve the chapter using "mind"
    And I advance the chapter
    And the next action roll is a hit
    And I resolve the chapter using "social"
    And I advance the chapter
    And I generate the epilogue book
    And I advance the epilogue
    And the next action roll is a hit
    And I resolve the epilogue action using "body"
    And I advance the epilogue
    And the next action roll is a hit
    And I resolve the epilogue action using "mind"
    And I advance the epilogue
    And the next action roll is a hit
    And I resolve the epilogue action using "social"
    And I advance the epilogue
    And the next final roll challenge dice are 5 and 6
    And I roll the final outcome
    Then the game phase is "completed"
