# TaskFlow — Task Tracker (PHP · MySQL · Bootstrap 5)

A secure, multi-user task manager with a SaaS-style dashboard. It implements **all three levels** of the assignment plus the optional extras (priority, due dates, pagination, profile management, charts).

| Level | What is implemented |
|-------|---------------------|
| **1 – CRUD** | MySQL database + `tasks` table (`id, title, description, status, created_at`, plus `user_id, priority, due_date, updated_at`). Create, read, edit (title/description/…), delete **after a confirmation modal**, with success/error messages. |
| **2 – Status, search, validation** | Statuses *Pending / In Progress / Completed*, inline status change, title search, status filter (+ priority filter & sort), frontend **and** backend validation, empty titles rejected with friendly messages. |
| **3 – Auth & security** | Registration + login with PHP sessions, `password_hash()` / `password_verify()`, protected dashboard, per-user task ownership enforced **in the backend**, PDO prepared statements, output escaping (XSS), CSRF tokens, secure logout. |
| **Extras** | Priority (Low/Medium/High), due dates + overdue highlighting, pagination (8/page), profile page (rename, change password), Chart.js completion & priority charts, responsive sidebar layout. |

---

## 1. Folder structure

```
task-tracker/
├── index.php                  → redirects to dashboard or login
├── dashboard.php              → summary cards, charts, search/filter, task table, pagination
├── profile.php                → profile + change password
├── database.sql               → full MySQL export (tables + sample data)
├── README.md
├── .htaccess                  → Apache hardening (no directory listing, blocks .sql/.md)
├── config/
│   └── database.php           → DB settings + PDO connection  (edit credentials here)
├── includes/                  → reusable pieces (not meant to be opened directly)
│   ├── bootstrap.php          → session, security headers, error handling, loads helpers
│   ├── functions.php          → escaping, CSRF, auth guards, validation, helpers
│   ├── header.php  sidebar.php  footer.php  task_form.php
├── auth/
│   ├── login.php  register.php  logout.php
├── tasks/                     → task operations
│   ├── add.php  edit.php  delete.php  update_status.php
├── assets/
│   ├── css/style.css          → theme, animations, responsive rules
│   ├── js/app.js              → validation, modal, charts, UX (no inline scripts)
│   └── img/favicon.svg
└── tests/                     → automated test-suite (optional, safe to delete)
    ├── run_tests.py  prepend.php  sqlite_schema.sql  TEST_RESULTS.md
```

---

## 2. Requirements

* **XAMPP** with PHP **8.1 or newer** (current XAMPP releases ship 8.x), Apache and MySQL/MariaDB
* **Internet connection** in the browser – Bootstrap 5, Bootstrap Icons and Chart.js are loaded from the jsDelivr CDN
* VS Code (any editor works)

---

## 3. Setup with XAMPP + phpMyAdmin + VS Code (step by step)

1. **Download & unzip** `task-tracker.zip`. You get a folder called `task-tracker`.
2. **Copy the folder** into XAMPP's web root:
   * Windows: `C:\xampp\htdocs\task-tracker`
   * macOS: `/Applications/XAMPP/htdocs/task-tracker`
   * Linux: `/opt/lampp/htdocs/task-tracker`
3. **Start the servers**: open the *XAMPP Control Panel* and press **Start** next to **Apache** and **MySQL** (both turn green).
4. **Import the database**
   1. Open <http://localhost/phpmyadmin>.
   2. Click the **Import** tab → **Choose file** → select `task-tracker/database.sql` → scroll down and click **Import** (or *Go*).
   3. The database **`task_tracker`** with tables **`users`** and **`tasks`** now appears in the left panel.
   *(The script runs `CREATE DATABASE`, so you do not need to create it first. It drops and recreates the two tables, so re-importing resets the demo data.)*
5. **Check the DB credentials** in `config/database.php`. XAMPP defaults already match: host `localhost`, user `root`, **empty** password, database `task_tracker`. Change them only if you set a MySQL root password.
6. **Open the app**: <http://localhost/task-tracker/> → you land on the login page.
7. **Open the project in VS Code**: *File → Open Folder…* → choose the folder in `htdocs`. Edit files there; refresh the browser to see changes. (Optional extensions: *PHP Intelephense*.)

> **Alternative without Apache:** keep MySQL running in XAMPP, open a terminal in the project folder and run `php -S localhost:8000`, then browse to <http://localhost:8000>. (Use the PHP binary from `C:\xampp\php` if `php` is not on your PATH.)

### Sample login credentials (created by `database.sql`)

| User | Email | Password |
|------|-------|----------|
| Alice (12 sample tasks) | `alice@example.com` | `Test@1234` |
| Bob (3 sample tasks) | `bob@example.com` | `Test@1234` |

Or click **Create an account** to register your own user.

### Quick manual test plan (5 minutes)

1. Sign in as Alice → dashboard shows 12 tasks (2 pages), summary cards and two charts.
2. **Add** a task (leave the title empty first → validation message), then save a valid one → success banner.
3. **Edit** it (change title/description) → saved. Change its **status** with the dropdown in the table.
4. **Search** `report`, then use the **status/priority** filters, then **Reset**.
5. **Delete** → confirmation modal → *Yes, delete* → success banner.
6. **Ownership test:** sign in as Bob in a private window. Copy the edit URL of one of Alice's tasks (e.g. `http://localhost/task-tracker/tasks/edit.php?id=1`) and open it as Bob → **404 "Task not found"**. Bob's dashboard never lists Alice's tasks.
7. Click **Sign out** → opening `dashboard.php` redirects to the login page.

### Run the automated tests (optional)

```bash
# against your own XAMPP install (needs Python 3, no extra packages)
python tests/run_tests.py http://localhost/task-tracker
```
The tests register throw-away users (`…@test.local`). Remove them afterwards in phpMyAdmin → SQL:
`DELETE FROM users WHERE email LIKE '%@test.local';` (their tasks are removed automatically by the foreign key).
Checks that need direct database access are reported as **SKIP** in this mode.

---

## 4. Database design

```
┌───────────────────────┐          ┌───────────────────────────────────────┐
│ users                 │ 1      * │ tasks                                 │
├───────────────────────┤──────────├───────────────────────────────────────┤
│ id  PK  AUTO_INCREMENT│          │ id  PK  AUTO_INCREMENT                │
│ name           VARCHAR│          │ user_id  FK → users.id  ON DELETE CASCADE
│ email  UNIQUE  VARCHAR│          │ title          VARCHAR(150) NOT NULL  │
│ password_hash  VARCHAR│          │ description    TEXT NULL              │
│ created_at     TIMESTAMP          │ status   ENUM('Pending','In Progress','Completed')
└───────────────────────┘          │ priority ENUM('Low','Medium','High')  │
                                   │ due_date       DATE NULL              │
                                   │ created_at / updated_at  TIMESTAMP    │
                                   └───────────────────────────────────────┘
```

* **One-to-many**: a user owns many tasks; every task belongs to exactly one user (`tasks.user_id` → `users.id`).
* `ON DELETE CASCADE` removes a user's tasks if the account is deleted – no orphan rows; the FK also prevents inserting a task for a non-existent user.
* `ENUM` columns make the database itself reject invalid statuses/priorities (in addition to PHP validation).
* `UNIQUE(email)` guarantees one account per address even under concurrent registrations.
* Indexes `(user_id, status)` and `(user_id, created_at)` speed up the per-user dashboard queries and filters.
* Passwords are stored only as `password_hash()` bcrypt hashes (60 chars, `VARCHAR(255)` leaves room for future algorithms).

---

## 5. Security implementation

| Threat | Protection (where) |
|--------|--------------------|
| **SQL injection** | Every query that touches user input uses **PDO prepared statements** with real server-side preparation (`ATTR_EMULATE_PREPARES = false`). Sort order comes from a fixed whitelist, never from user text. LIKE wildcards typed by the user (`%`, `_`) are escaped. |
| **Password theft** | `password_hash(PASSWORD_DEFAULT)` on register / password change, `password_verify()` on login, automatic `password_needs_rehash()`. Min 8 chars, letter + number, max 72 (bcrypt limit). Plain passwords are never stored or logged. |
| **User enumeration / timing** | Login always shows *"Invalid email or password"* and always runs `password_verify()` (against a dummy hash for unknown e-mails). |
| **Session attacks** | `session_regenerate_id(true)` on login and password change (prevents fixation); cookie is `HttpOnly` + `SameSite=Lax` (+ `Secure` on HTTPS); strict session mode; 30-minute idle timeout; logout clears the session data, destroys it and expires the cookie. |
| **Broken access control (IDOR)** | The logged-in user id is read **only from the server session**. `edit.php` loads a task with `WHERE id = ? AND user_id = ?` (other users' ids → **404**, no information leaked); `delete.php` and `update_status.php` put `user_id` into the `DELETE`/`UPDATE` itself. A `user_id` field posted by an attacker is ignored. Guests are redirected by `require_login()` on every page. |
| **CSRF** | 256-bit random token per session (`random_bytes`), embedded in every state-changing form (add, edit, delete, status, profile, **logout**, login, register) and checked with `hash_equals()`. Delete / status / logout accept **POST only**. Cookies are `SameSite=Lax`. |
| **XSS** | All user-generated output goes through `e()` = `htmlspecialchars(ENT_QUOTES, UTF-8)`, including flash messages and form re-population. JavaScript uses `textContent`, not `innerHTML`. A **Content-Security-Policy** allows scripts only from the site and jsDelivr and the project has **no inline scripts**. |
| **Clickjacking / sniffing / caching** | `X-Frame-Options: DENY`, `frame-ancestors 'none'`, `X-Content-Type-Options: nosniff`, `Referrer-Policy`, and `Cache-Control: no-store` so private pages are not cached or shown after logout via the Back button. |
| **Information leakage** | `display_errors` off; errors are logged, users see a generic message. Include/config files refuse direct execution (`APP_BOOT` guard) and are also blocked by `.htaccess` on Apache. `database.sql`/`.md` are not served over HTTP. |
| **Validation** | Done twice: HTML5 + Bootstrap + JS in the browser (convenience) and `validate_task()` / registration checks in PHP (the real enforcement): required/blank title, lengths, enum values, real calendar dates, e-mail format, password rules. |

---

## 6. Test cases and results

The suite in `tests/run_tests.py` drives the real application over HTTP (cookies, redirects, CSRF tokens), including two separate users to test authorization.

**Result: 47 passed, 0 failed.**

> **Important – how it was run:** the build environment had PHP but no MySQL server, so the suite ran against the application's own PDO code on a **SQLite stand-in** (`tests/prepend.php`, started with `php -S`). All SQL in the app is written to work on both engines, but `database.sql` itself (MySQL syntax) was reviewed, not executed. Please run the quick manual test plan above, and/or `python tests/run_tests.py http://localhost/task-tracker`, once on your XAMPP to confirm on MySQL.

| ID | Area | Test case | Result |
|----|------|-----------|--------|
| A01 | Level 3 · Auth | Guests are redirected to login from every protected page | ✅ PASS |
| A02 | Level 3 · Auth | Guests cannot call delete / update_status endpoints | ✅ PASS |
| A03 | Level 3 · Auth | Registration: empty form shows friendly backend validation messages | ✅ PASS |
| A04 | Level 3 · Auth | Registration: invalid e-mail, weak password and mismatch are rejected | ✅ PASS |
| A05 | Level 3 · Auth | Password is stored as a bcrypt hash (password_hash), never plain text | ✅ PASS |
| A06 | Level 3 · Auth | Registration: duplicate e-mail (case-insensitive) is refused | ✅ PASS |
| A07 | Level 3 · Auth | Login: wrong password / unknown e-mail give the same generic error | ✅ PASS |
| A08 | Level 3 · Auth | Login succeeds with password_verify and regenerates the session id | ✅ PASS |
| A09 | Level 3 · Auth | Login form without / with wrong CSRF token is rejected | ✅ PASS |
| A10 | Level 3 · Auth | Logged-in users visiting login are sent to the dashboard | ✅ PASS |
| A11 | Level 3 · Auth | Logout is POST + CSRF only and fully destroys the session | ✅ PASS |
| A12 | Level 3 · Auth | A session cookie copied before logout is useless afterwards | ✅ PASS |
| C01 | Level 1 · CRUD | Create: valid task is saved to MySQL and shown with a success message | ✅ PASS |
| C02 | Level 1 · CRUD | Read: saved tasks are listed on the dashboard | ✅ PASS |
| C03 | Level 1 · CRUD | Update: edit title/description (+status, priority, due date) persists | ✅ PASS |
| C04 | Level 1 · CRUD | Update: blank title is refused and the stored task stays unchanged | ✅ PASS |
| C05 | Level 1 · CRUD | Delete: confirmation modal present; POST removes the row with success message | ✅ PASS |
| C06 | Level 1 · CRUD | Delete: GET request and missing/forged CSRF token do not delete | ✅ PASS |
| V01 | Level 2 · Status/Search/Validation | Empty / whitespace-only / over-long titles are blocked by the backend | ✅ PASS |
| V02 | Level 2 · Status/Search/Validation | Tampered status / priority / due-date values are rejected | ✅ PASS |
| V03 | Level 2 · Status/Search/Validation | On a validation error the form is re-displayed with the entered values | ✅ PASS |
| V04 | Level 2 · Status/Search/Validation | Frontend validation hooks present (required, maxlength, Bootstrap feedback, JS) | ✅ PASS |
| V05 | Level 2 · Status/Search/Validation | Status can be changed Pending ↔ In Progress ↔ Completed | ✅ PASS |
| V06 | Level 2 · Status/Search/Validation | Invalid status value or missing CSRF token cannot change a task | ✅ PASS |
| S01 | Level 2 · Status/Search/Validation | Search by title (partial, case-insensitive, friendly empty state) | ✅ PASS |
| S02 | Level 2 · Status/Search/Validation | LIKE wildcards typed by the user (% and _) are escaped | ✅ PASS |
| S03 | Level 2 · Status/Search/Validation | Status / priority filters work alone and combined with search | ✅ PASS |
| S04 | Level 2 · Status/Search/Validation | Pagination splits tasks into pages; bad page numbers do not crash | ✅ PASS |
| S05 | Level 2 · Status/Search/Validation | Dashboard summary cards + chart data reflect the database | ✅ PASS |
| Z01 | Level 3 · Authorization | Each user only sees their own tasks (list and search) | ✅ PASS |
| Z02 | Level 3 · Authorization | Opening another user's edit URL (?id=…) returns 404 and leaks nothing | ✅ PASS |
| Z03 | Level 3 · Authorization | POSTing an edit for another user's task changes nothing | ✅ PASS |
| Z04 | Level 3 · Authorization | Deleting another user's task id is refused (row survives) | ✅ PASS |
| Z05 | Level 3 · Authorization | Changing the status of another user's task is refused | ✅ PASS |
| Z06 | Level 3 · Authorization | Injected user_id form field is ignored — owner comes from the session | ✅ PASS |
| Z07 | Level 3 · Authorization | Malformed ids (text, negative, arrays, SQL) never cause a 500 or a change | ✅ PASS |
| Z08 | Level 3 · Authorization | A CSRF token from another user's session is rejected | ✅ PASS |
| Z09 | Level 3 · Authorization | Foreign key tasks.user_id → users.id: cascade delete and no orphans | ✅ PASS |
| X01 | Security | XSS: titles, descriptions, search term and flash messages are HTML-escaped | ✅ PASS |
| X02 | Security | SQL injection attempts in search, filters, login and task title are harmless | ✅ PASS |
| X03 | Security | Security headers: X-Frame-Options, nosniff, CSP, no-store, Referrer-Policy | ✅ PASS |
| X04 | Security | Session cookie is HttpOnly + SameSite=Lax | ✅ PASS |
| X05 | Security | Include/config files cannot be run or read directly over HTTP | ✅ PASS |
| X06 | Security | No PHP warnings / stack traces leak into pages | ✅ PASS |
| P01 | Optional · Profile | Profile: name is validated and can be updated | ✅ PASS |
| P02 | Optional · Profile | Profile: password change needs the current password; new one works, old one fails | ✅ PASS |
| P03 | Optional · Profile | Seeded demo account (alice@example.com / Test@1234) can sign in | ✅ PASS |

---

## 7. Troubleshooting

| Problem | Fix |
|---------|-----|
| "Database connection failed" page | Start **MySQL** in XAMPP; make sure `database.sql` was imported; check user/password in `config/database.php`. |
| Page looks unstyled / charts missing | The browser needs internet access for the CDN (Bootstrap, Icons, Chart.js). |
| Links go to wrong URL / 404 | Keep the folder name `task-tracker` inside `htdocs` (any name works; URLs are generated automatically). To force a path add `define('BASE_URL', '/my-folder');` at the top of `config/database.php`. |
| Port 80 / 3306 already in use | Stop Skype/IIS/other MySQL, or change the port in XAMPP and use e.g. `http://localhost:8080/task-tracker/`. |
| MySQL root has a password | Set `DB_PASS` in `config/database.php`. |
| Logged out unexpectedly | Sessions expire after 30 minutes of inactivity (`SESSION_IDLE_SECONDS` in `includes/functions.php`). |

## 8. Known limitations / ideas

* No login rate-limiting or CAPTCHA, e-mail verification or password-reset flow (would need mail setup).
* E-mail address cannot be changed from the profile page.
* For production use HTTPS (the session cookie then becomes `Secure` automatically) and a dedicated MySQL user instead of `root`.
