@enforce-for-role
Feature: Backend module with 2FA enforced for administrators only

  # Requires FLOW_CONTEXT=Production/E2E-SUT/EnforceForRole
  # Config: enforce2FAForRoles: ['Neos.Neos:Administrator', 'Neos.Neos:SecondFactorUser']

  Background:
    Given A user with username "admin", password "password" and role "Neos.Neos:Administrator" with enrolled 2FA device with name "Admin Initial Device" exists
    And A user with username "editor", password "password" and role "Neos.Neos:Editor" exists
    And A user with username "secondFactorUser", password "password" and role "Neos.Neos:SecondFactorUser" with enrolled 2FA device with name "2FA-User Initial Device" exists

  Scenario: Administrator must enter TOTP before accessing the backend module
    When I log in with username "admin" and password "password"
    And I enter a valid TOTP for device "Admin Initial Device"
    And I navigate to the 2FA management page
    Then There should be a 2FA device with the name "Admin Initial Device"

  Scenario: Editor can access the backend module without 2FA
    When I log in with username "editor" and password "password"
    And I navigate to the 2FA management page
    And I add a new TOTP 2FA device with name "Editor Test Device"
    Then There should be 1 enrolled 2FA device
    And There should be a 2FA device with the name "Editor Test Device"

  Scenario: Editor can remove their own last 2FA device when 2FA is not enforced for their role
    When I log in with username "editor" and password "password"
    And I navigate to the 2FA management page
    And I add a new TOTP 2FA device with name "Editor Test Device"
    And I remove the 2FA device with the name "Editor Test Device"
    Then There should be 0 enrolled 2FA devices

  Scenario: Administrator can remove another user's last 2FA device, even when 2FA is enforced for their role
    When I log in with username "admin" and password "password"
    And I enter a valid TOTP for device "Admin Initial Device"
    And I navigate to the 2FA management page
    And I remove the 2FA device with the name "2FA-User Initial Device"
    Then There should be no 2FA device with the name "2FA-User Initial Device"

  Scenario: Administrator can remove their own last 2FA device even when 2FA is enforced for their role
    When I log in with username "admin" and password "password"
    And I enter a valid TOTP for device "Admin Initial Device"
    And I navigate to the 2FA management page
    And I remove the 2FA device with the name "Admin Initial Device"
    Then There should be 1 enrolled 2FA device
    And There should be no 2FA device with the name "Admin Initial Device"

  Scenario: User with role "Neos.Neos:SecondFactorUser" must enter TOTP before accessing the backend module
    When I log in with username "secondFactorUser" and password "password"
    And I enter a valid TOTP for device "2FA-User Initial Device"
    And I navigate to the 2FA management page
    And I remove the 2FA device with the name "2FA-User Initial Device"
    Then There should be 1 enrolled 2FA device
    And There should be a 2FA device with the name "2FA-User Initial Device"
