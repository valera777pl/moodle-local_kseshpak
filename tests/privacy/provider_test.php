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

/**
 * Privacy provider tests.
 *
 * @package    local_kseshpak
 * @category   test
 * @copyright  2026 KSE Shpak
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_kseshpak\privacy\provider
 */
final class provider_test extends \advanced_testcase {
    public function test_metadata_names_what_leaves_moodle(): void {
        $collection = provider::get_metadata(new collection('local_kseshpak'));
        $items = $collection->get_collection();
        $this->assertCount(1, $items);
        $this->assertSame('kseshpak', $items[0]->get_name());
        $this->assertEqualsCanonicalizing(['state', 'token'], array_keys($items[0]->get_privacy_fields()));
        // Every string the privacy page shows exists.
        foreach (array_merge([$items[0]->get_summary()], array_values($items[0]->get_privacy_fields())) as $identifier) {
            $this->assertTrue(get_string_manager()->string_exists($identifier, 'local_kseshpak'), $identifier);
        }
    }

    public function test_nothing_is_stored(): void {
        $this->assertCount(0, provider::get_contexts_for_userid(2)->get_contextids());
    }
}
