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

  Scenario: A credential registered while passwordless login is enabled is labelled "Passkey"
    Given I have a virtual security key
    When I log in with username "admin" and password "password"
    And I navigate to the 2FA management page
    And I add a new WebAuthn 2FA device with name "Admin Passkey"
    Then There should be 1 enrolled "Passkey" 2FA device
    And There should be a 2FA device with the name "Admin Passkey"

  Scenario: The management module nudges users without a passkey to register one
    Given I have a virtual security key
    When I log in with username "admin" and password "password"
    And I navigate to the 2FA management page
    Then I should see the register-a-passkey banner
    When I register a passkey from the banner
    Then There should be 1 enrolled "Passkey" 2FA device
    And I should not see the register-a-passkey banner

  Scenario: User lands on the originally requested page after a passwordless login
    Given I have a virtual security key
    When I log in with username "admin" and password "password"
    And I navigate to the 2FA management page
    And I add a new WebAuthn 2FA device
    And I log out
    And I open "/neos/management/twoFactorAuthentication" while logged out
    And I sign in with a passkey
    Then I should land on "/neos/management/twoFactorAuthentication"

  Scenario: Cancelling the passkey sign-in keeps the user on the login screen
    Given I have a virtual security key
    When I log in with username "admin" and password "password"
    And I navigate to the 2FA management page
    And I add a new WebAuthn 2FA device
    And I log out
    And I start a passkey sign-in but cancel it
    Then I should see the login page
