<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>{{ $title ?? 'Pivot Table Builder' }}</title>
        
        <!-- Fonts -->
        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=figtree:400,500,600&display=swap" rel="stylesheet" />
        
        <!-- Styles -->
        @if (file_exists(public_path('build/manifest.json')) || file_exists(public_path('hot')))
            @vite(['resources/css/app.css', 'resources/js/app.js'])
        @else
            <link href="https://cdn.jsdelivr.net/npm/tailwindcss@3.4.1/dist/tailwind.min.css" rel="stylesheet">
        @endif
        
        @livewireStyles
    </head>
    <body class="font-sans antialiased bg-gray-50">
        <div class="min-h-screen">
            <!-- Navigation -->
            <nav class="bg-white shadow-sm border-b border-gray-200">
                <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                    <div class="flex justify-between h-16">
                        <div class="flex items-center">
                            <h1 class="text-xl font-semibold text-gray-900">ProdStream</h1>
                        </div>
                        <div class="flex items-center space-x-4">
                            @auth
                                <!-- Pivot Table Tools -->
                                <div class="relative group">
                                    <button class="text-gray-700 hover:text-blue-600 flex items-center space-x-1">
                                        <span>Pivot Tools</span>
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path>
                                        </svg>
                                    </button>
                                    <div class="absolute right-0 mt-2 w-56 bg-white rounded-md shadow-lg ring-1 ring-black ring-opacity-5 opacity-0 invisible group-hover:opacity-100 group-hover:visible transition-all duration-200 z-50">
                                        <div class="py-1">
                                            <a href="{{ route('auto.pivot.builder') }}" class="block px-4 py-2 text-sm text-gray-700 hover:bg-blue-50 hover:text-blue-600">
                                                <div class="flex items-center space-x-2">
                                                    <svg class="w-4 h-4 text-green-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"></path>
                                                    </svg>
                                                    <div>
                                                        <div class="font-medium">Auto Pivot Builder</div>
                                                        <div class="text-xs text-gray-500">No CSV upload required</div>
                                                    </div>
                                                </div>
                                            </a>
                                            <a href="{{ route('pivot.table.builder') }}" class="block px-4 py-2 text-sm text-gray-700 hover:bg-blue-50 hover:text-blue-600">
                                                <div class="flex items-center space-x-2">
                                                    <svg class="w-4 h-4 text-blue-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                                                    </svg>
                                                    <div>
                                                        <div class="font-medium">CSV Pivot Builder</div>
                                                        <div class="text-xs text-gray-500">Upload & analyze CSV files</div>
                                                    </div>
                                                </div>
                                            </a>
                                        </div>
                                    </div>
                                </div>
                                
                                <span class="text-gray-700">{{ Auth::user()->name ?? 'User' }}</span>
                                @if(Auth::user() && Auth::user()->factory)
                                    <a href="{{ route('filament.admin.pages.dashboard', ['tenant' => Auth::user()->factory->id]) }}" class="text-blue-600 hover:text-blue-800">
                                        Admin Panel
                                    </a>
                                @else
                                    <a href="/admin" class="text-blue-600 hover:text-blue-800">
                                        Admin Panel
                                    </a>
                                @endif
                            @else
                                <a href="{{ route('login') }}" class="text-blue-600 hover:text-blue-800">Login</a>
                            @endauth
                        </div>
                    </div>
                </div>
            </nav>

            <!-- Main Content -->
            <main class="py-6">
                {{ $slot }}
            </main>
        </div>

        @livewireScripts
    </body>
</html>
