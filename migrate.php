<?php
declare(strict_types=1);

require_once __DIR__ . '/db.php';

$dataDir = __DIR__ . '/data';
if (!is_dir($dataDir)) {
    if (!mkdir($dataDir, 0775, true) && !is_dir($dataDir)) {
        fwrite(STDERR, "Failed to create data directory: {$dataDir}\n");
        exit(1);
    }
    echo "Created data directory: {$dataDir}\n";
} else {
    echo "Data directory exists: {$dataDir}\n";
}

$pdo = db();
$requiredTables = ['users', 'fields', 'crops', 'tasks', 'diary_entries', 'materials', 'pests', 'shipments'];
$existsStmt = $pdo->prepare("SELECT name FROM sqlite_master WHERE type='table' AND name = :name");

$missingBefore = [];
foreach ($requiredTables as $table) {
    $existsStmt->execute([':name' => $table]);
    if ($existsStmt->fetchColumn() === false) {
        $missingBefore[] = $table;
    }
}

if ($missingBefore === []) {
    echo "All required tables already exist. Re-applying schema/seed for safety.\n";
} else {
    echo "Missing tables detected before migration: " . implode(', ', $missingBefore) . "\n";
}

$schemaFile = __DIR__ . '/db/schema.sql';
$seedFile = __DIR__ . '/db/seed.sql';

if (!is_file($schemaFile) || !is_readable($schemaFile)) {
    fwrite(STDERR, "schema.sql not found or unreadable at {$schemaFile}\n");
    exit(1);
}
if (!is_file($seedFile) || !is_readable($seedFile)) {
    fwrite(STDERR, "seed.sql not found or unreadable at {$seedFile}\n");
    exit(1);
}

$schemaSql = file_get_contents($schemaFile);
$seedSql = file_get_contents($seedFile);
if ($schemaSql === false || $seedSql === false) {
    fwrite(STDERR, "Failed to read schema or seed SQL files.\n");
    exit(1);
}

try {
    $pdo->beginTransaction();
    $pdo->exec($schemaSql);
    echo "Applied schema.sql\n";

    $pdo->exec($seedSql);
    echo "Applied seed.sql\n";

    $pdo->commit();
} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    fwrite(STDERR, "Migration failed: " . $e->getMessage() . "\n");
    exit(1);
}

$missingAfter = [];
foreach ($requiredTables as $table) {
    $existsStmt->execute([':name' => $table]);
    if ($existsStmt->fetchColumn() === false) {
        $missingAfter[] = $table;
        echo "Missing after migration: {$table}\n";
    } else {
        echo "Ready table: {$table}\n";
    }
}

if ($missingAfter !== []) {
    fwrite(STDERR, "Table check failed after migration: " . implode(', ', $missingAfter) . "\n");
    exit(1);
}

echo "Migration completed. SQLite path: " . DB_PATH . "\n";
