<?php
require 'includes/functions.php'; // starts the session

// End only the student login (an admin in the same browser stays logged in)
unset($_SESSION['student_id']);
session_regenerate_id(true);
set_flash('success', 'You have been logged out.');
header('Location: login.php');
exit;
