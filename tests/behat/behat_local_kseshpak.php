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
 * Behat steps for local_kseshpak.
 *
 * @package    local_kseshpak
 * @category   test
 * @copyright  2026 KSE Shpak
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

// NOTE: no MOODLE_INTERNAL test here, this file may be required by behat before including /config.php.

require_once(__DIR__ . '/../../../../lib/behat/behat_base.php');

use Behat\Mink\Exception\ExpectationException;

/**
 * Behat steps for local_kseshpak.
 *
 * @package    local_kseshpak
 * @category   test
 * @copyright  2026 KSE Shpak
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class behat_local_kseshpak extends behat_base {
    /**
     * The page posts back to the configured address with this user's key for kse_shpak_readonly and this state.
     *
     * @Then the KSE Shpak return form carries the key of :username and the state :state
     * @param string $username
     * @param string $state
     */
    public function the_return_form_carries(string $username, string $state): void {
        global $DB;

        $form = $this->find('css', 'form#local_kseshpak_return');
        $action = $form->getAttribute('action');
        if ($action !== get_config('local_kseshpak', 'returnurl')) {
            throw new ExpectationException("The form posts to '$action'", $this->getSession());
        }
        if (strtolower((string) $form->getAttribute('method')) !== 'post') {
            throw new ExpectationException('The form is not sent by POST', $this->getSession());
        }
        $key = $this->find('css', 'form#local_kseshpak_return input[name="token"]')->getValue();
        $sentstate = $this->find('css', 'form#local_kseshpak_return input[name="state"]')->getValue();
        if ($sentstate !== $state) {
            throw new ExpectationException("The form carries the state '$sentstate'", $this->getSession());
        }
        $userid = $DB->get_field('user', 'id', ['username' => $username], MUST_EXIST);
        $serviceid = $DB->get_field('external_services', 'id', ['shortname' => 'kse_shpak_readonly'], MUST_EXIST);
        if (!$DB->record_exists('external_tokens', ['token' => $key, 'userid' => $userid, 'externalserviceid' => $serviceid])) {
            throw new ExpectationException("The form doesn't carry $username's key for kse_shpak_readonly", $this->getSession());
        }
    }

    /**
     * How many keys the user has, of any service.
     *
     * @Then :username has :count KSE Shpak key(s)
     * @param string $username
     * @param int $count
     */
    public function user_has_keys(string $username, int $count): void {
        global $DB;

        $userid = $DB->get_field('user', 'id', ['username' => $username], MUST_EXIST);
        $actual = $DB->count_records('external_tokens', ['userid' => $userid]);
        if ($actual !== $count) {
            throw new ExpectationException("$username has $actual keys", $this->getSession());
        }
    }
}
