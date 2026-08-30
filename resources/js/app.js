import './http';

class DreamModal {
    static instances = new WeakMap();

    constructor(element) {
        this.element = element;
        DreamModal.instances.set(element, this);
    }

    static getInstance(element) {
        return element ? DreamModal.instances.get(element) ?? null : null;
    }

    static getOrCreateInstance(element) {
        return DreamModal.getInstance(element) ?? new DreamModal(element);
    }

    show(trigger = null) {
        const showEvent = new CustomEvent('show.dream.modal', { bubbles: true });
        Object.defineProperty(showEvent, 'relatedTarget', { value: trigger });
        this.element.dispatchEvent(showEvent);
        this.element.style.display = 'block';
        this.element.classList.add('show');
        this.element.removeAttribute('aria-hidden');
        this.element.setAttribute('aria-modal', 'true');
        document.body.classList.add('overflow-hidden');
        const shownEvent = new CustomEvent('shown.dream.modal', { bubbles: true });
        Object.defineProperty(shownEvent, 'relatedTarget', { value: trigger });
        this.element.dispatchEvent(shownEvent);
    }

    hide() {
        this.element.dispatchEvent(new CustomEvent('hide.dream.modal', { bubbles: true }));
        this.element.classList.remove('show');
        this.element.style.display = 'none';
        this.element.setAttribute('aria-hidden', 'true');
        this.element.removeAttribute('aria-modal');
        if (!document.querySelector('.modal.show')) document.body.classList.remove('overflow-hidden');
        this.element.dispatchEvent(new CustomEvent('hidden.dream.modal', { bubbles: true }));
    }
}

window.DreamModal = DreamModal;

document.addEventListener('click', (event) => {
    const modalTrigger = event.target.closest('[data-ui-toggle="modal"]');
    if (modalTrigger) {
        event.preventDefault();
        const selector = modalTrigger.dataset.uiTarget ?? modalTrigger.getAttribute('href');
        const modal = selector ? document.querySelector(selector) : null;
        if (modal) DreamModal.getOrCreateInstance(modal).show(modalTrigger);
        return;
    }

    const dismiss = event.target.closest('[data-ui-dismiss]');
    if (dismiss) {
        const type = dismiss.dataset.uiDismiss;
        if (type === 'modal') {
            const modal = dismiss.closest('.modal');
            if (modal) DreamModal.getOrCreateInstance(modal).hide();
        }
        if (type === 'alert') dismiss.closest('.alert')?.remove();
        return;
    }

    const tabTrigger = event.target.closest('[data-ui-toggle="tab"]');
    if (tabTrigger) {
        event.preventDefault();
        const target = tabTrigger.dataset.uiTarget;
        const tab = target ? document.querySelector(target) : null;
        const tabList = tabTrigger.closest('[role="tablist"], .nav-tabs');
        tabList?.querySelectorAll('[data-ui-toggle="tab"]').forEach((item) => item.classList.remove('active'));
        document.querySelectorAll('.tab-pane').forEach((pane) => pane.classList.remove('active', 'show'));
        tabTrigger.classList.add('active');
        tab?.classList.add('active', 'show');
        return;
    }

    const collapseTrigger = event.target.closest('[data-ui-toggle="collapse"]');
    if (collapseTrigger) {
        event.preventDefault();
        const selector = collapseTrigger.dataset.uiTarget ?? collapseTrigger.getAttribute('href');
        const content = selector ? document.querySelector(selector) : null;
        if (content) {
            const opening = content.classList.contains('hidden');
            content.classList.toggle('hidden');
            content.classList.toggle('show', opening);
            collapseTrigger.setAttribute('aria-expanded', String(opening));
        }
    }
});

document.addEventListener('keydown', (event) => {
    if (event.key !== 'Escape') return;
    const openModal = document.querySelector('.modal.show');
    if (openModal) DreamModal.getOrCreateInstance(openModal).hide();
});
