<?php
/**
 * Run this once to set up the database and create the admin user.
 * Usage: php migrations/setup.php
 *
 * After running, DELETE this file from the server.
 */

require_once __DIR__ . '/../config/database.php';

$pdo = getDbConnection();

// Run schema
echo "Running schema migration...\n";
$sql = file_get_contents(__DIR__ . '/001_schema.sql');

// Split on semicolons and run each statement
$statements = array_filter(array_map('trim', explode(';', $sql)));
foreach ($statements as $stmt) {
    if (empty($stmt) || strpos($stmt, '--') === 0) continue;
    try {
        $pdo->exec($stmt);
    } catch (PDOException $e) {
        // Skip "already exists" errors, fail on others
        if (strpos($e->getMessage(), 'already exists') === false
            && strpos($e->getMessage(), 'Duplicate entry') === false) {
            echo "Error: " . $e->getMessage() . "\n";
        }
    }
}
echo "Schema applied.\n";

// Create admin user with proper hash
$adminEmail = 'admin@law-crm.com';
$adminPassword = 'changeme123';
$hash = password_hash($adminPassword, PASSWORD_DEFAULT);

$stmt = $pdo->prepare('SELECT id FROM users WHERE email = ?');
$stmt->execute([$adminEmail]);
if ($stmt->fetch()) {
    $stmt = $pdo->prepare('UPDATE users SET password_hash = ? WHERE email = ?');
    $stmt->execute([$hash, $adminEmail]);
    echo "Admin user updated.\n";
} else {
    $stmt = $pdo->prepare('INSERT INTO users (name, email, password_hash) VALUES (?, ?, ?)');
    $stmt->execute(['Admin', $adminEmail, $hash]);
    echo "Admin user created.\n";
}

echo "\n";
echo "Login credentials:\n";
echo "  Email:    $adminEmail\n";
echo "  Password: $adminPassword\n";
echo "\n";
echo "CHANGE THE PASSWORD after first login.\n";
echo "DELETE this file (migrations/setup.php) from your server.\n";
