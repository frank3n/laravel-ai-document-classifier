<?php

namespace App\Actions;

use App\DTOs\ClassificationResult;
use App\Models\Classification;

class LogClassificationAction
{
    public function handle(ClassificationResult $result): Classification
    {
        return Classification::create([
            'document_source'  => $result->documentSource,
            'filename'         => $result->filename,
            'document_excerpt' => $result->documentExcerpt,
            'category'         => $result->category,
            'confidence'       => $result->confidence,
            'rationale'        => $result->rationale,
            'raw_response'     => $result->rawResponse,
        ]);
    }
}
