<div>
    <div class="max-w-7xl mx-auto p-6 space-y-6">
        <!-- Header -->
        <div class="bg-white rounded-lg shadow-sm border p-6">
            <h1 class="text-2xl font-bold text-gray-900 mb-2">Pivot Table Builder</h1>
            <p class="text-gray-600">Upload a CSV file and create interactive pivot tables with custom filters and aggregations.</p>
        </div>

        <!-- Flash Messages -->
        @if (session()->has('success'))
            <div class="bg-green-50 border border-green-200 text-green-700 px-4 py-3 rounded-lg">
                {{ session('success') }}
            </div>
        @endif

        @if (session()->has('error'))
            <div class="bg-red-50 border border-red-200 text-red-700 px-4 py-3 rounded-lg">
                {{ session('error') }}
            </div>
        @endif

        <!-- File Upload Section -->
        <div class="bg-white rounded-lg shadow-sm border">
            <div class="p-6 border-b border-gray-200">
                <h2 class="text-lg font-semibold text-gray-900">1. Upload CSV File</h2>
                <p class="text-sm text-gray-600 mt-1">Select a CSV file to begin creating your pivot table</p>
            </div>
            <div class="p-6">
                <div class="flex items-center justify-center w-full">
                    <label for="csvFile" class="flex flex-col items-center justify-center w-full h-32 border-2 border-gray-300 border-dashed rounded-lg cursor-pointer bg-gray-50 hover:bg-gray-100 transition-colors">
                        <div class="flex flex-col items-center justify-center pt-5 pb-6">
                            <svg class="w-8 h-8 mb-4 text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12"></path>
                            </svg>
                            <p class="mb-2 text-sm text-gray-500">
                                <span class="font-semibold">Click to upload</span> or drag and drop
                            </p>
                            <p class="text-xs text-gray-500">CSV files only (MAX. 10MB)</p>
                        </div>
                        <input id="csvFile" type="file" wire:model="csvFile" class="hidden" accept=".csv,text/csv">
                    </label>
                </div>
                @error('csvFile') 
                    <p class="mt-2 text-sm text-red-600">{{ $message }}</p> 
                @enderror

                @if ($csvFile)
                    <div class="mt-4 p-3 bg-blue-50 rounded-lg">
                        <p class="text-sm text-blue-700">
                            <strong>File:</strong> {{ $csvFile->getClientOriginalName() }} 
                            ({{ round($csvFile->getSize() / 1024, 2) }} KB)
                        </p>
                    </div>
                @endif
            </div>
        </div>

        <!-- Test Section -->
        <div class="bg-white rounded-lg shadow-sm border p-6">
            <h3 class="text-lg font-semibold text-gray-900 mb-4">Component Status</h3>
            <div class="space-y-2">
                <p><strong>CSV Headers Count:</strong> {{ count($csvHeaders) }}</p>
                <p><strong>CSV Data Count:</strong> {{ count($csvData) }}</p>
                <p><strong>Show Configuration:</strong> {{ $showConfiguration ? 'Yes' : 'No' }}</p>
                <p><strong>Show Results:</strong> {{ $showResults ? 'Yes' : 'No' }}</p>
            </div>
        </div>
    </div>
</div>
