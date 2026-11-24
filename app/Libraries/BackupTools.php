<?php

namespace App\Libraries;

use PharData;
use RuntimeException;

/**
 * Helpers for backup:create / backup:restore. Credentials are passed to the
 * MySQL tools through a temporary --defaults-extra-file (mode 0600), never
 * on the command line where other users could read them.
 */
final class BackupTools
{
    public const TABLES = [
        'users', 'requests', 'accepted_requests', 'payments', 'messages', 'notifications',
        'request_notifications', 'referrals', 'user_ratings', 'website_ratings',
        'wallet_topups', 'user_blocks', 'user_reports', 'chat_deletions', 'price_submissions', 'migrations',
    ];

    /** @param array<string, mixed> $db */
    public static function dumpDatabase(array $db, string $target): void
    {
        $credentials = self::credentialsFile($db);

        try {
            $command = [
                getenv('MYSQLDUMP') ?: 'mysqldump',
                '--defaults-extra-file=' . $credentials,
                '--single-transaction', '--quick', '--routines', '--triggers', '--no-tablespaces',
                '--default-character-set=utf8mb4',
                $db['database'],
            ];

            $process = proc_open($command, [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);

            if (! is_resource($process)) {
                throw new RuntimeException('Could not start mysqldump.');
            }

            $gz = gzopen($target, 'wb6');

            while (! feof($pipes[1])) {
                gzwrite($gz, (string) fread($pipes[1], 1 << 16));
            }

            gzclose($gz);
            $errors = stream_get_contents($pipes[2]);
            fclose($pipes[1]);
            fclose($pipes[2]);

            if (proc_close($process) !== 0) {
                throw new RuntimeException('mysqldump failed: ' . trim((string) $errors));
            }
        } finally {
            @unlink($credentials);
        }
    }

    /** @param array<string, mixed> $db */
    public static function restoreDatabase(array $db, string $source): void
    {
        $credentials = self::credentialsFile($db);

        try {
            $command = [
                getenv('MYSQL') ?: 'mysql',
                '--defaults-extra-file=' . $credentials,
                '--default-character-set=utf8mb4',
                $db['database'],
            ];

            $process = proc_open($command, [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);

            if (! is_resource($process)) {
                throw new RuntimeException('Could not start mysql client.');
            }

            $gz = gzopen($source, 'rb');

            while (! gzeof($gz)) {
                fwrite($pipes[0], (string) gzread($gz, 1 << 16));
            }

            gzclose($gz);
            fclose($pipes[0]);
            stream_get_contents($pipes[1]);
            $errors = stream_get_contents($pipes[2]);
            fclose($pipes[1]);
            fclose($pipes[2]);

            if (proc_close($process) !== 0) {
                throw new RuntimeException('mysql restore failed: ' . trim((string) $errors));
            }
        } finally {
            @unlink($credentials);
        }
    }

    /** Returns the number of files archived. */
    public static function archiveUploads(string $target): int
    {
        $source = WRITEPATH . 'uploads';
        $tar    = substr($target, 0, -3); // strip .gz

        @unlink($tar);
        @unlink($target);

        $archive = new PharData($tar);
        $count   = 0;

        if (is_dir($source)) {
            $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($source, \FilesystemIterator::SKIP_DOTS));

            foreach ($iterator as $file) {
                if ($file->isFile()) {
                    $relative = str_replace('\\', '/', substr($file->getPathname(), strlen($source) + 1));
                    $archive->addFile($file->getPathname(), $relative);
                    $count++;
                }
            }
        }

        if ($count === 0) {
            $archive->addFromString('.empty', '');
        }

        $archive->compress(\Phar::GZ);
        unset($archive);
        @unlink($tar);

        return $count;
    }

    /** Restores uploads; returns where the previous uploads were moved, if any. */
    public static function restoreUploads(string $source): ?string
    {
        $target   = WRITEPATH . 'uploads';
        $previous = null;

        if (is_dir($target)) {
            $previous = WRITEPATH . 'uploads.before-restore-' . date('Ymd-His');

            if (! rename($target, $previous)) {
                throw new RuntimeException('Could not move current uploads aside.');
            }
        }

        mkdir($target, 0755, true);
        (new PharData($source))->extractTo($target, null, true);
        @unlink($target . DIRECTORY_SEPARATOR . '.empty');

        return $previous;
    }

    /**
     * Table names a backup's SQL dump creates (from its CREATE TABLE lines).
     *
     * @return list<string> sorted
     */
    public static function dumpTables(string $source): array
    {
        $gz = gzopen($source, 'rb');

        if ($gz === false) {
            throw new RuntimeException('Cannot read ' . basename($source) . '.');
        }

        $tables = [];

        while (($line = gzgets($gz, 1 << 20)) !== false) {
            if (preg_match('/^CREATE TABLE (?:IF NOT EXISTS )?`([^`]+)`/', $line, $m) === 1) {
                $tables[$m[1]] = true;
            }
        }

        gzclose($gz);
        $tables = array_keys($tables);
        sort($tables);

        return $tables;
    }

    /**
     * Base tables currently in the configured database (fresh query, not the
     * connection's cached table list, so it is accurate right after a restore).
     *
     * @return list<string> sorted
     */
    public static function databaseTables(): array
    {
        $rows   = db_connect()->query("SHOW FULL TABLES WHERE Table_type = 'BASE TABLE'")->getResultArray();
        $tables = array_map(static fn (array $row): string => (string) reset($row), $rows);
        sort($tables);

        return $tables;
    }

    /**
     * Tables in the target that the backup would not replace. A SQL dump only
     * drops and recreates its own tables, so restoring it over a newer schema
     * leaves these behind while row counts of the backed-up tables still match.
     *
     * @param list<string> $targetTables
     * @param list<string> $dumpTables
     *
     * @return list<string>
     */
    public static function tablesOutsideBackup(array $targetTables, array $dumpTables): array
    {
        return array_values(array_diff($targetTables, $dumpTables));
    }

    /** @return array<string, int> */
    public static function rowCounts(): array
    {
        $db     = db_connect();
        $counts = [];

        foreach (self::TABLES as $table) {
            $counts[$table] = $db->tableExists($table) ? $db->table($table)->countAllResults() : -1;
        }

        return $counts;
    }

    public static function writeChecksums(string $directory): void
    {
        $lines = [];

        foreach (['database.sql.gz', 'uploads.tar.gz', 'manifest.json'] as $file) {
            $lines[] = hash_file('sha256', $directory . DIRECTORY_SEPARATOR . $file) . '  ' . $file;
        }

        file_put_contents($directory . DIRECTORY_SEPARATOR . 'SHA256SUMS', implode("\n", $lines) . "\n");
    }

    public static function verifyChecksums(string $directory): void
    {
        $sums = @file($directory . DIRECTORY_SEPARATOR . 'SHA256SUMS', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);

        if ($sums === false || count($sums) !== 3) {
            throw new RuntimeException('SHA256SUMS missing or incomplete.');
        }

        foreach ($sums as $line) {
            [$hash, $file] = preg_split('/\s+/', trim($line), 2);
            $path          = $directory . DIRECTORY_SEPARATOR . basename($file);

            if (! is_file($path) || ! hash_equals($hash, hash_file('sha256', $path))) {
                throw new RuntimeException('Checksum mismatch for ' . $file);
            }
        }
    }

    /** @return list<string> removed directories */
    public static function prune(string $root, string $database, int $keep): array
    {
        $dirs = glob(rtrim($root, '/\\') . DIRECTORY_SEPARATOR . 'pasabuy-' . $database . '-*', GLOB_ONLYDIR) ?: [];
        rsort($dirs);
        $removed = [];

        foreach (array_slice($dirs, $keep) as $dir) {
            array_map('unlink', glob($dir . DIRECTORY_SEPARATOR . '*') ?: []);
            rmdir($dir);
            $removed[] = $dir;
        }

        return $removed;
    }

    public static function gitCommit(): ?string
    {
        $head = @file_get_contents(ROOTPATH . '.git/HEAD');

        if ($head === false) {
            return @file_get_contents(ROOTPATH . 'RELEASE') ?: null;
        }

        if (str_starts_with($head, 'ref: ')) {
            return trim((string) @file_get_contents(ROOTPATH . '.git/' . trim(substr($head, 5)))) ?: null;
        }

        return trim($head);
    }

    public static function latestMigration(): ?string
    {
        $db = db_connect();

        if (! $db->tableExists('migrations')) {
            return null;
        }

        $row = $db->table('migrations')->select('version, class')->orderBy('version', 'DESC')->get(1)->getRowArray();

        return $row ? $row['version'] . ' ' . $row['class'] : null;
    }

    /** @param array<string, mixed> $db */
    private static function credentialsFile(array $db): string
    {
        $path = tempnam(sys_get_temp_dir(), 'pbmy');
        chmod($path, 0600);

        $quote = static fn ($v): string => '"' . addcslashes((string) $v, "\\\"") . '"';

        file_put_contents($path, "[client]\n"
            . 'host=' . $quote($db['hostname']) . "\n"
            . 'port=' . (int) ($db['port'] ?? 3306) . "\n"
            . 'user=' . $quote($db['username']) . "\n"
            . 'password=' . $quote($db['password']) . "\n");

        return $path;
    }
}
