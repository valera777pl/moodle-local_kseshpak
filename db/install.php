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
 * Install steps for local_kseshpak.
 *
 * @package    local_kseshpak
 * @copyright  2026 KSE Shpak
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * The return address and the key lifetime are saved at install, so the settings page shows them from the start.
 *
 * @return bool
 */
function xmldb_local_kseshpak_install() {
    if (get_config('local_kseshpak', 'returnurl') === false) {
        set_config('returnurl', \local_kseshpak\issuer::DEFAULT_RETURN_URL, 'local_kseshpak');
    }
    if (get_config('local_kseshpak', 'keylifetime') === false) {
        set_config('keylifetime', \local_kseshpak\issuer::DEFAULT_KEY_LIFETIME, 'local_kseshpak');
    }
    return true;
}
