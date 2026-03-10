function crmApp() {
    return {
        leads: [],
        meta: { total: 0, page: 1, pages: 1, limit: 50 },
        showModal: false,
        showDetailPanel: false,
        formError: '',
        detailLead: {},
        currentLead: { id: null, name: '', email: '', phone: '', practice_area: '', status: 'New', score: 0, source: '', city: '', state: '', notes: '' },
        filters: { search: '', status: '', practice_area: '', source: '', state: '' },

        get avgScore() {
            if (this.leads.length === 0) return 0;
            const total = this.leads.reduce((sum, l) => sum + parseInt(l.score || 0), 0);
            return Math.round(total / this.leads.length);
        },

        get uniqueSources() {
            return new Set(this.leads.map(l => l.source || 'direct')).size;
        },

        getCsrf() {
            return window.CSRF_TOKEN || localStorage.getItem('csrf_token') || '';
        },

        async fetchLeads() {
            const params = new URLSearchParams({
                page: this.meta.page,
                limit: this.meta.limit,
            });
            if (this.filters.search) params.set('search', this.filters.search);
            if (this.filters.status) params.set('status', this.filters.status);
            if (this.filters.practice_area) params.set('practice_area', this.filters.practice_area);
            if (this.filters.source) params.set('source', this.filters.source);
            if (this.filters.state) params.set('state', this.filters.state);

            try {
                const res = await fetch('/api/leads?' + params, {
                    headers: { 'X-Requested-With': 'XMLHttpRequest' },
                });
                if (res.status === 401) {
                    window.location.href = '/views/login.php';
                    return;
                }
                const data = await res.json();
                this.leads = data.leads;
                this.meta = { total: data.total, page: data.page, pages: data.pages, limit: data.limit };
            } catch (err) {
                console.error('Failed to fetch leads:', err);
            }
        },

        addLead() {
            this.currentLead = { id: null, name: '', email: '', phone: '', practice_area: '', status: 'New', score: 0, source: '', city: '', state: '', notes: '' };
            this.formError = '';
            this.showModal = true;
        },

        editLead(lead) {
            this.currentLead = { ...lead };
            this.formError = '';
            this.showModal = true;
        },

        showDetail(lead) {
            this.detailLead = { ...lead };
            this.showDetailPanel = true;
        },

        async saveLead() {
            this.formError = '';
            const isNew = !this.currentLead.id;
            const method = isNew ? 'POST' : 'PUT';
            const payload = { ...this.currentLead, csrf_token: this.getCsrf() };

            try {
                const res = await fetch('/api/leads', {
                    method: method,
                    headers: {
                        'Content-Type': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest',
                    },
                    body: JSON.stringify(payload),
                });
                const data = await res.json();

                if (!res.ok) {
                    this.formError = data.error || 'Save failed';
                    return;
                }

                if (isNew) {
                    this.leads.unshift(data);
                    this.meta.total++;
                } else {
                    const idx = this.leads.findIndex(l => l.id == data.id);
                    if (idx !== -1) this.leads[idx] = data;
                }
                this.showModal = false;
            } catch (err) {
                this.formError = 'Connection error. Try again.';
            }
        },

        async deleteLead(id) {
            if (!confirm('Delete this lead?')) return;

            try {
                const res = await fetch('/api/leads', {
                    method: 'DELETE',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest',
                    },
                    body: JSON.stringify({ id: id, csrf_token: this.getCsrf() }),
                });

                if (res.ok) {
                    this.leads = this.leads.filter(l => l.id != id);
                    this.meta.total--;
                }
            } catch (err) {
                console.error('Delete failed:', err);
            }
        },

        prevPage() {
            if (this.meta.page > 1) {
                this.meta.page--;
                this.fetchLeads();
            }
        },

        nextPage() {
            if (this.meta.page < this.meta.pages) {
                this.meta.page++;
                this.fetchLeads();
            }
        },

        async logout() {
            await fetch('/api/auth/logout', {
                method: 'POST',
                headers: { 'X-Requested-With': 'XMLHttpRequest' },
            });
            localStorage.removeItem('csrf_token');
            window.location.href = '/views/login.php';
        },
    };
}
