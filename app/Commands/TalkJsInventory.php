<?php

namespace App\Commands;

use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;
use Throwable;

/**
 * Read-only inventory of chat history held by TalkJS, used before switching
 * the default chat to the built-in database chat. It lists conversation ids,
 * participants and message counts — never message text — and changes
 * nothing in TalkJS or in the database.
 *
 *   php spark chat:talkjs-inventory
 *
 * Works whenever talkjs.appId / talkjs.secretKey are set, even while
 * pasabuy.talkjsEnabled is false.
 */
class TalkJsInventory extends BaseCommand
{
    protected $group       = 'Pasa-Buy';
    protected $name        = 'chat:talkjs-inventory';
    protected $description = 'Summarise conversations stored in TalkJS (read-only, no message text).';

    public function run(array $params)
    {
        if (! config('TalkJS')->hasKeys()) {
            CLI::write('TalkJS keys are not set: there is no TalkJS history to inventory from this environment.');

            return EXIT_SUCCESS;
        }

        try {
            $rows = service('talkjs')->inventory();
        } catch (Throwable $e) {
            CLI::error('TalkJS could not be read: ' . $e->getMessage());

            return EXIT_ERROR;
        }

        $known    = array_flip(array_map('intval', array_column(db_connect()->table('users')->select('id')->get()->getResultArray(), 'id')));
        $messages = 0;
        $table    = [];

        foreach ($rows as $row) {
            $messages += $row['messages'];
            $people    = array_map(static function (string $p) use ($known): string {
                $id = str_starts_with($p, 'pb_') ? (int) substr($p, 3) : 0;

                return $id > 0 ? '#' . $id . (isset($known[$id]) ? '' : ' (not in this DB)') : $p;
            }, $row['participants']);

            $table[] = [
                substr($row['id'], 0, 4) . '…' . substr($row['id'], -4),
                implode(', ', $people),
                $row['messages'] . ($row['messages'] >= 100 ? '+' : ''),
                $row['createdAt'] !== null ? date('Y-m-d', intdiv($row['createdAt'], 1000)) : '-',
            ];
        }

        CLI::write(count($rows) . ' conversation(s), ' . $messages . ' message(s) in TalkJS app ' . substr(config('TalkJS')->appId, 0, 3) . '…');

        if ($table !== []) {
            CLI::table($table, ['Conversation', 'Participants (Pasa-Buy user ids)', 'Messages', 'Created']);
        }

        CLI::write('Nothing was copied, changed or deleted.');

        return EXIT_SUCCESS;
    }
}
