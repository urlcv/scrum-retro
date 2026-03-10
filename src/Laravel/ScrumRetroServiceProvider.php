<?php

declare(strict_types=1);

namespace URLCV\ScrumRetro\Laravel;

use Illuminate\Http\Request;
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
        $this->registerAreasRoute();
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
                'team_name' => $validated['team_name'] ?: null,
                'board_title' => $validated['board_title'] ?: null,
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
            $session = RetroSession::where('code', $code)->with(['participants', 'items.participant'])->first();

            if (! $session) {
                return response()->json(['error' => 'Session not found'], 404);
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
                'participant_token' => ['required', 'string', 'max:128'],
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

            $participant = RetroParticipant::where('session_id', $session->id)
                ->where('token', $validated['participant_token'])
                ->first();

            if (! $participant) {
                return response()->json([
                    'success' => false,
                    'message' => 'Participant not found',
                ], 404);
            }

            $participant->touchLastSeen();

            $item = RetroItem::create([
                'session_id' => $session->id,
                'participant_id' => $participant->id,
                'area_key' => $validated['area_key'],
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

            return $areas;
        }

        $presetAreas = $presets[$selectedTheme] ?? $presets['momentum'];

        return array_values(array_map(
            static fn (array $area): array => [
                'key' => (string) $area['key'],
                'title' => (string) $area['title'],
                'subtitle' => (string) $area['subtitle'],
                'color' => (string) $area['color'],
            ],
            $presetAreas,
        ));
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
                    'title' => 'Action Items',
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
