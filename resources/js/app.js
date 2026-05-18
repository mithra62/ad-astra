import './bootstrap';
import './tailwind2.js';

document.addEventListener('DOMContentLoaded', () => {
    const activeClasses = [
        'border-emerald-600',
        'bg-white',
        'font-semibold',
        'text-emerald-700',
    ];
    const inactiveClasses = [
        'border-transparent',
        'text-slate-500',
        'hover:border-slate-300',
        'hover:text-slate-700',
    ];

    const setClasses = (element, classes, enabled) => {
        classes.forEach((className) => {
            element.classList.toggle(className, enabled);
        });
    };

    const getTabs = (tabsRoot) => Array.from(tabsRoot.querySelectorAll('[data-tab-target]'));
    const getPanels = (tabsRoot) => Array.from(tabsRoot.querySelectorAll('[data-tab-panel]'));

    const activateTab = (tabsRoot, target) => {
        const tabs = getTabs(tabsRoot);
        const panels = getPanels(tabsRoot);

        if (!tabs.length || !panels.length) {
            return;
        }

        const activeTarget = target ?? tabs.find((tab) => tab.getAttribute('aria-selected') === 'true')?.dataset.tabTarget ?? tabs[0].dataset.tabTarget;
        const activeIndex = Math.max(0, tabs.findIndex((tab) => tab.dataset.tabTarget === activeTarget));

        tabs.forEach((tab) => {
            const isActive = tab.dataset.tabTarget === activeTarget;

            tab.classList.toggle('active', isActive);
            tab.classList.toggle('is-active', isActive);
            tab.setAttribute('aria-selected', String(isActive));
            tab.tabIndex = isActive ? 0 : -1;

            setClasses(tab, activeClasses, isActive);
            setClasses(tab, inactiveClasses, !isActive);
        });

        panels.forEach((panel) => {
            const isActive = panel.dataset.tabPanel === activeTarget;

            panel.hidden = !isActive;
            panel.classList.toggle('active', isActive);
            panel.classList.toggle('is-active', isActive);
        });

        tabsRoot.querySelectorAll('[data-tab-position]').forEach((position) => {
            position.textContent = `${activeIndex + 1} / ${tabs.length}`;
        });

        tabsRoot.querySelectorAll('[data-tab-prev]').forEach((button) => {
            button.disabled = activeIndex === 0;
        });

        tabsRoot.querySelectorAll('[data-tab-next]').forEach((button) => {
            button.disabled = activeIndex === tabs.length - 1;
        });
    };

    document.querySelectorAll('[data-tabs]').forEach((tabsRoot) => {
        tabsRoot.addEventListener('click', (event) => {
            const tab = event.target.closest('[data-tab-target]');
            const previous = event.target.closest('[data-tab-prev]');
            const next = event.target.closest('[data-tab-next]');
            const tabs = getTabs(tabsRoot);
            const currentIndex = tabs.findIndex((candidate) => candidate.getAttribute('aria-selected') === 'true');

            if (tab && tabsRoot.contains(tab)) {
                activateTab(tabsRoot, tab.dataset.tabTarget);
                return;
            }

            if (previous && tabsRoot.contains(previous) && currentIndex > 0) {
                activateTab(tabsRoot, tabs[currentIndex - 1].dataset.tabTarget);
                return;
            }

            if (next && tabsRoot.contains(next) && currentIndex < tabs.length - 1) {
                activateTab(tabsRoot, tabs[currentIndex + 1].dataset.tabTarget);
            }
        });

        tabsRoot.addEventListener('keydown', (event) => {
            if (!['ArrowLeft', 'ArrowRight', 'Home', 'End'].includes(event.key)) {
                return;
            }

            const tab = event.target.closest('[data-tab-target]');

            if (!tab || !tabsRoot.contains(tab)) {
                return;
            }

            event.preventDefault();

            const tabs = getTabs(tabsRoot);
            const currentIndex = tabs.indexOf(tab);
            let nextIndex = currentIndex;

            if (event.key === 'ArrowLeft') {
                nextIndex = currentIndex === 0 ? tabs.length - 1 : currentIndex - 1;
            } else if (event.key === 'ArrowRight') {
                nextIndex = currentIndex === tabs.length - 1 ? 0 : currentIndex + 1;
            } else if (event.key === 'Home') {
                nextIndex = 0;
            } else if (event.key === 'End') {
                nextIndex = tabs.length - 1;
            }

            tabs[nextIndex].focus();
            activateTab(tabsRoot, tabs[nextIndex].dataset.tabTarget);
        });

        activateTab(tabsRoot);
    });

    document.querySelectorAll('[data-permission-domain]').forEach((domain) => {
        const toggle = domain.querySelector('[data-permission-domain-toggle]');
        const checkboxes = Array.from(domain.querySelectorAll('[data-permission-checkbox]'));

        if (!toggle || !checkboxes.length) {
            return;
        }

        const syncToggle = () => {
            const checkedCount = checkboxes.filter((checkbox) => checkbox.checked).length;

            toggle.checked = checkedCount === checkboxes.length;
            toggle.indeterminate = checkedCount > 0 && checkedCount < checkboxes.length;
        };

        toggle.addEventListener('change', () => {
            checkboxes.forEach((checkbox) => {
                checkbox.checked = toggle.checked;
            });

            toggle.indeterminate = false;
        });

        checkboxes.forEach((checkbox) => {
            checkbox.addEventListener('change', syncToggle);
        });

        syncToggle();
    });
});
