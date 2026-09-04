<?php
/**
 * Schema migration runner.
 *
 * Records which migration-*.sql files have been applied, so that
 * "which schema version is this database?" has an answer you can read.
 * Two restores of an unlabelled dump have already rolled this database
 * backwards, and both times the only symptom was a blank page.
 *
 * USAGE (from the project root)
 *   php scripts/migrate.php status
 *   php scripts/migrate.php baseline      # adopt on an existing database
 *   php scripts/migrate.php migrate
 *
 * ENVIRONMENT
 *   MYSQL_BIN    path to the mysql client   (default: mysql)
 *   ADMIN_USER   MySQL user able to ALTER   (default: root)
 *
 * Migrations run as an ADMINISTRATIVE user, never as the application's
 * least-privilege account - that account deliberately cannot ALTER, so
 * an attacker who reaches the app cannot change its schema either.
 *
 * CLI only.
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit("This script is CLI only.\n");
}

$root = dirname(__DIR__);
require_once $root . '/config.php';
require_once $root . '/includes/db.php';

$command  = $argv[1] ?? 'status';
$mysqlBin = getenv('MYSQL_BIN') ?: 'mysql';
$adminUser = getenv('ADMIN_USER') ?: 'root';

$db = getDB();

// ── Is the ledger itself present? ────────────────────────────
$hasLedger = (bool) $db->query(
    "SELECT COUNT(*) FROM information_schema.tables
     WHERE table_schema = DATABASE() AND table_name = 'schema_migrations'"
)->fetchColumn();

if (!$hasLedger) {
    fwrite(STDERR,
        "The schema_migrations table does not exist.\n" .
        "Bootstrap it first:\n\n" .
        "  mysql -u $adminUser < migration-schema-ledger.sql\n\n" .
        "then run:  php scripts/migrate.php baseline\n");
    exit(1);
}

// ── Gather files and ledger state ────────────────────────────
$files = glob($root . '/migration-*.sql');
sort($files);

$applied = [];
foreach ($db->query("SELECT filename, checksum, applied_at, method FROM schema_migrations")->fetchAll() as $row) {
    $applied[$row['filename']] = $row;
}

$pending = [];
$changed = [];

foreach ($files as $path) {
    $name = basename($path);
    $sum  = migrationChecksum($path);

    if (!isset($applied[$name])) {
        $pending[$name] = $path;
    } elseif ($applied[$name]['checksum'] !== $sum) {
        // The file was edited after it was applied, so the database and
        // the repository no longer describe the same schema.
        $changed[$name] = $path;
    }
}

$orphans = array_diff(array_keys($applied), array_map('basename', $files));

/**
 * Checksum of a migration's CONTENT, independent of line endings.
 *
 * hash_file() over raw bytes is not stable here: git normalises line
 * endings on checkout, so the same file is LF in the repository and
 * CRLF in a Windows working copy. Hashing raw bytes made a migration
 * report itself as "edited" straight after being committed - a false
 * alarm, and a checker that cries wolf is one nobody reads.
 *
 * Normalising CRLF and lone CR to LF keeps the checksum meaningful: it
 * still catches a real content edit and ignores one a text editor made.
 */
function migrationChecksum(string $path): string
{
    $content = (string) file_get_contents($path);
    // \R matches CRLF, CR or LF - written as a regex so the
    // pattern itself cannot be mangled by line-ending conversion.
    $content = preg_replace('/\R/u', "\n", $content);

    return hash('sha256', $content);
}

/**
 * Apply one migration file through the mysql client.
 *
 * Shelling out rather than using PDO because these files contain
 * multi-statement SQL and PREPARE/EXECUTE blocks that PDO will not run
 * in a single call.
 */
function applyFile(string $path, string $mysqlBin, string $adminUser): bool
{
    $cmd = sprintf(
        '%s -u %s --default-character-set=utf8mb4 < %s 2>&1',
        escapeshellarg($mysqlBin),
        escapeshellarg($adminUser),
        escapeshellarg($path)
    );

    exec($cmd, $output, $code);

    if ($code !== 0) {
        fwrite(STDERR, "    " . implode("\n    ", $output) . "\n");
    }

    return $code === 0;
}

// ── Commands ─────────────────────────────────────────────────
switch ($command) {

    case 'status':
        printf("Database: %s\n\n", DB_NAME);

        printf("Applied (%d)\n", count($applied));
        foreach ($files as $path) {
            $name = basename($path);
            if (!isset($applied[$name])) continue;
            printf("  %-38s %s  %s%s\n",
                $name,
                substr($applied[$name]['applied_at'], 0, 16),
                $applied[$name]['method'],
                isset($changed[$name]) ? '   *** FILE CHANGED SINCE APPLIED ***' : '');
        }

        printf("\nPending (%d)\n", count($pending));
        foreach ($pending as $name => $_) {
            printf("  %s\n", $name);
        }
        if (!$pending) echo "  none\n";

        if ($orphans) {
            printf("\nRecorded but missing from disk (%d)\n", count($orphans));
            foreach ($orphans as $name) printf("  %s\n", $name);
        }

        if ($changed) {
            printf("\nWARNING: %d applied migration(s) have been edited since they ran.\n", count($changed));
            echo "The database and the repository no longer agree. Review before deploying.\n";
            exit(2);
        }

        exit($pending ? 1 : 0);

    case 'baseline':
        // Adopting the ledger on a database where the migrations were
        // already applied by hand. Records them WITHOUT running them.
        if (!$pending) {
            echo "Nothing to baseline - every migration is already recorded.\n";
            exit(0);
        }

        echo "Recording these as already applied, WITHOUT running them:\n";
        foreach ($pending as $name => $_) echo "  $name\n";
        echo "\nOnly do this if the database already has these changes.\n";
        echo "Type 'baseline' to confirm: ";

        if (trim((string) fgets(STDIN)) !== 'baseline') {
            echo "Aborted.\n";
            exit(1);
        }

        $stmt = $db->prepare(
            "INSERT INTO schema_migrations (filename, checksum, method) VALUES (?, ?, 'baseline')"
        );
        foreach ($pending as $name => $path) {
            $stmt->execute([$name, migrationChecksum($path)]);
            echo "  recorded $name\n";
        }
        printf("\n%d migration(s) baselined.\n", count($pending));
        exit(0);

    case 'migrate':
        if (!$pending) {
            echo "Nothing to apply. Schema is up to date.\n";
            exit(0);
        }

        printf("Applying %d migration(s) as '%s'.\n\n", count($pending), $adminUser);
        echo "Take a backup first:  ./scripts/backup.sh\n";
        echo "Type 'migrate' to confirm: ";

        if (trim((string) fgets(STDIN)) !== 'migrate') {
            echo "Aborted.\n";
            exit(1);
        }

        $stmt = $db->prepare(
            "INSERT INTO schema_migrations (filename, checksum, method) VALUES (?, ?, 'applied')"
        );

        foreach ($pending as $name => $path) {
            printf("  %-38s ", $name);

            if (!applyFile($path, $mysqlBin, $adminUser)) {
                echo "FAILED\n\nStopped. Earlier migrations in this run are already recorded.\n";
                exit(1);
            }

            $stmt->execute([$name, migrationChecksum($path)]);
            echo "ok\n";
        }

        printf("\n%d migration(s) applied.\n", count($pending));
        exit(0);

    case 'rehash':
        // One-time repair after the checksum method changed, or after a
        // deliberate reformat. Records the CURRENT content as correct, so
        // it silences the change detector - only run it when you already
        // know the files are right.
        if (!$changed) {
            echo "Nothing to rehash - every checksum already matches.
";
            exit(0);
        }

        echo "These recorded checksums will be replaced with the current content:
";
        foreach ($changed as $name => $_) echo "  $name
";
        echo "
Only do this if you know the current files are correct.
";
        echo "Type 'rehash' to confirm: ";

        if (trim((string) fgets(STDIN)) !== 'rehash') {
            echo "Aborted.
";
            exit(1);
        }

        $stmt = $db->prepare("UPDATE schema_migrations SET checksum = ? WHERE filename = ?");
        foreach ($changed as $name => $path) {
            $stmt->execute([migrationChecksum($path), $name]);
            echo "  rehashed $name
";
        }
        printf("
%d checksum(s) updated.
", count($changed));
        exit(0);


    default:
        fwrite(STDERR, "Unknown command '$command'. Use: status | baseline | migrate\n");
        exit(1);
}
