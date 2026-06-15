<?php

namespace App\Console\Commands;

use App\Exceptions\ClassificationException;
use App\Services\DocumentClassifierService;
use App\Services\DocumentRouter;
use Illuminate\Console\Command;
use Smalot\PdfParser\Parser as PdfParser;

class ClassifyDocument extends Command
{
    protected $signature = 'classify:document
                            {--text= : Document text to classify}
                            {--file= : Path to a .txt or .pdf file}
                            {--rag   : Inject relevant context from the knowledge base}';

    protected $description = 'Classify a document using Claude AI';

    public function __construct(
        private DocumentClassifierService $classifier,
        private DocumentRouter            $router,
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $text     = $this->option('text');
        $filePath = $this->option('file');
        $useRag   = (bool) $this->option('rag');

        if (! $text && ! $filePath) {
            $this->error('Provide --text or --file.');
            return self::FAILURE;
        }

        if ($text && $filePath) {
            $this->error('Provide only one of --text or --file, not both.');
            return self::FAILURE;
        }

        $filename = null;

        try {
            if ($filePath) {
                if (! file_exists($filePath)) {
                    $this->error("File not found: {$filePath}");
                    return self::FAILURE;
                }

                $ext      = strtolower(pathinfo($filePath, PATHINFO_EXTENSION));
                $filename = basename($filePath);

                if (! in_array($ext, ['txt', 'pdf'], true)) {
                    $this->error('Only .txt and .pdf files are supported.');
                    return self::FAILURE;
                }

                $text = $this->extractText($filePath, $ext);
            }

            if ($useRag) {
                $this->info('Classifying document with RAG context...');
            } else {
                $this->info('Classifying document...');
            }

            $result = $this->classifier->classify($text, 'artisan', $filename, $useRag);
            $record = $this->router->route($result);

            $label = config("classifier.categories.{$result->category}.label", $result->category);

            $this->table(
                ['Field', 'Value'],
                [
                    ['Category',   $label],
                    ['Confidence', ucfirst($result->confidence)],
                    ['Rationale',  wordwrap($result->rationale, 70, "\n", true)],
                    ['RAG',        $useRag ? 'enabled' : 'disabled'],
                    ['DB record',  "#{$record->id}"],
                    ['Webhook',    $record->webhook_fired ? 'fired' : 'skipped'],
                    ['Slack',      $record->slack_sent    ? 'sent'  : 'skipped'],
                ]
            );

            return self::SUCCESS;
        } catch (ClassificationException $e) {
            $this->error($e->getMessage());
            return self::FAILURE;
        }
    }

    private function extractText(string $path, string $ext): string
    {
        if ($ext === 'pdf') {
            $parser = new PdfParser();
            $pdf    = $parser->parseFile($path);
            $text   = $pdf->getText();

            if (empty(trim($text))) {
                throw new ClassificationException(
                    'Could not extract text from PDF — the file may be a scanned image.'
                );
            }

            return $text;
        }

        return file_get_contents($path);
    }
}
