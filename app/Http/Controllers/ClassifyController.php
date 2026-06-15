<?php

namespace App\Http\Controllers;

use App\Exceptions\ClassificationException;
use App\Services\DocumentClassifierService;
use App\Services\DocumentRouter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Smalot\PdfParser\Parser as PdfParser;

class ClassifyController extends Controller
{
    public function __construct(
        private DocumentClassifierService $classifier,
        private DocumentRouter            $router,
    ) {}

    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'text'    => 'required_without:file|string|min:10|max:50000',
            'file'    => 'required_without:text|file|mimes:txt,pdf|max:5120',
            'use_rag' => 'boolean',
        ]);

        $filename = null;
        $tmpPath  = null;
        $useRag   = (bool) $request->input('use_rag', false);

        try {
            if ($request->hasFile('file')) {
                $upload   = $request->file('file');
                $filename = $upload->getClientOriginalName();
                $tmpPath  = $upload->store('tmp', 'local');
                $fullPath = Storage::disk('local')->path($tmpPath);
                $text     = $this->extractTextFromFile($fullPath, $upload->getClientOriginalExtension());
            } else {
                $text = $request->input('text');
            }

            $result = $this->classifier->classify($text, 'api', $filename, $useRag);
            $record = $this->router->route($result);

            $label = config("classifier.categories.{$result->category}.label", $result->category);

            return response()->json([
                'success'        => true,
                'classification' => array_merge($result->toArray(), ['category_label' => $label]),
                'metadata'       => [
                    'classification_id' => $record->id,
                    'document_source'   => $result->documentSource,
                    'filename'          => $result->filename,
                    'rag_used'          => $useRag,
                    'routing'           => [
                        'logged'        => true,
                        'webhook_fired' => $record->webhook_fired,
                        'slack_sent'    => $record->slack_sent,
                    ],
                ],
            ]);
        } catch (ClassificationException $e) {
            return response()->json(['success' => false, 'error' => $e->getMessage()], 502);
        } finally {
            if ($tmpPath) {
                Storage::disk('local')->delete($tmpPath);
            }
        }
    }

    private function extractTextFromFile(string $path, string $extension): string
    {
        if (strtolower($extension) === 'pdf') {
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
