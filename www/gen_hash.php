<?php
$newPassword = "TuNuevaPassword123!";   // cámbiala
echo password_hash($newPassword, PASSWORD_BCRYPT) . PHP_EOL;