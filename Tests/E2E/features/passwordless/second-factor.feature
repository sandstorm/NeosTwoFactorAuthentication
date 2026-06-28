@passwordless
Feature: Touch-only security key as a 2nd factor while passwordless login is enabled

  Even with passwordless login enabled, a user may register a touch-only security key
  (no FIDO2 PIN, so it can prove presence but not user verification) as a plain second
  factor instead of a discoverable passkey. This previously failed at registration with
  "User authentication required." because every registration was forced to require user
  verification.

  Background:
    Given A user with username "admin", password "password" and role "Neos.Neos:Administrator" exists

  Scenario: A touch-only security key registers as a 2nd factor, not a passkey
    Given I have a touch-only virtual security key
    When I log in with username "admin" and password "password"
    And I navigate to the 2FA management page
    And I add a new WebAuthn 2nd-factor device with name "Admin Touch Key"
    Then There should be 1 enrolled 2FA device
    And There should be 1 enrolled "Passkey as 2nd factor" 2FA device
    And There should be a 2FA device with the name "Admin Touch Key"

  Scenario: A touch-only 2nd-factor security key satisfies the 2FA gate at login
    Given I have a touch-only virtual security key
    When I log in with username "admin" and password "password"
    And I navigate to the 2FA management page
    And I add a new WebAuthn 2nd-factor device with name "Admin Touch Key"
    And I log out
    And I log in with username "admin" and password "password"
    And I authenticate with my security key
    Then I should see the Neos content page
