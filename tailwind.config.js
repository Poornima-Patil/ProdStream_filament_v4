import defaultTheme from 'tailwindcss/defaultTheme'
import preset from './vendor/filament/support/tailwind.config.preset'

export default {
    presets: [preset],
    darkMode: 'class', // Enable class-based dark mode
    content: [
        './resources/**/*.blade.php',
        './resources/**/*.js',
        './resources/**/*.vue',
        './app/Filament/**/*.php',
        './vendor/filament/**/*.blade.php',
        './vendor/filament/**/*.js',
        './vendor/filament/**/*.php',
        './app/**/*.php',
        './resources/css/**/*.css',
    ],
    theme: {
        extend: {
            colors: {
                primary: {
                    50: '#e6f0f9',
                    100: '#cce1f3',
                    200: '#99c3e7',
                    300: '#66a5db',
                    400: '#3387cf',
                    500: '#106EBE',  // Your primary color
                    600: '#0d5898',
                    700: '#0a4272',
                    800: '#072c4c',
                    900: '#041626',
                },
                wo: {
                    assigned: '#3B82F6',
                    start: '#f5c50b',
                    hold: '#F97316',
                    completed: '#10B981',
                    closed: '#6B7280',
                }
            },
            fontFamily: {
                sans: ['Inter var', 'Inter', ...defaultTheme.fontFamily.sans],
            },
        },
    },
    plugins: [],
}
