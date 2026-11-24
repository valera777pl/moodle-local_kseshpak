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

/**
 * Upgrade steps for local_kseshpak.
 *
 * @package    local_kseshpak
 * @copyright  2026 KSE Shpak
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Upgrades local_kseshpak.
 *
 * @param int $oldversion
 * @return bool
 */
function xmldb_local_kseshpak_upgrade($oldversion) {
    if ($oldversion < 2026112404) {
        // Installed before 2.1.3 and never saved: the settings field was empty though the default applied. An address saved
        // on purpose, the empty one ("off") included, stays as it is.
        if (get_config('local_kseshpak', 'returnurl') === false) {
            set_config('returnurl', \local_kseshpak\issuer::DEFAULT_RETURN_URL, 'local_kseshpak');
        }
        if (get_config('local_kseshpak', 'keylifetime') === false) {
            set_config('keylifetime', \local_kseshpak\issuer::DEFAULT_KEY_LIFETIME, 'local_kseshpak');
        }
        upgrade_plugin_savepoint(true, 2026112404, 'local', 'kseshpak');
    }
    return true;
}
