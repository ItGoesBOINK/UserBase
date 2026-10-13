<?php

declare(strict_types=1);

use UserBase\Database\DatabaseConnection;

require_once dirname(__DIR__) . '/src/bootstrap.php';

try {
    $database = new DatabaseConnection($config);
    $pdo = $database->GetConnection();

    // Keep database timestamps consistent with application UTC timestamps
    $pdo->exec("SET time_zone = '+00:00'");

    $migrationDirectory = dirname(__DIR__) . '/database/migrations';

    if (!is_dir($migrationDirectory)) {
        throw new RuntimeException("Migration directory does not exist: [{$migrationDirectory}]");
    }

    // This table records which migrations have been applied and
    // detects migration fils that have changed after application
    $pdo->exec(
        "CREATE TABLE IF NOT EXISTS schema_migrations (
            migration VARCHAR(255)
                CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
            checksum CHAR(64)
                CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
            applied_at DATETIME(6) NOT NULL
                DEFAULT CURRENT_TIMESTAMP(6),
            PRIMARY KEY (migration)
        ) ENGINE=InnoDB"
    );

    $files = glob($migrationDirectory . '/*.sql');

    if ($files === false)
    {
        throw new RuntimeException('Could not read the migration directory');
    }

    sort($files, SORT_STRING);

    if ($files === []) {
        echo "No Migration Files found.\n";
        exit(0);
    }

    $findMigration = $pdo->prepare('SELECT checksum FROM schema_migrations WHERE migration = :migration');
    $recordMigration = $pdo->prepare("INSERT INTO schema_migrations (migration, checksum) VALUES (:migration, :checksum)");

    foreach ($files as $file)
    {
        $migration = basename($file);

        if (!preg_match('/^[0-9]{3,}_[a-z0-9_]+\.sql$/D', $migration))
        {
            throw new RuntimeException("Invalid migration filename: [{$migration}]!");
        }

        $sql = file_get_contents($file);

        if ($sql === false || trim($sql) === '') {
            throw new RuntimeException("Migration is empty or unreadable: [{$migration}]");
        }

        $checksum = hash('sha256', $sql);

        $findMigration->execute(['migration' => $migration]);

        $appliedChecksum = $findMigration->fetchColumn();

        if ($appliedChecksum !== false)
        {
            if (!hash_equals($appliedChecksum, $checksum)) {
                throw new RuntimeException("Applied migration was modified!: [{$migration}]");
            }

            echo "Already applied: [{$migration}]\n";
            continue;
        }

        echo "Applying: [{$migration}]\n";

        // Migration files are trusted, version-controlled SQL.
        // MySQL DDL is not generally transactionally reversible.
        $pdo->exec($sql);

        $recordMigration->execute(['migration' => $migration, 'checksum' => $checksum]);

        echo "Applied: [{$migration}]\n";

    }

    echo "Migration process completed successfully.\n";

} catch (Throwable $exception) {
    fwrite(STDERR, "Migration Failed: [{$exception->getMessage()}]\n");

    exit(1);
}

?>