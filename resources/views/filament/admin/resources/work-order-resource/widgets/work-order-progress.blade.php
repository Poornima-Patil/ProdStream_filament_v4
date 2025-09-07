@php
    use Carbon\Carbon;

    $now = now();
    $endTime = \Carbon\Carbon::parse($record->end_time);
    $isOverdue = $now->gt($endTime);

    $okQty = $record->ok_qtys ?? 0;
    $scrappedQty = $record->scrapped_qtys ?? 0;
    $totalQty = $record->qty ?? 1;

    $progress = min((($okQty + $scrappedQty) / $totalQty) * 100, 100);
    $pendingQty = max($totalQty - ($okQty + $scrappedQty), 0);

    // Use green for normal, amber for overdue in dark mode, red for overdue in light mode
    $progressColorLight = $isOverdue ? '#ef4444' : '#10b981'; // red / green
    $progressColorDark = $isOverdue ? '#f59e42' : '#22d3ee'; // amber-400 / cyan-400
    $ringBgLight = '#e5e7eb'; // slate-200
    $ringBgDark = '#334155'; // slate-700
@endphp

<div class="flex flex-col md:flex-row justify-center items-center min-h-[200px] md:min-h-[250px] p-4">
    <div class="relative w-32 h-32 sm:w-36 sm:h-36 md:w-40 md:h-40 mb-4 md:mb-0">
        {{-- Outer Ring --}}
        <div
            class="absolute inset-0 rounded-full"
            style="
                background: conic-gradient(var(--progress-color, {{ $progressColorLight }}) {{ $progress }}%, var(--ring-bg, {{ $ringBgLight }}) {{ $progress }}%);
            "
            x-data="{
                setRingColors() {
                    const isDark = document.documentElement.classList.contains('dark');
                    this.$el.style.setProperty('--ring-bg', isDark ? '{{ $ringBgDark }}' : '{{ $ringBgLight }}');
                    this.$el.style.setProperty('--progress-color', isDark ? '{{ $progressColorDark }}' : '{{ $progressColorLight }}');
                }
            }"
            x-init="setRingColors(); window.matchMedia('(prefers-color-scheme: dark)').addEventListener('change', setRingColors);"
        ></div>

        {{-- Inner Circle --}}
        <div class="absolute inset-[10px] sm:inset-[11px] md:inset-[12px] bg-white dark:bg-gray-900 rounded-full flex flex-col items-center justify-center text-center">
            <div class="text-lg sm:text-xl font-bold text-gray-900 dark:text-gray-100">{{ number_format($progress) }}%</div>
            <div class="text-xs text-gray-500 dark:text-gray-400 px-1 text-center">
                {{ $isOverdue
                    ? 'Ended ' . $endTime->diffForHumans($now, ['parts' => 1])
                    : 'Ending in ' . $endTime->diffForHumans($now, ['parts' => 1]) }}
            </div>
        </div>
    </div>

    {{-- QTY Summary --}}
    <div class="md:ml-8 space-y-2 text-sm text-gray-700 dark:text-gray-200 text-center md:text-left">
        <div><strong>✅ OK:</strong> {{ $okQty }}</div>
        <div><strong>❌ Scrapped:</strong> {{ $scrappedQty }}</div>
        <div><strong>⏳ Pending:</strong> {{ $pendingQty }}</div>
    </div>
</div>
