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
 * Turn KSE Shpak's access off: the signed-in user deletes their own keys for kse_shpak_readonly.
 *
 * Students can't see these keys under Preferences → Security keys (that page needs moodle/webservice:createtoken),
 * so this page is their way to revoke the access in Moodle itself. It needs no capability and works even when the
 * plugin is not set up: taking one's own access away must always be possible.
 *
 * @package    local_kseshpak
 * @copyright  2026 KSE Shpak
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require(__DIR__ . '/../../config.php');

use local_kseshpak\issuer;

$confirm = optional_param('confirm', 0, PARAM_BOOL);

$url = new moodle_url('/local/kseshpak/disconnect.php');
$PAGE->set_url($url);
$PAGE->set_context(context_system::instance());
$PAGE->set_pagelayout('login');
$PAGE->set_cacheable(false);
$PAGE->set_title(get_string('disconnecttitle', 'local_kseshpak'));

require_login(null, false);
if (isguestuser()) {
    throw new moodle_exception('guestsarenotallowed', 'error');
}

$serviceid = (int) $DB->get_field('external_services', 'id', ['shortname' => issuer::SERVICE]);
$count = $serviceid ? $DB->count_records('external_tokens', ['userid' => $USER->id, 'externalserviceid' => $serviceid]) : 0;

if ($confirm) {
    // Only the button of this page, pressed by this user.
    if (!data_submitted() || !confirm_sesskey()) {
        throw new moodle_exception('invalidsesskey', 'error');
    }
    if ($serviceid) {
        issuer::delete_tokens((int) $USER->id, $serviceid);
    }
    echo $OUTPUT->header();
    echo $OUTPUT->heading(get_string('disconnecttitle', 'local_kseshpak'));
    echo $OUTPUT->notification(get_string('disconnectdone', 'local_kseshpak'), \core\output\notification::NOTIFY_SUCCESS);
    echo $OUTPUT->continue_button(new moodle_url('/'));
    echo $OUTPUT->footer();
    die;
}

echo $OUTPUT->header();
if (!$count) {
    echo $OUTPUT->heading(get_string('disconnecttitle', 'local_kseshpak'));
    echo $OUTPUT->notification(get_string('disconnectnone', 'local_kseshpak'), \core\output\notification::NOTIFY_INFO);
    echo $OUTPUT->continue_button(new moodle_url('/'));
} else {
    $off = new moodle_url('/local/kseshpak/disconnect.php', ['confirm' => 1, 'sesskey' => sesskey()]);
    echo $OUTPUT->confirm(get_string('disconnectwhat', 'local_kseshpak'), $off, new moodle_url('/'), [
        'confirmtitle' => get_string('disconnecttitle', 'local_kseshpak'),
        'continuestr' => get_string('disconnectbutton', 'local_kseshpak'),
    ]);
}
echo $OUTPUT->footer();
