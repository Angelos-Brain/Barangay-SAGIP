{{--
    BarangaySafe design tokens — the only place colors, type and shadows are defined.
    Mirrors design-system/barangaysafe/MASTER.md (including its Dashboard Adaptation).
    Included from every layout's <head>.

    Theme: colors are RGB channel variables so Tailwind opacity modifiers (bg-navy/50) keep working.
    Brand fills never change between themes; only text shades, surfaces and neutral/tint scales do.
--}}
<meta name="color-scheme" content="light dark">
<script>
    (function () {
        var theme = null;
        try { theme = localStorage.getItem('theme'); } catch (error) {}
        if (theme !== 'light' && theme !== 'dark') {
            theme = window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light';
        }
        document.documentElement.setAttribute('data-theme', theme);
    })();

    function toggleTheme() {
        var theme = document.documentElement.getAttribute('data-theme') === 'dark' ? 'light' : 'dark';
        document.documentElement.setAttribute('data-theme', theme);
        try { localStorage.setItem('theme', theme); } catch (error) {}
        document.querySelectorAll('[data-theme-toggle]').forEach(function (button) {
            button.setAttribute('aria-pressed', String(theme === 'dark'));
        });
    }

    document.addEventListener('DOMContentLoaded', function () {
        var isDark = document.documentElement.getAttribute('data-theme') === 'dark';
        document.querySelectorAll('[data-theme-toggle]').forEach(function (button) {
            button.setAttribute('aria-pressed', String(isDark));
        });
    });
</script>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Atkinson+Hyperlegible:wght@400;700&display=swap" rel="stylesheet">
<script src="https://cdn.tailwindcss.com"></script>
<script>
    (function () {
        var token = function (name) { return 'rgb(var(--c-' + name + ') / <alpha-value>)'; };
        var scale = function (name, shades) {
            return shades.reduce(function (result, shade) {
                result[shade] = token(name + '-' + shade);
                return result;
            }, {});
        };

        tailwind.config = {
            theme: {
                extend: {
                    fontFamily: {
                        sans: ['"Atkinson Hyperlegible"', 'ui-sans-serif', 'system-ui', 'sans-serif'],
                    },
                    colors: {
                        navy: token('navy'),
                        secondary: token('secondary'),
                        accent: token('accent'),
                        canvas: token('canvas'),
                        surface: token('surface'),
                        ink: token('ink'),
                        muted: token('muted'),
                        'muted-fg': token('muted-fg'),
                        line: token('line'),
                        danger: token('danger'),
                        alert: token('alert'),
                        warning: token('warning'),
                        success: token('success'),
                        gray: scale('gray', [50, 100, 200, 300, 400]),
                        red: scale('red', [50, 100, 200]),
                        yellow: scale('yellow', [50, 100, 200]),
                        green: scale('green', [100]),
                        blue: scale('blue', [50, 100, 200]),
                        indigo: scale('indigo', [50, 100, 200]),
                        purple: scale('purple', [50, 100, 200]),
                        orange: scale('orange', [100]),
                    },
                    textColor: {
                        navy: token('text-navy'),
                        accent: token('text-accent'),
                        danger: token('text-danger'),
                        alert: token('text-alert'),
                        warning: token('text-warning'),
                        success: token('text-success'),
                        gray: scale('gray', [500, 600, 700]),
                        red: scale('red', [600, 700, 800, 900]),
                        yellow: scale('yellow', [800]),
                        green: scale('green', [700, 800]),
                        blue: scale('blue', [800]),
                        indigo: scale('indigo', [600, 800]),
                        purple: scale('purple', [800, 900]),
                        orange: scale('orange', [800]),
                    },
                    placeholderColor: {
                        slate: scale('slate', [400]),
                    },
                    borderColor: {
                        navy: token('border-navy'),
                    },
                    boxShadow: {
                        card: '0 1px 2px rgb(var(--c-shadow) / 0.04), 0 8px 24px -12px rgb(var(--c-shadow) / 0.12)',
                        pop: '0 10px 15px rgb(var(--c-black) / 0.1)',
                    },
                },
            },
        };
    })();
</script>
<style>
    :root {
        color-scheme: light;

        /* Brand — identical in both themes */
        --color-navy: #0F172A;
        --color-accent: #0369A1;
        --color-danger: #DC2626;
        --color-alert: #C2410C;
        --color-warning: #B45309;
        --color-success: #15803D;
        --color-neutral: #64748B;
        --c-navy: 15 23 42;
        --c-secondary: 51 65 85;
        --c-accent: 3 105 161;
        --c-danger: 220 38 38;
        --c-alert: 194 65 12;
        --c-warning: 180 83 9;
        --c-success: 21 128 61;
        --c-white: 255 255 255;
        --c-black: 0 0 0;

        /* Surfaces and text */
        --c-canvas: 248 250 252;
        --c-surface: 255 255 255;
        --c-ink: 2 6 23;
        --c-muted: 232 236 241;
        --c-muted-fg: 71 85 105;
        --c-line: 226 232 240;
        --c-shadow: 15 23 42;
        --c-text-navy: 15 23 42;
        --c-border-navy: 15 23 42;
        --c-text-accent: 3 105 161;
        --c-text-danger: 220 38 38;
        --c-text-alert: 194 65 12;
        --c-text-warning: 180 83 9;
        --c-text-success: 21 128 61;

        /* Neutral and tint scales (Tailwind defaults) */
        --c-gray-50: 249 250 251;
        --c-gray-100: 243 244 246;
        --c-gray-200: 229 231 235;
        --c-gray-300: 209 213 219;
        --c-gray-400: 107 114 128; /* gray-500: gray-400 text fails AA on white */
        --c-slate-400: 100 116 139; /* slate-500, placeholders only */
        --c-gray-500: 107 114 128;
        --c-gray-600: 75 85 99;
        --c-gray-700: 55 65 81;
        --c-red-50: 254 242 242;
        --c-red-100: 254 226 226;
        --c-red-200: 254 202 202;
        --c-red-600: 220 38 38;
        --c-red-700: 185 28 28;
        --c-red-800: 153 27 27;
        --c-red-900: 127 29 29;
        --c-yellow-50: 254 252 232;
        --c-yellow-100: 254 249 195;
        --c-yellow-200: 254 240 138;
        --c-yellow-800: 133 77 14;
        --c-green-100: 220 252 231;
        --c-green-700: 21 128 61;
        --c-green-800: 22 101 52;
        --c-blue-50: 239 246 255;
        --c-blue-100: 219 234 254;
        --c-blue-200: 191 219 254;
        --c-blue-800: 30 64 175;
        --c-indigo-50: 238 242 255;
        --c-indigo-100: 224 231 255;
        --c-indigo-200: 199 210 254;
        --c-indigo-600: 79 70 229;
        --c-indigo-800: 55 48 163;
        --c-purple-50: 250 245 255;
        --c-purple-100: 243 232 255;
        --c-purple-200: 233 213 255;
        --c-purple-800: 107 33 168;
        --c-purple-900: 88 28 135;
        --c-orange-100: 255 237 213;
        --c-orange-800: 154 52 18;
    }
    :root[data-theme="dark"] {
        color-scheme: dark;

        --c-canvas: 2 6 23;
        --c-surface: 30 41 59;
        --c-ink: 241 245 249;
        --c-muted: 51 65 85;
        --c-muted-fg: 168 180 198;
        --c-line: 51 65 85;
        --c-shadow: 0 0 0;
        --c-text-navy: 241 245 249;
        --c-border-navy: 148 163 184;
        --c-text-accent: 56 189 248;
        --c-text-danger: 248 113 113;
        --c-text-alert: 251 146 60;
        --c-text-warning: 251 191 36;
        --c-text-success: 74 222 128;

        --c-gray-50: 39 52 73;
        --c-gray-100: 51 65 85;
        --c-gray-200: 63 76 99;
        --c-gray-300: 82 96 122;
        --c-gray-400: 156 163 175;
        --c-slate-400: 148 163 184;
        --c-gray-500: 156 163 175;
        --c-gray-600: 209 213 219;
        --c-gray-700: 229 231 235;
        --c-red-50: 69 10 10;
        --c-red-100: 127 29 29;
        --c-red-200: 153 27 27;
        --c-red-600: 248 113 113;
        --c-red-700: 252 165 165;
        --c-red-800: 254 202 202;
        --c-red-900: 254 226 226;
        --c-yellow-50: 66 32 6;
        --c-yellow-100: 113 63 18;
        --c-yellow-200: 133 77 14;
        --c-yellow-800: 254 240 138;
        --c-green-100: 20 83 45;
        --c-green-700: 134 239 172;
        --c-green-800: 187 247 208;
        --c-blue-50: 23 37 84;
        --c-blue-100: 30 58 138;
        --c-blue-200: 30 64 175;
        --c-blue-800: 191 219 254;
        --c-indigo-50: 30 27 75;
        --c-indigo-100: 49 46 129;
        --c-indigo-200: 55 48 163;
        --c-indigo-600: 129 140 248;
        --c-indigo-800: 199 210 254;
        --c-purple-50: 59 7 100;
        --c-purple-100: 88 28 135;
        --c-purple-200: 107 33 168;
        --c-purple-800: 233 213 255;
        --c-purple-900: 243 232 255;
        --c-orange-100: 124 45 18;
        --c-orange-800: 254 215 170;
    }
    [x-cloak] { display: none !important; }
    body { font-family: 'Atkinson Hyperlegible', ui-sans-serif, system-ui, sans-serif; }
    :focus-visible { outline: 3px solid var(--color-accent); outline-offset: 2px; }
    :root[data-theme="dark"] :focus-visible { outline-color: rgb(var(--c-text-accent)); }
    /* Navy fills stay brand navy in dark mode; a faint edge keeps them distinct from dark cards. */
    :root[data-theme="dark"] :is(a, button).bg-navy { box-shadow: inset 0 0 0 1px rgb(var(--c-white) / 0.25); }
    :root:not([data-theme="dark"]) .theme-icon-sun,
    :root[data-theme="dark"] .theme-icon-moon { display: none; }
    @media (prefers-reduced-motion: reduce) {
        *, *::before, *::after { transition-duration: 0.01ms !important; animation-duration: 0.01ms !important; }
    }
</style>
