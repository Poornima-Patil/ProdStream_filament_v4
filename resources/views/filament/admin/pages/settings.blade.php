<x-filament-panels::page>
    <div class="space-y-6">
        <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-6">
            <div class="mb-4">
                <h2 class="text-lg font-semibold text-gray-900 dark:text-white">
                    KPI Settings
                </h2>
                <p class="text-sm text-gray-600 dark:text-gray-400">
                    Configure how KPI calculations are performed across the system.
                </p>
            </div>

            <form wire:submit="save">
                <div class="space-y-4">
                    <div>
                        <label class="text-base font-medium text-gray-900 dark:text-white">KPI Date Filter Basis</label>
                        <p class="text-sm text-gray-600 dark:text-gray-400">Global settings that affect all KPI calculations</p>
                    </div>

                    <div class="space-y-3">
                        <label class="flex items-center">
                            <input type="radio" wire:model="kpiDateType" value="created_at"
                                   class="form-radio text-primary-600 focus:ring-primary-500">
                            <div class="ml-3">
                                <div class="text-sm font-medium text-gray-900 dark:text-white">Work Order Created Date</div>
                                <div class="text-xs text-gray-500 dark:text-gray-400">Filter KPIs based on when work orders were created</div>
                            </div>
                        </label>

                        <label class="flex items-center">
                            <input type="radio" wire:model="kpiDateType" value="start_time"
                                   class="form-radio text-primary-600 focus:ring-primary-500">
                            <div class="ml-3">
                                <div class="text-sm font-medium text-gray-900 dark:text-white">Production Started Date</div>
                                <div class="text-xs text-gray-500 dark:text-gray-400">Filter KPIs based on when production actually started (first 'Start' log entry)</div>
                            </div>
                        </label>
                    </div>
                </div>

                <div class="mt-6 flex justify-between items-center">
                    <div class="text-xs text-gray-500">
                        Current setting:
                        <span class="font-medium">
                            {{ $kpiDateType === 'start_time' ? 'Production Started Date' : 'Work Order Created Date' }}
                        </span>
                    </div>

                    <div class="space-x-2">
                        <button type="submit"
                                class="inline-flex items-center px-4 py-2 bg-primary-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-primary-700 focus:bg-primary-700 active:bg-primary-900 focus:outline-none focus:ring-2 focus:ring-primary-500 focus:ring-offset-2 transition ease-in-out duration-150">
                            <x-heroicon-o-check class="w-4 h-4 mr-1" />
                            Save Settings
                        </button>
                    </div>
                </div>
            </form>
        </div>

        <div class="bg-blue-50 dark:bg-blue-900/20 border border-blue-200 dark:border-blue-800 rounded-lg p-4">
            <div class="flex">
                <div class="flex-shrink-0">
                    <x-heroicon-o-information-circle class="h-5 w-5 text-blue-400" />
                </div>
                <div class="ml-3">
                    <h3 class="text-sm font-medium text-blue-800 dark:text-blue-200">
                        Impact of Date Setting
                    </h3>
                    <div class="mt-2 text-sm text-blue-700 dark:text-blue-300">
                        <p>This setting affects all KPI calculations including Work Order Completion Rate, Production Throughput, and Quality Metrics across dashboards, reports, and analytics.</p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</x-filament-panels::page>