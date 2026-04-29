<?php
require_once 'src/Auth.php';

\App\Auth::logout();
header('Location: login.php');
exit;
