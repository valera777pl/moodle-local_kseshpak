@local @local_kseshpak
Feature: Connect Moodle to KSE Shpak with a read-only key
  In order to see my courses, grades and deadlines in KSE Shpak
  As a student
  I need to give KSE Shpak a key that can only read

  Background:
    Given the following config values are set as admin:
      | enablewebservices   | 1    |
      | webserviceprotocols | rest |
    And the following "users" exist:
      | username | firstname | lastname | email                |
      | student1 | Olena     | Koval    | student1@example.com |
    And the following "permission overrides" exist:
      | capability          | permission | role | contextlevel | reference |
      | webservice/rest:use | Allow      | user | System       |           |
    And the following "core_webservice > Services" exist:
      | name                | shortname        | enabled |
      | KSE Shpak (read-only) | kse_shpak_readonly | 1       |
    And the following "core_webservice > Service functions" exist:
      | service          | functions                                                                       |
      | kse_shpak_readonly | core_webservice_get_site_info,core_enrol_get_users_courses,core_course_get_contents |

  Scenario: A signed-in student allows KSE Shpak and goes back with a read-only key
    Given I log in as "student1"
    When I visit "/local/kseshpak/connect.php?state=42.1900000000.Signature_-x"
    Then I should see "Connect Moodle to KSE Shpak"
    And I should see "You are signed in as Olena Koval."
    And I should see "It will not be able to submit work, send messages or change anything in Moodle."
    And I press "Allow"
    And I should see "Returning to KSE Shpak"
    And the KSE Shpak return form carries the key of "student1" and the state "42.1900000000.Signature_-x"

  Scenario: A student who is not signed in signs in first and comes back to the same connection
    When I visit "/local/kseshpak/connect.php?state=42.1900000000.Signature_-x"
    And I set the field "Username" to "student1"
    And I set the field "Password" to "student1"
    And I press "Log in"
    Then I should see "Connect Moodle to KSE Shpak"
    And I press "Allow"
    And the KSE Shpak return form carries the key of "student1" and the state "42.1900000000.Signature_-x"

  Scenario: Connecting twice gives the same key
    Given I log in as "student1"
    And I visit "/local/kseshpak/connect.php?state=42.1900000000.Signature_-x"
    And I press "Allow"
    When I visit "/local/kseshpak/connect.php?state=43.1900000000.Another_state"
    And I press "Allow"
    Then the KSE Shpak return form carries the key of "student1" and the state "43.1900000000.Another_state"
    And "student1" has 1 KSE Shpak key
