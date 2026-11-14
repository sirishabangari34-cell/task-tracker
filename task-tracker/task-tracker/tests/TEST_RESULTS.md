# Test results

Run against: **SQLite stand-in database (php -S)** &middot; 2026-11-14 14:20

**47 passed, 0 failed, 0 skipped, 47 total**

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
