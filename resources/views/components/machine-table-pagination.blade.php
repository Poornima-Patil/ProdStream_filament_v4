@props(['pagination', 'status', 'wireMethod' => 'gotoPage'])

@if($pagination['total_pages'] > 1)
    <div class="flex items-center justify-between px-4 py-3 bg-gray-50 dark:bg-gray-800 border-t border-gray-200 dark:border-gray-700">
        <div class="flex-1 flex justify-between sm:hidden">
            @if($pagination['current_page'] > 1)
                <button
                    wire:click="{{ $wireMethod }}('{{ $status }}', {{ $pagination['current_page'] - 1 }})"
                    class="relative inline-flex items-center px-4 py-2 text-sm font-medium rounded-md text-gray-700 dark:text-gray-300 bg-white dark:bg-gray-700 border border-gray-300 dark:border-gray-600 hover:bg-gray-50 dark:hover:bg-gray-600">
                    Previous
                </button>
            @endif
            @if($pagination['current_page'] < $pagination['total_pages'])
                <button
                    wire:click="{{ $wireMethod }}('{{ $status }}', {{ $pagination['current_page'] + 1 }})"
                    class="ml-3 relative inline-flex items-center px-4 py-2 text-sm font-medium rounded-md text-gray-700 dark:text-gray-300 bg-white dark:bg-gray-700 border border-gray-300 dark:border-gray-600 hover:bg-gray-50 dark:hover:bg-gray-600">
                    Next
                </button>
            @endif
        </div>
        <div class="hidden sm:flex-1 sm:flex sm:items-center sm:justify-between">
            <div>
                <p class="text-sm text-gray-700 dark:text-gray-300">
                    Showing
                    <span class="font-medium">{{ $pagination['from'] }}</span>
                    to
                    <span class="font-medium">{{ $pagination['to'] }}</span>
                    of
                    <span class="font-medium">{{ $pagination['total'] }}</span>
                    results
                </p>
            </div>
            <div>
                <nav class="relative z-0 inline-flex rounded-md shadow-sm -space-x-px" aria-label="Pagination">
                    {{-- Previous Button --}}
                    @if($pagination['current_page'] > 1)
                        <button
                            wire:click="{{ $wireMethod }}('{{ $status }}', {{ $pagination['current_page'] - 1 }})"
                            class="relative inline-flex items-center px-2 py-2 rounded-l-md border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-sm font-medium text-gray-500 dark:text-gray-400 hover:bg-gray-50 dark:hover:bg-gray-600">
                            <x-heroicon-o-chevron-left class="h-5 w-5" />
                        </button>
                    @else
                        <span class="relative inline-flex items-center px-2 py-2 rounded-l-md border border-gray-300 dark:border-gray-600 bg-gray-100 dark:bg-gray-800 text-sm font-medium text-gray-300 dark:text-gray-600 cursor-not-allowed">
                            <x-heroicon-o-chevron-left class="h-5 w-5" />
                        </span>
                    @endif

                    {{-- Page Numbers --}}
                    @php
                        $start = max(1, $pagination['current_page'] - 2);
                        $end = min($pagination['total_pages'], $pagination['current_page'] + 2);
                    @endphp

                    @if($start > 1)
                        <button
                            wire:click="{{ $wireMethod }}('{{ $status }}', 1)"
                            class="relative inline-flex items-center px-4 py-2 border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-sm font-medium text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-600">
                            1
                        </button>
                        @if($start > 2)
                            <span class="relative inline-flex items-center px-4 py-2 border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-sm font-medium text-gray-700 dark:text-gray-300">
                                ...
                            </span>
                        @endif
                    @endif

                    @for($page = $start; $page <= $end; $page++)
                        @if($page === $pagination['current_page'])
                            <span class="relative inline-flex items-center px-4 py-2 border border-primary-500 bg-primary-50 dark:bg-primary-900/50 text-sm font-medium text-primary-600 dark:text-primary-400 z-10">
                                {{ $page }}
                            </span>
                        @else
                            <button
                                wire:click="{{ $wireMethod }}('{{ $status }}', {{ $page }})"
                                class="relative inline-flex items-center px-4 py-2 border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-sm font-medium text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-600">
                                {{ $page }}
                            </button>
                        @endif
                    @endfor

                    @if($end < $pagination['total_pages'])
                        @if($end < $pagination['total_pages'] - 1)
                            <span class="relative inline-flex items-center px-4 py-2 border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-sm font-medium text-gray-700 dark:text-gray-300">
                                ...
                            </span>
                        @endif
                        <button
                            wire:click="{{ $wireMethod }}('{{ $status }}', {{ $pagination['total_pages'] }})"
                            class="relative inline-flex items-center px-4 py-2 border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-sm font-medium text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-600">
                            {{ $pagination['total_pages'] }}
                        </button>
                    @endif

                    {{-- Next Button --}}
                    @if($pagination['current_page'] < $pagination['total_pages'])
                        <button
                            wire:click="{{ $wireMethod }}('{{ $status }}', {{ $pagination['current_page'] + 1 }})"
                            class="relative inline-flex items-center px-2 py-2 rounded-r-md border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-sm font-medium text-gray-500 dark:text-gray-400 hover:bg-gray-50 dark:hover:bg-gray-600">
                            <x-heroicon-o-chevron-right class="h-5 w-5" />
                        </button>
                    @else
                        <span class="relative inline-flex items-center px-2 py-2 rounded-r-md border border-gray-300 dark:border-gray-600 bg-gray-100 dark:bg-gray-800 text-sm font-medium text-gray-300 dark:text-gray-600 cursor-not-allowed">
                            <x-heroicon-o-chevron-right class="h-5 w-5" />
                        </span>
                    @endif
                </nav>
            </div>
        </div>
    </div>
@endif
