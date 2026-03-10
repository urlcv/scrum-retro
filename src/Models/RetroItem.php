<?php

declare(strict_types=1);

namespace URLCV\ScrumRetro\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RetroItem extends Model
{
    protected $table = 'scrum_retro_items';

    protected $fillable = [
        'session_id',
        'participant_id',
        'area_key',
        'sort_order',
        'text',
        'color',
    ];

    public function session(): BelongsTo
    {
        return $this->belongsTo(RetroSession::class, 'session_id');
    }

    public function participant(): BelongsTo
    {
        return $this->belongsTo(RetroParticipant::class, 'participant_id');
    }
}
