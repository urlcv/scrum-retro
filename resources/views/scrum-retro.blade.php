@php
    /** @var \App\Models\Tool $toolModel */
    /** @var \URLCV\ScrumRetro\Laravel\ScrumRetroTool $toolInstance */
@endphp

@push('head')
<style>
    [x-cloak] { display: none !important; }

    .retro-shell {
        --retro-ink: #0f172a;
        --retro-soft: #475569;
        --retro-panel: rgba(255, 255, 255, 0.9);
        --retro-line: rgba(15, 23, 42, 0.12);

        background:
            radial-gradient(circle at 12% 10%, rgba(34, 197, 94, 0.25), transparent 34%),
            radial-gradient(circle at 84% 14%, rgba(59, 130, 246, 0.25), transparent 30%),
            radial-gradient(circle at 60% 88%, rgba(244, 114, 182, 0.18), transparent 30%),
            linear-gradient(150deg, #f8fafc 0%, #e2e8f0 100%);
        border-radius: 1rem;
        border: 1px solid rgba(148, 163, 184, 0.35);
        padding: 1.25rem;
    }

    .retro-panel {
        border: 1px solid var(--retro-line);
        border-radius: 0.85rem;
        background: var(--retro-panel);
        backdrop-filter: blur(6px);
    }

    .retro-lane {
        border: 1px solid rgba(15, 23, 42, 0.12);
        border-radius: 0.85rem;
        background: rgba(255, 255, 255, 0.88);
        min-height: 23rem;
    }

    .retro-scroll {
        max-height: 16rem;
        overflow-y: auto;
    }
</style>
@endpush

<div x-data="scrumRetroBoard()" x-init="init()" x-cloak class="-m-6">
    <div class="retro-shell space-y-4">
        <div class="retro-panel px-4 py-3">
            <h2 class="text-lg font-semibold text-slate-900">Realtime Scrum Retro</h2>
            <p class="mt-1 text-sm text-slate-600">
                Create a share link, teammates join by name, and everyone adds ideas to flexible retro lanes in real time.
            </p>
        </div>

        <div x-show="!sessionCode" class="retro-panel p-4 space-y-4">
            <h3 class="text-sm font-semibold text-slate-900 uppercase tracking-wide">Create a retro room</h3>

            <div class="grid gap-3 md:grid-cols-3">
                <div>
                    <label class="block text-xs font-semibold text-slate-700">Your name</label>
                    <input
                        type="text"
                        x-model="createForm.host_name"
                        class="mt-1 block w-full rounded-md border-slate-300 text-sm shadow-sm focus:border-sky-500 focus:ring-sky-500"
                        placeholder="Facilitator"
                    >
                </div>
                <div>
                    <label class="block text-xs font-semibold text-slate-700">Team name (optional)</label>
                    <input
                        type="text"
                        x-model="createForm.team_name"
                        class="mt-1 block w-full rounded-md border-slate-300 text-sm shadow-sm focus:border-sky-500 focus:ring-sky-500"
                        placeholder="Payments Squad"
                    >
                </div>
                <div>
                    <label class="block text-xs font-semibold text-slate-700">Board title (optional)</label>
                    <input
                        type="text"
                        x-model="createForm.board_title"
                        class="mt-1 block w-full rounded-md border-slate-300 text-sm shadow-sm focus:border-sky-500 focus:ring-sky-500"
                        placeholder="Sprint 18 Retro"
                    >
                </div>
            </div>

            <div class="space-y-2">
                <label class="block text-xs font-semibold text-slate-700">Choose a lane style</label>
                <div class="flex flex-wrap gap-2">
                    <template x-for="preset in themeChoices" :key="preset.key">
                        <button
                            type="button"
                            @click="createForm.theme = preset.key"
                            class="rounded-full border px-3 py-1.5 text-xs font-semibold"
                            :class="createForm.theme === preset.key
                                ? 'border-sky-600 bg-sky-100 text-sky-800'
                                : 'border-slate-300 bg-white text-slate-700 hover:bg-slate-50'"
                            x-text="preset.label"
                        ></button>
                    </template>
                </div>
                <div class="rounded-md border border-slate-200 bg-slate-50 px-3 py-2 text-xs text-slate-700">
                    <div class="font-semibold text-slate-900">Preview</div>
                    <div class="mt-1" x-text="themePreviewLine(createForm.theme)"></div>
                </div>
            </div>

            <div x-show="createForm.theme === 'custom'" class="space-y-2 rounded-md border border-sky-200 bg-sky-50 p-3">
                <div class="flex items-center justify-between">
                    <label class="text-xs font-semibold text-sky-900">Custom lane titles</label>
                    <button
                        type="button"
                        class="rounded-md border border-sky-300 bg-white px-2 py-1 text-[11px] font-semibold text-sky-800 hover:bg-sky-100"
                        @click="addCustomAreaInput()"
                    >
                        Add lane
                    </button>
                </div>
                <template x-for="(title, idx) in createForm.custom_areas" :key="'create-custom-' + idx">
                    <div class="flex items-center gap-2">
                        <input
                            type="text"
                            x-model="createForm.custom_areas[idx]"
                            class="block w-full rounded-md border-sky-300 text-sm shadow-sm focus:border-sky-500 focus:ring-sky-500"
                            placeholder="Lane title"
                        >
                        <button
                            type="button"
                            class="rounded-md border border-slate-300 bg-white px-2 py-1 text-[11px] font-semibold text-slate-600 hover:bg-slate-100"
                            @click="removeCustomAreaInput(idx)"
                            :disabled="createForm.custom_areas.length <= 2"
                        >
                            Remove
                        </button>
                    </div>
                </template>
            </div>

            <div class="flex items-center justify-between">
                <p class="text-xs text-slate-600">The organiser gets a host token automatically and can rename lanes at any time.</p>
                <button
                    type="button"
                    class="rounded-md bg-slate-900 px-4 py-2 text-sm font-semibold text-white hover:bg-slate-800"
                    @click="createSession()"
                    :disabled="creating"
                >
                    <span x-show="!creating">Create share link</span>
                    <span x-show="creating">Creating...</span>
                </button>
            </div>

            <p x-show="createError" class="text-sm text-rose-700" x-text="createError"></p>
        </div>

        <div x-show="sessionCode" class="retro-panel p-4 space-y-4" x-cloak>
            <div class="flex flex-wrap items-start justify-between gap-3">
                <div>
                    <div class="text-xs uppercase tracking-wide text-slate-500">Active room</div>
                    <h3 class="text-lg font-semibold text-slate-900" x-text="session.board_title || 'Sprint Retro Board'"></h3>
                    <p class="text-sm text-slate-600" x-text="session.team_name || 'Shared with your team via link'"></p>
                    <div class="mt-1 text-xs text-slate-500">
                        Code: <span class="font-mono" x-text="sessionCode"></span>
                        <span class="mx-2">|</span>
                        <span x-text="participants.length + ' joined'"></span>
                    </div>
                </div>
                <div class="flex items-center gap-2">
                    <button
                        type="button"
                        class="rounded-md border border-slate-300 bg-white px-3 py-1.5 text-xs font-semibold text-slate-700 hover:bg-slate-100"
                        @click="copyInviteLink()"
                    >
                        <span x-show="!copied">Copy invite link</span>
                        <span x-show="copied">Copied</span>
                    </button>
                    <button
                        type="button"
                        class="rounded-md border border-slate-300 bg-white px-3 py-1.5 text-xs font-semibold text-slate-700 hover:bg-slate-100"
                        @click="startAnotherRoom()"
                    >
                        New room
                    </button>
                </div>
            </div>

            <div x-show="!participantToken" class="rounded-md border border-slate-200 bg-white p-3">
                <div class="flex flex-col gap-2 md:flex-row md:items-end">
                    <div class="flex-1">
                        <label class="block text-xs font-semibold text-slate-700">Join with your name</label>
                        <input
                            type="text"
                            x-model="joinForm.name"
                            class="mt-1 block w-full rounded-md border-slate-300 text-sm shadow-sm focus:border-sky-500 focus:ring-sky-500"
                            placeholder="Alex"
                        >
                    </div>
                    <button
                        type="button"
                        class="rounded-md bg-slate-900 px-4 py-2 text-sm font-semibold text-white hover:bg-slate-800"
                        @click="joinSession()"
                        :disabled="joining"
                    >
                        <span x-show="!joining">Join room</span>
                        <span x-show="joining">Joining...</span>
                    </button>
                </div>
                <p x-show="joinError" class="mt-2 text-sm text-rose-700" x-text="joinError"></p>
            </div>

            <div x-show="participantToken" class="grid gap-4 xl:grid-cols-12" x-cloak>
                <div class="xl:col-span-9 space-y-3">
                    <div class="grid gap-3 md:grid-cols-2 xl:grid-cols-3">
                        <template x-for="lane in areas" :key="lane.key">
                            <section class="retro-lane flex flex-col">
                                <header class="rounded-t-[0.75rem] border-b border-slate-200 px-3 py-2" :class="laneHeaderClass(lane.color)">
                                    <h4 class="text-sm font-semibold" x-text="lane.title"></h4>
                                    <p class="text-[11px] opacity-80" x-text="lane.subtitle"></p>
                                </header>

                                <div class="p-3 space-y-3 flex-1">
                                    <div class="retro-scroll space-y-2 pr-1">
                                        <template x-for="item in itemsForArea(lane.key)" :key="'item-' + item.id">
                                            <article class="rounded-md border bg-white p-2" :class="itemCardClass(item.color)">
                                                <p class="text-sm whitespace-pre-line text-slate-800" x-text="item.text"></p>
                                                <div class="mt-2 text-[11px] text-slate-500">
                                                    by <span class="font-semibold" x-text="item.author?.name || 'Teammate'"></span>
                                                </div>
                                            </article>
                                        </template>
                                        <p x-show="itemsForArea(lane.key).length === 0" class="text-xs text-slate-500">
                                            No ideas here yet.
                                        </p>
                                    </div>

                                    <div class="space-y-2">
                                        <textarea
                                            rows="2"
                                            x-model="draftByArea[lane.key]"
                                            class="block w-full rounded-md border-slate-300 text-sm shadow-sm focus:border-sky-500 focus:ring-sky-500"
                                            placeholder="Add an idea"
                                        ></textarea>
                                        <button
                                            type="button"
                                            class="w-full rounded-md bg-slate-900 px-3 py-1.5 text-xs font-semibold text-white hover:bg-slate-800"
                                            :disabled="addingByArea[lane.key]"
                                            @click="addIdea(lane.key)"
                                        >
                                            <span x-show="!addingByArea[lane.key]">Add to lane</span>
                                            <span x-show="addingByArea[lane.key]">Adding...</span>
                                        </button>
                                    </div>
                                </div>
                            </section>
                        </template>
                    </div>
                </div>

                <aside class="xl:col-span-3 space-y-3">
                    <div class="retro-panel p-3">
                        <h4 class="text-xs font-semibold uppercase tracking-wide text-slate-500">Participants</h4>
                        <ul class="mt-2 space-y-1 max-h-40 overflow-y-auto">
                            <template x-for="person in participants" :key="'person-' + person.id">
                                <li class="flex items-center justify-between rounded-md border border-slate-200 bg-white px-2 py-1.5">
                                    <span class="text-sm text-slate-800" x-text="person.name"></span>
                                    <span class="rounded-full px-2 py-0.5 text-[10px] font-semibold"
                                          :class="person.role === 'host' ? 'bg-amber-100 text-amber-800' : 'bg-slate-100 text-slate-600'"
                                          x-text="person.role === 'host' ? 'host' : 'member'">
                                    </span>
                                </li>
                            </template>
                        </ul>
                    </div>

                    <div x-show="isHost" class="retro-panel p-3 space-y-3" x-cloak>
                        <h4 class="text-xs font-semibold uppercase tracking-wide text-slate-500">Facilitator controls</h4>

                        <div>
                            <div class="text-[11px] font-semibold text-slate-700">Quick lane presets</div>
                            <div class="mt-1 flex flex-wrap gap-1.5">
                                <template x-for="preset in themeChoices" :key="'host-' + preset.key">
                                    <button
                                        type="button"
                                        class="rounded-md border px-2 py-1 text-[11px] font-semibold"
                                        :class="session.theme === preset.key ? 'border-sky-600 bg-sky-100 text-sky-800' : 'border-slate-300 bg-white text-slate-700 hover:bg-slate-50'"
                                        @click="applyPreset(preset.key)"
                                    >
                                        <span x-text="preset.label"></span>
                                    </button>
                                </template>
                            </div>
                        </div>

                        <div class="space-y-1.5">
                            <div class="flex items-center justify-between">
                                <div class="text-[11px] font-semibold text-slate-700">Custom lane titles</div>
                                <button
                                    type="button"
                                    class="rounded border border-slate-300 bg-white px-2 py-1 text-[11px] font-semibold text-slate-700 hover:bg-slate-100"
                                    @click="addAreaEditorField()"
                                >
                                    Add
                                </button>
                            </div>
                            <template x-for="(title, idx) in areaEditor" :key="'editor-' + idx">
                                <div class="flex items-center gap-1.5">
                                    <input
                                        type="text"
                                        x-model="areaEditor[idx]"
                                        class="block w-full rounded-md border-slate-300 text-xs shadow-sm focus:border-sky-500 focus:ring-sky-500"
                                    >
                                    <button
                                        type="button"
                                        class="rounded border border-slate-300 bg-white px-2 py-1 text-[10px] font-semibold text-slate-600 hover:bg-slate-100"
                                        @click="removeAreaEditorField(idx)"
                                        :disabled="areaEditor.length <= 2"
                                    >
                                        Del
                                    </button>
                                </div>
                            </template>
                            <button
                                type="button"
                                class="w-full rounded-md bg-slate-900 px-3 py-1.5 text-xs font-semibold text-white hover:bg-slate-800"
                                @click="saveAreaLayout()"
                                :disabled="savingAreas"
                            >
                                <span x-show="!savingAreas">Save custom layout</span>
                                <span x-show="savingAreas">Saving...</span>
                            </button>
                        </div>
                    </div>

                    <p x-show="boardError" class="text-sm text-rose-700" x-text="boardError"></p>
                </aside>
            </div>
        </div>
    </div>
</div>

@push('scripts')
<script>
    function scrumRetroBoard() {
        return {
            sessionCode: null,
            inviteLink: null,
            participantToken: null,
            hostToken: null,
            isHost: false,
            pollTimer: null,
            copied: false,

            creating: false,
            joining: false,
            savingAreas: false,
            addingByArea: {},

            createError: '',
            joinError: '',
            boardError: '',

            session: {
                team_name: null,
                board_title: null,
                theme: 'momentum',
            },
            areas: [],
            items: [],
            participants: [],
            draftByArea: {},
            areaEditor: [],
            areaEditorServerHash: '',

            createForm: {
                host_name: '',
                team_name: '',
                board_title: '',
                theme: 'momentum',
                custom_areas: ['Rocket Fuel', 'Gravity Wells', 'Next Orbit'],
            },
            joinForm: {
                name: '',
            },

            themeChoices: [
                { key: 'classic', label: 'Classic' },
                { key: 'momentum', label: 'Momentum' },
                { key: 'weather', label: 'Weather' },
                { key: 'trailblazer', label: 'Trailblazer' },
                { key: 'custom', label: 'Custom' },
            ],

            init() {
                const url = new URL(window.location.href);
                const code = url.searchParams.get('code');

                if (!code) {
                    return;
                }

                this.sessionCode = code;
                this.inviteLink = window.location.origin + '/tools/scrum-retro?code=' + code;

                const base = this.storageBase();
                this.participantToken = window.localStorage.getItem(base + 'participantToken');
                this.hostToken = window.localStorage.getItem(base + 'hostToken');
                this.isHost = !!this.hostToken;

                this.startPolling();
            },

            storageBase() {
                return 'scrumRetro:' + this.sessionCode + ':';
            },

            csrfToken() {
                return document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
            },

            themePreviewLine(theme) {
                const previews = {
                    classic: 'Went Well | Could Improve | Action Items',
                    momentum: 'Rocket Fuel | Gravity Wells | Next Orbit',
                    weather: 'Sunny Skies | Cloud Cover | Lightning Fixes',
                    trailblazer: 'Strong Footing | Loose Gravel | Trail Markers',
                    custom: (this.createForm.custom_areas || []).filter(Boolean).join(' | ') || 'Define your own lane names',
                };

                return previews[theme] || previews.momentum;
            },

            addCustomAreaInput() {
                if (this.createForm.custom_areas.length >= 8) {
                    return;
                }

                this.createForm.custom_areas.push('');
            },

            removeCustomAreaInput(index) {
                if (this.createForm.custom_areas.length <= 2) {
                    return;
                }

                this.createForm.custom_areas.splice(index, 1);
            },

            createSession() {
                this.createError = '';

                if (!this.createForm.host_name.trim()) {
                    this.createError = 'Please enter your name before creating the room.';
                    return;
                }

                const payload = {
                    host_name: this.createForm.host_name.trim(),
                    team_name: this.createForm.team_name.trim(),
                    board_title: this.createForm.board_title.trim(),
                    theme: this.createForm.theme,
                    custom_areas: this.createForm.theme === 'custom' ? this.createForm.custom_areas : null,
                };

                this.creating = true;

                fetch('/tools/scrum-retro/session', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': this.csrfToken(),
                        'Accept': 'application/json',
                    },
                    body: JSON.stringify(payload),
                })
                    .then(async (res) => {
                        const data = await res.json().catch(() => ({}));

                        if (!res.ok || !data.success) {
                            throw new Error(data.message || this.firstValidationError(data) || 'Unable to create room.');
                        }

                        this.sessionCode = data.code;
                        this.inviteLink = window.location.origin + '/tools/scrum-retro?code=' + data.code;

                        const base = this.storageBase();
                        if (data.host_token) {
                            this.hostToken = data.host_token;
                            this.isHost = true;
                            window.localStorage.setItem(base + 'hostToken', data.host_token);
                        }

                        if (data.host_participant_token) {
                            this.participantToken = data.host_participant_token;
                            window.localStorage.setItem(base + 'participantToken', data.host_participant_token);
                        }

                        this.joinForm.name = payload.host_name;

                        const url = new URL(window.location.href);
                        url.searchParams.set('code', data.code);
                        window.history.replaceState({}, '', url.toString());

                        this.startPolling();
                    })
                    .catch((error) => {
                        this.createError = error.message || 'Unable to create room.';
                    })
                    .finally(() => {
                        this.creating = false;
                    });
            },

            joinSession() {
                this.joinError = '';

                if (!this.sessionCode) {
                    this.joinError = 'Missing room code.';
                    return;
                }

                if (!this.joinForm.name.trim()) {
                    this.joinError = 'Please enter your name.';
                    return;
                }

                this.joining = true;

                fetch('/tools/scrum-retro/session/' + encodeURIComponent(this.sessionCode) + '/join', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': this.csrfToken(),
                        'Accept': 'application/json',
                    },
                    body: JSON.stringify({
                        name: this.joinForm.name.trim(),
                    }),
                })
                    .then(async (res) => {
                        const data = await res.json().catch(() => ({}));

                        if (!res.ok || !data.success) {
                            throw new Error(data.message || this.firstValidationError(data) || 'Unable to join room.');
                        }

                        this.participantToken = data.participant_token;
                        window.localStorage.setItem(this.storageBase() + 'participantToken', data.participant_token);
                        this.fetchState();
                    })
                    .catch((error) => {
                        this.joinError = error.message || 'Unable to join room.';
                    })
                    .finally(() => {
                        this.joining = false;
                    });
            },

            startPolling() {
                if (!this.sessionCode) {
                    return;
                }

                if (this.pollTimer) {
                    clearInterval(this.pollTimer);
                }

                this.fetchState();
                this.pollTimer = setInterval(() => this.fetchState(), 2500);
            },

            fetchState() {
                if (!this.sessionCode) {
                    return;
                }

                fetch('/tools/scrum-retro/session/' + encodeURIComponent(this.sessionCode) + '/state', {
                    headers: { 'Accept': 'application/json' },
                })
                    .then(async (res) => {
                        const data = await res.json().catch(() => ({}));
                        if (!res.ok || data.error) {
                            return;
                        }

                        this.session = data.session || this.session;
                        this.areas = data.areas || [];
                        this.items = data.items || [];
                        this.participants = data.participants || [];

                        this.ensureDraftKeys();

                        if (this.isHost) {
                            const serverHash = JSON.stringify(this.areas.map((lane) => lane.title || '').slice(0, 8));
                            if (this.areaEditorServerHash !== serverHash) {
                                this.areaEditor = this.areas.map((lane) => lane.title || '').slice(0, 8);
                                this.areaEditorServerHash = serverHash;
                            }
                        }
                    })
                    .catch(() => {
                        // Retry with next poll.
                    });
            },

            ensureDraftKeys() {
                const next = {};
                this.areas.forEach((lane) => {
                    next[lane.key] = this.draftByArea[lane.key] || '';
                });
                this.draftByArea = next;
            },

            itemsForArea(areaKey) {
                return this.items.filter((item) => item.area_key === areaKey);
            },

            addIdea(areaKey) {
                this.boardError = '';

                const message = (this.draftByArea[areaKey] || '').trim();

                if (!message) {
                    this.boardError = 'Write a short idea before adding it to a lane.';
                    return;
                }

                if (!this.participantToken) {
                    this.boardError = 'Join the room first.';
                    return;
                }

                this.addingByArea[areaKey] = true;

                fetch('/tools/scrum-retro/session/' + encodeURIComponent(this.sessionCode) + '/items', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': this.csrfToken(),
                        'Accept': 'application/json',
                    },
                    body: JSON.stringify({
                        participant_token: this.participantToken,
                        area_key: areaKey,
                        text: message,
                    }),
                })
                    .then(async (res) => {
                        const data = await res.json().catch(() => ({}));
                        if (!res.ok || !data.success) {
                            throw new Error(data.message || this.firstValidationError(data) || 'Unable to add idea.');
                        }

                        this.draftByArea[areaKey] = '';
                        this.fetchState();
                    })
                    .catch((error) => {
                        this.boardError = error.message || 'Unable to add idea.';
                    })
                    .finally(() => {
                        this.addingByArea[areaKey] = false;
                    });
            },

            applyPreset(theme) {
                if (!this.isHost || !this.hostToken || !this.sessionCode) {
                    return;
                }

                this.boardError = '';
                this.savingAreas = true;

                fetch('/tools/scrum-retro/session/' + encodeURIComponent(this.sessionCode) + '/areas', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': this.csrfToken(),
                        'Accept': 'application/json',
                    },
                    body: JSON.stringify({
                        host_token: this.hostToken,
                        theme: theme,
                    }),
                })
                    .then(async (res) => {
                        const data = await res.json().catch(() => ({}));
                        if (!res.ok || !data.success) {
                            throw new Error(data.message || this.firstValidationError(data) || 'Unable to apply preset.');
                        }

                        this.fetchState();
                    })
                    .catch((error) => {
                        this.boardError = error.message || 'Unable to apply preset.';
                    })
                    .finally(() => {
                        this.savingAreas = false;
                    });
            },

            addAreaEditorField() {
                if (this.areaEditor.length >= 8) {
                    return;
                }

                this.areaEditor.push('');
            },

            removeAreaEditorField(index) {
                if (this.areaEditor.length <= 2) {
                    return;
                }

                this.areaEditor.splice(index, 1);
            },

            saveAreaLayout() {
                if (!this.isHost || !this.hostToken || !this.sessionCode) {
                    return;
                }

                this.boardError = '';

                const areaTitles = this.areaEditor
                    .map((title) => (title || '').trim())
                    .filter((title) => title.length > 0);

                if (areaTitles.length < 2) {
                    this.boardError = 'Please provide at least 2 lane titles.';
                    return;
                }

                this.savingAreas = true;

                fetch('/tools/scrum-retro/session/' + encodeURIComponent(this.sessionCode) + '/areas', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': this.csrfToken(),
                        'Accept': 'application/json',
                    },
                    body: JSON.stringify({
                        host_token: this.hostToken,
                        theme: 'custom',
                        area_titles: areaTitles,
                    }),
                })
                    .then(async (res) => {
                        const data = await res.json().catch(() => ({}));
                        if (!res.ok || !data.success) {
                            throw new Error(data.message || this.firstValidationError(data) || 'Unable to save layout.');
                        }

                        this.fetchState();
                    })
                    .catch((error) => {
                        this.boardError = error.message || 'Unable to save layout.';
                    })
                    .finally(() => {
                        this.savingAreas = false;
                    });
            },

            copyInviteLink() {
                if (!this.inviteLink && this.sessionCode) {
                    this.inviteLink = window.location.origin + '/tools/scrum-retro?code=' + this.sessionCode;
                }

                if (!this.inviteLink) {
                    return;
                }

                navigator.clipboard?.writeText(this.inviteLink)
                    .then(() => {
                        this.copied = true;
                        setTimeout(() => {
                            this.copied = false;
                        }, 1500);
                    })
                    .catch(() => {
                        this.boardError = 'Copy failed. Please copy the URL from your browser bar.';
                    });
            },

            startAnotherRoom() {
                if (this.pollTimer) {
                    clearInterval(this.pollTimer);
                    this.pollTimer = null;
                }

                if (this.sessionCode) {
                    const base = this.storageBase();
                    window.localStorage.removeItem(base + 'hostToken');
                    window.localStorage.removeItem(base + 'participantToken');
                }

                this.sessionCode = null;
                this.inviteLink = null;
                this.participantToken = null;
                this.hostToken = null;
                this.isHost = false;
                this.areas = [];
                this.items = [];
                this.participants = [];
                this.areaEditor = [];
                this.areaEditorServerHash = '';
                this.draftByArea = {};

                const url = new URL(window.location.href);
                url.searchParams.delete('code');
                window.history.replaceState({}, '', url.toString());
            },

            firstValidationError(payload) {
                if (!payload || typeof payload !== 'object' || !payload.errors) {
                    return '';
                }

                const keys = Object.keys(payload.errors);
                if (!keys.length) {
                    return '';
                }

                const firstKey = keys[0];
                const firstEntry = payload.errors[firstKey];

                if (Array.isArray(firstEntry) && firstEntry.length > 0) {
                    return firstEntry[0];
                }

                return '';
            },

            laneHeaderClass(color) {
                const map = {
                    emerald: 'bg-emerald-100 text-emerald-900',
                    rose: 'bg-rose-100 text-rose-900',
                    amber: 'bg-amber-100 text-amber-900',
                    sky: 'bg-sky-100 text-sky-900',
                    violet: 'bg-violet-100 text-violet-900',
                    teal: 'bg-teal-100 text-teal-900',
                    fuchsia: 'bg-fuchsia-100 text-fuchsia-900',
                    orange: 'bg-orange-100 text-orange-900',
                    slate: 'bg-slate-100 text-slate-900',
                };

                return map[color] || map.slate;
            },

            itemCardClass(color) {
                const map = {
                    amber: 'border-amber-200 bg-amber-50',
                    sky: 'border-sky-200 bg-sky-50',
                    rose: 'border-rose-200 bg-rose-50',
                    teal: 'border-teal-200 bg-teal-50',
                    violet: 'border-violet-200 bg-violet-50',
                    slate: 'border-slate-200 bg-slate-50',
                };

                return map[color] || map.slate;
            },
        };
    }
</script>
@endpush
