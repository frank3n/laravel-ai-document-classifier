<?php

namespace App\Console\Commands;

use App\Services\RagService;
use Illuminate\Console\Command;

class IndexKnowledgeBase extends Command
{
    protected $signature = 'rag:index';

    protected $description = 'Index knowledge base documents into the FTS search table';

    public function __construct(private RagService $rag)
    {
        parent::__construct();
    }

    public function handle(): int
    {
        $this->info('Indexing knowledge base...');

        $result = $this->rag->index();

        if ($result['indexed'] === 0) {
            $this->warn('No documents found. Add .txt or .pdf files to storage/app/rag/ and re-run.');
            return self::SUCCESS;
        }

        $this->table(
            ['File', 'Chunks'],
            array_map(fn ($f) => [$f['file'], $f['chunks']], $result['files'])
        );

        $this->info("{$result['indexed']} file(s) indexed into {$result['chunks']} chunk(s).");

        return self::SUCCESS;
    }
}
