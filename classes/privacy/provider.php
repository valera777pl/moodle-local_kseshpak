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

namespace local_kseshpak\privacy;

use core_privacy\local\metadata\collection;
use core_privacy\local\request\approved_contextlist;
use core_privacy\local\request\approved_userlist;
use core_privacy\local\request\contextlist;
use core_privacy\local\request\userlist;

/**
 * Privacy provider: the plugin stores nothing itself; it sends the user's key to KSE Shpak.
 *
 * The key itself is kept in {external_tokens} by Moodle core and is covered by core's own privacy provider.
 *
 * @package    local_kseshpak
 * @copyright  2026 KSE Shpak
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class provider implements
    \core_privacy\local\metadata\provider,
    \core_privacy\local\request\core_userlist_provider,
    \core_privacy\local\request\plugin\provider {
    /**
     * What leaves Moodle, and where to.
     *
     * @param collection $collection
     * @return collection
     */
    public static function get_metadata(collection $collection): collection {
        $collection->add_external_location_link('kseshpak', [
            'token' => 'privacy:metadata:kseshpak:token',
            'state' => 'privacy:metadata:kseshpak:state',
        ], 'privacy:metadata:kseshpak');
        return $collection;
    }

    /**
     * No data is stored by this plugin.
     *
     * @param int $userid
     * @return contextlist
     */
    public static function get_contexts_for_userid(int $userid): contextlist {
        return new contextlist();
    }

    /**
     * No data is stored by this plugin.
     *
     * @param userlist $userlist
     */
    public static function get_users_in_context(userlist $userlist) {
    }

    /**
     * No data is stored by this plugin.
     *
     * @param approved_contextlist $contextlist
     */
    public static function export_user_data(approved_contextlist $contextlist) {
    }

    /**
     * No data is stored by this plugin.
     *
     * @param \context $context
     */
    public static function delete_data_for_all_users_in_context(\context $context) {
    }

    /**
     * No data is stored by this plugin.
     *
     * @param approved_contextlist $contextlist
     */
    public static function delete_data_for_user(approved_contextlist $contextlist) {
    }

    /**
     * No data is stored by this plugin.
     *
     * @param approved_userlist $userlist
     */
    public static function delete_data_for_users(approved_userlist $userlist) {
    }
}
