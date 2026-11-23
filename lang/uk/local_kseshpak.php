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
 * Ukrainian strings for local_kseshpak.
 *
 * @package    local_kseshpak
 * @copyright  2026 KSE Shpak
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$string['allow'] = 'Дозволити';
$string['badstate'] = 'Посилання пошкоджене або неповне. Відкрий підключення ще раз з KSE Shpak.';
$string['consentrevoke'] = 'Вимкнути можна будь-коли: у KSE Shpak або в Moodle → Налаштування → Ключі безпеки.';
$string['consenttitle'] = 'Підключити Moodle до KSE Shpak';
$string['consentwhat'] = 'KSE Shpak зможе читати твої курси, оцінки, дедлайни завдань і спроби тестів. Здавати роботи, писати повідомлення чи щось змінювати в Moodle він не зможе.';
$string['consentwho'] = 'Вхід виконано як {$a}.';
$string['kseshpak:connect'] = 'Підключати Moodle до KSE Shpak ключем лише на читання';
$string['noadmins'] = 'Адміністратори сайту так ключ не отримують: їхній ключ читав би дані всіх. Скористайтеся студентським акаунтом.';
$string['norestuse'] = 'Цьому акаунту не можна користуватися REST веб-сервісом: ролі «Authenticated user» потрібне право webservice/rest:use.';
$string['notconfigured'] = 'Плагін KSE Shpak не налаштований: адреса повернення порожня або не починається з https:// (Site administration → Plugins → Local plugins → KSE Shpak).';
$string['pluginname'] = 'KSE Shpak';
$string['privacy:metadata:kseshpak'] = 'Щоб підключити Moodle до KSE Shpak, ключ веб-сервісу користувача лише на читання передається в KSE Shpak, який читає ним курси, оцінки й дедлайни самого користувача.';
$string['privacy:metadata:kseshpak:state'] = 'Службове значення від KSE Shpak: з якого акаунта KSE Shpak почали підключення.';
$string['privacy:metadata:kseshpak:token'] = 'Ключ користувача для веб-сервісу лише на читання kse_shpak_readonly.';
$string['restdisabled'] = 'Протокол REST вимкнено (Site administration → Server → Web services → Manage protocols).';
$string['returnbutton'] = 'Повернутися в KSE Shpak';
$string['returning'] = 'Повертаємось у KSE Shpak…';
$string['returnurl'] = 'Адреса повернення';
$string['returnurl_desc'] = 'Сторінка KSE Shpak, яка отримує ключ лише на читання. Працюють лише адреси https://. Очистіть поле, щоб вимкнути плагін.';
$string['serviceempty'] = 'У веб-сервісі «{$a}» немає функцій (Site administration → Server → Web services → External services → Functions).';
$string['servicemissing'] = 'Веб-сервіс «{$a}» не існує або вимкнений (Site administration → Server → Web services → External services).';
$string['servicenotreadonly'] = 'У веб-сервісі «kse_shpak_readonly» є функції, які не лише читають: {$a}. Приберіть їх: плагін видає лише ключі на читання.';
