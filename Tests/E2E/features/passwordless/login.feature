@passwordless
Feature: Passwordless passkey login

  When passwordless login is enabled, a user who has registered a (discoverable)
  passkey can sign in with a single tap on the login screen — no username, no
  password — and lands straight in the Neos backend.

  Background:
    Given A user with username "admin", password "password" and role "Neos.Neos:Administrator" exists

  Scenario: User can log in passwordlessly with a registered passkey
    Given I have a virtual security key
    When I log in with username "admin" and password "password"
    And I navigate to the 2FA management page
    And I add a new WebAuthn 2FA device
    And I log out
    And I sign in with a passkey
    Then I should see the Neos content page
