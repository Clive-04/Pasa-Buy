<?php

use App\Libraries\BackupTools;
use Tests\Support\DatabaseTestCase;

final class BackupCoverageTest extends DatabaseTestCase
{
    public function testBackupVerificationCoversEveryMigratedTable(): void
    {
        $this->db->resetDataCache();
        $actual = array_keys(BackupTools::rowCounts());
        $expected = $this->db->listTables();
        sort($actual);
        sort($expected);
        $this->assertSame($expected, $actual, 'Every migrated table must participate in restore verification.');
        $this->assertCount(8, $this->db->table('migrations')->get()->getResultArray());
    }

    public function testRestoreGuardFindsTablesTheBackupWouldLeaveBehind(): void
    {
        $dump = tempnam(sys_get_temp_dir(), 'pbdump');
        $gz   = gzopen($dump, 'wb');
        gzwrite($gz, "-- MariaDB dump\nDROP TABLE IF EXISTS `users`;\nCREATE TABLE `users` (\n  `id` int(11) NOT NULL\n);\n"
            . "INSERT INTO `users` VALUES (1,'CREATE TABLE `fake` inside data');\nCREATE TABLE `migrations` (\n  `id` int\n);\n");
        gzclose($gz);

        try {
            $this->assertSame(['migrations', 'users'], BackupTools::dumpTables($dump), 'Only real CREATE TABLE lines count.');
        } finally {
            unlink($dump);
        }

        $this->assertSame(
            ['chat_deletions', 'wallet_topups'],
            BackupTools::tablesOutsideBackup(['chat_deletions', 'migrations', 'users', 'wallet_topups'], ['migrations', 'users']),
        );
        $this->assertSame([], BackupTools::tablesOutsideBackup([], ['users']), 'An empty target is always safe.');

        $this->db->resetDataCache();
        $expected = $this->db->listTables();
        sort($expected);
        $this->assertSame($expected, BackupTools::databaseTables());
    }
}
