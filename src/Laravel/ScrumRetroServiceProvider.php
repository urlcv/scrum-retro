<?php

declare(strict_types=1);

namespace URLCV\ScrumRetro\Laravel;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use URLCV\ScrumRetro\Models\RetroItem;
use URLCV\ScrumRetro\Models\RetroParticipant;
use URLCV\ScrumRetro\Models\RetroSession;

class ScrumRetroServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        $this->loadViewsFrom(__DIR__ . '/../../resources/views', 'scrum-retro');
        $this->loadMigrationsFrom(__DIR__ . '/../../database/migrations');

        $this->registerSessionRoutes();
        $this->registerJoinRoute();
        $this->registerItemsRoute();
        $this->registerMoveItemRoute();
        $this->registerAreasRoute();
        $this->registerDeleteSessionRoute();
    }

    private function registerSessionRoutes(): void
    {
        Route::post('/tools/scrum-retro/session', static function (Request $request) {
            $validated = $request->validate([
                'team_name' => ['nullable', 'string', 'max:255'],
                'board_title' => ['nullable', 'string', 'max:255'],
                'host_name' => ['required', 'string', 'max:255'],
                'theme' => ['required', 'string', 'in:classic,momentum,weather,trailblazer,custom'],
                'custom_areas' => ['nullable', 'array', 'min:2', 'max:8'],
                'custom_areas.*' => ['nullable', 'string', 'max:80'],
            ]);

            $areas = self::buildAreas(
                $validated['theme'],
                $validated['custom_areas'] ?? null,
            );

            $session = RetroSession::create([
                'code' => RetroSession::generateCode(),
                'host_token' => RetroSession::generateHostToken(),
                'team_name' => ($validated['team_name'] ?? '') ?: null,
                'board_title' => ($validated['board_title'] ?? '') ?: null,
                'theme' => $validated['theme'],
                'areas' => $areas,
            ]);

            $host = RetroParticipant::createHostForSession($session, $validated['host_name']);

            return response()->json([
                'success' => true,
                'code' => $session->code,
                'join_url' => url('/tools/scrum-retro?code=' . $session->code),
                'host_token' => $session->host_token,
                'host_participant_token' => $host->token,
            ]);
        })
            ->middleware(['web', 'throttle:30,1'])
            ->name('tools.scrum-retro.session.create');

        Route::get('/tools/scrum-retro/session/{code}/state', static function (string $code) {
            $session = RetroSession::where('code', $code)
                ->with([
                    'participants',
                    'items' => static function ($query): void {
                        $query
                            ->orderBy('area_key')
                            ->orderBy('sort_order')
                            ->orderBy('created_at')
                            ->orderBy('id')
                            ->with('participant');
                    },
                ])
                ->first();

            if (! $session) {
                return response()->json(['error' => 'Session not found'], 404);
            }

            if (! self::hasActionsLane($session->areas ?? [])) {
                $session->applyAreas(
                    self::ensureActionsLane($session->areas ?? []),
                    $session->theme ?: 'momentum',
                );

                $session->load([
                    'participants',
                    'items' => static function ($query): void {
                        $query
                            ->orderBy('area_key')
                            ->orderBy('sort_order')
                            ->orderBy('created_at')
                            ->orderBy('id')
                            ->with('participant');
                    },
                ]);
            }

            return response()->json($session->buildStatePayload());
        })
            ->middleware(['web'])
            ->name('tools.scrum-retro.session.state');
    }

    private function registerJoinRoute(): void
    {
        Route::post('/tools/scrum-retro/session/{code}/join', static function (Request $request, string $code) {
            $validated = $request->validate([
                'name' => ['required', 'string', 'max:255'],
            ]);

            $session = RetroSession::where('code', $code)->first();

            if (! $session) {
                return response()->json([
                    'success' => false,
                    'message' => 'Session not found',
                ], 404);
            }

            $participant = RetroParticipant::firstOrCreateForSession($session, $validated['name']);

            return response()->json([
                'success' => true,
                'participant_token' => $participant->token,
            ]);
        })
            ->middleware(['web', 'throttle:60,1'])
            ->name('tools.scrum-retro.session.join');
    }

    private function registerItemsRoute(): void
    {
        Route::post('/tools/scrum-retro/session/{code}/items', static function (Request $request, string $code) {
            $validated = $request->validate([
                'participant_token' => ['nullable', 'string', 'max:128', 'required_without:host_token'],
                'host_token' => ['nullable', 'string', 'max:128', 'required_without:participant_token'],
                'area_key' => ['required', 'string', 'max:40'],
                'text' => ['required', 'string', 'max:600'],
            ]);

            $session = RetroSession::where('code', $code)->first();

            if (! $session) {
                return response()->json([
                    'success' => false,
                    'message' => 'Session not found',
                ], 404);
            }

            if (! $session->hasAreaKey($validated['area_key'])) {
                return response()->json([
                    'success' => false,
                    'message' => 'This column no longer exists. Refresh the board and try again.',
                ], 422);
            }

            $participant = null;

            if (! empty($validated['participant_token'])) {
                $participant = RetroParticipant::where('session_id', $session->id)
                    ->where('token', (string) $validated['participant_token'])
                    ->first();
            }

            if (
                ! $participant
                && ! empty($validated['host_token'])
                && hash_equals($session->host_token, (string) $validated['host_token'])
            ) {
                $participant = RetroParticipant::where('session_id', $session->id)
                    ->where('role', 'host')
                    ->orderBy('id')
                    ->first();

                if (! $participant) {
                    $participant = RetroParticipant::createHostForSession($session, 'Host');
                }
            }

            if (! $participant) {
                return response()->json([
                    'success' => false,
                    'message' => 'Participant not found',
                ], 404);
            }

            $participant->touchLastSeen();

            $nextOrder = (int) RetroItem::where('session_id', $session->id)
                ->where('area_key', $validated['area_key'])
                ->max('sort_order');

            $item = RetroItem::create([
                'session_id' => $session->id,
                'participant_id' => $participant->id,
                'area_key' => $validated['area_key'],
                'sort_order' => $nextOrder + 1,
                'text' => trim($validated['text']),
                'color' => self::pickItemColor($validated['area_key']),
            ]);

            return response()->json([
                'success' => true,
                'item_id' => $item->id,
            ]);
        })
            ->middleware(['web', 'throttle:180,1'])
            ->name('tools.scrum-retro.items.create');
    }

    private function registerAreasRoute(): void
    {
        Route::post('/tools/scrum-retro/session/{code}/areas', static function (Request $request, string $code) {
            $validated = $request->validate([
                'host_token' => ['required', 'string', 'max:128'],
                'theme' => ['nullable', 'string', 'in:classic,momentum,weather,trailblazer,custom'],
                'area_titles' => ['nullable', 'array', 'min:2', 'max:8'],
                'area_titles.*' => ['nullable', 'string', 'max:80'],
            ]);

            $session = RetroSession::where('code', $code)
                ->where('host_token', $validated['host_token'])
                ->first();

            if (! $session) {
                return response()->json([
                    'success' => false,
                    'message' => 'Invalid host or session.',
                ], 403);
            }

            $theme = $validated['theme'] ?? ($session->theme ?: 'momentum');

            $areas = self::buildAreas(
                $theme,
                $validated['area_titles'] ?? null,
            );

            $session->applyAreas($areas, $theme);

            return response()->json([
                'success' => true,
                'areas' => $areas,
            ]);
        })
            ->middleware(['web', 'throttle:30,1'])
            ->name('tools.scrum-retro.areas.update');
    }

    private function registerMoveItemRoute(): void
    {
        Route::post('/tools/scrum-retro/session/{code}/items/{item}/move', static function (Request $request, string $code, int $item) {
            $validated = $request->validate([
                'participant_token' => ['nullable', 'string', 'max:128', 'required_without:host_token'],
                'host_token' => ['nullable', 'string', 'max:128', 'required_without:participant_token'],
                'target_area_key' => ['required', 'string', 'max:40'],
                'target_index' => ['required', 'integer', 'min:0', 'max:500'],
            ]);

            $session = RetroSession::where('code', $code)->first();

            if (! $session) {
                return response()->json([
                    'success' => false,
                    'message' => 'Session not found',
                ], 404);
            }

            if (! $session->hasAreaKey($validated['target_area_key'])) {
                return response()->json([
                    'success' => false,
                    'message' => 'Target lane does not exist.',
                ], 422);
            }

            $movingItem = RetroItem::where('session_id', $session->id)->where('id', $item)->first();

            if (! $movingItem) {
                return response()->json([
                    'success' => false,
                    'message' => 'Item not found',
                ], 404);
            }

            $participant = self::resolveActorParticipant(
                session: $session,
                participantToken: $validated['participant_token'] ?? null,
                hostToken: $validated['host_token'] ?? null,
            );

            if (! $participant) {
                return response()->json([
                    'success' => false,
                    'message' => 'Participant not found',
                ], 404);
            }

            self::moveItem(
                item: $movingItem,
                targetAreaKey: (string) $validated['target_area_key'],
                targetIndex: (int) $validated['target_index'],
            );

            $participant->touchLastSeen();

            return response()->json([
                'success' => true,
            ]);
        })
            ->middleware(['web', 'throttle:180,1'])
            ->name('tools.scrum-retro.items.move');
    }

    private function registerDeleteSessionRoute(): void
    {
        Route::post('/tools/scrum-retro/session/{code}/delete', static function (Request $request, string $code) {
            $validated = $request->validate([
                'host_token' => ['required', 'string', 'max:128'],
            ]);

            $session = RetroSession::where('code', $code)
                ->where('host_token', $validated['host_token'])
                ->first();

            if (! $session) {
                return response()->json([
                    'success' => false,
                    'message' => 'Invalid host or session.',
                ], 403);
            }

            $session->delete();

            return response()->json([
                'success' => true,
            ]);
        })
            ->middleware(['web', 'throttle:20,1'])
            ->name('tools.scrum-retro.session.delete');
    }

    /**
     * @param array<int, string|null>|null $customAreaTitles
     * @return array<int, array<string, string>>
     */
    private static function buildAreas(string $theme, ?array $customAreaTitles = null): array
    {
        $presets = self::presetAreas();

        $selectedTheme = array_key_exists($theme, $presets) ? $theme : 'momentum';

        if ($selectedTheme === 'custom') {
            $titles = self::sanitizeTitles($customAreaTitles ?? []);

            if ($titles === []) {
                $titles = [
                    'Rocket Fuel',
                    'Gravity Wells',
                    'Next Orbit',
                ];
            }

            $defaultSubtitles = [
                'What we should keep',
                'What slowed us down',
                'What we will try next',
                'Questions to explore',
                'Things to celebrate',
                'Risks to remove',
                'Experiments to run',
                'Ideas for next sprint',
            ];

            $areas = [];
            foreach ($titles as $index => $title) {
                $areas[] = [
                    'key' => 'lane_' . ($index + 1),
                    'title' => $title,
                    'subtitle' => $defaultSubtitles[$index] ?? 'Team ideas',
                    'color' => self::laneColorForIndex($index),
                ];
            }

            return self::ensureActionsLane($areas);
        }

        $presetAreas = $presets[$selectedTheme] ?? $presets['momentum'];

        $areas = array_values(array_map(
            static fn (array $area): array => [
                'key' => (string) $area['key'],
                'title' => (string) $area['title'],
                'subtitle' => (string) $area['subtitle'],
                'color' => (string) $area['color'],
            ],
            $presetAreas,
        ));

        return self::ensureActionsLane($areas);
    }

    /**
     * @param array<int, string|null> $titles
     * @return array<int, string>
     */
    private static function sanitizeTitles(array $titles): array
    {
        $clean = [];

        foreach ($titles as $raw) {
            $title = trim((string) $raw);
            if ($title === '') {
                continue;
            }
            $clean[] = mb_substr($title, 0, 80);
        }

        $clean = array_values(array_unique($clean));

        if (count($clean) < 2) {
            return [];
        }

        return array_slice($clean, 0, 8);
    }

    private static function resolveActorParticipant(
        RetroSession $session,
        ?string $participantToken,
        ?string $hostToken,
    ): ?RetroParticipant {
        if ($participantToken) {
            $participant = RetroParticipant::where('session_id', $session->id)
                ->where('token', $participantToken)
                ->first();

            if ($participant) {
                return $participant;
            }
        }

        if ($hostToken && hash_equals($session->host_token, $hostToken)) {
            $host = RetroParticipant::where('session_id', $session->id)
                ->where('role', 'host')
                ->orderBy('id')
                ->first();

            if ($host) {
                return $host;
            }

            return RetroParticipant::createHostForSession($session, 'Host');
        }

        return null;
    }

    private static function moveItem(RetroItem $item, string $targetAreaKey, int $targetIndex): void
    {
        DB::transaction(static function () use ($item, $targetAreaKey, $targetIndex): void {
            $freshItem = RetroItem::where('id', $item->id)->firstOrFail();

            $sessionId = (int) $freshItem->session_id;
            $sourceAreaKey = (string) $freshItem->area_key;

            $targetIds = RetroItem::where('session_id', $sessionId)
                ->where('area_key', $targetAreaKey)
                ->where('id', '!=', $freshItem->id)
                ->orderBy('sort_order')
                ->orderBy('id')
                ->pluck('id')
                ->map(static fn ($id): int => (int) $id)
                ->all();

            $safeIndex = max(0, min($targetIndex, count($targetIds)));
            array_splice($targetIds, $safeIndex, 0, [(int) $freshItem->id]);

            if ($sourceAreaKey !== $targetAreaKey) {
                $sourceIds = RetroItem::where('session_id', $sessionId)
                    ->where('area_key', $sourceAreaKey)
                    ->where('id', '!=', $freshItem->id)
                    ->orderBy('sort_order')
                    ->orderBy('id')
                    ->pluck('id')
                    ->map(static fn ($id): int => (int) $id)
                    ->all();

                self::resequenceLane($sessionId, $sourceAreaKey, $sourceIds);
            }

            self::resequenceLane($sessionId, $targetAreaKey, $targetIds);
        });
    }

    /**
     * @param array<int, int> $itemIds
     */
    private static function resequenceLane(int $sessionId, string $areaKey, array $itemIds): void
    {
        foreach ($itemIds as $index => $itemId) {
            RetroItem::where('id', $itemId)
                ->where('session_id', $sessionId)
                ->update([
                    'area_key' => $areaKey,
                    'sort_order' => $index + 1,
                ]);
        }
    }

    /**
     * @param array<int, array<string, string>> $areas
     * @return array<int, array<string, string>>
     */
    private static function ensureActionsLane(array $areas): array
    {
        if (self::hasActionsLane($areas)) {
            return array_values($areas);
        }

        $areas[] = [
            'key' => 'lane_' . (count($areas) + 1),
            'title' => 'Action Takeaways',
            'subtitle' => 'Decisions to carry into next sprint',
            'color' => 'violet',
        ];

        return array_values($areas);
    }

    /**
     * @param array<int, array<string, mixed>> $areas
     */
    private static function hasActionsLane(array $areas): bool
    {
        foreach ($areas as $area) {
            $title = mb_strtolower((string) ($area['title'] ?? ''));
            if (str_contains($title, 'action') || str_contains($title, 'takeaway')) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return array<string, array<int, array<string, string>>>
     */
    private static function presetAreas(): array
    {
        return [
            'classic' => [
                [
                    'key' => 'lane_1',
                    'title' => 'Went Well',
                    'subtitle' => 'Wins we should repeat',
                    'color' => 'emerald',
                ],
                [
                    'key' => 'lane_2',
                    'title' => 'Could Improve',
                    'subtitle' => 'Pain points to fix',
                    'color' => 'amber',
                ],
                [
                    'key' => 'lane_3',
                    'title' => 'Try Next',
                    'subtitle' => 'Experiments for next sprint',
                    'color' => 'sky',
                ],
            ],
            'momentum' => [
                [
                    'key' => 'lane_1',
                    'title' => 'Rocket Fuel',
                    'subtitle' => 'Things that gave us momentum',
                    'color' => 'emerald',
                ],
                [
                    'key' => 'lane_2',
                    'title' => 'Gravity Wells',
                    'subtitle' => 'Things that slowed the team',
                    'color' => 'rose',
                ],
                [
                    'key' => 'lane_3',
                    'title' => 'Next Orbit',
                    'subtitle' => 'Changes to test next sprint',
                    'color' => 'sky',
                ],
            ],
            'weather' => [
                [
                    'key' => 'lane_1',
                    'title' => 'Sunny Skies',
                    'subtitle' => 'What felt smooth and clear',
                    'color' => 'emerald',
                ],
                [
                    'key' => 'lane_2',
                    'title' => 'Cloud Cover',
                    'subtitle' => 'What created drag or confusion',
                    'color' => 'amber',
                ],
                [
                    'key' => 'lane_3',
                    'title' => 'Lightning Fixes',
                    'subtitle' => 'Quick improvements we can make',
                    'color' => 'sky',
                ],
            ],
            'trailblazer' => [
                [
                    'key' => 'lane_1',
                    'title' => 'Strong Footing',
                    'subtitle' => 'Practices worth keeping',
                    'color' => 'emerald',
                ],
                [
                    'key' => 'lane_2',
                    'title' => 'Loose Gravel',
                    'subtitle' => 'Bumps that tripped us up',
                    'color' => 'rose',
                ],
                [
                    'key' => 'lane_3',
                    'title' => 'Trail Markers',
                    'subtitle' => 'Actions for the next leg',
                    'color' => 'sky',
                ],
            ],
            'custom' => [],
        ];
    }

    private static function laneColorForIndex(int $index): string
    {
        $palette = ['emerald', 'sky', 'amber', 'rose', 'violet', 'teal', 'fuchsia', 'orange'];

        return $palette[$index % count($palette)];
    }

    private static function pickItemColor(string $areaKey): string
    {
        $hash = abs(crc32($areaKey));
        $palette = ['slate', 'amber', 'sky', 'rose', 'teal', 'violet'];

        return $palette[$hash % count($palette)];
    }
}
