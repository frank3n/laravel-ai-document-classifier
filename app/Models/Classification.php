<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Classification extends Model
{
    protected $fillable = [
        'document_source',
        'filename',
        'document_excerpt',
        'category',
        'confidence',
        'rationale',
        'raw_response',
        'webhook_fired',
        'slack_sent',
    ];

    protected $casts = [
        'raw_response'  => 'array',
        'webhook_fired' => 'boolean',
        'slack_sent'    => 'boolean',
    ];
}
