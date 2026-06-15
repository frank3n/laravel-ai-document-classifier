<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Smalot\PdfParser\Parser as PdfParser;

class RagService
{
    public function index(): array
    {
        $basePath = Storage::disk('local')->path(config('classifier.rag.knowledge_base_path'));

        if (! is_dir($basePath)) {
            mkdir($basePath, 0755, true);
        }

        $files = array_merge(
            glob($basePath . '/*.txt') ?: [],
            glob($basePath . '/*.pdf') ?: [],
        );

        if (empty($files)) {
            return ['indexed' => 0, 'chunks' => 0, 'files' => []];
        }

        DB::statement('DELETE FROM rag_chunks');

        $totalChunks = 0;
        $indexed     = [];

        foreach ($files as $filePath) {
            $text = $this->extractText($filePath);

            if (empty(trim($text))) {
                continue;
            }

            $chunks = $this->chunk($text);

            foreach ($chunks as $i => $chunk) {
                DB::table('rag_chunks')->insert([
                    'source_file' => basename($filePath),
                    'chunk_index' => $i,
                    'content'     => $chunk,
                ]);
            }

            $totalChunks += count($chunks);
            $indexed[]    = ['file' => basename($filePath), 'chunks' => count($chunks)];
        }

        return ['indexed' => count($indexed), 'chunks' => $totalChunks, 'files' => $indexed];
    }

    public function retrieve(string $queryText): array
    {
        $topN = config('classifier.rag.top_n', 3);

        // Build a cleaned FTS5 query from the most significant words
        $query = $this->buildFtsQuery($queryText);

        if (empty($query)) {
            return [];
        }

        try {
            $rows = DB::select("
                SELECT source_file, chunk_index, content,
                       bm25(rag_chunks) AS rank
                FROM rag_chunks
                WHERE rag_chunks MATCH ?
                ORDER BY rank
                LIMIT ?
            ", [$query, $topN]);
        } catch (\Throwable) {
            // If the query has no matching terms, FTS5 throws — return empty
            return [];
        }

        return array_map(fn ($row) => [
            'source' => $row->source_file,
            'chunk'  => $row->chunk_index,
            'text'   => $row->content,
        ], $rows);
    }

    public function formatContext(array $chunks): string
    {
        if (empty($chunks)) {
            return '';
        }

        $parts = [];
        foreach ($chunks as $i => $chunk) {
            $parts[] = "[{$chunk['source']}]\n{$chunk['text']}";
        }

        return implode("\n\n---\n\n", $parts);
    }

    private function chunk(string $text): array
    {
        $wordsPerChunk = config('classifier.rag.chunk_size', 300);
        $words         = preg_split('/\s+/', trim($text));
        $chunks        = [];

        foreach (array_chunk($words, $wordsPerChunk) as $wordGroup) {
            $chunk = implode(' ', $wordGroup);
            if (! empty(trim($chunk))) {
                $chunks[] = $chunk;
            }
        }

        return $chunks;
    }

    private function buildFtsQuery(string $text): string
    {
        // Strip punctuation and extract unique words longer than 3 chars
        $words = preg_split('/\s+/', strtolower(preg_replace('/[^\w\s]/', ' ', $text)));
        $stop  = ['this', 'that', 'with', 'from', 'have', 'been', 'were', 'they',
                   'their', 'will', 'would', 'could', 'should', 'which', 'about',
                   'into', 'more', 'also', 'than', 'your', 'when', 'what'];

        $words = array_unique(array_filter($words, fn ($w) => strlen($w) > 3 && ! in_array($w, $stop, true)));

        // FTS5 OR query — any chunk containing any of these words ranks up
        return implode(' OR ', array_slice(array_values($words), 0, 20));
    }

    private function extractText(string $path): string
    {
        $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));

        if ($ext === 'pdf') {
            $parser = new PdfParser();
            $pdf    = $parser->parseFile($path);
            return $pdf->getText();
        }

        return file_get_contents($path);
    }
}
