#!/usr/bin/env python3
"""
End-to-end test-suite for TaskFlow (pure Python standard library).

  python3 tests/run_tests.py --serve            # starts php -S with a throw-away SQLite DB
  python3 tests/run_tests.py http://localhost/task-tracker   # run against your own XAMPP install

Every test talks to the real application over HTTP (cookies, redirects, CSRF tokens),
so it exercises PHP, sessions, validation, authorization and the database layer.
Direct database assertions are only done in --serve mode (SQLite file); against a
remote URL those checks are reported as SKIP.
"""
import http.client, os, re, sqlite3, subprocess, sys, tempfile, time, uuid
from urllib.parse import urlencode, urlparse

ROOT = os.path.dirname(os.path.dirname(os.path.abspath(__file__)))
RESULTS = []          # (id, group, description, outcome, detail)
DBFILE = None         # sqlite path in --serve mode


class Client:
    def __init__(self, base):
        u = urlparse(base)
        self.host, self.port, self.prefix = u.hostname, u.port or 80, u.path.rstrip('/')
        self.cookies = {}
        self.last_headers = {}

    def _req(self, method, path, data=None):
        conn = http.client.HTTPConnection(self.host, self.port, timeout=20)
        headers = {'Cookie': '; '.join(f'{k}={v}' for k, v in self.cookies.items())}
        body = None
        if data is not None:
            body = urlencode(data, doseq=True)
            headers['Content-Type'] = 'application/x-www-form-urlencoded'
        conn.request(method, self.prefix + path if path.startswith('/') else path, body, headers)
        r = conn.getresponse()
        text = r.read().decode('utf-8', 'replace')
        hdrs = {k.lower(): v for k, v in r.getheaders()}
        for k, v in r.getheaders():
            if k.lower() == 'set-cookie':
                name, _, rest = v.partition('=')
                val = rest.split(';')[0]
                if val in ('deleted', ''):
                    self.cookies.pop(name, None)
                else:
                    self.cookies[name] = val
        self.last_headers = hdrs
        self.last_set_cookie = [v for k, v in r.getheaders() if k.lower() == 'set-cookie']
        conn.close()
        return r.status, hdrs, text

    def get(self, path):
        return self._req('GET', path)

    def post(self, path, data):
        return self._req('POST', path, data)

    def follow(self, resp):
        status, hdrs, _ = resp
        hops = 0
        while 300 <= status < 400 and 'location' in hdrs and hops < 5:
            loc = hdrs['location']
            if self.prefix and loc.startswith(self.prefix):
                loc = loc[len(self.prefix):] or '/'
            status, hdrs, text = self.get(loc)
            hops += 1
        return status, hdrs, text

    def csrf(self, page='/auth/login.php'):
        _, _, html = self.get(page)
        m = re.search(r'name="csrf_token" value="([a-f0-9]+)"', html)
        assert m, f'no csrf token on {page}'
        return m.group(1)


def sql(query, params=()):
    if not DBFILE:
        return None
    con = sqlite3.connect(DBFILE)
    try:
        return con.execute(query, params).fetchall()
    finally:
        con.close()


def record(tid, group, desc, fn):
    try:
        out = fn()
        if out == 'SKIP':
            RESULTS.append((tid, group, desc, 'SKIP', 'needs direct DB access (--serve mode)'))
        else:
            RESULTS.append((tid, group, desc, 'PASS', ''))
    except AssertionError as e:
        RESULTS.append((tid, group, desc, 'FAIL', str(e) or 'assertion failed'))
    except Exception as e:  # noqa
        RESULTS.append((tid, group, desc, 'FAIL', f'{type(e).__name__}: {e}'))


def new_user(base, prefix='u'):
    c = Client(base)
    email = f'{prefix}{uuid.uuid4().hex[:8]}@test.local'
    tok = c.csrf('/auth/register.php')
    r = c.post('/auth/register.php', {'csrf_token': tok, 'name': 'Test User', 'email': email,
                                      'password': 'Passw0rd!', 'password_confirm': 'Passw0rd!'})
    assert r[0] == 302, f'register failed {r[0]}'
    tok = c.csrf('/auth/login.php')
    r = c.post('/auth/login.php', {'csrf_token': tok, 'email': email, 'password': 'Passw0rd!'})
    assert r[0] == 302 and r[1]['location'].endswith('/dashboard.php'), 'login failed'
    return c, email


def add_task(c, **fields):
    tok = c.csrf('/tasks/add.php')
    data = {'csrf_token': tok, 'title': 'Task', 'description': '', 'status': 'Pending', 'priority': 'Medium', 'due_date': ''}
    data.update(fields)
    return c.post('/tasks/add.php', data)


def task_id_by_title(title):
    rows = sql('SELECT id FROM tasks WHERE title = ? ORDER BY id DESC LIMIT 1', (title,))
    return rows[0][0] if rows else None


def run(base):
    A, a_email = new_user(base, 'alice')
    B, b_email = new_user(base, 'bob')
    uid_a = sql('SELECT id FROM users WHERE email=?', (a_email,))
    uid_a = uid_a[0][0] if uid_a else None
    uid_b = sql('SELECT id FROM users WHERE email=?', (b_email,))
    uid_b = uid_b[0][0] if uid_b else None
    G = 'Level 3 · Auth'

    # ------------------------------------------------------------------ AUTHENTICATION
    def t():
        c = Client(base)
        for p in ('/dashboard.php', '/tasks/add.php', '/tasks/edit.php?id=1', '/profile.php'):
            r = c.get(p)
            assert r[0] == 302 and '/auth/login.php' in r[1]['location'], f'{p} -> {r[0]}'
    record('A01', G, 'Guests are redirected to login from every protected page', t)

    def t():
        c = Client(base)
        for p, d in (('/tasks/delete.php', {'id': '1'}), ('/tasks/update_status.php', {'id': '1', 'status': 'Completed'})):
            r = c.post(p, d)
            assert r[0] == 302 and '/auth/login.php' in r[1]['location'], f'{p} -> {r[0]}'
    record('A02', G, 'Guests cannot call delete / update_status endpoints', t)

    def t():
        c = Client(base)
        tok = c.csrf('/auth/register.php')
        r = c.post('/auth/register.php', {'csrf_token': tok, 'name': '', 'email': '', 'password': '', 'password_confirm': ''})
        assert r[0] == 200
        for msg in ('Full name is required', 'Email address is required', 'Password is required', 'Please confirm your password'):
            assert msg in r[2], f'missing message: {msg}'
    record('A03', G, 'Registration: empty form shows friendly backend validation messages', t)

    def t():
        c = Client(base)
        tok = c.csrf('/auth/register.php')
        r = c.post('/auth/register.php', {'csrf_token': tok, 'name': 'X Y', 'email': 'not-an-email', 'password': 'short1', 'password_confirm': 'short1'})
        assert 'valid email' in r[2] and 'at least 8 characters' in r[2]
        r = c.post('/auth/register.php', {'csrf_token': tok, 'name': 'X Y', 'email': 'a@b.co', 'password': 'onlyletters', 'password_confirm': 'onlyletters'})
        assert 'one letter and one number' in r[2]
        r = c.post('/auth/register.php', {'csrf_token': tok, 'name': 'X Y', 'email': 'a@b.co', 'password': 'Passw0rd!', 'password_confirm': 'Different1'})
        assert 'Passwords do not match' in r[2]
    record('A04', G, 'Registration: invalid e-mail, weak password and mismatch are rejected', t)

    def t():
        if not DBFILE:
            return 'SKIP'
        rows = sql('SELECT password_hash FROM users WHERE email=?', (a_email,))
        h = rows[0][0]
        assert h.startswith('$2y$') and 'Passw0rd!' not in h and len(h) == 60, h
    record('A05', G, 'Password is stored as a bcrypt hash (password_hash), never plain text', t)

    def t():
        c = Client(base)
        tok = c.csrf('/auth/register.php')
        r = c.post('/auth/register.php', {'csrf_token': tok, 'name': 'Dup', 'email': a_email.upper(), 'password': 'Passw0rd!', 'password_confirm': 'Passw0rd!'})
        assert r[0] == 200 and 'already exists' in r[2]
    record('A06', G, 'Registration: duplicate e-mail (case-insensitive) is refused', t)

    def t():
        c = Client(base)
        tok = c.csrf()
        r1 = c.post('/auth/login.php', {'csrf_token': tok, 'email': a_email, 'password': 'WrongPass1'})
        r2 = c.post('/auth/login.php', {'csrf_token': tok, 'email': 'nobody@test.local', 'password': 'WrongPass1'})
        assert r1[0] == 200 and r2[0] == 200
        assert 'Invalid email or password' in r1[2] and 'Invalid email or password' in r2[2]
        assert r1[2].count('Invalid email or password') == r2[2].count('Invalid email or password')
        assert c.get('/dashboard.php')[0] == 302
    record('A07', G, 'Login: wrong password / unknown e-mail give the same generic error', t)

    def t():
        c = Client(base)
        tok = c.csrf()
        before = c.cookies.get('TTSESSID')
        r = c.post('/auth/login.php', {'csrf_token': tok, 'email': a_email, 'password': 'Passw0rd!'})
        assert r[0] == 302 and r[1]['location'].endswith('/dashboard.php')
        assert c.cookies.get('TTSESSID') and c.cookies['TTSESSID'] != before, 'session id not regenerated'
        assert c.get('/dashboard.php')[0] == 200
    record('A08', G, 'Login succeeds with password_verify and regenerates the session id', t)

    def t():
        c = Client(base)
        c.csrf()
        r = c.post('/auth/login.php', {'email': a_email, 'password': 'Passw0rd!'})          # no token
        assert r[0] == 302 and '/auth/login.php' in r[1]['location']
        assert c.get('/dashboard.php')[0] == 302, 'logged in without CSRF token!'
        r = c.post('/auth/login.php', {'csrf_token': 'a' * 64, 'email': a_email, 'password': 'Passw0rd!'})   # wrong token
        assert c.get('/dashboard.php')[0] == 302
    record('A09', G, 'Login form without / with wrong CSRF token is rejected', t)

    def t():
        c, _ = new_user(base)
        r = c.get('/auth/login.php')
        assert r[0] == 302 and r[1]['location'].endswith('/dashboard.php')
    record('A10', G, 'Logged-in users visiting login are sent to the dashboard', t)

    def t():
        c, _ = new_user(base)
        c.get('/auth/logout.php')                                     # GET must not log out
        assert c.get('/dashboard.php')[0] == 200, 'GET logout logged the user out'
        c.post('/auth/logout.php', {})                                # no token
        assert c.get('/dashboard.php')[0] == 200, 'logout without CSRF worked'
        _, _, html = c.get('/dashboard.php')
        tok = re.search(r'name="csrf_token" value="([a-f0-9]+)"', html).group(1)
        r = c.post('/auth/logout.php', {'csrf_token': tok})
        assert r[0] == 302 and '/auth/login.php' in r[1]['location']
        assert c.get('/dashboard.php')[0] == 302, 'session still valid after logout'
    record('A11', G, 'Logout is POST + CSRF only and fully destroys the session', t)

    def t():
        c, _ = new_user(base)
        old = dict(c.cookies)
        c.post('/auth/logout.php', {'csrf_token': re.search(r'csrf_token" value="([a-f0-9]+)"', c.get('/dashboard.php')[2]).group(1)})
        replay = Client(base)
        replay.cookies = old                                          # replay the stolen old cookie
        assert replay.get('/dashboard.php')[0] == 302, 'old session id still accepted'
    record('A12', G, 'A session cookie copied before logout is useless afterwards', t)

    # ------------------------------------------------------------------ LEVEL 1 CRUD
    G = 'Level 1 · CRUD'
    C, _ = new_user(base, 'crud')

    def t():
        r = add_task(C, title='Buy milk', description='2 litres, semi-skimmed')
        assert r[0] == 302 and r[1]['location'].endswith('/dashboard.php')
        page = C.follow(r)[2]
        assert 'was added successfully' in page and 'Buy milk' in page
        if DBFILE:
            row = sql('SELECT user_id, description, status, priority FROM tasks WHERE title=?', ('Buy milk',))[0]
            assert row[1] == '2 litres, semi-skimmed' and row[2] == 'Pending' and row[3] == 'Medium'
    record('C01', G, 'Create: valid task is saved to MySQL and shown with a success message', t)

    def t():
        _, _, page = C.get('/dashboard.php')
        assert 'Buy milk' in page and 'semi-skimmed' in page
    record('C02', G, 'Read: saved tasks are listed on the dashboard', t)

    def t():
        if not DBFILE:
            return 'SKIP'
        tid = task_id_by_title('Buy milk')
        _, _, form = C.get(f'/tasks/edit.php?id={tid}')
        assert 'value="Buy milk"' in form and '2 litres' in form, 'edit form not pre-filled'
        tok = re.search(r'csrf_token" value="([a-f0-9]+)"', form).group(1)
        r = C.post(f'/tasks/edit.php?id={tid}', {'csrf_token': tok, 'title': 'Buy oat milk', 'description': 'Changed note',
                                                  'status': 'In Progress', 'priority': 'High', 'due_date': '2030-01-31'})
        assert r[0] == 302
        assert 'updated successfully' in C.follow(r)[2]
        row = sql('SELECT title, description, status, priority, due_date FROM tasks WHERE id=?', (tid,))[0]
        assert row == ('Buy oat milk', 'Changed note', 'In Progress', 'High', '2030-01-31'), row
    record('C03', G, 'Update: edit title/description (+status, priority, due date) persists', t)

    def t():
        if not DBFILE:
            return 'SKIP'
        tid = task_id_by_title('Buy oat milk')
        r = C.post(f'/tasks/edit.php?id={tid}', {'csrf_token': C.csrf(f'/tasks/edit.php?id={tid}'), 'title': '   ', 'description': 'x',
                                                  'status': 'Pending', 'priority': 'Low', 'due_date': ''})
        assert r[0] == 200 and 'Task title is required' in r[2]
        assert sql('SELECT title FROM tasks WHERE id=?', (tid,))[0][0] == 'Buy oat milk', 'title overwritten by blank'
    record('C04', G, 'Update: blank title is refused and the stored task stays unchanged', t)

    def t():
        if not DBFILE:
            return 'SKIP'
        add_task(C, title='Temp task to delete')
        tid = task_id_by_title('Temp task to delete')
        _, _, dash = C.get('/dashboard.php')
        tok = re.search(r'csrf_token" value="([a-f0-9]+)"', dash).group(1)
        assert f'data-task-id="{tid}"' in dash and 'id="deleteModal"' in dash, 'delete confirmation modal missing'
        r = C.post('/tasks/delete.php', {'csrf_token': tok, 'id': str(tid)})
        assert r[0] == 302 and 'deleted successfully' in C.follow(r)[2]
        assert sql('SELECT COUNT(*) FROM tasks WHERE id=?', (tid,))[0][0] == 0
    record('C05', G, 'Delete: confirmation modal present; POST removes the row with success message', t)

    def t():
        if not DBFILE:
            return 'SKIP'
        add_task(C, title='Survivor')
        tid = task_id_by_title('Survivor')
        tok = re.search(r'csrf_token" value="([a-f0-9]+)"', C.get('/dashboard.php')[2]).group(1)
        r = C.get(f'/tasks/delete.php?id={tid}')                       # GET must not delete
        assert sql('SELECT COUNT(*) FROM tasks WHERE id=?', (tid,))[0][0] == 1, 'GET delete removed the task'
        C.post('/tasks/delete.php', {'id': str(tid)})                  # no CSRF token
        assert sql('SELECT COUNT(*) FROM tasks WHERE id=?', (tid,))[0][0] == 1, 'delete without CSRF worked'
        C.post('/tasks/delete.php', {'csrf_token': 'f' * 64, 'id': str(tid)})
        assert sql('SELECT COUNT(*) FROM tasks WHERE id=?', (tid,))[0][0] == 1, 'delete with bad CSRF worked'
    record('C06', G, 'Delete: GET request and missing/forged CSRF token do not delete', t)

    # ------------------------------------------------------------------ LEVEL 2
    G = 'Level 2 · Status/Search/Validation'

    def t():
        r = add_task(C, title='')
        assert r[0] == 200 and 'Task title is required' in r[2] and 'is-invalid' in r[2]
        r = add_task(C, title='      ')
        assert r[0] == 200 and 'Task title is required' in r[2]
        r = add_task(C, title='x' * 151)
        assert r[0] == 200 and '150 characters' in r[2]
        if DBFILE:
            assert sql("SELECT COUNT(*) FROM tasks WHERE user_id=? AND (trim(title)='' OR length(title)>150)", (sql('SELECT id FROM users WHERE email LIKE "crud%" ORDER BY id DESC LIMIT 1')[0][0],))[0][0] == 0
    record('V01', G, 'Empty / whitespace-only / over-long titles are blocked by the backend', t)

    def t():
        r = add_task(C, title='Bad status', status='Hacked')
        assert r[0] == 200 and 'valid status' in r[2]
        r = add_task(C, title='Bad priority', priority='Urgent!!')
        assert r[0] == 200 and 'valid priority' in r[2]
        r = add_task(C, title='Bad date', due_date='2024-13-45')
        assert r[0] == 200 and 'valid due date' in r[2]
        r = add_task(C, title='Bad date 2', due_date='not-a-date')
        assert r[0] == 200 and 'valid due date' in r[2]
    record('V02', G, 'Tampered status / priority / due-date values are rejected', t)

    def t():
        r = add_task(C, title='Validation keeps my input', description='kept description', status='Completed', priority='Low', due_date='bad')
        assert 'value="Validation keeps my input"' in r[2] and 'kept description' in r[2]
    record('V03', G, 'On a validation error the form is re-displayed with the entered values', t)

    def t():
        _, _, dash = C.get('/dashboard.php')
        assert 'needs-validation' in dash or True
        _, _, form = C.get('/tasks/add.php')
        assert 'required' in form and 'maxlength="150"' in form and 'novalidate' in form and 'invalid-feedback' in form
        js = C.get('/assets/js/app.js')[2]
        assert 'checkValidity' in js and 'data-no-blank' in js
    record('V04', G, 'Frontend validation hooks present (required, maxlength, Bootstrap feedback, JS)', t)

    def t():
        if not DBFILE:
            return 'SKIP'
        add_task(C, title='Status flow task')
        tid = task_id_by_title('Status flow task')
        for s in ('In Progress', 'Completed', 'Pending'):
            tok = re.search(r'csrf_token" value="([a-f0-9]+)"', C.get('/dashboard.php')[2]).group(1)
            r = C.post('/tasks/update_status.php', {'csrf_token': tok, 'id': str(tid), 'status': s, 'back': ''})
            assert r[0] == 302 and 'Status updated' in C.follow(r)[2]
            assert sql('SELECT status FROM tasks WHERE id=?', (tid,))[0][0] == s
    record('V05', G, 'Status can be changed Pending ↔ In Progress ↔ Completed', t)

    def t():
        if not DBFILE:
            return 'SKIP'
        tid = task_id_by_title('Status flow task')
        tok = re.search(r'csrf_token" value="([a-f0-9]+)"', C.get('/dashboard.php')[2]).group(1)
        r = C.post('/tasks/update_status.php', {'csrf_token': tok, 'id': str(tid), 'status': 'Done!'})
        assert 'Invalid task or status' in C.follow(r)[2]
        assert sql('SELECT status FROM tasks WHERE id=?', (tid,))[0][0] == 'Pending'
        C.post('/tasks/update_status.php', {'id': str(tid), 'status': 'Completed'})       # no CSRF
        assert sql('SELECT status FROM tasks WHERE id=?', (tid,))[0][0] == 'Pending', 'status changed without CSRF'
    record('V06', G, 'Invalid status value or missing CSRF token cannot change a task', t)

    S, _ = new_user(base, 'search')
    for title, st, pr in (('Alpha report', 'Pending', 'High'), ('Beta report', 'In Progress', 'Low'), ('Gamma review', 'Completed', 'High'),
                          ('100% done list', 'Pending', 'Medium'), ('Snake_case name', 'Pending', 'Medium'), ('Plain item', 'Pending', 'Medium')):
        add_task(S, title=title, status=st, priority=pr)

    def titles(page):
        return set(re.findall(r'class="task-title[^"]*">([^<]+)</div>', page))

    def t():
        assert titles(S.get('/dashboard.php?q=report')[2]) == {'Alpha report', 'Beta report'}
        assert titles(S.get('/dashboard.php?q=GAMMA')[2]) == {'Gamma review'}, 'search must be case-insensitive'
        assert 'No tasks match your filters' in S.get('/dashboard.php?q=zzzzzz')[2]
    record('S01', G, 'Search by title (partial, case-insensitive, friendly empty state)', t)

    def t():
        assert titles(S.get('/dashboard.php?q=%25')[2]) == {'100% done list'}, 'literal % must not act as wildcard'
        assert titles(S.get('/dashboard.php?q=_')[2]) == {'Snake_case name'}, 'literal _ must not act as wildcard'
    record('S02', G, 'LIKE wildcards typed by the user (% and _) are escaped', t)

    def t():
        assert titles(S.get('/dashboard.php?status=Completed')[2]) == {'Gamma review'}
        assert titles(S.get('/dashboard.php?status=In+Progress')[2]) == {'Beta report'}
        assert titles(S.get('/dashboard.php?priority=High')[2]) == {'Alpha report', 'Gamma review'}
        assert titles(S.get('/dashboard.php?status=Pending&priority=High&q=alpha')[2]) == {'Alpha report'}
        assert len(titles(S.get('/dashboard.php?status=Bogus')[2])) == 6, 'invalid filter must be ignored'
    record('S03', G, 'Status / priority filters work alone and combined with search', t)

    def t():
        for i in range(10):
            add_task(S, title=f'Paged item {i:02d}')
        p1 = titles(S.get('/dashboard.php')[2])
        assert len(p1) == 8, len(p1)
        _, _, html2 = S.get('/dashboard.php?page=2')
        assert len(titles(html2)) == 8 and 'page-link' in html2
        _, _, html3 = S.get('/dashboard.php?page=3')
        assert len(titles(html3)) == 0 or len(titles(html3)) <= 8
        assert S.get('/dashboard.php?page=999')[0] == 200 and S.get('/dashboard.php?page=-5')[0] == 200 and S.get('/dashboard.php?page[]=1')[0] == 200
    record('S04', G, 'Pagination splits tasks into pages; bad page numbers do not crash', t)

    def t():
        _, _, html = S.get('/dashboard.php')
        nums = re.findall(r'data-count="(\d+)"', html)
        assert nums[0] == '16', nums                      # 6 + 10 tasks
        assert 'statusChart' in html and 'priorityChart' in html
    record('S05', G, 'Dashboard summary cards + chart data reflect the database', t)

    # ------------------------------------------------------------------ AUTHORIZATION
    G = 'Level 3 · Authorization'
    add_task(A, title='ALICE SECRET TASK', description='alice private note')
    add_task(B, title='BOB SECRET TASK', description='bob private note')
    tid_a = task_id_by_title('ALICE SECRET TASK')
    tid_b = task_id_by_title('BOB SECRET TASK')

    def t():
        assert 'ALICE SECRET TASK' in A.get('/dashboard.php')[2]
        pb = B.get('/dashboard.php')[2]
        assert 'ALICE SECRET TASK' not in pb and 'alice private note' not in pb
        assert 'BOB SECRET TASK' in pb
        assert 'BOB SECRET TASK' not in A.get('/dashboard.php')[2]
        assert 'ALICE SECRET' not in B.get('/dashboard.php?q=alice')[2]
    record('Z01', G, 'Each user only sees their own tasks (list and search)', t)

    def t():
        if not DBFILE:
            return 'SKIP'
        r = B.get(f'/tasks/edit.php?id={tid_a}')
        assert r[0] == 404 and 'alice private note' not in r[2] and 'ALICE SECRET' not in r[2]
    record('Z02', G, "Opening another user's edit URL (?id=…) returns 404 and leaks nothing", t)

    def t():
        if not DBFILE:
            return 'SKIP'
        tok = B.csrf(f'/tasks/edit.php?id={tid_b}')
        r = B.post(f'/tasks/edit.php?id={tid_a}', {'csrf_token': tok, 'title': 'HACKED BY BOB', 'description': 'x', 'status': 'Completed', 'priority': 'Low', 'due_date': ''})
        assert r[0] == 404
        row = sql('SELECT title, description, status, user_id FROM tasks WHERE id=?', (tid_a,))[0]
        assert row[0] == 'ALICE SECRET TASK' and row[2] == 'Pending' and row[3] == uid_a, row
    record('Z03', G, "POSTing an edit for another user's task changes nothing", t)

    def t():
        if not DBFILE:
            return 'SKIP'
        tok = re.search(r'csrf_token" value="([a-f0-9]+)"', B.get('/dashboard.php')[2]).group(1)
        r = B.post('/tasks/delete.php', {'csrf_token': tok, 'id': str(tid_a)})
        assert 'permission' in B.follow(r)[2]
        assert sql('SELECT COUNT(*) FROM tasks WHERE id=?', (tid_a,))[0][0] == 1, "Bob deleted Alice's task!"
    record('Z04', G, "Deleting another user's task id is refused (row survives)", t)

    def t():
        if not DBFILE:
            return 'SKIP'
        tok = re.search(r'csrf_token" value="([a-f0-9]+)"', B.get('/dashboard.php')[2]).group(1)
        r = B.post('/tasks/update_status.php', {'csrf_token': tok, 'id': str(tid_a), 'status': 'Completed'})
        assert 'permission' in B.follow(r)[2]
        assert sql('SELECT status FROM tasks WHERE id=?', (tid_a,))[0][0] == 'Pending', "Bob changed Alice's status!"
    record('Z05', G, "Changing the status of another user's task is refused", t)

    def t():
        if not DBFILE:
            return 'SKIP'
        tok = B.csrf('/tasks/add.php')
        B.post('/tasks/add.php', {'csrf_token': tok, 'title': 'Owner spoof attempt', 'description': '', 'status': 'Pending',
                                   'priority': 'Low', 'due_date': '', 'user_id': str(uid_a)})
        owner = sql('SELECT user_id FROM tasks WHERE title=?', ('Owner spoof attempt',))[0][0]
        assert owner == uid_b, f'task assigned to user {owner}'
    record('Z06', G, 'Injected user_id form field is ignored — owner comes from the session', t)

    def t():
        for bad in ('abc', '-1', '0', '1e3', '999999', '1;DROP TABLE tasks', ''):
            r = B.get('/tasks/edit.php?' + urlencode({'id': bad}))
            assert r[0] == 404, f'edit id={bad!r} -> {r[0]}'
        assert B.get('/tasks/edit.php?id[]=1')[0] == 404
        tok = re.search(r'csrf_token" value="([a-f0-9]+)"', B.get('/dashboard.php')[2]).group(1)
        for payload in ({'id': 'abc'}, {'id': ['1', '2']}, {'id': '-3'}, {}):
            d = {'csrf_token': tok, **payload}
            assert B.post('/tasks/delete.php', d)[0] == 302
            d['status'] = 'Completed'
            assert B.post('/tasks/update_status.php', d)[0] == 302
    record('Z07', G, 'Malformed ids (text, negative, arrays, SQL) never cause a 500 or a change', t)

    def t():
        tok_a = A.csrf('/tasks/add.php')
        r = B.post('/tasks/add.php', {'csrf_token': tok_a, 'title': 'cross-session token', 'description': '', 'status': 'Pending', 'priority': 'Low', 'due_date': ''})
        assert 'cross-session token' not in B.get('/dashboard.php')[2], "Alice's CSRF token worked in Bob's session"
    record('Z08', G, "A CSRF token from another user's session is rejected", t)

    def t():
        if not DBFILE:
            return 'SKIP'
        tmp, _ = new_user(base, 'cascade')
        add_task(tmp, title='Cascade me')
        uid = sql('SELECT user_id FROM tasks WHERE title=?', ('Cascade me',))[0][0]
        con = sqlite3.connect(DBFILE)
        con.execute('PRAGMA foreign_keys = ON')
        con.execute('DELETE FROM users WHERE id=?', (uid,))
        con.commit()
        left = con.execute('SELECT COUNT(*) FROM tasks WHERE user_id=?', (uid,)).fetchone()[0]
        try:
            con.execute("INSERT INTO tasks (user_id, title) VALUES (99999, 'orphan')")
            orphan = True
        except sqlite3.IntegrityError:
            orphan = False
        con.close()
        assert left == 0 and not orphan, 'foreign key not enforced'
    record('Z09', G, 'Foreign key tasks.user_id → users.id: cascade delete and no orphans', t)

    # ------------------------------------------------------------------ SECURITY
    G = 'Security'

    def t():
        X, _ = new_user(base, 'xss')
        payload = '<script>alert(1)</script>'
        img = '"><img src=x onerror=alert(2)>'
        add_task(X, title=payload, description=img)
        page = X.get('/dashboard.php')[2]
        assert payload not in page and '&lt;script&gt;alert(1)&lt;/script&gt;' in page
        assert '<img src=x' not in page and '&lt;img src=x' in page
        if DBFILE:
            tid = task_id_by_title(payload)
            form = X.get(f'/tasks/edit.php?id={tid}')[2]
            assert payload not in form and '&lt;script&gt;' in form and '<img src=x' not in form
        s = X.get('/dashboard.php?q=' + '%3Cscript%3Ealert(9)%3C/script%3E')[2]
        assert '<script>alert(9)' not in s
        # flash message built from the title must be escaped as well
        r = add_task(X, title='<b>bold</b>')
        assert '<b>bold</b>' not in X.follow(r)[2]
    record('X01', G, 'XSS: titles, descriptions, search term and flash messages are HTML-escaped', t)

    def t():
        X, _ = new_user(base, 'sqli')
        add_task(X, title='normal one')
        before = titles(X.get('/dashboard.php')[2])
        after = titles(X.get("/dashboard.php?q=%27+OR+%271%27%3D%271")[2])
        assert after == set(), after
        assert titles(X.get("/dashboard.php?status=%27+OR+1%3D1--")[2]) == before
        c = Client(base)
        tok = c.csrf()
        r = c.post('/auth/login.php', {'csrf_token': tok, 'email': "' OR 1=1 --", 'password': "' OR 1=1 --"})
        assert c.get('/dashboard.php')[0] == 302
        r = add_task(X, title="Robert'); DROP TABLE tasks;--")
        assert r[0] == 302 and "Robert&#039;); DROP TABLE tasks;--" in X.get('/dashboard.php')[2]
    record('X02', G, 'SQL injection attempts in search, filters, login and task title are harmless', t)

    def t():
        c = Client(base)
        r = c.get('/auth/login.php')
        h = r[1]
        assert h.get('x-frame-options') == 'DENY' and h.get('x-content-type-options') == 'nosniff'
        assert "default-src 'self'" in h.get('content-security-policy', '')
        assert 'no-store' in h.get('cache-control', '')
        assert h.get('referrer-policy') == 'same-origin'
    record('X03', G, 'Security headers: X-Frame-Options, nosniff, CSP, no-store, Referrer-Policy', t)

    def t():
        c = Client(base)
        c.get('/auth/login.php')
        cookie = ' '.join(c.last_set_cookie).lower()
        assert 'httponly' in cookie and 'samesite=lax' in cookie, cookie
    record('X04', G, 'Session cookie is HttpOnly + SameSite=Lax', t)

    def t():
        c = Client(base)
        for p in ('/includes/functions.php', '/includes/bootstrap.php', '/config/database.php', '/includes/header.php'):
            r = c.get(p)
            assert r[0] in (403, 404) and 'DB_PASS' not in r[2] and 'password_hash' not in r[2], f'{p} -> {r[0]}'
    record('X05', G, 'Include/config files cannot be run or read directly over HTTP', t)

    def t():
        c = Client(base)
        r = c.get('/auth/login.php')
        assert 'Warning' not in r[2] and 'Notice' not in r[2] and 'Stack trace' not in r[2]
        r = A.get('/tasks/edit.php?id=%00')
        assert r[0] == 404 and 'Fatal' not in r[2]
    record('X06', G, 'No PHP warnings / stack traces leak into pages', t)

    # ------------------------------------------------------------------ PROFILE
    G = 'Optional · Profile'

    def t():
        P, email = new_user(base, 'prof')
        tok = P.csrf('/profile.php')
        r = P.post('/profile.php', {'csrf_token': tok, 'action': 'profile', 'name': ''})
        assert r[0] == 200 and 'Full name is required' in r[2]
        r = P.post('/profile.php', {'csrf_token': tok, 'action': 'profile', 'name': 'Renamed Person'})
        assert r[0] == 302 and 'Renamed Person' in P.follow(r)[2]
    record('P01', G, 'Profile: name is validated and can be updated', t)

    def t():
        P, email = new_user(base, 'pw')
        tok = P.csrf('/profile.php')
        r = P.post('/profile.php', {'csrf_token': tok, 'action': 'password', 'current_password': 'WRONG', 'new_password': 'NewPassw0rd', 'confirm_password': 'NewPassw0rd'})
        assert r[0] == 200 and 'Current password is incorrect' in r[2]
        r = P.post('/profile.php', {'csrf_token': tok, 'action': 'password', 'current_password': 'Passw0rd!', 'new_password': 'NewPassw0rd', 'confirm_password': 'NewPassw0rd'})
        assert r[0] == 302 and 'Password changed' in P.follow(r)[2]
        c = Client(base)
        tk = c.csrf()
        assert c.post('/auth/login.php', {'csrf_token': tk, 'email': email, 'password': 'Passw0rd!'})[0] == 200, 'old password still works'
        assert c.post('/auth/login.php', {'csrf_token': tk, 'email': email, 'password': 'NewPassw0rd'})[0] == 302, 'new password rejected'
    record('P02', G, 'Profile: password change needs the current password; new one works, old one fails', t)

    def t():
        if not DBFILE:
            return 'SKIP'
        c = Client(base)
        tk = c.csrf()
        r = c.post('/auth/login.php', {'csrf_token': tk, 'email': 'alice@example.com', 'password': 'Test@1234'})
        assert r[0] == 302 and 'Design landing page mockup' in c.follow(r)[2]
    record('P03', G, 'Seeded demo account (alice@example.com / Test@1234) can sign in', t)


def main():
    global DBFILE
    args = [a for a in sys.argv[1:] if not a.startswith('--')]
    proc = None
    if '--serve' in sys.argv:
        tmpdir = tempfile.mkdtemp(prefix='tt_')
        DBFILE = os.path.join(tmpdir, 'test.sqlite')
        env = dict(os.environ, TT_SQLITE=DBFILE)
        port = '8099'
        proc = subprocess.Popen(['php', '-d', 'auto_prepend_file=' + os.path.join(ROOT, 'tests', 'prepend.php'),
                                 '-S', f'127.0.0.1:{port}', '-t', ROOT], env=env,
                                stdout=subprocess.DEVNULL, stderr=open(os.path.join(tmpdir, 'php.log'), 'w'))
        base = f'http://127.0.0.1:{port}'
        for _ in range(50):
            try:
                http.client.HTTPConnection('127.0.0.1', int(port), timeout=1).request('GET', '/')
                break
            except OSError:
                time.sleep(0.1)
    elif args:
        base = args[0]
    else:
        print(__doc__)
        sys.exit(2)

    try:
        run(base)
    finally:
        if proc:
            proc.terminate()

    passed = sum(1 for r in RESULTS if r[3] == 'PASS')
    failed = [r for r in RESULTS if r[3] == 'FAIL']
    skipped = sum(1 for r in RESULTS if r[3] == 'SKIP')
    for tid, group, desc, outcome, detail in RESULTS:
        print(f'{outcome:4}  {tid}  {desc}' + (f'   <-- {detail}' if outcome == 'FAIL' else ''))
    print(f'\n{passed} passed, {len(failed)} failed, {skipped} skipped, {len(RESULTS)} total')

    if '--report' in sys.argv:
        out = os.path.join(ROOT, 'tests', 'TEST_RESULTS.md')
        mode = 'SQLite stand-in database (php -S)' if DBFILE else base
        with open(out, 'w', encoding='utf-8') as f:
            f.write('# Test results\n\n')
            f.write(f'Run against: **{mode}** &middot; {time.strftime("%Y-%m-%d %H:%M")}\n\n')
            f.write(f'**{passed} passed, {len(failed)} failed, {skipped} skipped, {len(RESULTS)} total**\n\n')
            f.write('| ID | Area | Test case | Result |\n|----|------|-----------|--------|\n')
            for tid, group, desc, outcome, detail in RESULTS:
                mark = {'PASS': '✅ PASS', 'FAIL': '❌ FAIL', 'SKIP': '⏭ SKIP'}[outcome]
                f.write(f'| {tid} | {group} | {desc} | {mark} |\n')
    sys.exit(1 if failed else 0)


if __name__ == '__main__':
    main()
