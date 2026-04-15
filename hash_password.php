<?php
$password = $argv[1] ?? 'admin123';
echo password_hash($password, PASSWORD_DEFAULT) . "\n";
