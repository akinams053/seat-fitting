/* global $, jQuery, Sortable */

function dEscape(value) {
    return $('<div>').text(value == null ? '' : value).html();
}

function dI18n(key) {
    return (window.doctrineI18n || {})[key] || key;
}

const DoctrineState = {
    groups: [],
    pool: [],
    planPool: [],
    sortables: [],
};

function initializeDoctrineWorkspace() {
    if (!$('#doctrineGroupList').length) return;
    loadDoctrineWorkspace();

    $(document).on('click', '#createDoctrineBtn', function () {
        const name = window.prompt(dI18n('workspaceNewGroupPlaceholder'));
        if (!name || !name.trim()) return;
        $.ajax({
            url: '/fitting/doctrine',
            type: 'POST',
            data: {_token: window.doctrineCsrf, name: name.trim()},
            dataType: 'json',
            timeout: 10000,
        }).done(loadDoctrineWorkspace);
    });

    $(document).on('input', '#doctrinePoolSearch', function () {
        filterPool($(this).val());
    });

    $(document).on('click', '.doctrine-group-rename', function () {
        const groupId = $(this).closest('.doctrine-group').data('groupId');
        const current = $(this).closest('.doctrine-group').find('.doctrine-group-header-title').text();
        const name = window.prompt(dI18n('workspaceRenameBtn'), current);
        if (!name || !name.trim()) return;
        $.ajax({
            url: '/fitting/doctrine/' + groupId,
            type: 'POST',
            data: {_token: window.doctrineCsrf, _method: 'PATCH', name: name.trim()},
            dataType: 'json',
            timeout: 10000,
        }).done(loadDoctrineWorkspace);
    });

    $(document).on('click', '.doctrine-group-delete', function () {
        const groupId = $(this).closest('.doctrine-group').data('groupId');
        if (!window.confirm(dI18n('deleteDoctrineConfirm'))) return;
        $.ajax({
            url: '/fitting/doctrine/' + groupId,
            type: 'POST',
            data: {_token: window.doctrineCsrf, _method: 'DELETE'},
            dataType: 'json',
            timeout: 10000,
        }).done(loadDoctrineWorkspace);
    });

    $(document).on('click', '.fit-card-remove', function (evt) {
        evt.stopPropagation();
        const groupId = $(this).closest('.doctrine-group').data('groupId');
        const fittingId = $(this).closest('.fit-card').data('fittingId');
        if (!groupId || !fittingId) return;
        $.ajax({
            url: '/fitting/doctrine/' + groupId + '/fittings/' + fittingId,
            type: 'POST',
            data: {_token: window.doctrineCsrf, _method: 'DELETE'},
            dataType: 'json',
            timeout: 10000,
        }).done(loadDoctrineWorkspace);
    });

    $(document).on('click', '.plan-card-remove', function (evt) {
        evt.stopPropagation();
        const groupId = $(this).closest('.doctrine-group').data('groupId');
        const planId = $(this).closest('.plan-card-attached').data('planId');
        if (!groupId || !planId) return;
        $.ajax({
            url: '/fitting/plans/' + planId + '/doctrines/' + groupId,
            type: 'POST',
            data: {_token: window.doctrineCsrf, _method: 'DELETE'},
            dataType: 'json',
            timeout: 10000,
        }).done(loadDoctrineWorkspace);
    });
}

function loadDoctrineWorkspace() {
    $.ajax({
        url: '/fitting/doctrine-workspace',
        type: 'GET',
        dataType: 'json',
        timeout: 15000,
    }).done(function (data) {
        DoctrineState.groups = data.groups || [];
        DoctrineState.pool = data.pool || [];
        DoctrineState.planPool = data.planPool || [];
        renderGroups();
        renderPool();
        renderPlanPool();
        wireSortables();
    });
}

function renderGroups() {
    const container = $('#doctrineGroupList');
    container.empty();

    if (!DoctrineState.groups.length) {
        container.append(`<div class="fit-tree-empty">${dEscape(dI18n('workspaceGroupsEmptyHint'))}</div>`);
        return;
    }

    for (const group of DoctrineState.groups) {
        const actions = window.doctrineI18n.canCreate
            ? `<button class="btn btn-xs btn-link text-muted doctrine-group-rename" title="${dEscape(dI18n('workspaceRenameBtn'))}"><i class="fa fa-pen"></i></button>
               <button class="btn btn-xs btn-link text-danger doctrine-group-delete" title="${dEscape(dI18n('deleteDoctrineBtn'))}"><i class="fa fa-trash"></i></button>`
            : '';

        const items = group.fittings.map(g => fitCardHtml(g, true)).join('');
        const emptyHint = `<div class="doctrine-group-empty">${dEscape(dI18n('workspaceGroupEmptyHint'))}</div>`;

        const plans = (group.plans || []).map(p => planAttachedCardHtml(p)).join('');
        const plansEmpty = `<div class="doctrine-group-empty">${dEscape(dI18n('workspaceGroupPlansEmpty'))}</div>`;

        container.append(`<div class="doctrine-group" data-group-id="${group.id}">
            <div class="doctrine-group-header">
                <span class="doctrine-group-header-title">${dEscape(group.name)}</span>
                <span class="doctrine-group-count">(${group.fittings.length})</span>
                ${actions}
            </div>
            <div class="doctrine-group-body" data-group-id="${group.id}">
                ${items || emptyHint}
            </div>
            <div class="doctrine-group-plans-body" data-group-id="${group.id}">
                ${plans || plansEmpty}
            </div>
        </div>`);
    }
}

function renderPool() {
    const pool = $('#doctrinePool');
    pool.empty();

    if (!DoctrineState.pool.length) {
        pool.append(`<div class="fit-tree-empty">${dEscape(dI18n('workspacePoolEmptyHint'))}</div>`);
        return;
    }

    for (const fit of DoctrineState.pool) {
        pool.append(fitCardHtml(fit, false));
    }
}

function renderPlanPool() {
    const pool = $('#doctrinePlanPool');
    pool.empty();

    if (!DoctrineState.planPool.length) {
        pool.append(`<div class="fit-tree-empty">${dEscape(dI18n('workspacePlanPoolEmpty'))}</div>`);
        return;
    }

    for (const plan of DoctrineState.planPool) {
        pool.append(planPoolCardHtml(plan));
    }
}

function fitCardHtml(fit, inGroup) {
    const iconUrl = `https://images.evetech.net/types/${fit.typeID}/icon?size=32`;
    const removeBtn = inGroup
        ? `<button class="fit-card-remove" type="button" title="${dEscape(dI18n('workspaceRemoveFitBtn'))}"><i class="fa fa-times"></i></button>`
        : '';
    return `<div class="fit-card" data-fitting-id="${fit.id}">
        <img class="fit-card-icon" src="${iconUrl}" alt="">
        <div class="fit-card-body">
            <div class="fit-card-name">${dEscape(fit.name)}</div>
            <div class="fit-card-ship">${dEscape(fit.shipType || '')}</div>
        </div>
        ${removeBtn}
    </div>`;
}

function planPoolCardHtml(plan) {
    const tierLabel = plan.tier === 'advanced' ? dI18n('tierAdvanced') : dI18n('tierEntry');
    const tierClass = plan.tier === 'advanced' ? 'plan-tier-advanced' : 'plan-tier-minimum';
    return `<div class="plan-pool-card" data-plan-id="${plan.id}" data-tier="${plan.tier}">
        <span class="plan-card-grip"><i class="fa fa-grip-vertical"></i></span>
        <span class="plan-pool-card-name">${dEscape(plan.name)}</span>
        <span class="plan-card-tier ${tierClass}">${dEscape(tierLabel)}</span>
    </div>`;
}

function planAttachedCardHtml(plan) {
    const tierLabel = plan.tier === 'advanced' ? dI18n('tierAdvanced') : dI18n('tierEntry');
    const tierClass = plan.tier === 'advanced' ? 'plan-tier-advanced' : 'plan-tier-minimum';
    return `<div class="plan-card-attached" data-plan-id="${plan.id}">
        <span class="plan-pool-card-name">${dEscape(plan.name)}</span>
        <span class="plan-card-tier ${tierClass}">${dEscape(tierLabel)}</span>
        <button type="button" class="plan-card-remove" title="${dEscape(dI18n('workspaceRemovePlanBtn'))}"><i class="fa fa-times"></i></button>
    </div>`;
}

function wireSortables() {
    /* Destroy old sortable instances */
    for (const s of DoctrineState.sortables) {
        try { s.destroy(); } catch (e) { /* ignore */ }
    }
    DoctrineState.sortables = [];

    if (!window.doctrineI18n.canCreate) return;

    /* Fittings pool — items can be cloned/pulled into group fittings bodies. */
    const pool = document.getElementById('doctrinePool');
    if (pool) {
        DoctrineState.sortables.push(new Sortable(pool, {
            group: {name: 'fittings', pull: 'clone', put: false},
            sort: false,
            animation: 180,
            ghostClass: 'sortable-ghost',
            chosenClass: 'sortable-chosen',
            onClone: function (evt) { evt.item.style.display = ''; },
        }));
    }

    /* Plans pool — items can be cloned/pulled into group plans bodies. */
    const planPool = document.getElementById('doctrinePlanPool');
    if (planPool) {
        DoctrineState.sortables.push(new Sortable(planPool, {
            group: {name: 'doctrine-plans', pull: 'clone', put: false},
            sort: false,
            animation: 180,
            ghostClass: 'sortable-ghost',
            chosenClass: 'sortable-chosen',
            onClone: function (evt) { evt.item.style.display = ''; },
        }));
    }

    $('.doctrine-group-body').each(function () {
        const el = this;
        DoctrineState.sortables.push(new Sortable(el, {
            group: {name: 'fittings', pull: false, put: true},
            animation: 180,
            ghostClass: 'sortable-ghost',
            chosenClass: 'sortable-chosen',
            onAdd: function (evt) {
                const groupId = $(el).data('groupId');
                const fittingId = $(evt.item).data('fittingId');
                if (!groupId || !fittingId) {
                    evt.item.parentNode && evt.item.parentNode.removeChild(evt.item);
                    return;
                }
                const existing = $(el).find(`.fit-card[data-fitting-id="${fittingId}"]`);
                if (existing.length > 1) {
                    evt.item.parentNode && evt.item.parentNode.removeChild(evt.item);
                    return;
                }

                $.ajax({
                    url: '/fitting/doctrine/' + groupId + '/fittings/' + fittingId,
                    type: 'POST',
                    data: {_token: window.doctrineCsrf},
                    dataType: 'json',
                    timeout: 10000,
                }).done(loadDoctrineWorkspace).fail(loadDoctrineWorkspace);
            },
        }));
    });

    $('.doctrine-group-plans-body').each(function () {
        const el = this;
        DoctrineState.sortables.push(new Sortable(el, {
            group: {name: 'doctrine-plans', pull: false, put: true},
            animation: 180,
            ghostClass: 'sortable-ghost',
            chosenClass: 'sortable-chosen',
            onAdd: function (evt) {
                const groupId = $(el).data('groupId');
                const planId = $(evt.item).data('planId');
                if (!groupId || !planId) {
                    evt.item.parentNode && evt.item.parentNode.removeChild(evt.item);
                    return;
                }
                /* Dedup: if this plan is already attached, drop the clone. */
                const existing = $(el).find(`.plan-card-attached[data-plan-id="${planId}"]`);
                if (existing.length > 0) {
                    evt.item.parentNode && evt.item.parentNode.removeChild(evt.item);
                    return;
                }

                $.ajax({
                    url: '/fitting/plans/' + planId + '/doctrines/' + groupId,
                    type: 'POST',
                    data: {_token: window.doctrineCsrf},
                    dataType: 'json',
                    timeout: 10000,
                }).done(loadDoctrineWorkspace).fail(loadDoctrineWorkspace);
            },
        }));
    });
}

function filterPool(query) {
    const q = (query || '').toLowerCase().trim();
    $('#doctrinePool .fit-card').each(function () {
        const name = $(this).find('.fit-card-name').text().toLowerCase();
        const ship = $(this).find('.fit-card-ship').text().toLowerCase();
        const visible = !q || name.indexOf(q) !== -1 || ship.indexOf(q) !== -1;
        $(this).toggle(visible);
    });
}
