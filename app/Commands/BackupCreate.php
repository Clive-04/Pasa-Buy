<?php

namespace App\Commands;

use App\Libraries\BackupTools;
use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;
use Throwable;

/**
 * Creates a consistent, checksummed backup of the database and private
 * uploads:
 *
 *   php spark backup:create /var/backups/pasabuy
 *
 * Produces <dir>/pasabuy-<db>-<timestamp>/ containing database.sql.gz,
 * uploads.tar.gz, manifest.json and SHA256SUMS. The dump uses
 * --single-transaction, so the site can stay online.
 *
 * Set MYSQLDUMP to the mysqldump binary if it is not on PATH.
 */
class BackupCreate extends BaseCommand
{
    protected $group       = 'Pasa-Buy';
    protected $name        = 'backup:create';
    protected $description = 'Back up the database and private uploads (checksummed).';
    protected $usage       = 'backup:create <backup-root> [--keep N]';
    protected $options     = ['--keep' => 'Delete older backups of this database beyond the newest N (default: keep all).'];

    public function run(array $params)
    {
        $root = $params[0] ?? null;

        if ($root === null) {
            CLI::error('Usage: php spark ' . $this->usage);

            return EXIT_USER_INPUT;
        }

        try {
            $db        = config('Database')->{config('Database')->defaultGroup};
            $stamp     = date('Ymd-His');
            $directory = rtrim($root, '/\\') . DIRECTORY_SEPARATOR . 'pasabuy-' . $db['database'] . '-' . $stamp;

            if (! is_dir($directory) && ! mkdir($directory, 0700, true)) {
                throw new \RuntimeException('Cannot create ' . $directory);
            }

            CLI::write('Dumping database ' . $db['database'] . '…');
            BackupTools::dumpDatabase($db, $directory . DIRECTORY_SEPARATOR . 'database.sql.gz');

            CLI::write('Archiving private uploads…');
            $files = BackupTools::archiveUploads($directory . DIRECTORY_SEPARATOR . 'uploads.tar.gz');

            $counts = BackupTools::rowCounts();

            file_put_contents($directory . DIRECTORY_SEPARATOR . 'manifest.json', json_encode([
                'created_at'   => date(DATE_ATOM),
                'database'     => $db['database'],
                'environment'  => ENVIRONMENT,
                'git_commit'   => BackupTools::gitCommit(),
                'migrations'   => BackupTools::latestMigration(),
                'row_counts'   => $counts,
                'upload_files' => $files,
            ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

            BackupTools::writeChecksums($directory);

            CLI::write('Backup complete: ' . $directory, 'green');
            CLI::write('Rows: ' . json_encode($counts));

            $keep = (int) (CLI::getOption('keep') ?? 0);

            if ($keep > 0) {
                foreach (BackupTools::prune($root, $db['database'], $keep) as $removed) {
                    CLI::write('Pruned old backup ' . $removed);
                }
            }

            return EXIT_SUCCESS;
        } catch (Throwable $e) {
            CLI::error('Backup failed: ' . $e->getMessage());

            return EXIT_ERROR;
        }
    }
}
