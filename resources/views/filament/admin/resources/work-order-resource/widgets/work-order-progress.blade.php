@php
    use Carbon\Carbon;

    $now = now();
    $endTime = \Carbon\Carbon::parse($record->end_time);
    $isOverdue = $now->gt($endTime);

    $okQty = $record->ok_qtys ?? 0;
    $scrappedQty = $record->scrapped_qtys ?? 0;
    $totalQty = $record->qty ?? 1;

    $progress = min(($okQty / $totalQty) * 100, 100);
    $pendingQty = max($totalQty - ($okQty + $scrappedQty), 0);

    $progressColor = $isOverdue ? '#ef4444' : '#10b981'; // red / green
@endphp

<div class="flex justify-center items-center min-h-[250px]">
    <div class="relative w-40 h-40">
        {{-- Outer Ring --}}
        <div
            class="absolute inset-0 rounded-full"
            style="background: conic-gradient({{ $progressColor }} {{ $progress }}%, #e5e7eb {{ $progress }}%);"
        ></div>

        {{-- Inner Circle --}}
        <div class="absolute inset-[12px] bg-white rounded-full flex flex-col items-center justify-center text-center">
            <div class="text-xl font-bold text-gray-900">{{ number_format($progress) }}%</div>
            <div class="text-xs text-gray-500">
                {{ $isOverdue
                    ? 'Ended ' . $endTime->diffForHumans($now, ['parts' => 1])
                    : 'Ending in ' . $endTime->diffForHumans($now, ['parts' => 1]) }}
            </div>
        </div>
    </div>

    {{-- QTY Summary --}}
    <div class="ml-8 space-y-2 text-sm text-gray-700">
        <div><strong>✅ OK:</strong> {{ $okQty }}</div>
        <div><strong>❌ Scrapped:</strong> {{ $scrappedQty }}</div>
        <div><strong>⏳ Pending:</strong> {{ $pendingQty }}</div>
    </div>
</div>
