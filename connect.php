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
 * Connect Moodle to KSE Shpak: sign in, agree, and go back to KSE Shpak with a read-only key.
 *
 * 1. KSE Shpak opens /local/kseshpak/connect.php?state=…[&sso=<issuer id>].
 * 2. Not signed in: Moodle's own sign-in (sso= jumps straight to that OAuth 2 issuer, as launch.php's oauthsso= does).
 * 3. The user sees what KSE Shpak gets and presses "Allow" (POST with sesskey).
 * 4. The key for the kse_shpak_readonly service is sent to the return address from the settings, by a form
 *    posted from the user's own browser, together with the state. Nothing is stored by this plugin.
 *
 * @package    local_kseshpak
 * @copyright  2026 KSE Shpak
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require(__DIR__ . '/../../config.php');

use local_kseshpak\issuer;

$state = required_param('state', PARAM_RAW_TRIMMED);
$sso = optional_param('sso', 0, PARAM_INT);
$confirm = optional_param('confirm', 0, PARAM_BOOL);

$url = new moodle_url('/local/kseshpak/connect.php', ['state' => $state]);
$PAGE->set_url($url);
$PAGE->set_context(context_system::instance());
$PAGE->set_pagelayout('login');
$PAGE->set_cacheable(false);
$PAGE->set_title(get_string('consenttitle', 'local_kseshpak'));

if (!issuer::valid_state($state)) {
    throw new moodle_exception('badstate', 'local_kseshpak');
}

// Straight to the Google sign-in, the way admin/tool/mobile/launch.php does it with oauthsso=.
if ($sso && (!isloggedin() || isguestuser()) && is_enabled_auth('oauth2')) {
    $oauthurl = new moodle_url('/auth/oauth2/login.php', ['id' => $sso, 'sesskey' => sesskey(), 'wantsurl' => $url->out(false)]);
    redirect($oauthurl);
}

require_login(null, false);
if (isguestuser()) {
    throw new moodle_exception('guestsarenotallowed', 'error');
}

// The site's setup is checked before the user is asked anything.
$service = issuer::service();
$returnurl = issuer::return_url();
issuer::require_can_connect($service);

if (!$confirm) {
    $allow = new moodle_url('/local/kseshpak/connect.php', ['state' => $state, 'confirm' => 1, 'sesskey' => sesskey()]);
    $message = html_writer::tag('p', get_string('consentwho', 'local_kseshpak', s(fullname($USER))))
        . html_writer::tag('p', get_string('consentwhat', 'local_kseshpak'))
        . html_writer::tag('p', get_string('consentrevoke', 'local_kseshpak'));

    echo $OUTPUT->header();
    echo $OUTPUT->confirm($message, $allow, new moodle_url('/'), [
        'confirmtitle' => get_string('consenttitle', 'local_kseshpak'),
        'continuestr' => get_string('allow', 'local_kseshpak'),
    ]);
    echo $OUTPUT->footer();
    die;
}

// Only the "Allow" button of this site, pressed by this user, gets here.
if (!data_submitted() || !confirm_sesskey()) {
    throw new moodle_exception('invalidsesskey', 'error');
}

$token = issuer::issue_token($service);

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('returning', 'local_kseshpak'));
echo html_writer::start_tag('form', ['method' => 'post', 'action' => $returnurl, 'id' => 'local_kseshpak_return']);
echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'token', 'value' => $token]);
echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'state', 'value' => $state]);
echo html_writer::tag('button', get_string('returnbutton', 'local_kseshpak'), ['type' => 'submit', 'class' => 'btn btn-primary']);
echo html_writer::end_tag('form');
// Sent at once; the button stays for a browser without JavaScript.
echo html_writer::script("document.getElementById('local_kseshpak_return').submit();");
echo $OUTPUT->footer();
