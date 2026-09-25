<?php
require_once __DIR__ . '/../config/config.php';
if (!is_logged_in()) {
    set_flash('warning', 'Please sign in to continue.');
    redirect('auth/login.php');
}
