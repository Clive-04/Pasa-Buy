<?php

namespace App\Commands;

use App\Libraries\BackupTools;
use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;
use Throwable;

/**
 * Restores a backup made by backup:create into the CONFIGURED database
 * (database.default.* of the current environment) and writable/uploads.
 *
 *   php spark backup:restore /var/backups/pasabuy/pasabuy-x-20261122-120000 --confirm <database-name>
 *
 * Safety:
 *  - checksums are verified before anything is touched;
 *  - --confirm must repeat the target database name exactly;
 *  - current uploads are moved aside (writable/uploads.before-restore-<ts>),
 *    not deleted.
 *
 * Set MYSQL to the mysql client binary if it is not on PATH.
 */
class BackupRestore extends BaseCommand
{
    protected $group       = 'Pasa-Buy';
    protected $name        = 'backup:restore';
    protected $description = 'Restore a backup:create backup (DESTRUCTIVE for the target database).';
    protected $usage       = 'backup:restore <backup-dir> --confirm <database-name>';
    protected $options     = ['--confirm' => 'Must equal the configured target database name.'];

    public function run(array $params)
    {
        $directory = $params[0] ?? null;
        $db        = config('Database')->{config('Database')->defaultGroup};

        if ($directory === null || ! is_dir($directory)) {
            CLI::error('Usage: php spark ' . $this->usage);

            return EXIT_USER_INPUT;
        }

        if (CLI::getOption('confirm') !== $db['database']) {
            CLI::error('Refusing to restore: pass --confirm ' . $db['database'] . ' to overwrite that database.');

            return EXIT_USER_INPUT;
        }

        try {
            CLI::write('Verifying checksums…');
            BackupTools::verifyChecksums($directory);

            $manifest = json_decode((string) file_get_contents($directory . DIRECTORY_SEPARATOR . 'manifest.json'), true);

            CLI::write('Restoring database into ' . $db['database'] . '…');
            BackupTools::restoreDatabase($db, $directory . DIRECTORY_SEPARATOR . 'database.sql.gz');

            CLI::write('Restoring uploads…');
            $previous = BackupTools::restoreUploads($directory . DIRECTORY_SEPARATOR . 'uploads.tar.gz');

            if ($previous !== null) {
                CLI::write('Previous uploads kept at ' . $previous);
            }

            $counts   = BackupTools::rowCounts();
            $expected = $manifest['row_counts'] ?? [];

            if ($counts !== $expected) {
                CLI::error('Row counts differ from the manifest: ' . json_encode(['expected' => $expected, 'actual' => $counts]));

                return EXIT_ERROR;
            }

            CLI::write('Restore verified: row counts match the manifest ' . json_encode($counts), 'green');

            return EXIT_SUCCESS;
        } catch (Throwable $e) {
            CLI::error('Restore failed: ' . $e->getMessage());

            return EXIT_ERROR;
        }
    }
}
