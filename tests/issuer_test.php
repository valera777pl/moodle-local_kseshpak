<?php
// This file is part of Moodle - https://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <https://www.gnu.org/licenses/>.

namespace local_kseshpak;

/**
 * Tests for the read-only key issuer.
 *
 * @package    local_kseshpak
 * @category   test
 * @copyright  2026 KSE Shpak
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_kseshpak\issuer
 */
final class issuer_test extends \advanced_testcase {
    /** @var string The read-only functions KSE Shpak calls, as IT adds them to the service. */
    const FUNCTIONS = 'core_webservice_get_site_info,core_enrol_get_users_courses,gradereport_overview_get_course_grades,'
        . 'gradereport_user_get_grade_items,core_calendar_get_calendar_events,mod_assign_get_assignments,'
        . 'mod_assign_get_submission_status,mod_quiz_get_quizzes_by_courses,core_course_get_contents,'
        . 'core_group_get_course_user_groups';

    /** @var string Quiz attempts since Moodle 5 (KSE moved to Moodle 5 in November 2026). */
    const QUIZ_ATTEMPTS = 'mod_quiz_get_user_quiz_attempts';

    /** @var string Quiz attempts on Moodle 4.x, deprecated in Moodle 5. */
    const QUIZ_ATTEMPTS_OLD = 'mod_quiz_get_user_attempts';

    /**
     * The service's functions on this Moodle: the quiz attempts function it has.
     *
     * @return string comma-separated
     */
    private static function functions(): string {
        global $DB;
        $new = $DB->record_exists('external_functions', ['name' => self::QUIZ_ATTEMPTS]);
        return self::FUNCTIONS . ',' . ($new ? self::QUIZ_ATTEMPTS : self::QUIZ_ATTEMPTS_OLD);
    }

    /**
     * Web services on, REST on, and the service with the given functions.
     *
     * @param string|null $functions comma-separated, '' for this Moodle's functions, null for none
     * @param array $fields more fields of the service
     * @return \stdClass the service record
     */
    private function make_service(?string $functions = '', array $fields = []): \stdClass {
        $functions = $functions === '' ? self::functions() : $functions;
        set_config('enablewebservices', 1);
        set_config('webserviceprotocols', 'rest');
        $generator = $this->getDataGenerator()->get_plugin_generator('core_webservice');
        $defaults = ['name' => 'KSE Shpak (read-only)', 'shortname' => issuer::SERVICE, 'enabled' => 1];
        $service = $generator->create_service(array_merge($defaults, $fields));
        if ($functions !== null) {
            $generator->create_service_functions(['service' => issuer::SERVICE, 'functions' => $functions]);
        }
        return $service;
    }

    /**
     * A signed-in student, with webservice/rest:use on "Authenticated user" as "Enable mobile web services" gives it.
     *
     * @return \stdClass the user
     */
    private function student(): \stdClass {
        $user = $this->getDataGenerator()->create_user();
        $this->set_user_capability('webservice/rest:use', CAP_ALLOW);
        $this->setUser($user);
        return $user;
    }

    /**
     * Change a capability of "Authenticated user" at system level.
     *
     * @param string $capability
     * @param int $permission CAP_ALLOW, CAP_PROHIBIT…
     */
    private function set_user_capability(string $capability, int $permission): void {
        global $DB;
        $roleid = $DB->get_field('role', 'id', ['shortname' => 'user'], MUST_EXIST);
        assign_capability($capability, $permission, $roleid, \context_system::instance()->id, true);
        accesslib_clear_all_caches_for_unit_testing();
    }

    /**
     * The call fails with this error code.
     *
     * @param callable $call
     * @param string $errorcode
     * @return \moodle_exception
     */
    private function assert_error(callable $call, string $errorcode): \moodle_exception {
        try {
            $call();
        } catch (\moodle_exception $e) {
            $this->assertSame($errorcode, $e->errorcode, $e->getMessage());
            return $e;
        }
        $this->fail("Expected the error '$errorcode'");
    }

    public function test_valid_state(): void {
        $this->assertTrue(issuer::valid_state('42.1900000000.AbC_dE-f'));
        $this->assertFalse(issuer::valid_state('short'));
        $this->assertFalse(issuer::valid_state('has space in it'));
        $this->assertFalse(issuer::valid_state('<script>x</script>'));
        $this->assertFalse(issuer::valid_state('a&b=c.12345678'));
        $this->assertFalse(issuer::valid_state(str_repeat('a', 257)));
    }

    public function test_return_url(): void {
        $this->resetAfterTest();
        // The default from settings.php.
        $this->assertSame('https://kse-hub.vercel.app/api/moodle-plugin/return', issuer::return_url());

        set_config('returnurl', 'https://example.org/back?x=1', 'local_kseshpak');
        $this->assertSame('https://example.org/back?x=1', issuer::return_url());

        $bads = [
            '',
            'http://kse-hub.vercel.app/api/moodle-plugin/return',
            'javascript:alert(1)',
            '//evil.example',
            'https://',
            'https://a.example/"><script>',
        ];
        foreach ($bads as $bad) {
            set_config('returnurl', $bad, 'local_kseshpak');
            $this->assert_error(fn() => issuer::return_url(), 'notconfigured');
        }
    }

    public function test_return_url_default_applies_until_saved_and_empty_means_off(): void {
        $this->resetAfterTest();
        // The setting was never saved: get_config() says false, and the default address applies.
        unset_config('returnurl', 'local_kseshpak');
        $this->assertFalse(get_config('local_kseshpak', 'returnurl'));
        $this->assertSame(issuer::DEFAULT_RETURN_URL, issuer::return_url());

        // Saved as an empty string by the administrator: off, not the default.
        set_config('returnurl', '', 'local_kseshpak');
        $this->assert_error(fn() => issuer::return_url(), 'notconfigured');
    }

    public function test_service_needs_web_services_and_rest(): void {
        $this->resetAfterTest();
        $this->make_service();
        set_config('enablewebservices', 0);
        $this->assert_error(fn() => issuer::service(), 'enablewsdescription');
        set_config('enablewebservices', 1);
        set_config('webserviceprotocols', 'soap');
        $this->assert_error(fn() => issuer::service(), 'restdisabled');
    }

    public function test_service_missing_or_disabled(): void {
        $this->resetAfterTest();
        set_config('enablewebservices', 1);
        set_config('webserviceprotocols', 'rest');
        $this->assert_error(fn() => issuer::service(), 'servicemissing');

        $this->make_service(self::functions(), ['enabled' => 0]);
        $this->assert_error(fn() => issuer::service(), 'servicemissing');
    }

    public function test_service_with_the_read_functions_is_accepted(): void {
        $this->resetAfterTest();
        $made = $this->make_service();
        $this->assertEquals($made->id, issuer::service()->id);
    }

    public function test_service_with_a_write_function_is_refused(): void {
        $this->resetAfterTest();
        $this->make_service(self::functions() . ',mod_assign_save_submission,core_message_send_instant_messages');
        $e = $this->assert_error(fn() => issuer::service(), 'servicenotreadonly');
        $this->assertStringContainsString('core_message_send_instant_messages, mod_assign_save_submission', $e->getMessage());
    }

    public function test_service_with_a_function_kse_shpak_does_not_call_is_refused(): void {
        $this->resetAfterTest();
        // The function core_course_get_courses only reads, but KSE Shpak does not call it.
        $this->make_service(self::functions() . ',core_course_get_courses,core_message_get_conversations');
        $e = $this->assert_error(fn() => issuer::service(), 'serviceextrafunctions');
        $this->assertStringContainsString('core_course_get_courses, core_message_get_conversations', $e->getMessage());
    }

    public function test_a_function_a_moodle_upgrade_removed_does_not_stop_the_keys(): void {
        global $DB;
        $this->resetAfterTest();
        $service = $this->make_service();
        // Core keeps a removed function listed in the services that had it (upgradelib.php), though nobody can call it.
        $DB->insert_record('external_services_functions', ['externalserviceid' => $service->id, 'functionname' => 'mod_quiz_gone']);
        $this->assertSame(issuer::SERVICE, issuer::service()->shortname);
    }

    public function test_a_service_without_the_groups_function_is_accepted(): void {
        $this->resetAfterTest();
        // Asked of IT since 2.1.3 (group events); a service set up before that, without it, still gets keys.
        $this->make_service(str_replace(',core_group_get_course_user_groups', '', self::functions()));
        $this->assertSame(issuer::SERVICE, issuer::service()->shortname);
    }

    public function test_service_without_functions_is_refused(): void {
        $this->resetAfterTest();
        $this->make_service(null);
        $this->assert_error(fn() => issuer::service(), 'serviceempty');
    }

    public function test_student_gets_one_key_without_createtoken(): void {
        global $DB;
        $this->resetAfterTest();
        $service = $this->make_service();
        $user = $this->student();
        $this->assertFalse(has_capability('moodle/webservice:createtoken', \context_system::instance()));

        $key = issuer::issue_token(issuer::service());
        $this->assertMatchesRegularExpression('/^[a-f0-9]{32}$/', $key);
        $token = $DB->get_record('external_tokens', ['token' => $key], '*', MUST_EXIST);
        $this->assertEquals($user->id, $token->userid);
        $this->assertEquals($service->id, $token->externalserviceid);
        $this->assertEquals(EXTERNAL_TOKEN_PERMANENT, $token->tokentype);
        $this->assertEqualsWithDelta(time() + issuer::DEFAULT_KEY_LIFETIME, $token->validuntil, 5);

        // Connecting again gives the same key, not a second one.
        $this->assertSame($key, issuer::issue_token($service));
        $this->assertEquals(1, $DB->count_records('external_tokens', ['userid' => $user->id]));
    }

    public function test_expired_key_is_replaced(): void {
        global $DB;
        $this->resetAfterTest();
        $service = $this->make_service();
        $this->student();
        $old = issuer::issue_token($service);
        $DB->set_field('external_tokens', 'validuntil', time() - 10, ['token' => $old]);
        $this->assertNotSame($old, issuer::issue_token($service));
    }

    public function test_key_is_issued_only_with_the_plugin_capability(): void {
        $this->resetAfterTest();
        $service = $this->make_service();
        $this->student();
        $this->set_user_capability('local/kseshpak:connect', CAP_PROHIBIT);
        $this->expectException(\required_capability_exception::class);
        issuer::issue_token($service);
    }

    public function test_site_admins_get_no_key(): void {
        $this->resetAfterTest();
        $service = $this->make_service();
        $this->setAdminUser();
        $this->assert_error(fn() => issuer::issue_token($service), 'noadmins');
    }

    public function test_key_needs_rest_use(): void {
        $this->resetAfterTest();
        $service = $this->make_service();
        $this->student();
        $this->set_user_capability('webservice/rest:use', CAP_PROHIBIT);
        $this->assert_error(fn() => issuer::issue_token($service), 'norestuse');
    }

    public function test_restricted_service_needs_an_authorised_user(): void {
        $this->resetAfterTest();
        $this->make_service(self::functions(), ['restrictedusers' => 1]);
        $this->student();
        $this->assert_error(fn() => issuer::issue_token(issuer::service()), 'usernotallowed');
    }

    public function test_every_function_the_readme_asks_for_exists_here_and_only_reads(): void {
        foreach (explode(',', self::functions()) as $name) {
            $info = \core_external\external_api::external_function_info($name, IGNORE_MISSING);
            $this->assertNotEmpty($info, "$name is missing on this Moodle");
            $this->assertSame('read', $info->type, $name);
        }
    }

    public function test_a_service_that_moves_files_is_refused(): void {
        $this->resetAfterTest();
        $this->make_service('', ['uploadfiles' => 1]);
        $this->assert_error(fn() => issuer::service(), 'servicefiles');
    }

    public function test_a_service_that_hands_out_files_is_refused(): void {
        $this->resetAfterTest();
        $this->make_service('', ['downloadfiles' => 1]);
        $this->assert_error(fn() => issuer::service(), 'servicefiles');
    }

    public function test_a_student_of_a_course_gets_a_key(): void {
        $this->resetAfterTest();
        $service = $this->make_service();
        $user = $this->student();
        $course = $this->getDataGenerator()->create_course();
        $this->getDataGenerator()->enrol_user($user->id, $course->id, 'student');
        $this->assertMatchesRegularExpression('/^[a-f0-9]{32}$/', issuer::issue_token($service));
    }

    /**
     * A user with this role in one course (and a student in another) asks for a key: refused, none made.
     *
     * @param string $role shortname of a course role whose key would read other people's grades
     */
    private function assert_course_staff_gets_no_key(string $role): void {
        global $DB;
        $service = $this->make_service();
        $user = $this->student();
        $this->getDataGenerator()->enrol_user($user->id, $this->getDataGenerator()->create_course()->id, 'student');
        $this->getDataGenerator()->enrol_user($user->id, $this->getDataGenerator()->create_course()->id, $role);
        $this->assert_error(fn() => issuer::issue_token($service), 'nostaff');
        $this->assertEquals(0, $DB->count_records('external_tokens', ['userid' => $user->id]));
    }

    public function test_a_teacher_gets_no_key(): void {
        $this->resetAfterTest();
        $this->assert_course_staff_gets_no_key('editingteacher');
    }

    public function test_an_assistant_without_editing_gets_no_key(): void {
        $this->resetAfterTest();
        $this->assert_course_staff_gets_no_key('teacher');
    }

    public function test_a_manager_gets_no_key(): void {
        global $DB;
        $this->resetAfterTest();
        $service = $this->make_service();
        $user = $this->student();
        $managerid = $DB->get_field('role', 'id', ['shortname' => 'manager'], MUST_EXIST);
        role_assign($managerid, $user->id, \context_system::instance()->id);
        $this->assert_error(fn() => issuer::issue_token($service), 'nostaff');
    }

    public function test_a_teacher_of_one_assignment_only_gets_no_key(): void {
        global $DB;
        $this->resetAfterTest();
        $service = $this->make_service();
        $user = $this->student();
        $course = $this->getDataGenerator()->create_course();
        $this->getDataGenerator()->enrol_user($user->id, $course->id, 'student');
        $assign = $this->getDataGenerator()->create_module('assign', ['course' => $course->id]);
        $teacherid = $DB->get_field('role', 'id', ['shortname' => 'teacher'], MUST_EXIST);
        role_assign($teacherid, $user->id, \context_module::instance($assign->cmid)->id);
        $this->assert_error(fn() => issuer::issue_token($service), 'nostaff');
        $this->assertEquals(0, $DB->count_records('external_tokens', ['userid' => $user->id]));
    }

    public function test_a_parent_or_mentor_gets_no_key(): void {
        global $DB;
        $this->resetAfterTest();
        $service = $this->make_service();
        $user = $this->student();
        $child = $this->getDataGenerator()->create_user();
        $roleid = create_role('Parent', 'kseshpakparent', 'A role in a student\'s own context');
        set_role_contextlevels($roleid, [CONTEXT_USER]);
        role_assign($roleid, $user->id, \context_user::instance($child->id)->id);
        $this->assert_error(fn() => issuer::issue_token($service), 'nostaff');
        $this->assertEquals(0, $DB->count_records('external_tokens', ['userid' => $user->id]));
    }

    public function test_a_student_with_a_role_in_an_activity_that_grades_nothing_gets_a_key(): void {
        global $DB;
        $this->resetAfterTest();
        $service = $this->make_service();
        $user = $this->student();
        $course = $this->getDataGenerator()->create_course();
        $this->getDataGenerator()->enrol_user($user->id, $course->id, 'student');
        $assign = $this->getDataGenerator()->create_module('assign', ['course' => $course->id]);
        $studentid = $DB->get_field('role', 'id', ['shortname' => 'student'], MUST_EXIST);
        role_assign($studentid, $user->id, \context_module::instance($assign->cmid)->id);
        $this->assertMatchesRegularExpression('/^[a-f0-9]{32}$/', issuer::issue_token($service));
    }

    public function test_the_return_address_is_in_the_settings_right_after_install(): void {
        $this->resetAfterTest();
        $this->assertSame(issuer::DEFAULT_RETURN_URL, get_config('local_kseshpak', 'returnurl'));
        $this->assertEquals(issuer::DEFAULT_KEY_LIFETIME, get_config('local_kseshpak', 'keylifetime'));
    }

    public function test_a_key_lives_a_year_unless_the_setting_says_otherwise(): void {
        global $CFG, $DB;
        $this->resetAfterTest();
        $service = $this->make_service();
        $user = $this->student();
        $this->assertSame(365 * DAYSECS, issuer::key_lifetime());

        // A month, set by the administrator.
        set_config('keylifetime', 30 * DAYSECS, 'local_kseshpak');
        $token = issuer::issue_token($service);
        $validuntil = $DB->get_field('external_tokens', 'validuntil', ['token' => $token, 'userid' => $user->id]);
        $this->assertEqualsWithDelta(time() + 30 * DAYSECS, $validuntil, 5);

        // 0: the site's own lifetime.
        set_config('keylifetime', 0, 'local_kseshpak');
        $CFG->tokenduration = 12 * WEEKSECS;
        $this->assertSame(12 * WEEKSECS, issuer::key_lifetime());

        // Never saved: a year.
        unset_config('keylifetime', 'local_kseshpak');
        $this->assertSame(issuer::DEFAULT_KEY_LIFETIME, issuer::key_lifetime());
    }

    public function test_logged_in_as_someone_else_gets_no_key(): void {
        $this->resetAfterTest();
        $service = $this->make_service();
        $this->set_user_capability('webservice/rest:use', CAP_ALLOW);
        $user = $this->getDataGenerator()->create_user();
        $this->setAdminUser();
        \core\session\manager::loginas($user->id, \context_system::instance());
        $this->assert_error(fn() => issuer::issue_token($service), 'nologinas');
    }

    public function test_a_suspended_user_gets_no_key(): void {
        $this->resetAfterTest();
        $service = $this->make_service();
        $this->set_user_capability('webservice/rest:use', CAP_ALLOW);
        $this->setUser($this->getDataGenerator()->create_user(['suspended' => 1]));
        $this->assert_error(fn() => issuer::issue_token($service), 'suspended');
    }

    public function test_a_key_close_to_its_end_is_replaced_by_a_full_one(): void {
        global $DB;
        $this->resetAfterTest();
        $service = $this->make_service();
        $user = $this->student();
        $old = issuer::issue_token($service);
        $DB->set_field('external_tokens', 'validuntil', time() + DAYSECS, ['token' => $old]);

        $new = issuer::issue_token($service);
        $this->assertNotSame($old, $new);
        // The old one is gone: KSE Shpak keeps only the newest, and nobody else holds it.
        $this->assertEquals([$new], array_values($DB->get_fieldset_select('external_tokens', 'token', 'userid = ?', [$user->id])));
        $validuntil = $DB->get_field('external_tokens', 'validuntil', ['token' => $new]);
        $this->assertEqualsWithDelta(time() + issuer::DEFAULT_KEY_LIFETIME, $validuntil, 5);
    }

    public function test_delete_tokens_takes_only_this_users_keys_for_this_service(): void {
        global $DB;
        $this->resetAfterTest();
        $service = $this->make_service();
        $other = $this->getDataGenerator()->create_user();
        $this->set_user_capability('webservice/rest:use', CAP_ALLOW);
        $this->setUser($other);
        issuer::issue_token($service);
        $user = $this->student();
        issuer::issue_token($service);

        $this->assertSame(1, issuer::delete_tokens((int) $user->id, (int) $service->id));
        $this->assertEquals(0, $DB->count_records('external_tokens', ['userid' => $user->id]));
        $this->assertEquals(1, $DB->count_records('external_tokens', ['userid' => $other->id]));
    }
}
