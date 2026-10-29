<script <?= csp_script_nonce() ?>>
    window.crm = {
        csrfHeader: () => document.querySelector('meta[name="csrf-header"]').content,
        csrfToken: () => document.querySelector('meta[name="csrf-token"]').content,

        /**
         * POST form-urlencoded via fetch dengan header CSRF. Token baru dari respons JSON
         * disimpan kembali ke meta tag karena token diganti setiap request POST.
         */
        async post(url, data = {}) {
            const response = await fetch(url, {
                method: 'POST',
                headers: {
                    'Accept': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                    'Content-Type': 'application/x-www-form-urlencoded;charset=UTF-8',
                    [this.csrfHeader()]: this.csrfToken(),
                },
                body: new URLSearchParams(data),
            });
            let json = {};
            try {
                json = await response.json();
            } catch (e) {
                json = { message: response.status === 403 ? 'Sesi keamanan kedaluwarsa. Muat ulang halaman lalu coba lagi.' : 'Terjadi kesalahan pada server.' };
            }
            if (json.csrf) {
                document.querySelector('meta[name="csrf-token"]').content = json.csrf;
            }
            return { ok: response.ok, status: response.status, data: json };
        },

        toast(message, type = 'success') {
            window.dispatchEvent(new CustomEvent('toast', { detail: { message, type } }));
        },

        rupiah(value) {
            return 'Rp ' + Math.round(Number(value) || 0).toLocaleString('id-ID');
        },
    };

    document.addEventListener('alpine:init', () => {
        // Dropdown lonceng notifikasi (lihat layouts/partials/topbar).
        Alpine.data('notificationBell', (config) => ({
            open: false,
            loading: false,
            unread: config.unread || 0,
            items: [],
            async toggle() {
                this.open = !this.open;
                if (!this.open) return;
                this.loading = true;
                try {
                    const response = await fetch(config.listUrl, { headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' } });
                    const json = await response.json();
                    this.items = json.items || [];
                    this.unread = json.unread || 0;
                } catch (e) {
                    this.items = [];
                }
                this.loading = false;
            },
            async readAll() {
                const result = await window.crm.post(config.readAllUrl);
                if (result.ok) {
                    this.unread = 0;
                    this.items = this.items.map((item) => ({ ...item, read: true }));
                }
            },
        }));

        // Combobox pemilihan perusahaan (lihat components/company-select).
        Alpine.data('companySelect', (config) => ({
            url: config.url,
            selectedId: config.selectedId || '',
            selectedLabel: config.selectedLabel || '',
            query: config.selectedLabel || '',
            results: [],
            active: 0,
            open: false,
            loading: false,
            async search() {
                this.loading = true;
                try {
                    const response = await fetch(this.url + '?q=' + encodeURIComponent(this.query === this.selectedLabel ? '' : this.query), {
                        headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                    });
                    this.results = response.ok ? await response.json() : [];
                } catch (e) {
                    this.results = [];
                }
                this.active = 0;
                this.loading = false;
                this.open = true;
            },
            move(step) {
                if (this.results.length === 0) return;
                this.active = (this.active + step + this.results.length) % this.results.length;
            },
            pick(item) {
                if (!item) return;
                this.selectedId = String(item.id);
                this.selectedLabel = item.name;
                this.query = item.name;
                this.open = false;
                this.$dispatch('company-selected', { id: this.selectedId, name: item.name });
            },
            clear() {
                this.selectedId = '';
                this.selectedLabel = '';
                this.query = '';
                this.results = [];
                this.$dispatch('company-selected', { id: '', name: '' });
            },
            close() {
                this.open = false;
                this.query = this.selectedLabel;
            },
        }));
    });
</script>
