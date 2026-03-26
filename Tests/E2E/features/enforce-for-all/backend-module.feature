@enforce-for-all
Feature: Backend module for two-factor authentication management with default settings

  # Requires FLOW_CONTEXT=Production/E2E-SUT/EnforceForAll
  # Config: enforceTwoFactorAuthentication: true

  Background:
    Given A user with username "admin", password "password" and role "Neos.Neos:Administrator" with enrolled 2FA device with name "Admin Initial Device" exists
    And A user with username "editor", password "password" and role "Neos.Neos:Editor" with enrolled 2FA device with name "Editor Initial Device" exists

  Scenario: Admin user can add a 2FA in backend module
    When I log in with username "admin" and password "password"
    And I enter a valid TOTP for device "Admin Initial Device"
    And I navigate to the 2FA management page
    And I add a new TOTP 2FA device with name "Admin Test Device"
    Then There should be a 2FA device with the name "Admin Test Device"
    And There should be a 2FA device with the name "Admin Initial Device"

  Scenario: Admin user can remove a 2FA device when there are more than 1 enrolled devices
    When I log in with username "admin" and password "password"
    And I enter a valid TOTP for device "Admin Initial Device"
    And I navigate to the 2FA management page
    And I add a new TOTP 2FA device with name "Admin Test Device"
    And I remove the 2FA device with the name "Admin Initial Device"
    And There should be a 2FA device with the name "Admin Test Device"
    And There should be no 2FA device with the name "Admin Initial Device"

  Scenario: Editor user can add a 2FA in backend module
    When I log in with username "editor" and password "password"
    And I enter a valid TOTP for device "Editor Initial Device"
    And I navigate to the 2FA management page
    And I add a new TOTP 2FA device with name "Editor Test Device"
    Then There should be 2 enrolled 2FA device
    And There should be a 2FA device with the name "Editor Test Device"
    And There should be a 2FA device with the name "Editor Initial Device"

  Scenario: Editor user can remove a 2FA device when there are more than 1 enrolled devices
    When I log in with username "editor" and password "password"
    And I enter a valid TOTP for device "Editor Initial Device"
    And I navigate to the 2FA management page
    And I add a new TOTP 2FA device with name "Editor Test Device"
    And I remove the 2FA device with the name "Editor Initial Device"
    Then There should be 1 enrolled 2FA devices
    And There should be a 2FA device with the name "Editor Test Device"
    And There should be no 2FA device with the name "Editor Initial Device"

  Scenario: Admin user can remove another user's last 2FA, even when 2FA is enforced
    When I log in with username "admin" and password "password"
    And I enter a valid TOTP for device "Admin Initial Device"
    And I navigate to the 2FA management page
    And I remove the 2FA device with the name "Editor Initial Device"
    Then There should be no 2FA device with the name "Editor Initial Device"
