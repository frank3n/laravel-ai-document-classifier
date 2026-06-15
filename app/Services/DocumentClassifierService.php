<?php

namespace App\Services;

use App\DTOs\ClassificationResult;
use App\Exceptions\ClassificationException;
use Illuminate\Support\Facades\Http;

class DocumentClassifierService
{
    public function classify(string $text, string $source, ?string $filename = null, bool $useRag = false): ClassificationResult
    {
        $ragChunks    = $useRag ? app(RagService::class)->retrieve($text) : [];
        $systemPrompt = $this->buildSystemPrompt($ragChunks);
        $excerpt      = mb_substr($text, 0, 500);

        $response = Http::timeout(config('classifier.api.timeout'))
            ->withHeaders([
                'x-api-key'         => config('classifier.api.key'),
                'anthropic-version' => config('classifier.api.version'),
                'content-type'      => 'application/json',
            ])
            ->post(config('classifier.api.base_url'), [
                'model'      => config('classifier.model'),
                'max_tokens' => config('classifier.api.max_tokens'),
                'system'     => $systemPrompt,
                'messages'   => [
                    ['role' => 'user', 'content' => "Please classify the following document:\n\n" . $text],
                ],
            ]);

        if (! $response->successful()) {
            throw new ClassificationException(
                "Claude API error {$response->status()}: " . $response->body()
            );
        }

        $body    = $response->json();
        $rawText = $body['content'][0]['text'] ?? '';

        $parsed = $this->parseJson($rawText);

        $this->validateParsed($parsed, $rawText);

        return new ClassificationResult(
            category:        $parsed['category'],
            confidence:      $parsed['confidence'],
            rationale:       $parsed['rationale'],
            rawResponse:     $body,
            documentSource:  $source,
            filename:        $filename,
            documentExcerpt: $excerpt,
        );
    }

    private function buildSystemPrompt(array $ragChunks = []): string
    {
        $categories = config('classifier.categories');

        $categoryLines = '';
        foreach ($categories as $key => $meta) {
            $categoryLines .= "- {$key}: {$meta['description']}\n";
        }

        $validKeys  = implode('|', array_keys($categories));
        $extraInstr = config('classifier.extra_instructions');

        $contextBlock = '';
        if (! empty($ragChunks)) {
            $parts = [];
            foreach ($ragChunks as $chunk) {
                $parts[] = "[{$chunk['source']}]\n{$chunk['text']}";
            }
            $contextText  = implode("\n\n---\n\n", $parts);
            $contextBlock = "\nRelevant context from your knowledge base:\n\n{$contextText}\n\nUse this context to inform your classification.\n";
        }

        return <<<PROMPT
You are a document classification assistant.
{$contextBlock}
Classify the document provided by the user into EXACTLY ONE of the following categories:

{$categoryLines}
{$extraInstr}

You MUST respond with valid JSON only. No prose, no markdown fences, no explanation outside the JSON object.

The JSON must have exactly these three keys:
{
  "category": "<one of: {$validKeys}>",
  "confidence": "<high|medium|low>",
  "rationale": "<one or two sentences explaining your classification>"
}
PROMPT;
    }

    private function parseJson(string $text): array
    {
        $decoded = json_decode(trim($text), true);

        if ($decoded !== null) {
            return $decoded;
        }

        // Fallback: extract JSON object if Claude added any preamble
        if (preg_match('/\{.*\}/s', $text, $matches)) {
            $decoded = json_decode($matches[0], true);
            if ($decoded !== null) {
                return $decoded;
            }
        }

        throw new ClassificationException(
            "Could not parse JSON from Claude response: {$text}"
        );
    }

    private function validateParsed(array $parsed, string $rawText): void
    {
        $validCategories  = array_keys(config('classifier.categories'));
        $validConfidences = ['high', 'medium', 'low'];

        if (! isset($parsed['category'], $parsed['confidence'], $parsed['rationale'])) {
            throw new ClassificationException(
                "Claude response missing required keys: {$rawText}"
            );
        }

        if (! in_array($parsed['category'], $validCategories, true)) {
            throw new ClassificationException(
                "Claude returned unknown category '{$parsed['category']}': {$rawText}"
            );
        }

        if (! in_array($parsed['confidence'], $validConfidences, true)) {
            throw new ClassificationException(
                "Claude returned unknown confidence '{$parsed['confidence']}': {$rawText}"
            );
        }
    }
}
