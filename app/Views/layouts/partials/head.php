<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="csrf-token" content="<?= csrf_hash() ?>">
<meta name="csrf-header" content="<?= csrf_header() ?>">
<title><?= esc($title ?? 'CRM') ?> · <?= esc(app_setting('company_name', 'CRM')) ?></title>

<script src="https://cdn.tailwindcss.com/3.4.17?plugins=forms"></script>
<script <?= csp_script_nonce() ?>>
    tailwind.config = {
        theme: {
            extend: {
                fontFamily: {
                    sans: ['Inter', 'ui-sans-serif', 'system-ui', '-apple-system', 'Segoe UI', 'Roboto', 'sans-serif'],
                },
                colors: {
                    brand: {
                        50: '#eef6ff',
                        100: '#d9eaff',
                        200: '#bcdaff',
                        300: '#8ec3ff',
                        400: '#59a1fc',
                        500: '#337ef8',
                        600: '#1d5fed',
                        700: '#1549da',
                        800: '#183cb0',
                        900: '#19378a',
                        950: '#142354',
                    },
                },
            },
        },
    };
</script>
<style>
    [x-cloak] { display: none !important; }
</style>
<script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.17.5/dist/cdn.min.js"></script>
