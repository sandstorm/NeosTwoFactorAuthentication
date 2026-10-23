@user-management
Feature: User management backend module

  Background:
    Given A user with username "admin", password "password" and role "Neos.Neos:Administrator" exists
    And user "admin" has the validated email "admin@example.com"
    And A user with username "jane", password "password" and role "Neos.Neos:Editor" exists
    And A user with username "john", password "password" and role "Neos.Neos:Editor" exists

  Scenario: The core user module is replaced
    When I log in with username "admin" and password "password"
    Then the core user module should be disabled
    And the user list should contain "jane"

  Scenario: Bulk delete users
    When I log in with username "admin" and password "password"
    And I delete the users "jane, john" via bulk delete
    Then the user list should not contain "jane"
    And the user list should not contain "john"
    And the user list should contain "admin"

  Scenario: Send a test mail to check the mail configuration
    When I log in with username "admin" and password "password"
    And I send a test mail
    Then "admin@example.com" should receive 1 mail

  Scenario: Admin sends a password reset mail
    Given user "jane" has the validated email "jane@example.com"
    When I log in with username "admin" and password "password"
    And I open the user "jane" in the user management module
    And I click "send-reset-mail" in the user details
    Then "jane@example.com" should receive 1 mail
