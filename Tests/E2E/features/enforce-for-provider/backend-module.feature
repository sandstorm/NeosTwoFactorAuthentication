@enforce-for-provider
Feature: Backend module with 2FA enforced for the Neos.Neos:Backend authentication provider

  # Requires FLOW_CONTEXT=Production/E2E-SUT/EnforceForProvider
  # Config: enforce2FAForAuthenticationProviders: ['Neos.Neos:Backend']

  Background:
    Given A user with username "admin", password "password" and role "Neos.Neos:Administrator" with enrolled 2FA device with name "Admin Initial Device" exists
    And A user with username "editor", password "password" and role "Neos.Neos:Editor" with enrolled 2FA device with name "Editor Initial Device" exists

  Scenario: User cannot remove their last 2FA device when 2FA is enforced for their provider
    When I log in with username "editor" and password "password"
    And I enter a valid TOTP for device "Editor Initial Device"
    And I navigate to the 2FA management page
    And I remove the 2FA device with the name "Editor Initial Device"
    Then There should be 1 enrolled 2FA device
    And There should be a 2FA device with the name "Editor Initial Device"

  Scenario: Admin user can remove another user's last 2FA device, even when 2FA is enforced for their provider
    When I log in with username "admin" and password "password"
    And I enter a valid TOTP for device "Admin Initial Device"
    And I navigate to the 2FA management page
    And I remove the 2FA device with the name "Editor Initial Device"
    Then There should be no 2FA device with the name "Editor Initial Device"
