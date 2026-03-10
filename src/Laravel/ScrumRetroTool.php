<?php

declare(strict_types=1);

namespace URLCV\ScrumRetro\Laravel;

use App\Tools\Contracts\ToolInterface;

class ScrumRetroTool implements ToolInterface
{
    public function slug(): string
    {
        return 'scrum-retro';
    }

    public function name(): string
    {
        return 'Realtime Scrum Retrospective Board';
    }

    public function summary(): string
    {
        return 'Run collaborative retros with shareable links, flexible columns, and live updates for remote teams.';
    }

    public function descriptionMd(): ?string
    {
        return <<<'MD'
## Run lively sprint retros in one shared board

Create a retrospective room, share the link with your team, and let everyone add ideas in real time.

### What makes it useful

1. **Fast room setup** - the organiser creates a room and gets a share link instantly.
2. **Join by name** - no account required for teammates.
3. **Flexible sections** - start with a preset or rename the board columns to match your ceremony style.
4. **Live updates** - ideas appear for everyone without refreshing.
5. **Remote-first flow** - ideal for distributed squads using Zoom, Teams, Meet, or Slack.

Use it for classic *Start / Stop / Continue* formats or themed retros like *Rocket Fuel / Gravity Wells / Next Orbit*.
MD;
    }

    public function categories(): array
    {
        return ['productivity', 'agile'];
    }

    public function tags(): array
    {
        return ['scrum', 'retrospective', 'agile', 'team-collaboration', 'remote'];
    }

    public function inputSchema(): array
    {
        return [];
    }

    public function run(array $input): array
    {
        return [];
    }

    public function mode(): string
    {
        return 'frontend';
    }

    public function isAsync(): bool
    {
        return false;
    }

    public function isPublic(): bool
    {
        return true;
    }

    public function frontendView(): ?string
    {
        return 'scrum-retro::scrum-retro';
    }

    public function rateLimitPerMinute(): int
    {
        return 60;
    }

    public function cacheTtlSeconds(): int
    {
        return 0;
    }

    public function sortWeight(): int
    {
        return 95;
    }
}
