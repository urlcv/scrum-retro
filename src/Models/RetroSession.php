<?php

declare(strict_types=1);

namespace URLCV\ScrumRetro\Models;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;

class RetroSession extends Model
{
    protected $table = 'scrum_retro_sessions';

    protected $fillable = [
        'code',
        'host_token',
        'team_name',
        'board_title',
        'theme',
        'areas',
    ];

    protected $casts = [
        'areas' => 'array',
    ];

    protected $hidden = [
        'host_token',
    ];

    public function participants(): HasMany
    {
        return $this->hasMany(RetroParticipant::class, 'session_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(RetroItem::class, 'session_id');
    }

    public static function generateCode(): string
    {
        do {
            $code = Str::upper(Str::random(6));
        } while (self::where('code', $code)->exists());

        return $code;
    }

    public static function generateHostToken(): string
    {
        do {
            $token = Str::random(48);
        } while (self::where('host_token', $token)->exists());

        return $token;
    }

    public function hasAreaKey(string $areaKey): bool
    {
        foreach (Arr::wrap($this->areas) as $area) {
            if (($area['key'] ?? null) === $areaKey) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param array<int, array<string, string>> $areas
     */
    public function applyAreas(array $areas, ?string $theme = null): void
    {
        if ($areas === []) {
            return;
        }

        $this->areas = array_values($areas);

        if ($theme !== null && $theme !== '') {
            $this->theme = $theme;
        }

        $this->save();

        $allowedKeys = array_values(array_map(
            static fn (array $area): string => (string) ($area['key'] ?? ''),
            $areas,
        ));

        $defaultArea = $allowedKeys[0] ?? 'lane_1';

        RetroItem::query()
            ->where('session_id', $this->id)
            ->whereNotIn('area_key', $allowedKeys)
            ->update(['area_key' => $defaultArea]);
    }

    /**
     * @return array<string, mixed>
     */
    public function buildStatePayload(): array
    {
        /** @var Collection<int, RetroParticipant> $participants */
        $participants = $this->relationLoaded('participants')
            ? $this->participants
            : $this->participants()->orderBy('created_at')->get();

        /** @var Collection<int, RetroItem> $items */
        $items = $this->relationLoaded('items')
            ? $this->items
            : $this->items()->with('participant')->orderBy('created_at')->get();

        $participantsPayload = $participants->map(static function (RetroParticipant $participant): array {
            return [
                'id' => $participant->id,
                'name' => $participant->name,
                'role' => $participant->role,
                'last_seen_at' => $participant->last_seen_at?->toIso8601String(),
            ];
        })->values()->all();

        $itemsPayload = $items->map(static function (RetroItem $item): array {
            return [
                'id' => $item->id,
                'area_key' => $item->area_key,
                'text' => $item->text,
                'color' => $item->color,
                'created_at' => $item->created_at?->toIso8601String(),
                'author' => [
                    'name' => $item->participant?->name,
                    'role' => $item->participant?->role,
                ],
            ];
        })->values()->all();

        return [
            'session' => [
                'team_name' => $this->team_name,
                'board_title' => $this->board_title,
                'theme' => $this->theme,
                'code' => $this->code,
            ],
            'areas' => array_values(Arr::wrap($this->areas)),
            'participants' => $participantsPayload,
            'items' => $itemsPayload,
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
