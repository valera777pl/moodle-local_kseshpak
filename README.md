# local_kseshpak — Moodle → KSE Shpak лише на читання, без копіювання посилань

[![Moodle plugin CI](https://github.com/valera777pl/moodle-local_kseshpak/actions/workflows/ci.yml/badge.svg)](https://github.com/valera777pl/moodle-local_kseshpak/actions/workflows/ci.yml)

*A Moodle local plugin that lets a student connect Moodle to KSE Shpak (a Telegram bot for KSE students) with a read-only web service key, without copying links. GPL v3.*

**Завантажити:** [Releases](https://github.com/valera777pl/moodle-local_kseshpak/releases) → `local_kseshpak.zip`. Zip збирає GitHub Actions прямо з коду цього репозиторію, поруч лежить його SHA-256. Перед встановленням увесь код можна прочитати тут, у браузері: основне — [`connect.php`](connect.php) і [`classes/issuer.php`](classes/issuer.php).

Кожну зміну автоматично перевіряють на справжньому Moodle 4.2 і 4.5 ([CI](https://github.com/valera777pl/moodle-local_kseshpak/actions/workflows/ci.yml)): синтаксис, стиль коду Moodle, документація, валідація плагіна, тести PHPUnit і Behat.

Плагін для Moodle KSE (4.2, перевірено також на 4.5). Він додає одну сторінку, `/local/kseshpak/connect.php`, на якій студент підключає Moodle до KSE Shpak:

1. У KSE Shpak (Telegram) студент натискає «Підключити Moodle».
2. Відкривається сторінка плагіна в Moodle. Якщо студент не увійшов, Moodle веде його на вхід через Google (як `launch.php` з `oauthsso`).
3. Плагін показує, що саме отримає KSE Shpak, і чекає, поки студент натисне «Дозволити».
4. Moodle видає ключ **лише для сервісу `kse_shpak_readonly`**. Браузер студента передає ключ формою (POST) на адресу з налаштувань плагіна, і більше нікуди.

## Що плагін робить і чого не робить

- Видає ключ стандартною функцією Moodle `\core_external\util::generate_token()`. Термін дії такий самий, як у ядра (`tokenduration`, за замовчуванням 12 тижнів). Якщо в студента вже є дійсний ключ, плагін повертає його, а не робить другий.
- **Не видає ключ взагалі**, якщо в сервісі `kse_shpak_readonly` є хоч одна функція, яка в Moodle не позначена як `read`. Додати туди функцію запису не вийде навіть випадково.
- **Не видає ключ адміністраторам сайту**, як і ядро Moodle: такий ключ читав би дані всіх.
- Студентам **не потрібне** право `moodle/webservice:createtoken`. Плагін має власне право `local/kseshpak:connect`, і воно дає ключ лише для `kse_shpak_readonly`.
- Не створює таблиць, не змінює файлів Moodle, не бачить паролів і нічого не зберігає сам. Ключ зберігає ядро Moodle в `external_tokens`.
- Ключ діє з правами самого студента: його курси, його оцінки, його дедлайни.

## Налаштування в Moodle (приблизно 15 хвилин)

**1. Сервіс.** Site administration → Server → Web services → External services → *Add*:

| Поле | Значення |
|---|---|
| Name | KSE Shpak (read-only) |
| Short name | `kse_shpak_readonly` |
| Enabled | так |
| Authorised users only | ні |
| Required capability | порожньо |
| Can upload files / Can download files | ні |

**2. Функції сервісу:** *Functions* → *Add functions*. Рівно ці 11, і всі вони лише читають:

```
core_webservice_get_site_info
core_enrol_get_users_courses
gradereport_overview_get_course_grades
gradereport_user_get_grade_items
core_calendar_get_calendar_events
mod_assign_get_assignments
mod_assign_get_submission_status
mod_quiz_get_quizzes_by_courses
mod_quiz_get_user_quiz_attempts
core_course_get_contents
core_group_get_course_user_groups
```

На Moodle 4.x замість `mod_quiz_get_user_quiz_attempts` (з'явилася в Moodle 5) додайте `mod_quiz_get_user_attempts`.

Навіщо кожна: профіль студента; його курси; оцінки (підсумки й по завданнях); дедлайни з календаря; завдання і чи здано; тести і спроби; матеріали курсу (лише посилання на сторінки Moodle, файли через ключ не завантажуються); групи самого студента, щоб показувати лише квізи його групи. Усі функції Moodle перевіряє правами самого студента: чужих оцінок чи груп ключ не відкриє.

**3. Встановити плагін** одним зі способів:
- Site administration → Plugins → *Install plugins* → завантажити `local_kseshpak.zip`;
- або розпакувати папку `kseshpak` у `local/` (вийде `local/kseshpak`) і відкрити Site administration → *Notifications*.

**4. Налаштування плагіна:** Site administration → Plugins → Local plugins → *KSE Shpak*. Адреса повернення вже стоїть: `https://kse-hub.vercel.app/api/moodle-plugin/return`. Нічого міняти не треба.

**5. Права:** нічого міняти не треба.
- `webservice/rest:use` у ролі *Authenticated user* уже є, бо в KSE увімкнено «Enable mobile web services» і працює застосунок Moodle. Якщо цього права немає, плагін так і напише.
- `local/kseshpak:connect` після встановлення є в *Authenticated user* за замовчуванням.

## Перевірка

Увійдіть **студентським акаунтом** (адміністратор ключ не отримає) і відкрийте:

```
https://teaching.kse.org.ua/local/kseshpak/connect.php?state=check.only.test
```

Має відкритися сторінка «Підключити Moodle до KSE Shpak» з кнопкою «Дозволити». Після «Дозволити» браузер перейде на сторінку KSE Shpak:
- **«Налаштовано правильно ✓»** — ключ лише на читання, усі функції на місці. Можна казати розробнику, що готово;
- **«Ще не все налаштовано»** — там буде написано, яких функцій бракує або які зайві.

Перевірочне посилання нічого не підключає й ключ не зберігає: KSE Shpak лише дивиться, які функції він дає. Ключ «KSE Shpak (read-only)» з'явиться в Manage tokens (Site administration → Server → Web services), його можна видалити.

Якщо щось налаштовано не так, плагін не покаже загальну помилку, а напише, що саме змінити: немає сервісу, сервіс вимкнено, у сервісі є функція запису, вимкнено REST тощо.

## Як вимкнути

- **Ключ одного студента:** Site administration → Server → Web services → *Manage tokens*. Студент може й сам: Налаштування → Ключі безпеки.
- **Усе одразу:** вимкнути сервіс `kse_shpak_readonly`, або очистити адресу повернення в налаштуваннях плагіна, або видалити плагін (Plugins overview → Uninstall).

## Файли

| Файл | Що в ньому |
|---|---|
| `version.php` | назва й версія |
| `connect.php` | сторінка: вхід → «Дозволити» → повернення в KSE Shpak |
| `classes/issuer.php` | перевірки й видача ключа |
| `settings.php` | одне поле: адреса повернення |
| `db/access.php` | право `local/kseshpak:connect` |
| `classes/privacy/provider.php` | що залишає Moodle (Privacy API) |
| `lang/en`, `lang/uk` | тексти |
| `tests/` | PHPUnit і Behat; на кожну зміну проганяються на справжньому Moodle 4.2 і 4.5 |
| `LICENSE` | GNU GPL v3, як і сам Moodle |
