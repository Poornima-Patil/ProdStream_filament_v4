<div class="flex items-center gap-4 p-3 bg-gray-50 dark:bg-gray-900/50 rounded-lg border border-gray-200 dark:border-gray-700">
    <div class="flex items-center gap-2">
        <svg class="w-4 h-4 text-blue-600 dark:text-blue-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"></path>
        </svg>
        <span class="text-sm font-medium text-gray-900 dark:text-white">Date Range:</span>
    </div>

    <div class="flex items-center gap-2">
        <input
            type="date"
            wire:model="fromDate"
            class="px-2 py-1 text-sm border border-gray-300 dark:border-gray-600 rounded-md bg-white dark:bg-gray-800 text-gray-900 dark:text-gray-100 focus:ring-1 focus:ring-blue-500 focus:border-blue-500"
        >
        <span class="text-xs text-gray-500 dark:text-gray-400">to</span>
        <input
            type="date"
            wire:model="toDate"
            class="px-2 py-1 text-sm border border-gray-300 dark:border-gray-600 rounded-md bg-white dark:bg-gray-800 text-gray-900 dark:text-gray-100 focus:ring-1 focus:ring-blue-500 focus:border-blue-500"
        >
    </div>

    <button
        wire:click="applyFilter"
        class="px-3 py-1 text-sm font-medium text-white bg-blue-600 hover:bg-blue-700 rounded-md focus:ring-1 focus:ring-blue-500 transition-colors"
    >
        Apply
    </button>

    <button
        wire:click="clearFilter"
        class="px-2 py-1 text-sm font-medium text-gray-600 dark:text-gray-400 bg-gray-100 dark:bg-gray-700 hover:bg-gray-200 dark:hover:bg-gray-600 rounded-md focus:ring-1 focus:ring-gray-400 transition-colors"
    >
        Clear
    </button>

    @if($errors->any())
        <span class="text-xs text-red-600 dark:text-red-400 ml-auto">
            {{ $errors->first() }}
        </span>
    @else
        <span class="text-xs text-green-600 dark:text-green-400 ml-auto">
            {{ \Carbon\Carbon::parse($fromDate)->format('M j') }} - {{ \Carbon\Carbon::parse($toDate)->format('M j, Y') }}
        </span>
    @endif
</div>