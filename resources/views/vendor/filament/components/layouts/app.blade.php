{{-- resources/views/vendor/filament/components/layouts/app.blade.php --}}

<x-filament::layouts.app>
    {{ $slot }}

    {{-- Confirm this override is working --}}
    <script>
        console.log('✅ Filament layout override loaded!');
    </script>

    {{-- Include ApexCharts --}}
    <script src="https://cdn.jsdelivr.net/npm/apexcharts"></script>
</x-filament::layouts.app>
