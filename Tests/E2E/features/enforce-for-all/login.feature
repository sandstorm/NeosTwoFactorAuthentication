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

  Scenario: User can log in when after setting up a 2FA device
    When I log in with username "editor" and password "password"
    And I set up a 2FA device with name "Editor Test Device"
    Then I should see the Neos content page
