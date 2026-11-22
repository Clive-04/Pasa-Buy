<?php

namespace App\Controllers;

use Throwable;

/**
 * Liveness/readiness probe for deployment smoke checks and uptime monitors.
 * Reports only ok/fail per component — no versions, paths or errors.
 */
class Health extends BaseController
{
    public function index()
    {
        $checks = ['app' => 'ok', 'database' => 'fail', 'storage' => 'fail'];

        try {
            db_connect()->query('SELECT 1');
            $checks['database'] = 'ok';
        } catch (Throwable) {
            // reported as fail
        }

        $uploads = WRITEPATH . 'uploads';

        if (is_dir($uploads) && is_writable($uploads) && is_writable(WRITEPATH . 'session')) {
            $checks['storage'] = 'ok';
        }

        $healthy = ! in_array('fail', $checks, true);

        return $this->response
            ->setStatusCode($healthy ? 200 : 503)
            ->setHeader('Cache-Control', 'no-store')
            ->setJSON(['status' => $healthy ? 'ok' : 'degraded', 'checks' => $checks]);
    }
}
