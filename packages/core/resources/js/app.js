import './prototype.js';
import Choices from 'choices.js';

document.addEventListener('DOMContentLoaded', () => {
    const activeClasses = [
        'border-emerald-600',
        'bg-surface',
        'font-semibold',
        'text-accent',
    ];
    const inactiveClasses = [
        'border-transparent',
        'text-muted',
        'hover:border-edge',
        'hover:text-content',
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

    document.querySelectorAll('[data-choices]').forEach((select) => {
        new Choices(select, {
            removeItemButton: true,
            searchEnabled: true,
            shouldSort: false,
            placeholderValue: select.dataset.choicesPlaceholder || 'Search…',
            noResultsText: 'No matches found',
            noChoicesText: 'No options to choose from',
        });
    });

    // Ajax-driven single-select parent entry picker for the entry Hierarchy
    // tab. Remote search against entries.parent_picker.index; the current
    // parent is prefilled server-side as a selected <option>. Deliberately
    // keyed off data-parent-picker (not data-choices) so the generic
    // enhancer above never double-initialises it.
    document.querySelectorAll('select[data-parent-picker]').forEach((select) => {
        const pickerUrl = select.dataset.pickerUrl;
        const entryGroupId = select.dataset.entryGroupId;
        const exclude = select.dataset.exclude || '';
        const perPage = select.dataset.perPage || '';
        const initialCount = select.dataset.initialCount || '';
        const noneLabel = '— None (top-level) —';

        const choices = new Choices(select, {
            removeItemButton: false,
            searchEnabled: true,
            // Results come from the server already filtered; letting Choices
            // re-filter them locally would drop valid matches.
            searchChoices: false,
            searchFloor: 2,
            shouldSort: false,
            placeholderValue: select.dataset.choicesPlaceholder || 'Search entries…',
            noResultsText: 'No matching entries',
            noChoicesText: 'Type to search entries',
        });

        let debounceTimer = null;
        let requestSeq = 0;

        const loadChoices = (q, limit) => {
            const seq = ++requestSeq;
            const url = new URL(pickerUrl, window.location.origin);
            url.searchParams.set('q', q);
            url.searchParams.set('entry_group_id', entryGroupId);
            if (exclude) {
                url.searchParams.set('exclude', exclude);
            }
            if (limit) {
                url.searchParams.set('per_page', limit);
            }

            fetch(url, { headers: { Accept: 'application/json' } })
                .then((response) => (response.ok ? response.json() : Promise.reject(response)))
                .then((json) => {
                    if (seq !== requestSeq) {
                        return; // a newer search superseded this response
                    }

                    const items = (json.data || []).map((entry) => ({
                        value: String(entry.id),
                        label: entry.uri ? `${entry.title} — /${entry.uri}` : entry.title,
                    }));
                    // setChoices(..., replace) wipes the list, so the
                    // top-level option has to be re-added each time.
                    items.unshift({ value: '', label: noneLabel });

                    choices.setChoices(items, 'value', 'label', true);
                })
                .catch(() => {});
        };

        select.addEventListener('search', (event) => {
            const q = event.detail.value;
            clearTimeout(debounceTimer);
            debounceTimer = setTimeout(() => loadChoices(q, perPage), 300);
        });

        // Prepopulate the dropdown so it opens with options before the
        // user types; the count comes from the markup like the other
        // request values.
        if (initialCount) {
            loadChoices('', initialCount);
        }
    });
});
