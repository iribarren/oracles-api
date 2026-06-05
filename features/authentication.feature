# Authentication rules — registration, login (with throttling), profile and refresh.
# These exercise the real auth endpoints end to end.

Feature: Authentication
  As a visitor
  I register and log in
  So that I can own and resume my game sessions

  Scenario: Registering with valid data returns a token
    When I register with email "new@biblioteca.test" password "password123" and confirmation "password123"
    Then the response status code should be 201
    And I receive an authentication token

  Scenario: Registering with an invalid email is rejected
    When I register with email "not-an-email" password "password123" and confirmation "password123"
    Then the response status code should be 422

  Scenario: Registering with a short password is rejected
    When I register with email "short@biblioteca.test" password "short" and confirmation "short"
    Then the response status code should be 422

  Scenario: Registering with a mismatched confirmation is rejected
    When I register with email "mismatch@biblioteca.test" password "password123" and confirmation "different456"
    Then the response status code should be 422

  Scenario: Registering an already-registered email reveals nothing (no enumeration)
    Given a registered player with email "dup@biblioteca.test" and password "password123"
    When I register with email "dup@biblioteca.test" password "password123" and confirmation "password123"
    Then the response status code should be 201
    And I do not receive an authentication token

  Scenario: Logging in with valid credentials returns a token and a refresh token
    Given a registered player with email "login@biblioteca.test" and password "password123"
    When I log in with email "login@biblioteca.test" and password "password123"
    Then the response status code should be 200
    And I receive an authentication token
    And I receive a refresh token

  Scenario: Logging in with a wrong password is unauthorized
    Given a registered player with email "wrong@biblioteca.test" and password "password123"
    When I log in with email "wrong@biblioteca.test" and password "incorrect-password"
    Then the response status code should be 401

  # The throttle trips after 5 failures; the JWT failure handler normalises all auth
  # failures to 401, so it surfaces as 401 with a distinct throttling message.
  Scenario: Too many failed logins are throttled
    Given a registered player with email "throttle@biblioteca.test" and password "password123"
    When I log in 5 times with the wrong password for "throttle@biblioteca.test"
    And I log in with email "throttle@biblioteca.test" and password "password123"
    Then the response status code should be 401
    And the JSON response key "message" should contain "Too many failed login attempts"

  Scenario: The profile endpoint returns the authenticated user
    Given I am an authenticated player
    When I request my profile
    Then the response status code should be 200
    And my profile email is "player@biblioteca.test"

  Scenario: The profile endpoint rejects unauthenticated access
    When I request my profile without a token
    Then the response status code should be 401

  Scenario: A valid refresh token yields a fresh access token
    Given I am an authenticated player
    When I refresh my session
    Then the response status code should be 200
    And I receive an authentication token

  Scenario: An invalid refresh token is unauthorized
    When I refresh my session with an invalid token
    Then the response status code should be 401
