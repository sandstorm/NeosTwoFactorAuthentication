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
