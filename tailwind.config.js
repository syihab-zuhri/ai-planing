import defaultTheme from 'tailwindcss/defaultTheme';

/** @type {import('tailwindcss').Config} */
export default {
    content: [
        './vendor/laravel/framework/src/Illuminate/Pagination/resources/views/*.blade.php',
        './storage/framework/views/*.php',
        './resources/**/*.blade.php',
        './resources/**/*.js',
        './resources/**/*.vue',
    ],
    theme: {
        // Default palette (slate/gray/red/...) TETAP ADA agar view dari
        // backend agent (bg-slate-900, text-gray-500, dll) tetap compile.
        // Cukup extend dengan token DSD.
        extend: {
            colors: {
                // Brand palette — DSD.md §2
                primary: {
                    DEFAULT: '#0C2D5C',
                    hover:   '#185FA5',
                },
                accent: {
                    DEFAULT: '#BA7517',
                },
                success: '#16A34A',
                warning: '#D97706',
                danger:  '#DC2626',

                // Semantic — DSD.md
                bg: {
                    DEFAULT: '#FFFFFF',
                    alt:     '#F8FAFC',
                },
                text: {
                    DEFAULT: '#0F172A',
                    muted:   '#64748B',
                },
                border: '#E2E8F0',
            },
            fontFamily: {
                sans: ['Inter', 'system-ui', ...defaultTheme.fontFamily.sans],
                mono: ['ui-monospace', 'SFMono-Regular', 'Menlo', 'monospace'],
            },
            borderRadius: {
                // DSD.md §2 Radius
                sm: '4px',
                DEFAULT: '6px',
                md: '6px',
                lg: '8px',
                xl: '8px',
            },
            maxWidth: {
                wizard: '720px',
            },
            boxShadow: {
                sm: '0 1px 2px rgba(0,0,0,0.05)',
                md: '0 4px 6px rgba(0,0,0,0.07)',
                lg: '0 10px 15px rgba(0,0,0,0.1)',
            },
        },
    },
    plugins: [],
};