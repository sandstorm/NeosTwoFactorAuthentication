@enforce-for-all
Feature: Login flow with 2FA enforced for all users

  Background:
    Given A user with username "admin", password "password" and role "Neos.Neos:Administrator" exists
    And A user with username "editor", password "password" and role "Neos.Neos:Editor" exists

  Scenario: Admin has to setup a 2FA when 2FA is enforced for all users, even without a device
    When I log in with username "admin" and password "password"
    Then I should see the 2FA setup page
    And I cannot access the Neos content page

  Scenario: Editor has to setup a 2FA when 2FA is enforced for all users, even without a device
    When I log in with username "editor" and password "password"
    Then I should see the 2FA setup page
    And I cannot access the Neos content page

  Scenario: User is offered a choice of 2FA methods when setting up
    When I log in with username "editor" and password "password"
    Then I should see the 2FA setup page
    And I should see the 2FA method selection

  Scenario: User can cancel the enforced 2FA setup and return to the login screen
    When I log in with username "editor" and password "password"
    And I should see the 2FA setup page
    And I cancel the 2FA login
    Then I should see the login page

  Scenario: User can log in when after setting up a 2FA device
    When I log in with username "editor" and password "password"
    And I set up a 2FA device with name "Editor Test Device"
    Then I should see the Neos content page

  Scenario: User can set up a WebAuthn device and log in
    Given I have a virtual security key
    When I log in with username "editor" and password "password"
    And I set up a WebAuthn 2FA device
    Then I should see the Neos content page

  Scenario: A WebAuthn device set up during enforced onboarding keeps its name
    Given I have a virtual security key
    When I log in with username "editor" and password "password"
    And I set up a WebAuthn 2FA device with name "Editor Security Key"
    And I navigate to the 2FA management page
    Then There should be 1 enrolled "Passkey as 2nd factor" 2FA device
    And There should be a 2FA device with the name "Editor Security Key"
