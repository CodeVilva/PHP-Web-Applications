<?php
require_once __DIR__ . '/../config/config.php';
session_unset();
session_destroy();
session_start();
set_flash('info', 'You have been signed out successfully.');
redirect('auth/login.php');
