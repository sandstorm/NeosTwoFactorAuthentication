@default-context
Feature: Passwordless login is disabled by default

  Passwordless passkey login is opt-in. With the default configuration it must be impossible:
  the login screen shows no passkey button, and the endpoint refuses to authenticate anyone.

  Scenario: The passkey sign-in button is not shown when passwordless login is disabled
    When I open the login page
    Then I should not see the passkey sign-in button

  Scenario: The passwordless verify endpoint is forbidden when passwordless login is disabled
    Then the passwordless verify endpoint is forbidden
