{{-- filepath: /Users/poornimapatil/Herd/ProdStream_v1.1/resources/views/filament/admin/pages/work-order-widgets.blade.php --}}
<x-filament::page>
    <div class="p-6 bg-white rounded-lg shadow">
        <h1 class="text-2xl font-bold text-gray-800">Work Order Widgets</h1>
        <p class="text-gray-600">Explore various widgets and graphs related to Work Orders.</p>

        <div class="mt-6 grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-6">
            {{-- Link to Advanced Gantt Chart Widget --}}
            <a href="{{ route('filament.admin.widgets.advanced-work-order-gantt', ['tenant' => auth()->user()->factory_id]) }}" 
               class="block p-4 bg-blue-100 rounded-lg shadow hover:bg-blue-200">
                <h2 class="text-lg font-semibold text-blue-800">Advanced Gantt Chart</h2>
                <p class="text-sm text-blue-600">View the advanced Gantt chart for Work Orders.</p>
            </a>
        </div>
    </div>
</x-filament::page>