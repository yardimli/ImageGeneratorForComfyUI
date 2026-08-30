(() => {
    const config = window.dreamCoverRenderQueue;
    const panel = document.getElementById('renderQueuePanel');

    if (!config || !panel) {
        return;
    }

    const elements = {
        badge: document.getElementById('renderQueueBadge'),
        body: document.getElementById('renderQueueBody'),
        chevron: document.getElementById('renderQueueChevron'),
        collapse: document.getElementById('renderQueueCollapse'),
        failed: document.getElementById('renderFailedCount'),
        jobs: document.getElementById('renderQueueJobs'),
        navBadge: document.getElementById('navQueueCount'),
        processing: document.getElementById('renderProcessingCount'),
        pulse: document.getElementById('renderQueuePulse'),
        queued: document.getElementById('renderQueuedCount'),
        retrying: document.getElementById('renderRetryCount'),
        summary: document.getElementById('renderQueueSummary'),
    };

    let processingRequest = false;
    let queueCheckInFlight = false;
    let cancelRequestInFlight = false;
    let pollTimer = null;
    let collapsed = localStorage.getItem('renderQueueCollapsed') === 'true';

    function setCollapsed(value) {
        collapsed = value;
        elements.body.classList.toggle('hidden', collapsed);
        elements.chevron.classList.toggle('rotate-180', collapsed);
        elements.collapse.setAttribute('aria-expanded', String(!collapsed));
        elements.collapse.querySelector('.sr-only').textContent = collapsed
            ? 'Expand render queue'
            : 'Collapse render queue';
        localStorage.setItem('renderQueueCollapsed', String(collapsed));
    }

    function escapeHtml(value) {
        const element = document.createElement('div');
        element.textContent = value ?? '';
        return element.innerHTML;
    }

    function renderJobs(jobs) {
        if (!jobs.length) {
            elements.jobs.innerHTML = '<p class="py-4 text-center text-sm text-slate-500">The global queue is clear.</p>';
            return;
        }

        elements.jobs.innerHTML = jobs.map((job) => {
            const statusClasses = {
                processing: 'bg-sky-100 text-sky-700 dark:bg-sky-500/20 dark:text-sky-200',
                retrying: 'bg-amber-100 text-amber-700 dark:bg-amber-500/20 dark:text-amber-200',
                queued: 'bg-slate-100 text-slate-600 dark:bg-slate-800 dark:text-slate-300',
            };

            const cancelButton = job.status === 'processing'
                ? `<button type="button" data-cancel-render-job="${job.id}" class="rounded-lg border border-red-200 px-2.5 py-1 text-[11px] font-bold text-red-600 transition hover:bg-red-50 disabled:cursor-wait disabled:opacity-60 dark:border-red-500/40 dark:text-red-300 dark:hover:bg-red-500/10">Cancel</button>`
                : '';

            return `
                <div class="flex items-center gap-3 rounded-xl border border-slate-200 p-3 dark:border-slate-700">
                    <div class="min-w-0 flex-1">
                        <p class="truncate text-xs font-semibold text-slate-900 dark:text-white">#${job.id} · ${escapeHtml(job.model)}</p>
                        <p class="mt-0.5 text-[11px] text-slate-500">${job.status === 'processing' ? 'Current job · ' : ''}User #${job.user_id}</p>
                    </div>
                    <span class="rounded-full px-2 py-1 text-[10px] font-bold uppercase tracking-wide ${statusClasses[job.status] || statusClasses.queued}">${escapeHtml(job.status)}</span>
                    ${cancelButton}
                </div>
            `;
        }).join('');
    }

    function applyStatus(data) {
        const counts = data.counts;
        panel.classList.toggle('hidden', data.active_count === 0);
        elements.queued.textContent = counts.queued;
        elements.processing.textContent = counts.processing;
        elements.retrying.textContent = counts.retrying;
        elements.failed.textContent = counts.failed;
        elements.badge.textContent = data.active_count;
        if (elements.navBadge) {
            elements.navBadge.textContent = data.active_count;
        }

        if (processingRequest || counts.processing > 0) {
            elements.summary.textContent = 'Rendering an image…';
            elements.pulse.className = 'size-2.5 animate-pulse rounded-full bg-sky-500';
        } else if (data.active_count > 0) {
            elements.summary.textContent = `${data.active_count} job${data.active_count === 1 ? '' : 's'} waiting`;
            elements.pulse.className = 'size-2.5 animate-pulse rounded-full bg-amber-500';
        } else {
            elements.summary.textContent = 'Queue is clear';
            elements.pulse.className = 'size-2.5 rounded-full bg-emerald-500';
        }

        renderJobs(data.jobs || []);
    }

    async function fetchStatus() {
        const response = await fetch(config.statusUrl, {
            headers: { Accept: 'application/json' },
            cache: 'no-store',
        });

        if (!response.ok) {
            throw new Error('Could not read the global render queue.');
        }

        return response.json();
    }

    async function processNext() {
        if (processingRequest) {
            return;
        }

        processingRequest = true;
        elements.summary.textContent = 'Rendering an image…';
        elements.pulse.className = 'size-2.5 animate-pulse rounded-full bg-sky-500';

        try {
            const response = await fetch(config.processUrl, {
                method: 'POST',
                headers: {
                    Accept: 'application/json',
                    'X-CSRF-TOKEN': config.csrfToken,
                },
            });

            if (!response.ok) {
                const data = await response.json().catch(() => ({}));
                throw new Error(data.message || 'The render worker request failed.');
            }
        } catch (error) {
            console.error('Render worker request failed:', error);
            elements.summary.textContent = error.message;
            elements.pulse.className = 'size-2.5 rounded-full bg-red-500';
        } finally {
            processingRequest = false;
        }
    }

    async function cancelCurrentJob(jobId, button) {
        if (cancelRequestInFlight) {
            return;
        }

        cancelRequestInFlight = true;
        button.disabled = true;
        button.textContent = 'Cancelling…';

        try {
            const response = await fetch(`${config.cancelUrl}/${jobId}/cancel`, {
                method: 'POST',
                headers: {
                    Accept: 'application/json',
                    'X-CSRF-TOKEN': config.csrfToken,
                },
            });
            const data = await response.json().catch(() => ({}));

            if (!response.ok && response.status !== 409) {
                throw new Error(data.message || 'Could not cancel the current render job.');
            }

            const status = await fetchStatus();
            applyStatus(status);
        } catch (error) {
            console.error('Render cancellation failed:', error);
            elements.summary.textContent = error.message;
            elements.pulse.className = 'size-2.5 rounded-full bg-red-500';
            button.disabled = false;
            button.textContent = 'Cancel';
        } finally {
            cancelRequestInFlight = false;
        }
    }

    async function tick() {
        clearTimeout(pollTimer);

        if (queueCheckInFlight) {
            pollTimer = setTimeout(tick, 3000);
            return;
        }

        queueCheckInFlight = true;

        try {
            let status = await fetchStatus();
            applyStatus(status);

            if ((status.counts.queued > 0 || status.counts.retrying > 0) && !status.worker_busy) {
                void processNext();
            }
        } catch (error) {
            console.error('Global render queue polling failed:', error);
            elements.summary.textContent = error.message;
            elements.pulse.className = 'size-2.5 rounded-full bg-red-500';
        } finally {
            queueCheckInFlight = false;
            pollTimer = setTimeout(tick, 3000);
        }
    }

    elements.collapse.addEventListener('click', () => setCollapsed(!collapsed));
    elements.jobs.addEventListener('click', (event) => {
        const button = event.target.closest('[data-cancel-render-job]');
        if (button) {
            void cancelCurrentJob(button.dataset.cancelRenderJob, button);
        }
    });
    setCollapsed(collapsed);
    tick();
})();
