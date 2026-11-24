<?php

namespace App\Models;

use CodeIgniter\Model;

class UserModel extends Model
{
    protected $table = 'users';
    protected $primaryKey = 'id';

    /*
     * Deliberately NOT mass-assignable:
     *  - role:           set by the column default; admins are provisioned
     *                    with `php spark user:role` only.
     *  - wallet_balance: changed only by the ledger services
     *                    (App\Services\WalletService / RequestLifecycle),
     *                    which write a matching payments row.
     */
    protected $allowedFields = [
        'first_name',
        'last_name',
        'email',
        'phone_number',
        'password',
        'address',
        'username',
        'about_me',
        'profile_photo',
    ];

    protected $useTimestamps = false;
    protected $createdField = null;
    protected $updatedField = null;
}
