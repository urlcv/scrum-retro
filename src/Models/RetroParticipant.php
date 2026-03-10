<?php

declare(strict_types=1);

namespace URLCV\ScrumRetro\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class RetroParticipant extends Model
{
    protected $table = 'scrum_retro_participants';

    protected $fillable = [
        'session_id',
        'name',
        'token',
        'role',
        'last_seen_at',
    ];

    protected $casts = [
        'last_seen_at' => 'datetime',
    ];

    public function session(): BelongsTo
    {
        return $this->belongsTo(RetroSession::class, 'session_id');
    }

    public static function createHostForSession(RetroSession $session, string $name): self
    {
        return self::create([
            'session_id' => $session->id,
            'name' => trim($name),
            'token' => self::generateToken(),
            'role' => 'host',
            'last_seen_at' => now(),
        ]);
    }

    public static function firstOrCreateForSession(RetroSession $session, string $name): self
    {
        $cleanName = trim($name);

        $participant = self::where('session_id', $session->id)
            ->whereRaw('LOWER(name) = ?', [mb_strtolower($cleanName)])
            ->first();

        if ($participant) {
            if (! $participant->token) {
                $participant->token = self::generateToken();
            }

            $participant->name = $cleanName;
            $participant->last_seen_at = now();
            $participant->save();

            return $participant;
        }

        return self::create([
            'session_id' => $session->id,
            'name' => $cleanName,
            'token' => self::generateToken(),
            'role' => 'participant',
            'last_seen_at' => now(),
        ]);
    }

    public static function generateToken(): string
    {
        do {
            $token = Str::random(48);
        } while (self::where('token', $token)->exists());

        return $token;
    }

    public function touchLastSeen(): void
    {
        $this->last_seen_at = now();
        $this->save();
    }
}
