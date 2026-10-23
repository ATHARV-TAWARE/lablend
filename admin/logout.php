<?php
require '../includes/functions.php';

// End only the admin login (a student logged in in the same browser stays logged in)
unset($_SESSION['admin_logged_in'], $_SESSION['admin_username']);
session_regenerate_id(true);
header('Location: login.php');
exit;
