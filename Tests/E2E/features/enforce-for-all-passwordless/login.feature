@enforce-for-all-passwordless
Feature: Enforced 2FA setup with passwordless passkey registration

  When 2FA is enforced for all users AND passwordless login is enabled, a factor-less
  user forced to set up a second factor can enrol a discoverable, usernameless passkey
  right at the enforced-setup gate — and then sign in passwordlessly with it afterwards.

  Background:
    Given A user with username "admin", password "password" and role "Neos.Neos:Administrator" exists
    And A user with username "editor", password "password" and role "Neos.Neos:Editor" exists

  Scenario: The enforced-setup screen offers passwordless passkey registration
    When I log in with username "editor" and password "password"
    Then I should see the 2FA setup page
    And I should see the "Register a passkey" 2FA method option

  Scenario: A passkey registered during enforced setup enables passwordless login
    Given I have a virtual security key
    When I log in with username "editor" and password "password"
    And I set up a passwordless passkey during enforced setup
    Then I should see the Neos content page
    When I log out
    And I sign in with a passkey
    Then I should see the Neos content page

  Scenario: A separator divides the passwordless passkey option from the second-factor methods
    When I log in with username "editor" and password "password"
    Then I should see the 2FA setup page
    And I should see a separator between the passkey option and the other 2FA methods

  Scenario: The enforced-setup screen explains the requirement and labels each option
    When I log in with username "editor" and password "password"
    Then I should see the 2FA setup page
    And I should see the enforced 2FA setup notice
    And I should see a 2FA section heading "Passkey"
    And I should see a 2FA section heading "One-Time-Password 2FA"
    And I should see a 2FA section heading "Passkey 2FA"
