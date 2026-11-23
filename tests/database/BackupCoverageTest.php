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
}
