<x-filament-panels::page>
    {{-- Push-activatie + test (client-side via window.StockPulsePush, SP-29) --}}
    <div
        x-data="{
            status: 'default',
            busy: false,
            isIos: /iphone|ipad|ipod/i.test(navigator.userAgent),
            isStandalone: window.matchMedia('(display-mode: standalone)').matches || window.navigator.standalone === true,
            async refresh() {
                this.status = window.StockPulsePush ? await window.StockPulsePush.getStatus() : 'unsupported';
            },
            async enable() {
                this.busy = true;
                try { await window.StockPulsePush.subscribe(); window.location.reload(); }
                catch (e) { alert(e.message); }
                finally { this.busy = false; await this.refresh(); }
            },
            async disable() {
                this.busy = true;
                try { await window.StockPulsePush.unsubscribe(); window.location.reload(); }
                finally { this.busy = false; await this.refresh(); }
            },
            async test() {
                this.busy = true;
                try { await window.StockPulsePush.test(); }
                catch (e) { alert(e.message); }
                finally { this.busy = false; }
            },
        }"
        x-init="refresh()"
        class="fi-section rounded-xl bg-white p-6 shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10 space-y-4"
    >
        <div class="flex items-center justify-between">
            <div>
                <h3 class="text-base font-semibold">Push op dit apparaat</h3>
                <p class="text-sm text-gray-500" x-text="{
                    'unsupported': 'Deze browser ondersteunt geen push.',
                    'denied': 'Toestemming is geblokkeerd in de browserinstellingen.',
                    'subscribed': 'Push is actief op dit apparaat.',
                    'granted': 'Toestemming verleend, nog niet geabonneerd.',
                    'default': 'Push is nog niet ingeschakeld op dit apparaat.'
                }[status]"></p>
            </div>
            <div class="flex gap-2">
                <x-filament::button x-show="status !== 'subscribed' && status !== 'unsupported'" x-on:click="enable()" x-bind:disabled="busy">
                    Schakel push in
                </x-filament::button>
                <x-filament::button color="gray" x-show="status === 'subscribed'" x-on:click="test()" x-bind:disabled="busy">
                    Stuur test-notificatie
                </x-filament::button>
                <x-filament::button color="danger" x-show="status === 'subscribed'" x-on:click="disable()" x-bind:disabled="busy">
                    Uitschakelen
                </x-filament::button>
            </div>
        </div>

        {{-- iOS-hint: push werkt alleen vanuit een geïnstalleerde PWA (iOS 16.4+) --}}
        <template x-if="isIos && !isStandalone">
            <div class="rounded-lg bg-amber-50 dark:bg-amber-500/10 p-3 text-sm text-amber-700 dark:text-amber-300">
                Op iOS werkt push alleen vanuit de geïnstalleerde app. Tik op <strong>Deel</strong> →
                <strong>Zet op beginscherm</strong>, open de app daarvandaan en schakel push daar in.
            </div>
        </template>
    </div>

    {{-- Actieve devices --}}
    <div class="fi-section rounded-xl bg-white p-6 shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10">
        <h3 class="text-base font-semibold mb-3">Actieve devices</h3>
        @forelse ($this->subscriptions as $sub)
            <div class="flex items-center justify-between border-b border-gray-100 dark:border-white/5 py-2 last:border-0">
                <div>
                    <div class="font-medium text-sm">{{ $sub['host'] }}</div>
                    <div class="text-xs text-gray-500">Toegevoegd {{ $sub['created_at']?->diffForHumans() }}</div>
                </div>
                <x-filament::button size="sm" color="danger" wire:click="deleteSubscription({{ $sub['id'] }})">
                    Verwijderen
                </x-filament::button>
            </div>
        @empty
            <p class="text-sm text-gray-500">Nog geen geregistreerde devices.</p>
        @endforelse
    </div>

    {{-- Voorkeuren-formulier --}}
    <form wire:submit="save" class="space-y-6">
        {{ $this->form }}

        <div class="flex justify-end gap-2">
            <x-filament::button type="submit">Opslaan</x-filament::button>
        </div>
    </form>
</x-filament-panels::page>
