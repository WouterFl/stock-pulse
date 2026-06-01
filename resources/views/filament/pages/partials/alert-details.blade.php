@php
    $details = $alert->details ?? [];
@endphp

<div class="space-y-4 text-sm">
    <div class="grid grid-cols-2 gap-3">
        <div>
            <div class="text-gray-500 dark:text-gray-400">Bedrijf</div>
            <div class="font-medium">{{ $alert->company?->ticker }} — {{ $alert->company?->name }}</div>
        </div>
        <div>
            <div class="text-gray-500 dark:text-gray-400">Getriggerd</div>
            <div class="font-medium">{{ $alert->triggered_at?->format('d-m-Y H:i:s') }}</div>
        </div>

        @if (isset($details['from'], $details['to']))
            <div>
                <div class="text-gray-500 dark:text-gray-400">Van → naar</div>
                <div class="font-medium">
                    {{ $alert->company?->currency }} {{ number_format((float) $details['from'], 2) }}
                    →
                    {{ $alert->company?->currency }} {{ number_format((float) $details['to'], 2) }}
                </div>
            </div>
        @endif

        @if (isset($details['change_percent']))
            <div>
                <div class="text-gray-500 dark:text-gray-400">Verandering</div>
                <div class="font-medium {{ (float) $details['change_percent'] >= 0 ? 'text-success-600' : 'text-danger-600' }}">
                    {{ sprintf('%+.2f%%', (float) $details['change_percent']) }}
                    @isset($details['window_minutes'])
                        <span class="text-gray-500">in {{ $details['window_minutes'] }} min</span>
                    @endisset
                </div>
            </div>
        @endif

        @isset($details['z_score'])
            <div>
                <div class="text-gray-500 dark:text-gray-400">Z-score (σ)</div>
                <div class="font-medium">{{ $details['z_score'] }} (mean {{ $details['mean'] ?? '–' }}, stddev {{ $details['stddev'] ?? '–' }})</div>
            </div>
        @endisset

        @isset($details['article_count'])
            <div>
                <div class="text-gray-500 dark:text-gray-400">Artikelen (1u)</div>
                <div class="font-medium">{{ $details['article_count'] }}</div>
            </div>
        @endisset
    </div>

    <div>
        <div class="mb-2 font-semibold">Mogelijk gerelateerd nieuws</div>
        @forelse ($alert->newsArticles as $article)
            <a href="{{ $article->url }}" target="_blank" rel="noopener"
               class="block rounded-lg border border-gray-200 dark:border-gray-700 p-2 mb-2 hover:bg-gray-50 dark:hover:bg-white/5">
                <div class="font-medium">{{ $article->title }}</div>
                <div class="text-xs text-gray-500">{{ $article->source }} · {{ $article->published_at?->format('d-m-Y H:i') }}</div>
            </a>
        @empty
            <div class="text-gray-500">Geen gekoppelde artikelen gevonden.</div>
        @endforelse
    </div>
</div>
