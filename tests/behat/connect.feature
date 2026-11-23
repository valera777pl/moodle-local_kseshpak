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
      | teacher1 | Taras     | Bondar   | teacher1@example.com |
    And the following "courses" exist:
      | fullname          | shortname |
      | Principles of Eco | ECON100   |
    And the following "course enrolments" exist:
      | user     | course  | role           |
      | student1 | ECON100 | student        |
      | teacher1 | ECON100 | editingteacher |
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

  Scenario: A teacher gets no key: it would read the students' grades
    Given I log in as "teacher1"
    When I visit "/local/kseshpak/connect.php?state=42.1900000000.Signature_-x"
    Then I should see "can see other people's grades"
    And I should see "Return to KSE Shpak"
    And I should not see "Allow"
    And "teacher1" has 0 KSE Shpak keys

  Scenario: The allow link opened directly, without the button, gives no key
    Given I log in as "student1"
    When I visit "/local/kseshpak/connect.php?state=42.1900000000.Signature_-x&confirm=1"
    Then I should not see "Returning to KSE Shpak"
    And "student1" has 0 KSE Shpak keys

  Scenario: A student turns KSE Shpak's access off in Moodle itself
    Given I log in as "student1"
    And I visit "/local/kseshpak/connect.php?state=42.1900000000.Signature_-x"
    And I should see "/local/kseshpak/disconnect.php"
    And I press "Allow"
    And "student1" has 1 KSE Shpak key
    When I visit "/local/kseshpak/disconnect.php"
    And I press "Turn off"
    Then I should see "KSE Shpak can no longer read your Moodle"
    And "student1" has 0 KSE Shpak keys
    And I visit "/local/kseshpak/disconnect.php"
    And I should see "there is nothing to turn off"
