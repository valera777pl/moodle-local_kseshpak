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
 * English strings for local_kseshpak.
 *
 * @package    local_kseshpak
 * @copyright  2026 KSE Shpak
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$string['allow'] = 'Allow';
$string['badstate'] = 'This link is broken or incomplete. Open the connection again from KSE Shpak.';
$string['consentrevoke'] = 'You can turn it off at any time: in KSE Shpak, or here in Moodle at {$a}.';
$string['consenttitle'] = 'Connect Moodle to KSE Shpak';
$string['consentwhat'] = 'KSE Shpak will be able to read your courses and their materials, your grades, deadlines, assignments and quiz attempts. It will not be able to submit work, send messages or change anything in Moodle.';
$string['consentwho'] = 'You are signed in as {$a}.';
$string['disconnectbutton'] = 'Turn off';
$string['disconnectdone'] = 'Done: KSE Shpak can no longer read your Moodle. To connect again, use «Connect Moodle» in KSE Shpak.';
$string['disconnectnone'] = 'KSE Shpak has no access to your Moodle: there is nothing to turn off.';
$string['disconnecttitle'] = 'Turn off KSE Shpak\'s access to Moodle';
$string['disconnectwhat'] = 'KSE Shpak will no longer be able to read your Moodle: its read-only key is deleted at once.';
$string['kseshpak:connect'] = 'Connect Moodle to KSE Shpak with a read-only key';
$string['noadmins'] = 'Site administrators don\'t get a key this way: their key would read everyone\'s data. Use a student account.';
$string['nologinas'] = 'You are logged in as another user. Only that user can connect their own Moodle.';
$string['norestuse'] = 'Your account may not use the REST web service: the role "Authenticated user" needs the capability webservice/rest:use.';
$string['nostaff'] = 'Your account can see other people\'s grades (as a teacher, assistant or manager), so its key would open their data too. KSE Shpak connects only accounts that see their own grades.';
$string['notconfigured'] = 'The KSE Shpak plugin is not set up: its return address is empty or does not start with https:// (Site administration → Plugins → Local plugins → KSE Shpak).';
$string['pluginname'] = 'KSE Shpak';
$string['privacy:metadata:kseshpak'] = 'To connect Moodle to KSE Shpak, the user\'s read-only web service key is sent to KSE Shpak, which uses it to read the user\'s own courses, grades and deadlines.';
$string['privacy:metadata:kseshpak:state'] = 'An opaque value from KSE Shpak that tells which KSE Shpak account started the connection.';
$string['privacy:metadata:kseshpak:token'] = 'The user\'s web service key for the read-only service kse_shpak_readonly.';
$string['restdisabled'] = 'The REST protocol is turned off (Site administration → Server → Web services → Manage protocols).';
$string['returnbutton'] = 'Return to KSE Shpak';
$string['returning'] = 'Returning to KSE Shpak…';
$string['returnurl'] = 'Return address';
$string['returnurl_desc'] = 'The KSE Shpak page that receives the read-only key. Only https:// addresses work. Clear it to stop new connections. Keys already issued keep working until they are deleted (Manage tokens).';
$string['serviceempty'] = 'The web service "{$a}" has no functions (Site administration → Server → Web services → External services → Functions).';
$string['serviceextrafunctions'] = 'The web service "kse_shpak_readonly" has functions that KSE Shpak does not use: {$a}. Remove them (README, step 2): this plugin issues keys only for the listed functions.';
$string['servicefiles'] = 'The web service "{$a}" may upload or download files. Turn off "Can upload files" and "Can download files" (Site administration → Server → Web services → External services → Edit).';
$string['servicemissing'] = 'The web service "{$a}" does not exist or is disabled (Site administration → Server → Web services → External services).';
$string['servicenotreadonly'] = 'The web service "kse_shpak_readonly" has functions that do more than read: {$a}. Remove them: this plugin issues read-only keys only.';
