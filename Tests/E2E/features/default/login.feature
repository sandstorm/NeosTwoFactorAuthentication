@default-context
Feature: Login flow with default settings

  Background:
    Given A user with username "admin", password "password" and role "Neos.Neos:Administrator" exists
    And A user with username "editor", password "password" and role "Neos.Neos:Editor" exists

  Scenario: Admin user can log in without 2FA when 2FA is not enforced
    When I log in with username "admin" and password "password"
    Then I should see the Neos content page

  Scenario: Editor user can log in without 2FA when 2FA is not enforced
    When I log in with username "editor" and password "password"
    Then I should see the Neos content page

  Scenario: User has to enter 2FA code when a TOTP 2FA device is added to his account
    When I log in with username "admin" and password "password"
    And I navigate to the 2FA management page
    And I add a new TOTP 2FA device with name "Admin Test Device"
    And I log out
    And I log in with username "admin" and password "password"
    Then I should see the 2FA verification page

  Scenario: User can log in with 2FA when a TOTP device is added to his account
    When I log in with username "admin" and password "password"
    And I navigate to the 2FA management page
    And I add a new TOTP 2FA device with name "Admin Test Device"
    And I log out
    And I log in with username "admin" and password "password"
    And I enter a valid TOTP for device "Admin Test Device"
    Then I should see the Neos content page

  Scenario: User has to pass 2FA when a WebAuthn device is registered on his account
    Given I have a virtual security key
    When I log in with username "admin" and password "password"
    And I navigate to the 2FA management page
    And I add a new WebAuthn 2FA device
    And I log out
    And I log in with username "admin" and password "password"
    And I authenticate with my security key
    Then I should see the Neos content page

  Scenario: User can log in with TOTP after cancelling the auto-started WebAuthn challenge
    Given I have a virtual security key
    When I log in with username "admin" and password "password"
    And I navigate to the 2FA management page
    And I add a new TOTP 2FA device with name "Admin Test Device"
    And I add a new WebAuthn 2FA device
    And I log out
    And I log in with username "admin" and password "password" but cancel the WebAuthn challenge
    And I enter a valid TOTP for device "Admin Test Device"
    Then I should see the Neos content page

  Scenario: User can restart the cancelled WebAuthn challenge and log in with the security key
    Given I have a virtual security key
    When I log in with username "admin" and password "password"
    And I navigate to the 2FA management page
    And I add a new TOTP 2FA device with name "Admin Test Device"
    And I add a new WebAuthn 2FA device
    And I log out
    And I log in with username "admin" and password "password" but cancel the WebAuthn challenge
    And I restart the WebAuthn challenge and authenticate with my security key
    Then I should see the Neos content page

  Scenario: User can cancel the 2FA verification and return to the login screen
    When I log in with username "admin" and password "password"
    And I navigate to the 2FA management page
    And I add a new TOTP 2FA device with name "Admin Test Device"
    And I log out
    And I log in with username "admin" and password "password"
    And I should see the 2FA verification page
    And I cancel the 2FA login
    Then I should see the login page

  Scenario: Cancelling the 2FA verification keeps the originally requested page for the next login
    When I log in with username "admin" and password "password"
    And I navigate to the 2FA management page
    And I add a new TOTP 2FA device with name "Admin Test Device"
    And I log out
    And I open "/neos/management/twoFactorAuthentication" while logged out
    And I log in with username "admin" and password "password"
    And I should see the 2FA verification page
    And I cancel the 2FA login
    And I log in with username "admin" and password "password"
    And I enter a valid TOTP for device "Admin Test Device"
    Then I should land on "/neos/management/twoFactorAuthentication"

  Scenario: User is redirected to the originally requested page after logging in with TOTP
    When I log in with username "admin" and password "password"
    And I navigate to the 2FA management page
    And I add a new TOTP 2FA device with name "Admin Test Device"
    And I log out
    And I open "/neos/management/twoFactorAuthentication" while logged out
    And I log in with username "admin" and password "password"
    And I enter a valid TOTP for device "Admin Test Device"
    Then I should land on "/neos/management/twoFactorAuthentication"

  Scenario: User is redirected to the originally requested page after logging in with WebAuthn
    Given I have a virtual security key
    When I log in with username "admin" and password "password"
    And I navigate to the 2FA management page
    And I add a new WebAuthn 2FA device
    And I log out
    And I open "/neos/management/twoFactorAuthentication" while logged out
    And I log in with username "admin" and password "password"
    And I authenticate with my security key
    Then I should land on "/neos/management/twoFactorAuthentication"
