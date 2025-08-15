<x-filament::page>
    <div class="space-y-6">
        <div class="bg-gradient-to-r from-blue-500 to-purple-600 rounded-lg p-6 text-white">
            <h2 class="text-xl font-bold mb-2">Export Dashboard</h2>
            <p class="text-blue-100">Download work order data as Excel/CSV or create interactive pivot tables</p>
        </div>

        {{ $this->form }}
        
        <div class="grid grid-cols-1 md:grid-cols-4 gap-4 mt-6">
            <!-- Excel Export Card -->
            <div class="bg-white rounded-lg shadow-sm border-2 border-green-200 p-6 hover:shadow-md transition-shadow">
                <div class="flex items-center mb-4">
                    <div class="bg-green-100 p-3 rounded-full">
                        <svg class="w-6 h-6 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                        </svg>
                    </div>
                    <h3 class="ml-3 text-lg font-semibold text-gray-900">Excel Export</h3>
                </div>
                <p class="text-gray-600 text-sm mb-4">Download formatted Excel file with all work order data including calculations and charts.</p>
                <ul class="text-xs text-gray-500 space-y-1">
                    <li>• Pre-formatted templates</li>
                    <li>• Built-in formulas</li>
                    <li>• Professional layout</li>
                </ul>
            </div>

            <!-- CSV Export Card -->
            <div class="bg-white rounded-lg shadow-sm border-2 border-blue-200 p-6 hover:shadow-md transition-shadow">
                <div class="flex items-center mb-4">
                    <div class="bg-blue-100 p-3 rounded-full">
                        <svg class="w-6 h-6 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"></path>
                        </svg>
                    </div>
                    <h3 class="ml-3 text-lg font-semibold text-gray-900">CSV Export</h3>
                </div>
                <p class="text-gray-600 text-sm mb-4">Download raw data in CSV format, perfect for further analysis or importing into other tools.</p>
                <ul class="text-xs text-gray-500 space-y-1">
                    <li>• Raw data format</li>
                    <li>• Import to any tool</li>
                    <li>• Lightweight file</li>
                </ul>
            </div>

            <!-- Auto Pivot Table Card (Featured) -->
            <div class="bg-white rounded-lg shadow-sm border-2 border-purple-200 p-6 hover:shadow-md transition-shadow relative overflow-hidden">
                <div class="absolute top-0 right-0 bg-purple-500 text-white text-xs px-2 py-1 rounded-bl-md font-semibold">
                    NEW
                </div>
                <div class="flex items-center mb-4">
                    <div class="bg-purple-100 p-3 rounded-full">
                        <svg class="w-6 h-6 text-purple-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"></path>
                        </svg>
                    </div>
                    <h3 class="ml-3 text-lg font-semibold text-gray-900">Auto Pivot</h3>
                </div>
                <p class="text-gray-600 text-sm mb-4">Generate pivot tables instantly from your work order data. No CSV upload required!</p>
                <ul class="text-xs text-gray-500 space-y-1">
                    <li>• Direct database access</li>
                    <li>• Real-time data</li>
                    <li>• No file uploads</li>
                </ul>
                <a href="/auto-pivot" target="_blank" class="mt-4 inline-flex items-center px-3 py-2 bg-purple-600 text-white text-sm font-medium rounded-md hover:bg-purple-700 transition-colors">
                    <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"></path>
                    </svg>
                    Open Auto Pivot
                </a>
            </div>

            <!-- Manual Pivot Table Card -->
            <div class="bg-white rounded-lg shadow-sm border-2 border-orange-200 p-6 hover:shadow-md transition-shadow">
                <div class="flex items-center mb-4">
                    <div class="bg-orange-100 p-3 rounded-full">
                        <svg class="w-6 h-6 text-orange-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 7v10c0 2.21 3.79 4 8.5 4s8.5-1.79 8.5-4V7M4 7c0 2.21 3.79 4 8.5 4s8.5-1.79 8.5-4M4 7c0-2.21 3.79-4 8.5-4s8.5 1.79 8.5 4"></path>
                        </svg>
                    </div>
                    <h3 class="ml-3 text-lg font-semibold text-gray-900">CSV Pivot</h3>
                </div>
                <p class="text-gray-600 text-sm mb-4">Upload your own CSV files to create custom pivot tables with advanced filtering options.</p>
                <ul class="text-xs text-gray-500 space-y-1">
                    <li>• Custom CSV upload</li>
                    <li>• Advanced filtering</li>
                    <li>• Flexible analysis</li>
                </ul>
            </div>
        </div>

        <!-- Quick Stats -->
        @if(Auth::user()->factory)
            <div class="bg-white rounded-lg shadow-sm border p-6">
                <h3 class="text-lg font-semibold text-gray-900 mb-4">Export Information</h3>
                <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
                    <div class="text-center">
                        <div class="text-2xl font-bold text-blue-600">{{ Auth::user()->factory->name }}</div>
                        <div class="text-sm text-gray-600">Current Factory</div>
                    </div>
                    <div class="text-center">
                        <div class="text-2xl font-bold text-green-600">{{ now()->format('M Y') }}</div>
                        <div class="text-sm text-gray-600">Current Period</div>
                    </div>
                    <div class="text-center">
                        <div class="text-2xl font-bold text-purple-600">Auto + Manual</div>
                        <div class="text-sm text-gray-600">Pivot Options</div>
                    </div>
                    <div class="text-center">
                        <div class="text-2xl font-bold text-orange-600">Real-time</div>
                        <div class="text-sm text-gray-600">Data Updates</div>
                    </div>
                </div>
            </div>
        @endif
    </div>

    <style>
        .fi-fo-field-wrp {
            margin-bottom: 1.5rem;
        }
        
        .fi-btn-group {
            display: flex;
            flex-wrap: wrap;
            gap: 0.75rem;
            margin-top: 1rem;
        }
        
        .fi-btn {
            transition: all 0.2s ease-in-out;
        }
        
        .fi-btn:hover {
            transform: translateY(-1px);
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
        }

        .bg-gradient-to-r {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        }
    </style>
</x-filament::page>
