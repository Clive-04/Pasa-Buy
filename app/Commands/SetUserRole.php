<?php

namespace App\Commands;

use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;

/**
 * Controlled admin provisioning. Public registration always creates
 * ordinary users; only someone with server/CLI access can grant admin.
 *
 *   php spark user:role someone@example.com admin
 *   php spark user:role someone@example.com user
 */
class SetUserRole extends BaseCommand
{
    protected $group       = 'Pasa-Buy';
    protected $name        = 'user:role';
    protected $description = 'Grant or revoke the admin role for an existing account.';
    protected $usage       = 'user:role <email> <admin|user>';
    protected $arguments   = [
        'email' => 'Email address of an existing account',
        'role'  => 'admin or user',
    ];

    public function run(array $params)
    {
        $email = strtolower(trim((string) ($params[0] ?? '')));
        $role  = (string) ($params[1] ?? '');

        if ($email === '' || ! in_array($role, ['admin', 'user'], true)) {
            CLI::error('Usage: php spark ' . $this->usage);

            return EXIT_USER_INPUT;
        }

        $db   = db_connect();
        $user = $db->table('users')->select('id, role')->where('email', $email)->get()->getRowArray();

        if ($user === null) {
            CLI::error('No account uses that email address.');

            return EXIT_ERROR;
        }

        $db->table('users')->where('id', $user['id'])->update(['role' => $role]);
        CLI::write("Account #{$user['id']} role: {$user['role']} -> {$role}", 'green');

        return EXIT_SUCCESS;
    }
}
