@enforce-for-role
Feature: Login flow with 2FA enforced for administrators only

  # Requires FLOW_CONTEXT=Production/E2E-SUT/EnforceForRole
  # Config: enforce2FAForRoles: ['Neos.Neos:Administrator']

  Background:
    Given A user with username "admin", password "password" and role "Neos.Neos:Administrator" exists
    And A user with username "editor", password "password" and role "Neos.Neos:Editor" exists

  Scenario: Administrator is redirected to 2FA setup when no device is enrolled
    When I log in with username "admin" and password "password"
    Then I should see the 2FA setup page
    And I cannot access the Neos content page

  Scenario: Administrator can log in after setting up a 2FA device
    When I log in with username "admin" and password "password"
    And I set up a 2FA device with name "Admin Test Device"
    Then I should see the Neos content page

  Scenario: Editor can log in without 2FA when no device is enrolled
    When I log in with username "editor" and password "password"
    Then I should see the Neos content page

  Scenario: Editor still has to enter a TOTP code when a device is enrolled
    When I log in with username "editor" and password "password"
    And I navigate to the 2FA management page
    And I add a new TOTP 2FA device with name "Editor Test Device"
    And I log out
    And I log in with username "editor" and password "password"
    Then I should see the 2FA verification page
