<?php

namespace App\Commands;

use App\Libraries\UploadStorage;
use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;

/**
 * Copies uploads saved by the pre-release code (project-root uploads/)
 * into private storage (writable/uploads/). Originals are NOT deleted;
 * remove them manually once the copy is verified and backed up.
 *
 *   php spark uploads:import-legacy
 */
class ImportLegacyUploads extends BaseCommand
{
    protected $group       = 'Pasa-Buy';
    protected $name        = 'uploads:import-legacy';
    protected $description = 'Copy legacy root uploads/ files into private writable/uploads storage.';

    public function run(array $params)
    {
        $copied = 0;
        $skipped = 0;

        foreach ([UploadStorage::PROFILE_PHOTOS, UploadStorage::MESSAGE_FILES] as $dir) {
            $source = ROOTPATH . 'uploads' . DIRECTORY_SEPARATOR . $dir . DIRECTORY_SEPARATOR;
            $target = UploadStorage::directory($dir);

            if (! is_dir($source)) {
                continue;
            }

            if (! is_dir($target)) {
                mkdir($target, 0755, true);
            }

            foreach (new \DirectoryIterator($source) as $file) {
                if (! $file->isFile() || ! preg_match('/^[A-Za-z0-9_-]+\.[A-Za-z0-9]{1,5}$/', $file->getFilename())) {
                    continue;
                }

                $destination = $target . $file->getFilename();

                if (is_file($destination)) {
                    $skipped++;

                    continue;
                }

                copy($file->getPathname(), $destination) ? $copied++ : CLI::error('Could not copy ' . $dir . '/' . $file->getFilename());
            }
        }

        CLI::write("Copied {$copied} file(s); {$skipped} already present. Originals left in place.", 'green');

        return EXIT_SUCCESS;
    }
}
