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

    public function test_service_without_functions_is_refused(): void {
        $this->resetAfterTest();
        $this->make_service(null);
        $this->assert_error(fn() => issuer::service(), 'serviceempty');
    }

    public function test_student_gets_one_key_without_createtoken(): void {
        global $CFG, $DB;
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
        $this->assertEqualsWithDelta(time() + $CFG->tokenduration, $token->validuntil, 5);

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

    public function test_key_works_for_the_service_and_its_functions_only(): void {
        global $DB;
        $this->resetAfterTest();
        $service = $this->make_service();
        $this->student();
        $key = issuer::issue_token($service);
        $token = $DB->get_record('external_tokens', ['token' => $key], '*', MUST_EXIST);
        $functions = $DB->get_fieldset_select(
            'external_services_functions',
            'functionname',
            'externalserviceid = ?',
            [$token->externalserviceid]
        );
        sort($functions);
        $expected = explode(',', self::functions());
        sort($expected);
        $this->assertSame($expected, $functions);
    }
}
