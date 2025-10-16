<x-filament-widgets::widget>
    <x-filament::section>
        <div class="flex items-center justify-between p-4 bg-gradient-to-r from-primary-50 to-primary-100 dark:from-primary-900/20 dark:to-primary-800/20 rounded-lg border-2 border-primary-200 dark:border-primary-700">
            <div class="flex items-center space-x-4">
                <div class="flex items-center justify-center w-12 h-12 bg-primary-600 rounded-lg">
                    <x-heroicon-o-presentation-chart-line class="w-6 h-6 text-white" />
                </div>
                <div>
                    <h3 class="text-lg font-semibold text-gray-900 dark:text-white">
                        KPI Analytics Dashboard
                    </h3>
                    <p class="text-sm text-gray-600 dark:text-gray-400">
                        View real-time machine status and historical analytics with advanced filtering
                    </p>
                </div>
            </div>
            <a href="{{ \App\Filament\Admin\Pages\KPIAnalyticsDashboard::getUrl() }}"
               class="inline-flex items-center px-4 py-2 bg-primary-600 hover:bg-primary-700 text-white font-medium rounded-lg transition-colors">
                Open Dashboard
                <x-heroicon-m-arrow-right class="w-4 h-4 ml-2" />
            </a>
        </div>
    </x-filament::section>
</x-filament-widgets::widget>
