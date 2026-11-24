# local_kseshpak — Moodle → KSE Shpak лише на читання, без копіювання посилань

[![Moodle plugin CI](https://github.com/valera777pl/moodle-local_kseshpak/actions/workflows/ci.yml/badge.svg)](https://github.com/valera777pl/moodle-local_kseshpak/actions/workflows/ci.yml)

*A Moodle local plugin that lets a student connect Moodle to KSE Shpak (a Telegram bot for KSE students) with a read-only web service key, without copying links. GPL v3.*

**Завантажити:** [Releases](https://github.com/valera777pl/moodle-local_kseshpak/releases) → **остання** версія → `local_kseshpak.zip`. Zip збирає GitHub Actions прямо з коду цього репозиторію, поруч лежить його SHA-256. Перед встановленням увесь код можна прочитати тут, у браузері: основне — [`connect.php`](connect.php), [`disconnect.php`](disconnect.php) і [`classes/issuer.php`](classes/issuer.php).

Кожну зміну автоматично перевіряють на справжньому Moodle 4.2, 4.5, 5.0, 5.1, 5.2 і 5.3 ([CI](https://github.com/valera777pl/moodle-local_kseshpak/actions/workflows/ci.yml)): синтаксис, стиль коду Moodle, документація, валідація плагіна, тести PHPUnit і Behat.

Плагін додає дві сторінки:
- `/local/kseshpak/connect.php` — студент підключає Moodle до KSE Shpak;
- `/local/kseshpak/disconnect.php` — студент сам вимикає цей доступ.

Як проходить підключення:
1. У KSE Shpak (Telegram) студент натискає «Підключити Moodle».
2. Відкривається сторінка плагіна в Moodle. Якщо студент не увійшов, Moodle веде його на вхід через Google (як `launch.php` з `oauthsso`).
3. Плагін показує, що саме отримає KSE Shpak і де це вимкнути, і чекає, поки студент натисне «Дозволити».
4. Moodle видає ключ **лише для сервісу `kse_shpak_readonly`**. Браузер студента передає ключ формою (POST) на адресу з налаштувань плагіна, і більше нікуди.

## Що плагін робить і чого не робить

- Видає ключ стандартною функцією Moodle `\core_external\util::generate_token()`. Термін дії такий самий, як у ядра (`tokenduration`, за замовчуванням 12 тижнів). Якщо в студента вже є дійсний ключ, плагін повертає його. Ключ, якому лишилося менше 30 днів, плагін замінює новим, а старий видаляє.
- **Не видає ключ взагалі**, якщо в сервісі `kse_shpak_readonly`:
  - є хоч одна функція, яка в Moodle не позначена як `read`;
  - є хоч одна функція, якої немає в переліку нижче (навіть та, що лише читає: повідомлення, чужі профілі);
  - увімкнено завантаження чи вивантаження файлів.

  Додати туди функцію запису чи зайву функцію читання не вийде навіть випадково.
- **Не видає ключ тим, хто бачить чужі оцінки:**
  - адміністраторам сайту;
  - менеджерам;
  - викладачам і асистентам хоча б одного курсу (будь-хто з `moodle/grade:viewall` у системі чи в курсі).

  Ті самі функції дали б такому ключу оцінки їхніх студентів. Ключ отримують лише ті, хто бачить тільки свої оцінки.

  **Обмеження:** це перевірка в момент видачі. Moodle перевіряє права на кожен виклик, тож студент, якого потім зробили асистентом чи викладачем, своїм уже виданим ключем бачитиме й чужі оцінки, поки ключ не закінчиться (за замовчуванням 12 тижнів). Надійний спосіб: після призначення такої ролі видалити ключі цього користувача (*Manage tokens*) або попросити його скористатися сторінкою «Вимкнути» нижче. Наш сервер читає лише власні дані студента, але ключ, який витік би з нашої бази, мав би ширші права.
- Не видає ключ у режимі «Увійти як» (Log in as), гостю й заблокованому користувачу.
- Студентам **не потрібне** право `moodle/webservice:createtoken`. Плагін має власне право `local/kseshpak:connect`, і воно дає ключ лише для `kse_shpak_readonly`.
- Не створює таблиць, не змінює файлів Moodle, не бачить паролів і нічого не зберігає сам. Ключ зберігає ядро Moodle в `external_tokens`.

## Налаштування в Moodle (приблизно 20 хвилин)

**1. Сервіс.** Site administration → Server → Web services → External services → *Add*:

| Поле | Значення |
|---|---|
| Name | KSE Shpak (read-only) |
| Short name | `kse_shpak_readonly` |
| Enabled | так |
| Authorised users only | ні |
| Required capability | порожньо |
| Can upload files / Can download files | **ні** (з «так» плагін ключів не видає) |

**2. Функції сервісу:** *Functions* → *Add functions*. Ці 10 потрібні, і всі вони лише читають:

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
```

На Moodle 4.x функції `mod_quiz_get_user_quiz_attempts` ще немає (вона з'явилася в Moodle 5.0). Замість неї додайте `mod_quiz_get_user_attempts`.

Плагін також дозволяє, але не вимагає, `core_group_get_course_user_groups` і `mod_quiz_get_user_attempts` (на 5.x разом із новою функцією). Будь-яка інша функція в сервісі: плагін не видає ключ.

Навіщо кожна:
- профіль студента;
- його курси;
- оцінки: підсумки й по завданнях;
- дедлайни з календаря;
- завдання і чи здано;
- тести і спроби;
- матеріали курсу: лише посилання на сторінки Moodle, наш сервер файли не завантажує.

Про файли чесно: будь-який ключ веб-сервісу Moodle дає виклик `core_webservice_get_site_info`, який повертає ще й приватний ключ користувача для файлів (`userprivateaccesskey`, його ж видає застосункові Moodle). З ним `/tokenpluginfile.php` віддає файли курсів цього студента, навіть якщо в сервісі вимкнено «Can download files». Це файли, які студент і так бачить у Moodle. Сторінка «Вимкнути» цей приватний ключ не видаляє, бо він спільний з мобільним застосунком Moodle.

Moodle перевіряє кожну функцію правами власника ключа. Для студента це лише його власні курси, оцінки й здачі (про зміну ролі після видачі: див. «Що плагін робить»).

Групові події календаря (подія тільки для групи студента): ключ може їх прочитати для груп самого студента, якщо в запиті передати ці групи. KSE Shpak їх не запитує: у застосунок потрапляють події курсу, завдання, тести й дати зі сторінок курсу.

**3. Встановити плагін** одним зі способів:
- Site administration → Plugins → *Install plugins* → завантажити `local_kseshpak.zip`;
- або розпакувати папку `kseshpak` у `local/` (вийде `local/kseshpak`; на Moodle 5.1+ — `public/local/kseshpak`) і відкрити Site administration → *Notifications*.

**4. Налаштування плагіна.** Після встановлення Moodle покаже сторінку нових налаштувань: натисніть там *Save changes*. Потім перевірте в Site administration → Plugins → Local plugins → *KSE Shpak*, що адреса повернення `https://kse-hub.vercel.app/api/moodle-plugin/return` стоїть у полі, і більше нічого міняти не треба.

Якщо сторінку нових налаштувань пропустили, плагін все одно бере цю типову адресу, поки в полі нічого не збережено. Але якщо відкрити налаштування й натиснути *Save changes* з порожнім полем, збережеться порожній рядок, і це означає «вимкнено».

**5. Права.** Перевірте, що в ролі *Authenticated user* дозволено `webservice/rest:use`: Site administration → Users → Permissions → Define roles → Authenticated user. Без цього права ключ не працюватиме, і плагін так і напише.

Moodle сам дає це право ролі *Authenticated user*, коли вмикають «Enable mobile web services», але при вимкненні його не забирає. Тож якщо право хтось прибрав вручну (чи його ніколи не було), дайте його вручну. `local/kseshpak:connect` після встановлення є в *Authenticated user* за замовчуванням.

**6. Cloudflare.** Сервер KSE Shpak (Vercel, без фіксованої IP-адреси) читає Moodle через `/webservice/rest/server.php`, а для студентів, які ще підключили Moodle старим способом (посилання на експорт календаря), ще й через `/calendar/export_execute.php`. Якщо teaching.kse.org.ua стоїть за Cloudflare, без винятку Cloudflare може відповідати серверу 403 («Just a moment...»), і жоден ключ не запрацює.

Спершу з'ясуйте, що саме зупиняє запит: в Cloudflare Security → Events знайдіть запит до `/webservice/rest/server.php` чи `/calendar/export_execute.php` (наш сервер пише у своїх логах `cf-ray`, його можна передати) і подивіться, яке правило спрацювало.

Винятки, які Cloudflare підтримує (перевірено за документацією Cloudflare на 2026-11-23):
- **Bot Fight Mode (безкоштовний план) пропустити правилом неможливо**: «You cannot bypass or skip Bot Fight Mode using WAF custom rules». Якщо зупиняє саме він, його доведеться вимкнути для сайту або перейти на план із Super Bot Fight Mode.
- **Super Bot Fight Mode** (плани Pro і вище), **Managed rules**, **Security level**, **Browser Integrity Check**: правило WAF Security → WAF → Custom rules, дія **Skip** (обрати відповідні пункти), для запитів, де виконуються обидві умови:
  - шлях `/webservice/rest/server.php` або `/calendar/export_execute.php` (другий потрібен лише тим, хто підключив календар посиланням: без нього їхні дедлайни не оновлюються, ключів плагіна це не стосується);
  - заголовок `X-KSE-Hub` дорівнює секрету, який розробник передасть окремо.

Без секрету правило нічого не відкриває. Самі запити захищені ключем кожного студента. Перевірити, чи спрацювало, можна кроком «Перевірка» нижче: він називає Cloudflare, якщо 403 лишився.

## Перевірка

Увійдіть **студентським акаунтом**. Адміністратор, менеджер чи викладач ключ не отримає. Відкрийте:

```
https://teaching.kse.org.ua/local/kseshpak/connect.php?state=check.only.test
```

Має відкритися сторінка «Підключити Moodle до KSE Shpak» з кнопкою «Дозволити». Після «Дозволити» браузер перейде на сторінку KSE Shpak:
- **«Налаштовано правильно ✓»** — ключ лише на читання, усі функції на місці, і сервер KSE Shpak дістається до Moodle. Можна казати розробнику, що готово.
- **«Ще не все налаштовано»** — там буде написано, що саме:
  - яких функцій бракує чи які зайві;
  - чи дозволено файли;
  - чи Cloudflare не пускає сервер (крок 6).

Перевірочне посилання нічого не підключає й ключ не зберігає: KSE Shpak лише дивиться, які функції він дає. Ключ «KSE Shpak (read-only)» з'явиться в Manage tokens (Site administration → Server → Web services), його можна видалити.

Якщо щось налаштовано не так ще в самому Moodle, плагін не покаже загальну помилку. Він напише, що саме змінити: немає сервісу, сервіс вимкнено, у сервісі є функція запису чи дозволено файли, вимкнено REST, немає `webservice/rest:use`.

## Як вимкнути

- **Студент сам:** `https://teaching.kse.org.ua/local/kseshpak/disconnect.php` → «Вимкнути». Ключ видаляється одразу. Посилання є на сторінці згоди.

  У «Налаштування → Ключі безпеки» студент цього ключа не побачить: Moodle показує там ключі лише тим, хто має `moodle/webservice:createtoken`.
- **Ключ одного студента (IT):** Site administration → Server → Web services → *Manage tokens*.
- **Нові підключення:** очистити адресу повернення в налаштуваннях плагіна. Уже видані ключі при цьому працюють далі.
- **Усі ключі одразу:** вимкнути сервіс `kse_shpak_readonly` або видалити ключі сервісу в Manage tokens. KSE Shpak тоді відʼєднає Moodle в студентів; після повторного ввімкнення вони підключать його знову. Видалення плагіна саме по собі ключів не видаляє.

## Файли

| Файл | Що в ньому |
|---|---|
| `version.php` | назва й версія |
| `connect.php` | сторінка: вхід → «Дозволити» → повернення в KSE Shpak |
| `disconnect.php` | сторінка: студент вимикає доступ KSE Shpak |
| `classes/issuer.php` | перевірки, видача й видалення ключа |
| `settings.php` | одне поле: адреса повернення |
| `db/access.php` | право `local/kseshpak:connect` |
| `classes/privacy/provider.php` | що залишає Moodle (Privacy API) |
| `lang/en`, `lang/uk` | тексти |
| `tests/` | PHPUnit і Behat; на кожну зміну проганяються на справжньому Moodle 4.2–5.3 |
| `LICENSE` | GNU GPL v3, як і сам Moodle |
