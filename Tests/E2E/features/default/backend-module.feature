@default-context
Feature: Backend module for two-factor authentication management with default settings

  Background:
    Given A user with username "admin", password "password" and role "Neos.Neos:Administrator" exists
    And A user with username "editor", password "password" and role "Neos.Neos:Editor" exists

  Scenario: Admin user can add a 2FA in backend module
    When I log in with username "admin" and password "password"
    And I navigate to the 2FA management page
    And I add a new TOTP 2FA device with name "Admin Test Device"
    Then There should be 1 enrolled 2FA device
    And There should be a 2FA device with the name "Admin Test Device"

  Scenario: Admin user can remove his own, last 2FA when 2FA is not enforced
    When I log in with username "admin" and password "password"
    And I navigate to the 2FA management page
    And I add a new TOTP 2FA device with name "Admin Test Device"
    And I remove the 2FA device with the name "Admin Test Device"
    Then There should be 0 enrolled 2FA devices
    And There should be no 2FA device with the name "Admin Test Device"

  Scenario: Editor user can add a 2FA in backend module
    When I log in with username "editor" and password "password"
    And I navigate to the 2FA management page
    And I add a new TOTP 2FA device with name "Editor Test Device"
    Then There should be 1 enrolled 2FA device
    And There should be a 2FA device with the name "Editor Test Device"

  Scenario: Editor user can remove his own, last 2FA when 2FA is not enforced
    When I log in with username "editor" and password "password"
    And I navigate to the 2FA management page
    And I add a new TOTP 2FA device with name "Editor Test Device"
    And I remove the 2FA device with the name "Editor Test Device"
    Then There should be 0 enrolled 2FA devices
    And There should be no 2FA device with the name "Editor Test Device"

  Scenario: Admin user can remove another user's 2FA when 2FA is not enforced
    When I log in with username "editor" and password "password"
    And I navigate to the 2FA management page
    And I add a new TOTP 2FA device with name "Editor Test Device"
    And I log out
    And I log in with username "admin" and password "password"
    And I navigate to the 2FA management page
    And I remove the 2FA device with the name "Editor Test Device"
    Then There should be no 2FA device with the name "Editor Test Device"
