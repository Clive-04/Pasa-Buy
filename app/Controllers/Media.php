<?php

namespace App\Controllers;

use App\Libraries\UploadStorage;
use CodeIgniter\Exceptions\PageNotFoundException;

/**
 * Serves profile photos from private storage to signed-in users only.
 * Only names that belong to a user record are served, so the endpoint
 * cannot be used to read arbitrary files.
 */
class Media extends BaseController
{
    public function avatar(string $name)
    {
        $owned = db_connect()->table('users')->where('profile_photo', $name)->countAllResults() > 0;
        $path  = $owned ? UploadStorage::path(UploadStorage::PROFILE_PHOTOS, $name) : null;

        if ($path === null) {
            throw PageNotFoundException::forPageNotFound();
        }

        return $this->response
            ->setHeader('Content-Type', UploadStorage::contentType($name))
            ->setHeader('Content-Length', (string) filesize($path))
            ->setHeader('X-Content-Type-Options', 'nosniff')
            ->setHeader('Content-Security-Policy', "default-src 'none'; sandbox")
            ->setHeader('Cache-Control', 'private, max-age=86400')
            ->setBody(file_get_contents($path));
    }
}
