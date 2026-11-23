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
        return $service;
    }

    /**
     * Refuses a service that has no functions, or any function not declared 'read' in its db/services.php.
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
     * The current user's key for the service: a valid one they already have, or a new one.
     *
     * Students don't need moodle/webservice:createtoken (that would let them make keys for any open service);
     * this plugin's own capability local/kseshpak:connect lets them get a key for kse_shpak_readonly only.
     * The checks and the expiry are those of \core_external\util::generate_token_for_current_user().
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
     * The user's newest permanent key for the service that is still valid, if any.
     *
     * @param stdClass $service record from {external_services}
     * @return stdClass|null record from {external_tokens}
     */
    private static function existing_token(stdClass $service): ?stdClass {
        global $DB, $USER;

        $tokens = $DB->get_records('external_tokens', [
            'userid' => $USER->id,
            'externalserviceid' => $service->id,
            'tokentype' => EXTERNAL_TOKEN_PERMANENT,
        ], 'timecreated DESC, id DESC');
        foreach ($tokens as $token) {
            if (!empty($token->sid) || (!empty($token->validuntil) && $token->validuntil < time())) {
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
