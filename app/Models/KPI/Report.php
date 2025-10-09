<?php

namespace App\Models\KPI;

use App\Models\Factory;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Report extends Model
{
    protected $table = 'kpi_reports';

    protected $fillable = [
        'factory_id',
        'report_type',
        'report_date',
        'file_path',
        'file_name',
        'file_size',
        'file_format',
        'kpi_count',
        'page_count',
        'generated_by',
        'generation_started_at',
        'generation_completed_at',
        'generation_duration_seconds',
        'email_sent',
        'email_sent_at',
        'recipients',
        'status',
        'error_message',
    ];

    protected function casts(): array
    {
        return [
            'report_date' => 'date',
            'generation_started_at' => 'datetime',
            'generation_completed_at' => 'datetime',
            'email_sent' => 'boolean',
            'email_sent_at' => 'datetime',
            'recipients' => 'array',
        ];
    }

    public function factory(): BelongsTo
    {
        return $this->belongsTo(Factory::class);
    }

    public function generatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'generated_by');
    }
}
