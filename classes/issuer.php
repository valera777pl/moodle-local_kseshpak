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

use context_system;
use moodle_exception;
use stdClass;

/**
 * Issues the current user's key for the read-only service, and nothing else.
 *
 * Every check that can fail because of the site's setup fails here with its own message, so the person
 * setting the plugin up sees what to change instead of a generic error.
 *
 * @package    local_kseshpak
 * @copyright  2026 KSE Shpak
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class issuer {
    /** @var string Short name of the only external service this plugin issues keys for. */
    const SERVICE = 'kse_shpak_readonly';

    /**
     * @var string[] The functions KSE Shpak calls, and nothing else (README step 2). A service with any other function,
     * even one that only reads (private messages, other users' profiles), gets no key from this plugin.
     */
    const FUNCTIONS = [
        'core_webservice_get_site_info', 'core_enrol_get_users_courses', 'gradereport_overview_get_course_grades',
        'gradereport_user_get_grade_items', 'core_calendar_get_calendar_events', 'mod_assign_get_assignments',
        'mod_assign_get_submission_status', 'mod_quiz_get_quizzes_by_courses', 'core_course_get_contents',
        'mod_quiz_get_user_quiz_attempts', 'mod_quiz_get_user_attempts', 'core_group_get_course_user_groups',
    ];

    /** @var int A key with less than this left is replaced by a new one (30 days), so a reconnect gives a full term. */
    const RENEW_BEFORE = 30 * DAYSECS;

    /**
     * The opaque value KSE Shpak puts in the link: letters, digits, dot, dash and underscore only.
     *
     * @param string $state
     * @return bool
     */
    public static function valid_state(string $state): bool {
        return (bool) preg_match('/^[A-Za-z0-9._-]{8,256}$/', $state);
    }

    /**
     * The address the key is sent to, from the plugin settings; https only.
     *
     * @return string
     * @throws moodle_exception when it is empty or not https (this is also how the plugin is turned off)
     */
    public static function return_url(): string {
        $url = trim((string) get_config('local_kseshpak', 'returnurl'));
        if (!preg_match('~^https://[A-Za-z0-9.-]+(:\d+)?(/[^\s"\'<>]*)?$~', $url)) {
            throw new moodle_exception('notconfigured', 'local_kseshpak');
        }
        return $url;
    }

    /**
     * The read-only service, after checking web services, REST and every function of the service.
     *
     * @return stdClass record from {external_services}
     * @throws moodle_exception
     */
    public static function service(): stdClass {
        global $CFG, $DB;

        if (empty($CFG->enablewebservices)) {
            throw new moodle_exception('enablewsdescription', 'webservice');
        }
        $protocols = empty($CFG->webserviceprotocols) ? [] : explode(',', $CFG->webserviceprotocols);
        if (!in_array('rest', $protocols)) {
            throw new moodle_exception('restdisabled', 'local_kseshpak');
        }
        $service = $DB->get_record('external_services', ['shortname' => self::SERVICE, 'enabled' => 1]);
        if (!$service) {
            throw new moodle_exception('servicemissing', 'local_kseshpak', '', self::SERVICE);
        }
        self::require_read_only($service);
        // Files go through their own scripts, not through the functions above: uploading is writing, and neither is needed.
        if (!empty($service->uploadfiles) || !empty($service->downloadfiles)) {
            throw new moodle_exception('servicefiles', 'local_kseshpak', '', self::SERVICE);
        }
        return $service;
    }

    /**
     * Refuses a service that has no functions, any function not declared 'read' in its db/services.php, and any
     * function that is not on the list of what KSE Shpak calls (self::FUNCTIONS).
     *
     * @param stdClass $service record from {external_services}
     * @throws moodle_exception
     */
    public static function require_read_only(stdClass $service): void {
        global $DB;

        $names = $DB->get_fieldset_select(
            'external_services_functions',
            'functionname',
            'externalserviceid = ?',
            [$service->id]
        );
        if (!$names) {
            throw new moodle_exception('serviceempty', 'local_kseshpak', '', self::SERVICE);
        }
        $notread = [];
        foreach ($names as $name) {
            try {
                $info = \core_external\external_api::external_function_info($name, IGNORE_MISSING);
            } catch (\Throwable $e) {
                // A function whose code is gone can't be shown to be read-only.
                $info = false;
            }
            if (!$info || ($info->type ?? '') !== 'read') {
                $notread[] = $name;
            }
        }
        if ($notread) {
            sort($notread);
            throw new moodle_exception('servicenotreadonly', 'local_kseshpak', '', implode(', ', $notread));
        }
        $extra = array_diff($names, self::FUNCTIONS);
        if ($extra) {
            sort($extra);
            throw new moodle_exception('serviceextrafunctions', 'local_kseshpak', '', implode(', ', $extra));
        }
    }

    /**
     * The current user may get a key for the service: everything that would stop it, checked before the user is asked.
     *
     * @param stdClass $service record from {external_services}
     * @throws moodle_exception
     */
    public static function require_can_connect(stdClass $service): void {
        global $DB, $USER;

        $context = context_system::instance();
        \core_user::require_active_user($USER, true, true);
        require_capability('local/kseshpak:connect', $context);
        // As in core: a site administrator's key would read everyone's data.
        if (is_siteadmin()) {
            throw new moodle_exception('noadmins', 'local_kseshpak');
        }
        // Someone "logged in as" this user is not this user.
        if (\core\session\manager::is_loggedinas()) {
            throw new moodle_exception('nologinas', 'local_kseshpak');
        }
        // The same functions give a teacher, an assistant or a manager their students' grades: the key is for one's own only.
        if (self::sees_others_grades((int) $USER->id)) {
            throw new moodle_exception('nostaff', 'local_kseshpak');
        }
        if (!has_capability('webservice/rest:use', $context)) {
            throw new moodle_exception('norestuse', 'local_kseshpak');
        }
        if ($service->requiredcapability && !has_capability($service->requiredcapability, $context)) {
            throw new moodle_exception('missingrequiredcapability', 'webservice', '', $service->requiredcapability);
        }
        if ($service->restrictedusers) {
            $allowed = $DB->get_record('external_services_users', ['externalserviceid' => $service->id, 'userid' => $USER->id]);
            if (!$allowed) {
                throw new moodle_exception('usernotallowed', 'webservice', '', $service->shortname);
            }
            if (!empty($allowed->validuntil) && $allowed->validuntil < time()) {
                throw new moodle_exception('invalidtimedtoken', 'webservice');
            }
            if (!empty($allowed->iprestriction) && !address_in_subnet(getremoteaddr(), $allowed->iprestriction)) {
                throw new moodle_exception('invalidiptoken', 'webservice');
            }
        }
    }

    /**
     * Whether the user may see other people's grades anywhere: in the whole site, or in at least one course.
     *
     * @param int $userid
     * @return bool
     */
    public static function sees_others_grades(int $userid): bool {
        if (has_capability('moodle/grade:viewall', context_system::instance(), $userid, false)) {
            return true;
        }
        return (bool) get_user_capability_course('moodle/grade:viewall', $userid, false, '', '', 1);
    }

    /**
     * The current user's key for the service: a valid one they already have, or a new one.
     *
     * Students don't need moodle/webservice:createtoken (that would let them make keys for any open service);
     * this plugin's own capability local/kseshpak:connect lets them get a key for kse_shpak_readonly only.
     * The checks and the expiry are those of \core_external\util::generate_token_for_current_user().
     * A key close to its expiry is not handed out again: a new one replaces every key of the user for this service,
     * since KSE Shpak keeps only the newest.
     *
     * @param stdClass $service record from {external_services}, as returned by service()
     * @return string the key
     * @throws moodle_exception
     */
    public static function issue_token(stdClass $service): string {
        global $CFG, $DB, $USER;

        self::require_can_connect($service);
        $context = context_system::instance();

        $token = self::existing_token($service);
        if (!$token) {
            self::delete_tokens((int) $USER->id, (int) $service->id);
            $validuntil = empty($CFG->tokenduration) ? 0 : time() + (int) $CFG->tokenduration;
            $key = \core_external\util::generate_token(EXTERNAL_TOKEN_PERMANENT, $service, $USER->id, $context, $validuntil);
            $token = $DB->get_record('external_tokens', ['token' => $key], '*', MUST_EXIST);

            $snapshot = clone $token;
            $snapshot->privatetoken = null;
            $event = \core\event\webservice_token_created::create([
                'objectid' => $token->id,
                'relateduserid' => $USER->id,
                'other' => ['auto' => true],
            ]);
            $event->add_record_snapshot('external_tokens', $snapshot);
            $event->trigger();
        }
        \core_external\util::log_token_request($token);
        return $token->token;
    }

    /**
     * Deletes every key of the user for the service.
     *
     * @param int $userid
     * @param int $serviceid
     * @return int how many there were
     */
    public static function delete_tokens(int $userid, int $serviceid): int {
        global $DB;

        $conditions = ['userid' => $userid, 'externalserviceid' => $serviceid];
        $count = $DB->count_records('external_tokens', $conditions);
        $DB->delete_records('external_tokens', $conditions);
        return $count;
    }

    /**
     * The user's newest permanent key for the service that is still valid for a while, if any.
     *
     * @param stdClass $service record from {external_services}
     * @return stdClass|null record from {external_tokens}
     */
    private static function existing_token(stdClass $service): ?stdClass {
        global $CFG, $DB, $USER;

        // Renew a month before the end, or halfway through when the site's keys live less than two months.
        $renewbefore = min(self::RENEW_BEFORE, (int) (($CFG->tokenduration ?? 0) / 2));

        $tokens = $DB->get_records('external_tokens', [
            'userid' => $USER->id,
            'externalserviceid' => $service->id,
            'tokentype' => EXTERNAL_TOKEN_PERMANENT,
        ], 'timecreated DESC, id DESC');
        foreach ($tokens as $token) {
            if (!empty($token->sid) || (!empty($token->validuntil) && $token->validuntil < time() + $renewbefore)) {
                continue;
            }
            if (!empty($token->iprestriction) && !address_in_subnet(getremoteaddr(), $token->iprestriction)) {
                continue;
            }
            return $token;
        }
        return null;
    }
}
