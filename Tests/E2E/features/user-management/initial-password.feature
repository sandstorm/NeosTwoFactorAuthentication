@user-management
Feature: Initial password and email validation

  Background:
    Given A user with username "admin", password "password" and role "Neos.Neos:Administrator" exists
    And user "admin" has the validated email "admin@example.com"

  Scenario: Admin creates a user who sets the initial password via mail - this also validates the email
    When I log in with username "admin" and password "password"
    And I create the user "newbie" with email "newbie@example.com" and the initial password mail
    Then "newbie@example.com" should receive 1 mail
    And the email of the user should be shown as not validated
    When I log out
    And I open the password link from the mail to "newbie@example.com"
    And I set the new password "newbie-password"
    And I log in with username "newbie" and password "newbie-password"
    Then I should see the Neos content page
    When I log out
    And I log in with username "admin" and password "password"
    And I open the user "newbie" in the user management module
    Then the email of the user should be shown as validated

  Scenario: The initial password mail can be sent via CLI
    Given A user with username "cli-user", password "unknown" and role "Neos.Neos:Editor" exists
    And user "cli-user" has the unvalidated email "cli-user@example.com"
    And an initial password mail was sent to "cli-user" via CLI
    When I open the password link from the mail to "cli-user@example.com"
    And I set the new password "cli-password"
    And I log in with username "cli-user" and password "cli-password"
    Then I should see the Neos content page

  Scenario: Changing the email resets the validation and sends a confirmation mail
    Given A user with username "jane", password "password" and role "Neos.Neos:Editor" exists
    And user "jane" has the validated email "jane@example.com"
    When I log in with username "admin" and password "password"
    And I open the user "jane" in the user management module
    And I change the email to "jane-new@example.com"
    Then the email of the user should be shown as not validated
    And "jane-new@example.com" should receive 1 mail
    When I open the email confirmation link from the mail to "jane-new@example.com"
    And I confirm my email address
    Then I should see that my email address is confirmed
    When I open the user "jane" in the user management module
    Then the email of the user should be shown as validated

  # Also guards against Flow lowercasing the (case-sensitive) token in the generated form URL of the confirm button
  Scenario: A user without email sets their own email and confirms it
    Given A user with username "no-mail-admin", password "password" and role "Neos.Neos:Administrator" exists
    When I log in with username "no-mail-admin" and password "password"
    And I open the user "no-mail-admin" in the user management module
    And I change the email to "no-mail-admin@example.com"
    Then the email of the user should be shown as not validated
    And "no-mail-admin@example.com" should receive 1 mail
    When I open the email confirmation link from the mail to "no-mail-admin@example.com"
    And I confirm my email address
    Then I should see that my email address is confirmed
    When I open the user "no-mail-admin" in the user management module
    Then the email of the user should be shown as validated

  Scenario: Admin marks an email as validated manually
    Given A user with username "jane", password "password" and role "Neos.Neos:Editor" exists
    And user "jane" has the unvalidated email "jane@example.com"
    When I log in with username "admin" and password "password"
    And I open the user "jane" in the user management module
    And I click "mark-email-validated" in the user details
    Then the email of the user should be shown as validated
