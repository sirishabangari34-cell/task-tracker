<?php
require_once __DIR__ . '/../includes/bootstrap.php';

// Logging out changes state, so it only works through a POST form carrying a valid CSRF token.
// (A plain link/GET request, or an <img src="logout.php"> planted on another site, does nothing.)
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect(current_user() ? 'dashboard.php' : 'auth/login.php');
}

require_csrf(current_user() ? 'dashboard.php' : 'auth/login.php');

logout_user();
flash('success', 'You have been signed out securely.');
redirect('auth/login.php');
