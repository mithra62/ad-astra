// Shared JS extracted from resources/templates/tailwind2 Twig templates.
// Each source block is isolated so page-specific behavior can safely coexist.

window.generateHandleValue = function (value) {
    return String(value || '')
        .toLowerCase()
        .trim()
        .replace(/[^a-z0-9\s-]/g, '')
        .replace(/\s+/g, '-')
        .replace(/-+/g, '-')
        .replace(/^-|-$/g, '');
};

window.attachHandleGenerator = function (sourceId, targetId) {
    var source = document.getElementById(sourceId);
    var target = document.getElementById(targetId);

    if (!source || !target || target.dataset.handleGeneratorAttached) return;

    target.dataset.handleGeneratorAttached = '1';

    source.addEventListener('input', function () {
        if (target.dataset.manual) return;
        target.value = window.generateHandleValue(source.value);
    });

    target.addEventListener('input', function () {
        target.dataset.manual = '1';
        target.value = window.generateHandleValue(target.value);
    });
};

// Source: categories.twig
(function () {
    try {
(function () {
    // Select-all checkbox
    var selectAll = document.getElementById('select-all');
    var rowCbs = document.querySelectorAll('.row-cb');
    var selectedCount = document.getElementById('selected-count');
    var selectedNum = document.getElementById('selected-num');
    var categorySearch = document.getElementById('cat-search');

    if (!selectAll || !selectedCount || !selectedNum || !categorySearch) return;

    function updateSelectedCount() {
        var n = document.querySelectorAll('.row-cb:checked').length;
        selectedNum.textContent = n;
        selectedCount.classList.toggle('hidden', n === 0);
    }

    selectAll.addEventListener('change', function () {
        rowCbs.forEach(function (cb) { cb.checked = selectAll.checked; });
        updateSelectedCount();
    });
    rowCbs.forEach(function (cb) {
        cb.addEventListener('change', function () {
            selectAll.checked = Array.from(rowCbs).every(function (c) { return c.checked; });
            selectAll.indeterminate = !selectAll.checked && Array.from(rowCbs).some(function (c) { return c.checked; });
            updateSelectedCount();
        });
    });

    // Live search filter
    categorySearch.addEventListener('input', function () {
        var q = this.value.toLowerCase();
        var rows = document.querySelectorAll('#cat-tbody tr');
        var visible = 0;
        rows.forEach(function (row) {
            var name = (row.dataset.name || '').toLowerCase();
            var show = !q || name.indexOf(q) !== -1;
            row.style.display = show ? '' : 'none';
            if (show) visible++;
        });
        document.getElementById('row-count').textContent = 'Showing ' + visible + ' categor' + (visible === 1 ? 'y' : 'ies');
    });

    // Bulk action stub
    window.applyBulk = function () {
        var action = document.getElementById('bulk-action').value;
        var checked = Array.from(document.querySelectorAll('.row-cb:checked')).map(function (cb) { return cb.value; });
        if (!action) { alert('Please select an action.'); return; }
        if (!checked.length) { alert('Please select at least one category.'); return; }
        alert(action.charAt(0).toUpperCase() + action.slice(1) + ' ' + checked.length + ' categor' + (checked.length === 1 ? 'y' : 'ies') + ' (stub).');
    };

    // Auto-slug from name when the quick-add form exists.
    var categoryName = document.getElementById('cat_name');
    var categorySlug = document.getElementById('cat_slug');
    if (categoryName && categorySlug) {
        categoryName.addEventListener('input', function () {
            var slug = this.value.toLowerCase().trim()
                .replace(/[^a-z0-9\s-]/g, '').replace(/\s+/g, '-').replace(/-+/g, '-');
            if (!categorySlug.dataset.manual) categorySlug.value = slug;
        });
        categorySlug.addEventListener('input', function () {
            this.dataset.manual = '1';
        });
    }
})();

// Auth templates
(function () {
    document.querySelectorAll('[data-auth-password-toggle]').forEach(function (button) {
        button.addEventListener('click', function () {
            var input = document.getElementById(button.getAttribute('data-auth-password-toggle'));
            if (!input) return;

            var isPassword = input.type === 'password';
            input.type = isPassword ? 'text' : 'password';
            button.setAttribute('aria-label', isPassword ? 'Hide password' : 'Show password');
        });
    });
})();
    } catch (error) {
        // Page-specific block did not apply to this template.
    }
})();

// Source: create-article.twig
(function () {
    try {
(function () {
                            var ALL_TAGS = ['announcement','api','bugfix','design','devops','editorial',
                                            'engineering','feature','mobile','operations','performance',
                                            'planning','product','release','security','support','tutorial','update'];
                            var selected = new Set([]);
                            var cloud  = document.getElementById('tag-cloud-create');
                            var inputs = document.getElementById('tag-inputs-create');
                            var search = document.getElementById('tag-search-create');
                            function render() {
                                var f = search.value.toLowerCase();
                                cloud.innerHTML = '';
                                inputs.innerHTML = '';
                                ALL_TAGS.forEach(function (tag) {
                                    if (f && tag.indexOf(f) === -1) return;
                                    var on = selected.has(tag);
                                    var btn = document.createElement('button');
                                    btn.type = 'button';
                                    btn.textContent = tag;
                                    btn.className = on
                                        ? 'rounded-full px-2.5 py-1 text-xs font-medium bg-emerald-600 text-white ring-1 ring-emerald-600 hover:bg-emerald-700 transition-colors cursor-pointer'
                                        : 'rounded-full px-2.5 py-1 text-xs font-medium bg-slate-100 text-slate-600 ring-1 ring-slate-200 hover:bg-slate-200 transition-colors cursor-pointer';
                                    btn.addEventListener('click', function () {
                                        if (selected.has(tag)) { selected.delete(tag); } else { selected.add(tag); }
                                        render();
                                    });
                                    cloud.appendChild(btn);
                                    if (on) {
                                        var inp = document.createElement('input');
                                        inp.type = 'hidden';
                                        inp.name = 'tags[]';
                                        inp.value = tag;
                                        inputs.appendChild(inp);
                                    }
                                });
                            }
                            search.addEventListener('input', render);
                            render();
                        })();
    } catch (error) {
        // Page-specific block did not apply to this template.
    }
})();

// Source: create-article2.twig
(function () {
    try {
(function () {
    'use strict';

    /* ─── Tab switching ─────────────────────────────────────────────── */
    var tabsRoot = document.querySelector('[data-tabs]') || document;
    var usesGenericTabs = tabsRoot !== document;
    var currentIdx = 0;

    var buttons = tabsRoot.querySelectorAll('.tab-btn');
    var panels  = tabsRoot.querySelectorAll('.tab-panel');
    var TABS = Array.from(buttons).map(function (btn) {
        return btn.getAttribute('data-tab-target') || btn.getAttribute('data-target') || btn.getAttribute('aria-controls');
    });
    var prevBtn = document.getElementById('tab-prev');
    var nextBtn = document.getElementById('tab-next');
    var posEl   = document.getElementById('tab-position');

    if (!buttons.length || !panels.length || !prevBtn || !nextBtn || !posEl) return;

    function activateTab(idx) {
        if (idx < 0 || idx >= TABS.length) return;
        currentIdx = idx;

        buttons.forEach(function (btn, i) {
            var isActive = i === idx;
            btn.classList.toggle('active', isActive);
            btn.setAttribute('aria-selected', isActive ? 'true' : 'false');
        });

        panels.forEach(function (panel) {
            var panelTarget = panel.getAttribute('data-tab-panel') || panel.id;
            var isActive = panelTarget === TABS[idx] || panel.id === TABS[idx];
            panel.classList.toggle('active', isActive);
            panel.hidden = !isActive;
        });

        prevBtn.disabled = (idx === 0);
        nextBtn.disabled = (idx === TABS.length - 1);
        posEl.textContent = (idx + 1) + ' / ' + TABS.length;
    }

    if (!usesGenericTabs) {
        buttons.forEach(function (btn, i) {
            btn.addEventListener('click', function () { activateTab(i); });
        });

        prevBtn.addEventListener('click', function () { activateTab(currentIdx - 1); });
        nextBtn.addEventListener('click', function () { activateTab(currentIdx + 1); });

        activateTab(0);
    }

    /* ─── Unsaved-changes dirty tracking ───────────────────────────── */
    var unsavedNotice = document.getElementById('unsaved-notice');
    var dirtyTabs = {};

    function markDirty(tabName) {
        if (dirtyTabs[tabName]) return;
        dirtyTabs[tabName] = true;
        var tabIdx = TABS.indexOf(tabName);
        if (tabIdx !== -1) {
            buttons[tabIdx].classList.add('dirty');
        }
        unsavedNotice.classList.remove('hidden');
    }

    document.querySelectorAll('[data-tab-dirty]').forEach(function (el) {
        var tabName = el.getAttribute('data-tab-dirty');
        var evt = (el.tagName === 'SELECT' || el.type === 'checkbox' || el.type === 'radio')
            ? 'change' : 'input';
        el.addEventListener(evt, function () { markDirty(tabName); });
    });

    // Clear dirty state on submit
    document.getElementById('article-form').addEventListener('submit', function () {
        Object.keys(dirtyTabs).forEach(function (tabName) {
            var tabIdx = TABS.indexOf(tabName);
            if (tabIdx !== -1) buttons[tabIdx].classList.remove('dirty');
        });
        dirtyTabs = {};
        unsavedNotice.classList.add('hidden');
    });

    /* ─── Taxonomy badge: count selected categories ─────────────────── */
    var badge = document.getElementById('badge-taxonomy');
    function updateBadge() {
        var count = document.querySelectorAll('input[name="categories[]"]:checked').length
                  + selectedTags.size;
        badge.textContent = count;
        badge.style.display = count > 0 ? '' : '';
    }

    document.querySelectorAll('input[name="categories[]"]').forEach(function (cb) {
        cb.addEventListener('change', updateBadge);
    });

    /* ─── Tag cloud ──────────────────────────────────────────────────── */
    var ALL_TAGS = ['announcement','api','bugfix','design','devops','editorial',
                    'engineering','feature','mobile','operations','performance',
                    'planning','product','release','security','support','tutorial','update'];
    var selectedTags = new Set();
    var cloud  = document.getElementById('tag-cloud');
    var tagInputs = document.getElementById('tag-inputs');
    var search = document.getElementById('tag-search');

    function renderTags() {
        var filter = search.value.toLowerCase();
        cloud.innerHTML = '';
        tagInputs.innerHTML = '';
        ALL_TAGS.forEach(function (tag) {
            if (filter && tag.indexOf(filter) === -1) return;
            var on = selectedTags.has(tag);
            var btn = document.createElement('button');
            btn.type = 'button';
            btn.textContent = tag;
            btn.className = on
                ? 'rounded-full px-2.5 py-1 text-xs font-medium bg-emerald-600 text-white ring-1 ring-emerald-600 hover:bg-emerald-700 transition-colors cursor-pointer'
                : 'rounded-full px-2.5 py-1 text-xs font-medium bg-slate-100 text-slate-600 ring-1 ring-slate-200 hover:bg-slate-200 transition-colors cursor-pointer';
            btn.addEventListener('click', function () {
                if (selectedTags.has(tag)) { selectedTags.delete(tag); } else { selectedTags.add(tag); }
                markDirty('taxonomy');
                renderTags();
                updateBadge();
            });
            cloud.appendChild(btn);
            if (on) {
                var inp = document.createElement('input');
                inp.type = 'hidden';
                inp.name = 'tags[]';
                inp.value = tag;
                tagInputs.appendChild(inp);
            }
        });
    }

    search.addEventListener('input', renderTags);
    renderTags();

    /* ─── Auto-slug from title ───────────────────────────────────────── */
    document.getElementById('title').addEventListener('input', function () {
        var slug = this.value
            .toLowerCase().trim()
            .replace(/[^a-z0-9\s-]/g, '')
            .replace(/\s+/g, '-')
            .replace(/-+/g, '-');
        var handleEl = document.getElementById('handle');
        if (!handleEl.dataset.manuallyEdited) {
            handleEl.value = slug;
        }
        // Live-update SERP preview
        var serpTitle = document.getElementById('serp-title');
        var metaTitle = document.getElementById('meta_title');
        if (!metaTitle.value) {
            serpTitle.textContent = this.value || 'Your article title will appear here';
        }
        var serpUrl = document.getElementById('serp-url');
        serpUrl.textContent = 'https://example.com/articles/' + (slug || 'your-slug');
    });

    document.getElementById('handle').addEventListener('input', function () {
        this.dataset.manuallyEdited = '1';
        document.getElementById('serp-url').textContent =
            'https://example.com/articles/' + (this.value || 'your-slug');
    });

    /* ─── Character counters for SEO fields ─────────────────────────── */
    function bindCharCount(inputId, countId, max) {
        var el = document.getElementById(inputId);
        var counter = document.getElementById(countId);
        if (!el || !counter) return;
        el.addEventListener('input', function () {
            var len = this.value.length;
            counter.textContent = len + ' / ' + max;
            counter.className = 'tabular-nums ' + (len > max * 0.9 ? 'text-amber-600' : 'text-slate-400');
        });
    }
    bindCharCount('meta_title', 'meta-title-count', 70);
    bindCharCount('meta_description', 'meta-desc-count', 160);

    /* Live SERP preview updates */
    document.getElementById('meta_title').addEventListener('input', function () {
        document.getElementById('serp-title').textContent =
            this.value || document.getElementById('title').value || 'Your article title will appear here';
    });
    document.getElementById('meta_description').addEventListener('input', function () {
        document.getElementById('serp-desc').textContent =
            this.value || 'Your meta description will appear here. Make it descriptive and inviting — this is what users see in search results.';
    });

    /* Initial badge + prev/next state */
    updateBadge();

})();
    } catch (error) {
        // Page-specific block did not apply to this template.
    }
})();

// Source: create-category.twig
(function () {
    try {
(function () {
    // Auto-slug
    document.getElementById('name').addEventListener('input', function () {
        var slug = this.value.toLowerCase().trim()
            .replace(/[^a-z0-9\s-]/g, '').replace(/\s+/g, '-').replace(/-+/g, '-');
        var slugEl = document.getElementById('slug');
        if (!slugEl.dataset.manual) slugEl.value = slug;
        var serpTitle = document.getElementById('serp-title');
        var metaTitle = document.getElementById('meta_title');
        if (!metaTitle.value) serpTitle.textContent = this.value || 'Category name will appear here';
        document.getElementById('serp-url').textContent = 'https://example.com/category/' + (slug || 'your-slug');
    });
    document.getElementById('slug').addEventListener('input', function () {
        this.dataset.manual = '1';
        document.getElementById('serp-url').textContent = 'https://example.com/category/' + (this.value || 'your-slug');
    });

    // Char counters
    function bindCount(id, countId, max) {
        var el = document.getElementById(id);
        var counter = document.getElementById(countId);
        if (!el || !counter) return;
        el.addEventListener('input', function () {
            var len = this.value.length;
            counter.textContent = len + ' / ' + max;
            counter.className = 'tabular-nums ' + (len > max * 0.9 ? 'text-amber-600' : 'text-slate-400');
        });
    }
    bindCount('meta_title', 'meta-title-count', 70);
    bindCount('meta_description', 'meta-desc-count', 160);

    document.getElementById('meta_title').addEventListener('input', function () {
        document.getElementById('serp-title').textContent =
            this.value || document.getElementById('name').value || 'Category name will appear here';
    });
    document.getElementById('meta_description').addEventListener('input', function () {
        document.getElementById('serp-desc').textContent =
            this.value || 'Your meta description will appear here.';
    });
})();
    } catch (error) {
        // Page-specific block did not apply to this template.
    }
})();

// Source: create-field-layout-tab.twig
(function () {
    try {
(function () {
    var name = document.getElementById('name');
    var handle = document.getElementById('handle');
    name.addEventListener('input', function () {
        if (handle.dataset.manual) return;
        handle.value = name.value.toLowerCase().trim().replace(/[^a-z0-9\s-]/g, '').replace(/\s+/g, '-').replace(/-+/g, '-');
    });
    handle.addEventListener('input', function () { handle.dataset.manual = '1'; });
})();
    } catch (error) {
        // Page-specific block did not apply to this template.
    }
})();

// Source: create-media-library.twig
(function () {
    try {
(function () {
    document.getElementById('name').addEventListener('input', function () {
        var handle = this.value.toLowerCase().trim()
            .replace(/[^a-z0-9\s-]/g, '').replace(/\s+/g, '-').replace(/-+/g, '-');
        var handleEl = document.getElementById('handle');
        if (!handleEl.dataset.manual) handleEl.value = handle;
    });
    document.getElementById('handle').addEventListener('input', function () {
        this.dataset.manual = '1';
    });
})();
    } catch (error) {
        // Page-specific block did not apply to this template.
    }
})();

// Source: delete-category.twig
(function () {
    try {
(function () {
    var REQUIRED = 'Engineering';
    var confirmInput = document.getElementById('confirm_name');
    var deleteBtn    = document.getElementById('delete-btn');
    var confirmError = document.getElementById('confirm-error');

    // Enable delete button only when name matches exactly
    confirmInput.addEventListener('input', function () {
        var match = this.value === REQUIRED;
        deleteBtn.disabled = !match;
        confirmError.classList.toggle('hidden', match || this.value === '');
    });

    // Shake on submit if somehow still disabled (safety net)
    document.getElementById('delete-form').addEventListener('submit', function (e) {
        if (confirmInput.value !== REQUIRED) {
            e.preventDefault();
            confirmInput.classList.add('shake', 'border-red-400');
            confirmInput.addEventListener('animationend', function () {
                confirmInput.classList.remove('shake');
            }, { once: true });
        }
    });

    // Show/hide reassign select
    document.querySelectorAll('input[name="article_action"]').forEach(function (radio) {
        radio.addEventListener('change', function () {
            document.getElementById('reassign-select').classList.toggle('hidden', this.value !== 'reassign');
        });
    });

    // Show/hide reparent select
    document.querySelectorAll('input[name="subcategory_action"]').forEach(function (radio) {
        radio.addEventListener('change', function () {
            document.getElementById('reparent-select').classList.toggle('hidden', this.value !== 'reparent');
        });
    });
})();
    } catch (error) {
        // Page-specific block did not apply to this template.
    }
})();

// Source: delete-field-layout.twig
(function () {
    try {
(function () {
    var required = 'Article Layout';
    var input = document.getElementById('confirm_name');
    var button = document.getElementById('delete-btn');
    var error = document.getElementById('confirm-error');
    input.addEventListener('input', function () {
        var match = input.value === required;
        button.disabled = !match;
        error.classList.toggle('hidden', match || input.value === '');
    });
    document.getElementById('delete-form').addEventListener('submit', function (e) {
        if (input.value !== required) {
            e.preventDefault();
            input.classList.add('shake', 'border-red-400');
            input.addEventListener('animationend', function () { input.classList.remove('shake'); }, { once: true });
        }
    });
})();
    } catch (error) {
        // Page-specific block did not apply to this template.
    }
})();

// Source: delete-user.twig
(function () {
    try {
(function () {
    var required = 'Jane Smith';
    var input = document.getElementById('confirm_name');
    var button = document.getElementById('delete-btn');
    var error = document.getElementById('confirm-error');
    input.addEventListener('input', function () {
        var match = input.value === required;
        button.disabled = !match;
        error.classList.toggle('hidden', match || input.value === '');
    });
    document.getElementById('delete-form').addEventListener('submit', function (e) {
        if (input.value !== required) {
            e.preventDefault();
            input.classList.add('shake', 'border-red-400');
            input.addEventListener('animationend', function () { input.classList.remove('shake'); }, { once: true });
        }
    });
})();
    } catch (error) {
        // Page-specific block did not apply to this template.
    }
})();

// Source: edit-article.twig
(function () {
    try {
(function () {
                            var ALL_TAGS = ['announcement','api','bugfix','design','devops','editorial',
                                            'engineering','feature','mobile','operations','performance',
                                            'planning','product','release','security','support','tutorial','update'];
                            var selected = new Set(['editorial','planning','operations']);
                            var cloud  = document.getElementById('tag-cloud-edit');
                            var inputs = document.getElementById('tag-inputs-edit');
                            var search = document.getElementById('tag-search-edit');
                            function render() {
                                var f = search.value.toLowerCase();
                                cloud.innerHTML = '';
                                inputs.innerHTML = '';
                                ALL_TAGS.forEach(function (tag) {
                                    if (f && tag.indexOf(f) === -1) return;
                                    var on = selected.has(tag);
                                    var btn = document.createElement('button');
                                    btn.type = 'button';
                                    btn.textContent = tag;
                                    btn.className = on
                                        ? 'rounded-full px-2.5 py-1 text-xs font-medium bg-emerald-600 text-white ring-1 ring-emerald-600 hover:bg-emerald-700 transition-colors cursor-pointer'
                                        : 'rounded-full px-2.5 py-1 text-xs font-medium bg-slate-100 text-slate-600 ring-1 ring-slate-200 hover:bg-slate-200 transition-colors cursor-pointer';
                                    btn.addEventListener('click', function () {
                                        if (selected.has(tag)) { selected.delete(tag); } else { selected.add(tag); }
                                        render();
                                    });
                                    cloud.appendChild(btn);
                                    if (on) {
                                        var inp = document.createElement('input');
                                        inp.type = 'hidden';
                                        inp.name = 'tags[]';
                                        inp.value = tag;
                                        inputs.appendChild(inp);
                                    }
                                });
                            }
                            search.addEventListener('input', render);
                            render();
                        })();
    } catch (error) {
        // Page-specific block did not apply to this template.
    }
})();

// Source: edit-category.twig
(function () {
    try {
(function () {
    var form = document.getElementById('edit-form');
    var unsavedBar = document.getElementById('unsaved-bar');
    var discardBtn = document.getElementById('discard-btn');
    var isDirty = false;
    var originalData = new FormData(form);

    function markDirty() {
        if (!isDirty) {
            isDirty = true;
            unsavedBar.classList.add('visible');
        }
    }

    form.querySelectorAll('input, select, textarea').forEach(function (el) {
        el.addEventListener('input', markDirty);
        el.addEventListener('change', markDirty);
    });

    form.addEventListener('submit', function () {
        isDirty = false;
        unsavedBar.classList.remove('visible');
    });

    discardBtn.addEventListener('click', function () {
        if (confirm('Discard all unsaved changes?')) {
            form.reset();
            isDirty = false;
            unsavedBar.classList.remove('visible');
        }
    });

    // Char counters
    function bindCount(id, countId, max) {
        var el = document.getElementById(id);
        var counter = document.getElementById(countId);
        if (!el || !counter) return;
        function update() {
            var len = el.value.length;
            counter.textContent = len + ' / ' + max;
            counter.className = 'tabular-nums ' + (len > max * 0.9 ? 'text-amber-600' : 'text-slate-400');
        }
        el.addEventListener('input', update);
        update();
    }
    bindCount('meta_title', 'meta-title-count', 70);
    bindCount('meta_description', 'meta-desc-count', 160);

    // Live SERP
    document.getElementById('meta_title').addEventListener('input', function () {
        document.getElementById('serp-title').textContent = this.value || document.getElementById('name').value || 'Category name';
    });
    document.getElementById('meta_description').addEventListener('input', function () {
        document.getElementById('serp-desc').textContent = this.value || 'Meta description.';
    });
    document.getElementById('slug').addEventListener('input', function () {
        document.getElementById('serp-url').textContent = 'https://example.com/category/' + (this.value || 'engineering');
    });

    // Warn on unload if dirty
    window.addEventListener('beforeunload', function (e) {
        if (isDirty) { e.preventDefault(); e.returnValue = ''; }
    });
})();
    } catch (error) {
        // Page-specific block did not apply to this template.
    }
})();

// Source: edit-field-layout.twig
(function () {
    try {
Sortable.create(document.getElementById('tabs-list'), { handle: '.drag-handle', animation: 150 });
    } catch (error) {
        // Page-specific block did not apply to this template.
    }
})();

// Source: edit-user.twig
(function () {
    try {
(function () {
    var form = document.getElementById('user-form');
    var bar = document.getElementById('unsaved-bar');
    form.querySelectorAll('input, select, textarea').forEach(function (el) {
        el.addEventListener('input', function () { bar.classList.add('visible'); });
        el.addEventListener('change', function () { bar.classList.add('visible'); });
    });
})();
    } catch (error) {
        // Page-specific block did not apply to this template.
    }
})();

// Source: field-layout-tab-fields.twig
(function () {
    try {
(function () {
    // Admin implementation present — let its own script handle this page.
    if (document.getElementById('bulk-save-form')) return;

    var assigned = document.getElementById('assigned-fields');
    var available = document.getElementById('available-fields');
    var resetBtn = document.getElementById('reset-btn');
    var toast = document.getElementById('toast');
    var original = assigned.innerHTML;

    function getFieldMeta(row) {
        var title = row.querySelector('p.text-sm');
        var meta = row.querySelector('p.font-mono');
        var handle = row.dataset.handle;
        if (!handle && meta) handle = meta.textContent.split('/')[0].trim();
        return {
            name: title ? title.textContent.trim() : 'Untitled Field',
            handle: handle || 'field',
            type: meta && meta.textContent.indexOf('/') !== -1 ? meta.textContent.split('/')[1].trim() : 'Field'
        };
    }

    function ensureAssignedControls(row) {
        if (row.querySelector('.field-settings')) return;

        var meta = getFieldMeta(row);
        var header = row.querySelector('.field-row-header') || row.querySelector('.flex.items-center');
        if (!header) {
            header = document.createElement('div');
            header.className = 'field-row-header flex items-center gap-3 px-3 py-3';
            while (row.firstChild) header.appendChild(row.firstChild);
            row.className = 'field-row rounded-md hover:bg-slate-50';
            row.appendChild(header);
        }
        header.classList.add('field-row-header');

        var actions = document.createElement('div');
        actions.className = 'field-row-actions flex shrink-0 items-center gap-1';
        actions.innerHTML =
            '<button type="button" class="toggle-field-settings rounded p-1 text-slate-400 hover:bg-slate-100 hover:text-slate-600" title="Field settings" aria-expanded="false">' +
            '<svg class="settings-chevron h-4 w-4 transition-transform" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7"/></svg>' +
            '</button>' +
            '<button type="button" class="remove-field rounded p-1 text-slate-400 hover:bg-red-50 hover:text-red-600" title="Remove from tab">' +
            '<svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg>' +
            '</button>';

        var badgeWrap = document.createElement('div');
        badgeWrap.className = 'hidden flex-wrap gap-1.5 sm:flex';
        header.appendChild(badgeWrap);
        header.appendChild(actions);

        var settings = document.createElement('div');
        settings.className = 'field-settings hidden border-t border-slate-100 bg-slate-50 px-3 py-4 sm:px-5';
        settings.innerHTML =
            '<div class="grid grid-cols-1 gap-4 lg:grid-cols-[minmax(0,1fr)_minmax(0,1fr)]">' +
            '<div class="space-y-3">' +
            '<label class="flex cursor-pointer items-start gap-3 rounded-md border border-slate-200 bg-white px-4 py-3 text-sm text-slate-700 shadow-sm">' +
            '<input type="checkbox" name="fields[' + meta.handle + '][required]" class="required-toggle mt-0.5 rounded border-slate-300 text-emerald-600 focus:ring-emerald-500">' +
            '<span><span class="font-medium text-slate-800">Required</span><span class="mt-0.5 block text-xs text-slate-500">Users must provide a value before saving.</span></span>' +
            '</label>' +
            '<label class="flex cursor-pointer items-start gap-3 rounded-md border border-slate-200 bg-white px-4 py-3 text-sm text-slate-700 shadow-sm">' +
            '<input type="checkbox" name="fields[' + meta.handle + '][hidden]" class="hidden-toggle mt-0.5 rounded border-slate-300 text-emerald-600 focus:ring-emerald-500">' +
            '<span><span class="font-medium text-slate-800">Hidden</span><span class="mt-0.5 block text-xs text-slate-500">Store the field on the layout without showing it by default.</span></span>' +
            '</label>' +
            '</div>' +
            '<div class="grid grid-cols-1 gap-4 sm:grid-cols-2">' +
            '<div><label class="mb-1.5 block text-xs font-medium text-slate-700">Width</label><select name="fields[' + meta.handle + '][width]" class="w-full rounded-md border border-slate-300 bg-white px-3 py-2 text-sm shadow-sm focus:border-emerald-500 focus:outline-none focus:ring-2 focus:ring-emerald-500/20"><option value="full" selected>Full width</option><option value="half">Half width</option><option value="third">One third</option></select></div>' +
            '<div><label class="mb-1.5 block text-xs font-medium text-slate-700">Visibility Rule</label><input name="fields[' + meta.handle + '][condition]" type="text" placeholder="Optional condition" class="w-full rounded-md border border-slate-300 px-3 py-2 text-sm shadow-sm placeholder:text-slate-400 focus:border-emerald-500 focus:outline-none focus:ring-2 focus:ring-emerald-500/20"></div>' +
            '<div class="sm:col-span-2"><label class="mb-1.5 block text-xs font-medium text-slate-700">Instructions</label><textarea name="fields[' + meta.handle + '][instructions]" rows="2" placeholder="Optional helper text for editors..." class="w-full rounded-md border border-slate-300 px-3 py-2 text-sm shadow-sm placeholder:text-slate-400 focus:border-emerald-500 focus:outline-none focus:ring-2 focus:ring-emerald-500/20"></textarea></div>' +
            '</div>' +
            '</div>';
        row.appendChild(settings);
        syncBadges(row);
    }

    function normalizeAvailableRow(row) {
        var settings = row.querySelector('.field-settings');
        if (settings) settings.remove();
        row.querySelectorAll('.toggle-field-settings, .remove-field').forEach(function (button) { button.remove(); });
        row.querySelectorAll('.required-badge, .hidden-badge').forEach(function (badge) {
            var wrap = badge.parentElement;
            badge.remove();
            if (wrap && !wrap.children.length) wrap.remove();
        });
        row.querySelectorAll('.position-badge').forEach(function (badge) { badge.remove(); });
    }

    function refresh() {
        assigned.querySelectorAll('.field-row').forEach(function (row, index) {
            ensureAssignedControls(row);
            var badge = row.querySelector('.position-badge');
            if (!badge) {
                badge = document.createElement('span');
                badge.className = 'position-badge inline-flex h-6 w-6 shrink-0 items-center justify-center rounded-full bg-slate-100 text-xs font-semibold text-slate-600';
                var header = row.querySelector('.field-row-header') || row.querySelector('.flex.items-center') || row;
                header.insertBefore(badge, header.children[1]);
            }
            badge.textContent = index + 1;
            syncBadges(row);
        });
        available.querySelectorAll('.field-row').forEach(normalizeAvailableRow);
        document.getElementById('assigned-count').textContent = assigned.querySelectorAll('.field-row').length + ' assigned';
        document.getElementById('available-count').textContent = available.querySelectorAll('.field-row').length;
        resetBtn.classList.remove('hidden');
    }

    function syncBadges(row) {
        var required = row.querySelector('.required-toggle');
        var hidden = row.querySelector('.hidden-toggle');
        var requiredBadge = row.querySelector('.required-badge');
        var hiddenBadge = row.querySelector('.hidden-badge');

        if (required && !requiredBadge) {
            var badgeWrap = row.querySelector('.hidden.flex-wrap');
            if (!badgeWrap) return;
            requiredBadge = document.createElement('span');
            requiredBadge.className = 'required-badge rounded-full bg-red-50 px-2 py-0.5 text-xs font-medium text-red-700';
            requiredBadge.textContent = 'Required';
            badgeWrap.appendChild(requiredBadge);
        }
        if (requiredBadge) requiredBadge.classList.toggle('hidden', !required || !required.checked);
        if (hiddenBadge) hiddenBadge.classList.toggle('hidden', !hidden || !hidden.checked);
    }

    [assigned, available].forEach(function (list) {
        Sortable.create(list, {
            group: 'fields',
            draggable: '.field-row',
            handle: '.drag-handle',
            filter: 'input, textarea, select, button, label, a',
            preventOnFilter: false,
            animation: 150,
            ghostClass: 'sortable-ghost',
            onStart: function () { assigned.classList.add('drop-zone-highlight'); available.classList.add('drop-zone-highlight'); },
            onAdd: function (event) {
                if (event.to === assigned) ensureAssignedControls(event.item);
                if (event.to === available) normalizeAvailableRow(event.item);
            },
            onEnd: function () { assigned.classList.remove('drop-zone-highlight'); available.classList.remove('drop-zone-highlight'); refresh(); }
        });
    });

    assigned.addEventListener('click', function (event) {
        var toggle = event.target.closest('.toggle-field-settings');
        if (toggle) {
            var row = toggle.closest('.field-row');
            var settings = row.querySelector('.field-settings');
            var chevron = row.querySelector('.settings-chevron');
            var isHidden = settings.classList.toggle('hidden');
            toggle.setAttribute('aria-expanded', isHidden ? 'false' : 'true');
            chevron.classList.toggle('rotate-180', !isHidden);
            return;
        }

        var button = event.target.closest('.remove-field');
        if (!button) return;
        available.appendChild(button.closest('.field-row'));
        refresh();
    });

    assigned.addEventListener('change', function (event) {
        if (!event.target.matches('.required-toggle, .hidden-toggle')) return;
        syncBadges(event.target.closest('.field-row'));
        resetBtn.classList.remove('hidden');
    });

    document.getElementById('field-search').addEventListener('input', function () {
        var q = this.value.toLowerCase();
        available.querySelectorAll('.field-row').forEach(function (row) {
            row.style.display = !q || (row.dataset.name || '').indexOf(q) !== -1 ? '' : 'none';
        });
    });

    resetBtn.addEventListener('click', function () {
        assigned.innerHTML = original;
        refresh();
        showToast('Field order reset.');
    });

    document.getElementById('save-btn').addEventListener('click', function () {
        original = assigned.innerHTML;
        resetBtn.classList.add('hidden');
        showToast('Field layout saved.');
    });

    function showToast(message) {
        toast.className = 'rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-medium text-emerald-800 shadow-sm';
        toast.textContent = message;
        setTimeout(function () { toast.classList.add('hidden'); }, 4000);
    }
})();
    } catch (error) {
        // Page-specific block did not apply to this template.
    }
})();

// Source: general-delete.twig
(function () {
    try {
(function () {
    var required = 'Published';
    var input = document.getElementById('confirm_name');
    var button = document.getElementById('delete-btn');
    var error = document.getElementById('confirm-error');
    input.addEventListener('input', function () {
        var match = input.value === required;
        button.disabled = !match;
        error.classList.toggle('hidden', match || input.value === '');
    });
    document.getElementById('delete-form').addEventListener('submit', function (e) {
        if (input.value !== required) {
            e.preventDefault();
            input.classList.add('shake', 'border-red-400');
            input.addEventListener('animationend', function () { input.classList.remove('shake'); }, { once: true });
        }
    });
})();
    } catch (error) {
        // Page-specific block did not apply to this template.
    }
})();

// Source: general-edit.twig
(function () {
    try {
(function () {
    var form = document.getElementById('general-form');
    var unsavedBar = document.getElementById('unsaved-bar');
    var name = document.getElementById('name');
    var handle = document.getElementById('handle');

    form.querySelectorAll('input, select, textarea').forEach(function (el) {
        el.addEventListener('input', function () { unsavedBar.classList.add('visible'); });
        el.addEventListener('change', function () { unsavedBar.classList.add('visible'); });
    });

    name.addEventListener('input', function () {
        var slug = this.value.toLowerCase().trim().replace(/[^a-z0-9\s-]/g, '').replace(/\s+/g, '-').replace(/-+/g, '-');
        if (!handle.dataset.manual) handle.value = slug;
        this.classList.toggle('border-red-300', this.value.trim() === '');
        document.getElementById('name-error').classList.toggle('hidden', this.value.trim() !== '');
    });
    handle.addEventListener('input', function () { this.dataset.manual = '1'; });
})();
    } catch (error) {
        // Page-specific block did not apply to this template.
    }
})();

// Source: general-index.twig
(function () {
    try {
(function () {
    var search = document.getElementById('item-search');
    var type = document.getElementById('type-filter');
    var selectAll = document.getElementById('select-all');
    var rowCbs = document.querySelectorAll('.row-cb');

    function filterRows() {
        var q = search.value.toLowerCase();
        var t = type.value;
        var visible = 0;
        document.querySelectorAll('#item-tbody tr').forEach(function (row) {
            var show = (!q || (row.dataset.name || '').indexOf(q) !== -1) && (!t || row.dataset.type === t);
            row.style.display = show ? '' : 'none';
            if (show) visible++;
        });
        document.getElementById('row-count').textContent = 'Showing ' + visible + ' item' + (visible === 1 ? '' : 's');
    }

    search.addEventListener('input', filterRows);
    type.addEventListener('change', filterRows);
    selectAll.addEventListener('change', function () {
        rowCbs.forEach(function (cb) { cb.checked = selectAll.checked; });
    });

    window.applyBulk = function () {
        var action = document.getElementById('bulk-action').value;
        var checked = Array.from(document.querySelectorAll('.row-cb:checked'));
        if (!action) { alert('Please select an action.'); return; }
        if (!checked.length) { alert('Please select at least one item.'); return; }
        alert(action.charAt(0).toUpperCase() + action.slice(1) + ' ' + checked.length + ' item' + (checked.length === 1 ? '' : 's') + ' (stub).');
    };
})();
    } catch (error) {
        // Page-specific block did not apply to this template.
    }
})();

// Source: general-order-items.twig
(function () {
    try {
(function () {
    var list = document.getElementById('sortable-list');
    var resetBtn = document.getElementById('reset-btn');
    var changedCount = document.getElementById('changed-count');
    var noChangesLabel = document.getElementById('no-changes-label');
    var toast = document.getElementById('toast');
    var originalOrder = Array.from(list.querySelectorAll('.item-row')).map(function (row) { return row.dataset.id; });

    Sortable.create(list, {
        handle: '.drag-handle',
        animation: 150,
        ghostClass: 'sortable-ghost',
        onEnd: function () {
            refresh();
            updateDirtyState();
        }
    });

    list.addEventListener('click', function (e) {
        var btn = e.target.closest('.move-up, .move-down');
        if (!btn) return;
        var row = btn.closest('.item-row');
        var rows = Array.from(list.querySelectorAll('.item-row'));
        var index = rows.indexOf(row);
        if (btn.classList.contains('move-up') && index > 0) list.insertBefore(row, rows[index - 1]);
        if (btn.classList.contains('move-down') && index < rows.length - 1) list.insertBefore(rows[index + 1], row);
        refresh();
        updateDirtyState();
    });

    function refresh() {
        Array.from(list.querySelectorAll('.item-row')).forEach(function (row, index) {
            var badge = row.querySelector('.position-badge');
            badge.textContent = index + 1;
            badge.classList.toggle('position-changed', originalOrder.indexOf(row.dataset.id) !== index);
        });
    }

    function updateDirtyState() {
        var current = Array.from(list.querySelectorAll('.item-row')).map(function (row) { return row.dataset.id; });
        var moved = current.filter(function (id, index) { return id !== originalOrder[index]; }).length;
        resetBtn.classList.toggle('hidden', moved === 0);
        changedCount.classList.toggle('hidden', moved === 0);
        noChangesLabel.classList.toggle('hidden', moved > 0);
        changedCount.textContent = moved + ' item' + (moved === 1 ? '' : 's') + ' moved';
    }

    resetBtn.addEventListener('click', function () {
        var rowMap = {};
        Array.from(list.querySelectorAll('.item-row')).forEach(function (row) { rowMap[row.dataset.id] = row; });
        originalOrder.forEach(function (id) { list.appendChild(rowMap[id]); });
        refresh();
        updateDirtyState();
        showToast('Order reset.');
    });

    document.getElementById('save-btn').addEventListener('click', function () {
        originalOrder = Array.from(list.querySelectorAll('.item-row')).map(function (row) { return row.dataset.id; });
        refresh();
        updateDirtyState();
        showToast('General item order saved successfully.');
    });

    function showToast(message) {
        toast.className = 'rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-medium text-emerald-800 shadow-sm';
        toast.textContent = message;
        setTimeout(function () { toast.classList.add('hidden'); }, 4000);
    }
})();
    } catch (error) {
        // Page-specific block did not apply to this template.
    }
})();

// Source: media-libraries.twig
(function () {
    try {
(function () {
    var selectAll = document.getElementById('select-all');
    var rowCbs = document.querySelectorAll('.row-cb');
    var selectedCount = document.getElementById('selected-count');
    var selectedNum = document.getElementById('selected-num');
    var search = document.getElementById('library-search');
    var status = document.getElementById('library-status');

    function updateSelectedCount() {
        var n = document.querySelectorAll('.row-cb:checked').length;
        selectedNum.textContent = n;
        selectedCount.classList.toggle('hidden', n === 0);
    }

    function filterRows() {
        var q = search.value.toLowerCase();
        var s = status.value;
        var visible = 0;
        document.querySelectorAll('#library-tbody tr').forEach(function (row) {
            var matchesSearch = !q || (row.dataset.name || '').indexOf(q) !== -1;
            var matchesStatus = !s || row.dataset.status === s;
            var show = matchesSearch && matchesStatus;
            row.style.display = show ? '' : 'none';
            if (show) visible++;
        });
        document.getElementById('row-count').textContent = 'Showing ' + visible + ' librar' + (visible === 1 ? 'y' : 'ies');
    }

    selectAll.addEventListener('change', function () {
        rowCbs.forEach(function (cb) { cb.checked = selectAll.checked; });
        updateSelectedCount();
    });
    rowCbs.forEach(function (cb) {
        cb.addEventListener('change', function () {
            selectAll.checked = Array.from(rowCbs).every(function (c) { return c.checked; });
            selectAll.indeterminate = !selectAll.checked && Array.from(rowCbs).some(function (c) { return c.checked; });
            updateSelectedCount();
        });
    });
    search.addEventListener('input', filterRows);
    status.addEventListener('change', filterRows);

    window.applyBulk = function () {
        var action = document.getElementById('bulk-action').value;
        var checked = Array.from(document.querySelectorAll('.row-cb:checked')).map(function (cb) { return cb.value; });
        if (!action) { alert('Please select an action.'); return; }
        if (!checked.length) { alert('Please select at least one library.'); return; }
        alert(action.charAt(0).toUpperCase() + action.slice(1) + ' ' + checked.length + ' librar' + (checked.length === 1 ? 'y' : 'ies') + ' (stub).');
    };
})();
    } catch (error) {
        // Page-specific block did not apply to this template.
    }
})();

// Source: reorder-articles.twig
(function () {
    try {
(function () {
    'use strict';

    // ── Configuration ────────────────────────────────────────────────
    var AJAX_URL = '/admin/articles/reorder'; // override with your actual endpoint
    var CSRF_TOKEN = document.querySelector('meta[name="csrf-token"]')
                     ? document.querySelector('meta[name="csrf-token"]').getAttribute('content')
                     : '';

    // ── DOM references ───────────────────────────────────────────────
    var list        = document.getElementById('sortable-list');
    var saveBtn     = document.getElementById('save-btn');
    var resetBtn    = document.getElementById('reset-btn');
    var saveLabel   = document.getElementById('save-label');
    var saveIcon    = document.getElementById('save-icon');
    var saveSpinner = document.getElementById('save-spinner');
    var toast       = document.getElementById('toast');
    var changedCount  = document.getElementById('changed-count');
    var noChangesLabel = document.getElementById('no-changes-label');

    // Capture original order for reset / dirty-checking
    var originalOrder = Array.from(list.querySelectorAll('.article-row'))
                             .map(function (el) { return el.dataset.id; });

    // ── Sortable init ────────────────────────────────────────────────
    Sortable.create(list, {
        handle: '.drag-handle',
        animation: 150,
        ghostClass: 'sortable-ghost',
        chosenClass: 'sortable-chosen',
        dragClass: 'sortable-drag',
        onEnd: function () {
            refreshPositionBadges();
            updateDirtyState();
        }
    });

    // ── Arrow button move up / down ──────────────────────────────────
    list.addEventListener('click', function (e) {
        var btn = e.target.closest('.move-up, .move-down');
        if (!btn) return;
        var row  = btn.closest('.article-row');
        var rows = Array.from(list.querySelectorAll('.article-row'));
        var idx  = rows.indexOf(row);

        if (btn.classList.contains('move-up') && idx > 0) {
            list.insertBefore(row, rows[idx - 1]);
        } else if (btn.classList.contains('move-down') && idx < rows.length - 1) {
            list.insertBefore(rows[idx + 1], row);
        }

        refreshPositionBadges();
        updateDirtyState();
    });

    // ── Refresh position numbers & highlight changed rows ────────────
    function refreshPositionBadges() {
        var rows = list.querySelectorAll('.article-row');
        rows.forEach(function (row, i) {
            var badge = row.querySelector('.position-badge');
            badge.textContent = i + 1;
            var originalPos = originalOrder.indexOf(row.dataset.id);
            if (originalPos !== i) {
                badge.classList.add('position-changed');
            } else {
                badge.classList.remove('position-changed');
            }
        });
    }

    // ── Dirty state: show/hide reset button and changed count ────────
    function updateDirtyState() {
        var current = Array.from(list.querySelectorAll('.article-row'))
                           .map(function (el) { return el.dataset.id; });
        var isDirty = current.some(function (id, i) { return id !== originalOrder[i]; });
        var movedCount = current.filter(function (id, i) { return id !== originalOrder[i]; }).length;

        resetBtn.classList.toggle('hidden', !isDirty);

        if (isDirty) {
            changedCount.textContent = movedCount + ' row' + (movedCount !== 1 ? 's' : '') + ' moved';
            changedCount.classList.remove('hidden');
            noChangesLabel.classList.add('hidden');
        } else {
            changedCount.classList.add('hidden');
            noChangesLabel.classList.remove('hidden');
        }
    }

    // ── Reset to original order ──────────────────────────────────────
    resetBtn.addEventListener('click', function () {
        var rows = list.querySelectorAll('.article-row');
        var rowMap = {};
        rows.forEach(function (row) { rowMap[row.dataset.id] = row; });

        originalOrder.forEach(function (id) {
            list.appendChild(rowMap[id]);
        });

        refreshPositionBadges();
        updateDirtyState();
        showToast('info', 'Order reset to original.');
    });

    // ── Save: collect order and POST via fetch ───────────────────────
    saveBtn.addEventListener('click', function () {
        var payload = Array.from(list.querySelectorAll('.article-row'))
                          .map(function (row, i) {
                              return { id: parseInt(row.dataset.id, 10), sort_order: i + 1 };
                          });

        setLoading(true);

        fetch(AJAX_URL, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Accept':       'application/json',
                'X-CSRF-TOKEN': CSRF_TOKEN,
                'X-Requested-With': 'XMLHttpRequest'
            },
            body: JSON.stringify({ articles: payload })
        })
        .then(function (res) {
            if (!res.ok) {
                return res.json().then(function (data) {
                    throw new Error(data.message || 'Server error ' + res.status);
                });
            }
            return res.json();
        })
        .then(function () {
            // Update baseline so dirty-check resets
            originalOrder = Array.from(list.querySelectorAll('.article-row'))
                                 .map(function (el) { return el.dataset.id; });
            refreshPositionBadges();
            updateDirtyState();
            showToast('success', 'Article order saved successfully.');
        })
        .catch(function (err) {
            showToast('error', err.message || 'Something went wrong. Please try again.');
        })
        .finally(function () {
            setLoading(false);
        });
    });

    // ── Loading state ────────────────────────────────────────────────
    function setLoading(on) {
        saveBtn.disabled = on;
        saveLabel.textContent = on ? 'Saving…' : 'Save Order';
        saveIcon.classList.toggle('hidden', on);
        saveSpinner.classList.toggle('hidden', !on);
    }

    // ── Toast helper ─────────────────────────────────────────────────
    var toastTimer;
    function showToast(type, message) {
        clearTimeout(toastTimer);

        var colours = {
            success: 'bg-emerald-50 border-emerald-200 text-emerald-800',
            error:   'bg-red-50 border-red-200 text-red-800',
            info:    'bg-slate-50 border-slate-200 text-slate-700'
        };
        var icons = {
            success: '<path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/>',
            error:   '<path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/>',
            info:    '<path stroke-linecap="round" stroke-linejoin="round" d="M13 16h-1v-4h-1m1-4h.01"/>'
        };

        toast.className = 'flex items-start gap-3 rounded-lg border px-4 py-3 text-sm font-medium shadow-sm ' + colours[type];
        toast.innerHTML =
            '<svg class="mt-0.5 h-4 w-4 shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">' + icons[type] + '</svg>' +
            '<span>' + message + '</span>' +
            '<button type="button" class="ml-auto shrink-0 opacity-60 hover:opacity-100" onclick="this.parentElement.classList.add(\'hidden\')" aria-label="Dismiss">' +
            '<svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg>' +
            '</button>';

        toast.classList.remove('hidden');
        toastTimer = setTimeout(function () { toast.classList.add('hidden'); }, 5000);
    }

})();
    } catch (error) {
        // Page-specific block did not apply to this template.
    }
})();

// Source: user-roles.twig
(function () {
    try {
// Row toggle: check/uncheck all permission checkboxes for a given resource
    function toggleRow(masterCheckbox, resource) {
        const checked = masterCheckbox.checked;
        document.querySelectorAll('input[name^="perm[' + resource + ']"]').forEach(function(cb) {
            cb.checked = checked;
        });
    }

    // Select All toggle: check/uncheck every permission checkbox in the table
    function toggleAllPermissions(btn) {
        const allBoxes = document.querySelectorAll('input[name^="perm["]');
        const rowBoxes = document.querySelectorAll('input[data-row]');
        const anyUnchecked = Array.from(allBoxes).some(function(cb) { return !cb.checked; });
        allBoxes.forEach(function(cb) { cb.checked = anyUnchecked; });
        rowBoxes.forEach(function(cb) { cb.checked = anyUnchecked; });
        btn.textContent = anyUnchecked ? 'Deselect All' : 'Select All';
    }

    // Auto-generate slug from role name
    document.getElementById('role_name').addEventListener('input', function() {
        var slug = this.value
            .toLowerCase()
            .trim()
            .replace(/[^a-z0-9\s-]/g, '')
            .replace(/\s+/g, '-')
            .replace(/-+/g, '-');
        document.getElementById('role_slug').value = slug;
    });
    } catch (error) {
        // Page-specific block did not apply to this template.
    }
})();

// Source: users.twig
(function () {
    try {
(function () {
    var search = document.getElementById('user-search');
    var role = document.getElementById('role-filter');
    var selectAll = document.getElementById('select-all');
    var rowCbs = document.querySelectorAll('.row-cb');

    function filterRows() {
        var q = search.value.toLowerCase();
        var r = role.value;
        var visible = 0;
        document.querySelectorAll('#user-tbody tr').forEach(function (row) {
            var show = (!q || (row.dataset.name || '').indexOf(q) !== -1) && (!r || row.dataset.role === r);
            row.style.display = show ? '' : 'none';
            if (show) visible++;
        });
        document.getElementById('row-count').textContent = 'Showing ' + visible + ' user' + (visible === 1 ? '' : 's');
    }

    search.addEventListener('input', filterRows);
    role.addEventListener('change', filterRows);
    selectAll.addEventListener('change', function () {
        rowCbs.forEach(function (cb) { cb.checked = selectAll.checked; });
    });

    window.applyBulk = function () {
        var action = document.getElementById('bulk-action').value;
        var checked = Array.from(document.querySelectorAll('.row-cb:checked'));
        if (!action) { alert('Please select an action.'); return; }
        if (!checked.length) { alert('Please select at least one user.'); return; }
        alert(action.charAt(0).toUpperCase() + action.slice(1) + ' ' + checked.length + ' user' + (checked.length === 1 ? '' : 's') + ' (stub).');
    };
})();
    } catch (error) {
        // Page-specific block did not apply to this template.
    }
})();
