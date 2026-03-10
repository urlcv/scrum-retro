# urlcv/scrum-retro

Realtime scrum retrospective board with share links, join-by-name, and flexible sections for remote teams.

## Features

- Create a retro room in seconds and share a single link with your team.
- Teammates join by name, no account required.
- Start from themed column presets or switch to custom columns.
- Add ideas in real time with live polling updates.
- Edit column titles mid-session as facilitator needs change.

## Requirements

- PHP 8.1+
- Laravel 10/11/12 (for provider auto-discovery and migrations)

## Installation

```bash
composer require urlcv/scrum-retro
```

Register `URLCV\ScrumRetro\Laravel\ScrumRetroTool::class` in `config/tools.php`, then run:

```bash
php artisan migrate
php artisan tools:sync
php artisan sitemap:generate
```

## Usage

Open `/tools/scrum-retro`, create a room, copy the invite link, and share it with your sprint team.

## License

MIT
