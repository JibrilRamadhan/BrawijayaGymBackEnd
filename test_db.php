<?php
try {
    new PDO('mysql:host=127.0.0.1;port=3306', 'root', '');
    echo "MySQL is running locally!\n";
} catch (Exception $e) {
    echo "MySQL error: " . $e->getMessage() . "\n";
}
