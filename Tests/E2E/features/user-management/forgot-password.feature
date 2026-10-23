@user-management
Feature: Forgot password

  Background:
    Given A user with username "jane", password "old-password" and role "Neos.Neos:Editor" exists
    And user "jane" has the validated email "jane@example.com"

  Scenario: Reset the password by username
    When I request a password reset for "jane"
    Then I should see the generic password reset confirmation
    And "jane@example.com" should receive 1 mail
    When I open the password link from the mail to "jane@example.com"
    And I set the new password "new-password"
    Then I should see the login page
    When I log in with username "jane" and password "new-password"
    Then I should see the Neos content page

  Scenario: Reset the password by email address (case-insensitive)
    When I request a password reset for "JANE@example.com"
    Then "jane@example.com" should receive 1 mail
    When I open the password link from the mail to "jane@example.com"
    And I set the new password "new-password"
    And I log in with username "jane" and password "new-password"
    Then I should see the Neos content page

  Scenario: The old password no longer works after a reset
    When I request a password reset for "jane"
    And I open the password link from the mail to "jane@example.com"
    And I set the new password "new-password"
    And I log in with username "jane" and password "old-password"
    Then I should see the login page

  # Several accounts sharing one email ("one mail, one link per username") cannot be seeded here: our tooling
  # rejects duplicate emails, they only exist as legacy data. Covered by PasswordResetRequestHandlerTest.

  Scenario: Unknown input shows the same answer and sends no mail
    When I request a password reset for "nobody@example.com"
    Then I should see the generic password reset confirmation
    And "nobody@example.com" should receive no mail

  Scenario: No mail to an unvalidated email address
    Given user "jane" has the unvalidated email "jane-new@example.com"
    When I request a password reset for "jane"
    Then I should see the generic password reset confirmation
    And "jane-new@example.com" should receive no mail

  Scenario: A password link can only be used once
    When I request a password reset for "jane"
    And I open the password link from the mail to "jane@example.com"
    And I set the new password "new-password"
    And I open the same link again
    Then I should see that the link is invalid

  Scenario: Requesting a new link invalidates the previous one
    When I request a password reset for "jane"
    And I open the password link from the mail to "jane@example.com"
    And I request a password reset for "jane"
    And I open the same link again
    Then I should see that the link is invalid

  Scenario: Requests are throttled per username
    When I request a password reset for "jane"
    And I request a password reset for "jane"
    And I request a password reset for "jane"
    And I request a password reset for "jane"
    Then I should see the generic password reset confirmation
    And "jane@example.com" should receive 3 mails
