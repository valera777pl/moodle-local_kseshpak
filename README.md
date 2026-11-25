# local_kseshpak — Moodle → KSE Shpak лише на читання, без копіювання посилань

[![Moodle plugin CI](https://github.com/valera777pl/moodle-local_kseshpak/actions/workflows/ci.yml/badge.svg)](https://github.com/valera777pl/moodle-local_kseshpak/actions/workflows/ci.yml)

*A Moodle local plugin that lets a student connect Moodle to KSE Shpak (a Telegram bot for KSE students) with a read-only web service key, without copying links. GPL v3.*

## Спершу подивіться код

Увесь плагін — це кілька невеликих файлів, їх можна прочитати тут, у браузері, нічого не завантажуючи. Код саме версії 2.1.3: [tag v2.1.3](https://github.com/valera777pl/moodle-local_kseshpak/tree/v2.1.3).

| Файл | Що в ньому | Рядків |
|---|---|---|
| [`connect.php`](connect.php) | сторінка підключення: вхід → згода → «Дозволити» → ключ іде формою на адресу з налаштувань | ~135 |
| [`disconnect.php`](disconnect.php) | сторінка, де студент сам видаляє свій ключ | ~80 |
| [`classes/issuer.php`](classes/issuer.php) | усі перевірки (сервіс лише на читання, лише потрібні функції, без файлів, без викладачів і адмінів) і видача ключа стандартною функцією ядра | ~360 |
| [`settings.php`](settings.php) | два налаштування: адреса повернення і термін дії ключа | ~45 |
| [`db/access.php`](db/access.php) | одне право `local/kseshpak:connect` | ~40 |
| [`db/install.php`](db/install.php), [`db/upgrade.php`](db/upgrade.php) | записують налаштування за замовчуванням | ~40 |
| [`classes/privacy/provider.php`](classes/privacy/provider.php) | Privacy API: що передається назовні | ~100 |
| [`lang/en`](lang/en/local_kseshpak.php), [`lang/uk`](lang/uk/local_kseshpak.php) | тексти сторінок англійською й українською | ~60 |
| [`tests/`](tests) | тести PHPUnit і Behat, які проганяє CI | — |

Плагін не створює таблиць, не має зовнішніх бібліотек, нічого не завантажує з інтернету й нікуди не звертається сам: ключ передає браузер студента після «Дозволити». JavaScript — один рядок у `connect.php`, який сам надсилає форму повернення (кнопка поруч робить те саме).

## Завантажити

[Releases](https://github.com/valera777pl/moodle-local_kseshpak/releases/latest) → у розділі *Assets* файл **`local_kseshpak.zip`**. Саме його завантажуєте в Moodle (Site administration → Plugins → *Install plugins*).

Не беріть *Source code (zip)* / *Source code (tar.gz)*: ці архіви GitHub додає до кожної версії сам, і папка всередині називається `moodle-local_kseshpak-…`, а Moodle чекає `kseshpak`.

Zip збирає GitHub Actions прямо з коду цього репозиторію за тегом версії ([workflow](.github/workflows/release.yml)), поруч лежить його SHA-256. Щоб переконатися, що в zip той самий код: розпакуйте його й порівняйте з тегом версії (`git clone`, `git checkout v2.1.3`, `diff -r`).

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

- Видає ключ стандартною функцією Moodle `\core_external\util::generate_token()`. Ключ діє **рік** (налаштування плагіна «Термін дії ключа»; 0 там означає термін самого сайту, `tokenduration`). Раз на рік студент підключається знову одним натиском, KSE Shpak нагадує про це заздалегідь. Якщо в студента вже є дійсний ключ, плагін повертає його. Ключ, якому лишилося менше 30 днів, плагін замінює новим, а старий видаляє.
- **Не видає ключ взагалі**, якщо в сервісі `kse_shpak_readonly`:
  - є хоч одна функція, яка в Moodle не позначена як `read`;
  - є хоч одна функція, якої немає в переліку нижче (навіть та, що лише читає: повідомлення, чужі профілі);
  - увімкнено завантаження чи вивантаження файлів.

  Додати туди функцію запису чи зайву функцію читання не вийде навіть випадково.
- **Не видає ключ тим, хто бачить чужі оцінки:**
  - адміністраторам сайту;
  - менеджерам;
  - викладачам і асистентам хоча б одного курсу, категорії чи навіть одного завдання чи тесту (будь-хто з `moodle/grade:viewall`, `mod/assign:grade`, `mod/assign:viewgrades`, `mod/quiz:viewreports` чи `mod/quiz:grade` будь-де);
  - батькам і менторам (будь-яка роль у контексті іншого користувача).

  Ті самі функції дали б такому ключу оцінки їхніх студентів. Ключ отримують лише ті, хто бачить тільки свої оцінки.

  **Обмеження:** це перевірка в момент видачі. Moodle перевіряє права на кожен виклик, тож студент, якого потім зробили асистентом чи викладачем, своїм уже виданим ключем бачитиме й чужі оцінки, поки ключ не закінчиться (до року). Надійний спосіб: після призначення такої ролі видалити ключі цього користувача (*Manage tokens*) або попросити його скористатися сторінкою «Вимкнути» нижче. Наш сервер читає лише власні дані студента, але ключ, який витік би з нашої бази, мав би ширші права.
- Не видає ключ у режимі «Увійти як» (Log in as), гостю й заблокованому користувачу.
- Студентам **не потрібне** право `moodle/webservice:createtoken`. Плагін має власне право `local/kseshpak:connect`, і воно дає ключ лише для `kse_shpak_readonly`.
- Не створює таблиць, не змінює файлів Moodle, не бачить паролів і нічого не зберігає сам, крім двох своїх налаштувань (адреса повернення, термін ключа). Ключ зберігає ядро Moodle в `external_tokens`.

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

**2. Функції сервісу:** *Functions* → *Add functions*. Ці 11 потрібні, і всі вони лише читають:

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

На Moodle 4.x функції `mod_quiz_get_user_quiz_attempts` ще немає (вона з'явилася в Moodle 5.0). Замість неї додайте `mod_quiz_get_user_attempts`.

Плагін також дозволяє `mod_quiz_get_user_attempts` на 5.x разом із новою функцією. Будь-яка інша функція в сервісі: плагін не видає ключ.

Навіщо кожна:
- профіль студента;
- його курси;
- оцінки: підсумки й по завданнях;
- дедлайни з календаря;
- групи студента: щоб читати події календаря лише для його груп;
- завдання і чи здано;
- тести і спроби;
- матеріали курсу: лише посилання на сторінки Moodle, наш сервер файли не завантажує.

Про файли чесно: будь-який ключ веб-сервісу Moodle дає виклик `core_webservice_get_site_info`, який повертає ще й приватний ключ користувача для файлів (`userprivateaccesskey`, його ж видає застосункові Moodle). З ним `/tokenpluginfile.php` віддає файли курсів цього студента, навіть якщо в сервісі вимкнено «Can download files». Це файли, які студент і так бачить у Moodle. Сторінка «Вимкнути» цей приватний ключ не видаляє, бо він спільний з мобільним застосунком Moodle.

Moodle перевіряє кожну функцію правами власника ключа. Для студента це лише його власні курси, оцінки й здачі (про зміну ролі після видачі: див. «Що плагін робить»).

Групові події календаря (подія тільки для групи студента): KSE Shpak дізнається групи самого студента через `core_group_get_course_user_groups` і читає події лише цих груп. Без цієї функції ключ теж видається, але групових подій у застосунку не буде.

**3. Встановити плагін** одним зі способів:
- Site administration → Plugins → *Install plugins* → завантажити `local_kseshpak.zip`;
- або розпакувати папку `kseshpak` у `local/` (вийде `local/kseshpak`; на Moodle 5.1+ — `public/local/kseshpak`) і відкрити Site administration → *Notifications*.

**4. Налаштування плагіна.** Нічого робити не треба: адресу повернення `https://kse-hub.vercel.app/api/moodle-plugin/return` і термін дії ключа (рік) плагін записує сам під час встановлення. Їх видно в Site administration → Plugins → Local plugins → *KSE Shpak*. Порожня адреса означає «вимкнено» (див. «Як вимкнути»).

**5. Права.** Перевірте, що в ролі *Authenticated user* дозволено `webservice/rest:use`: Site administration → Users → Permissions → Define roles → Authenticated user. Без цього права ключ не працюватиме, і плагін так і напише.

Moodle сам дає це право ролі *Authenticated user*, коли вмикають «Enable mobile web services», але при вимкненні його не забирає. Тож якщо право хтось прибрав вручну (чи його ніколи не було), дайте його вручну. `local/kseshpak:connect` після встановлення є в *Authenticated user* за замовчуванням.

**6. Cloudflare.** Сервер KSE Shpak (Vercel, без фіксованої IP-адреси) читає Moodle через `/webservice/rest/server.php`, а для студентів, які ще підключили Moodle старим способом (посилання на експорт календаря), ще й через `/calendar/export_execute.php`. Якщо teaching.kse.org.ua стоїть за Cloudflare, без винятку Cloudflare може відповідати серверу 403 («Just a moment...»), і жоден ключ не запрацює.

Спершу з'ясуйте, що саме зупиняє запит: в Cloudflare Security → Events знайдіть запит до `/webservice/rest/server.php` чи `/calendar/export_execute.php` (наш сервер пише у своїх логах `cf-ray`, його можна передати) і подивіться, яке правило спрацювало.

Винятки, які Cloudflare підтримує (перевірено за документацією Cloudflare на 2026-11-23):
- **Bot Fight Mode (безкоштовний план) пропустити правилом неможливо**: «You cannot bypass or skip Bot Fight Mode using WAF custom rules». Якщо зупиняє саме він, його доведеться вимкнути для сайту або перейти на план із Super Bot Fight Mode.
- **Super Bot Fight Mode** (плани Pro і вище), **Managed rules**, **Security level**, **Browser Integrity Check**: правило WAF Security → WAF → Custom rules, дія **Skip** (обрати відповідні пункти), для запитів, де виконуються обидві умови:
  - шлях `/webservice/rest/server.php` або `/calendar/export_execute.php` (другий потрібен лише тим, хто підключив календар посиланням: без нього їхні дедлайни не оновлюються, ключів плагіна це не стосується);
  - заголовок `X-KSE-Shpak` дорівнює секрету, який розробник передасть окремо.

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

## Після оновлення Moodle

Нічого окремо робити не треба: плагін, сервіс, ключі й налаштування переживають оновлення, як будь-який інший плагін. Плагін перевірено на Moodle 4.2–5.3. Два моменти:
- при переході на 5.1+ папка плагіна має бути в `public/local/kseshpak` (як і всі додаткові плагіни);
- якщо нова версія Moodle прибере якусь функцію з кроку 2, вона лишиться в списку сервісу, але викликати її вже не можна; плагін такі зниклі функції не враховує й ключі видає далі. Чого бракуватиме KSE Shpak, видно на сторінці «Перевірка», і це виправляє розробник на своєму боці.

Після оновлення варто один раз відкрити посилання з розділу «Перевірка».

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
| `db/install.php`, `db/upgrade.php` | записують налаштування під час встановлення |
| `settings.php` | два поля: адреса повернення і термін дії ключа |
| `db/access.php` | право `local/kseshpak:connect` |
| `classes/privacy/provider.php` | що залишає Moodle (Privacy API) |
| `lang/en`, `lang/uk` | тексти |
| `tests/` | PHPUnit і Behat; на кожну зміну проганяються на справжньому Moodle 4.2–5.3 |
| `LICENSE` | GNU GPL v3, як і сам Moodle |
