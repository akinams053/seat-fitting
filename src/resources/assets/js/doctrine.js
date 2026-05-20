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
        renderGroups();
        renderPool();
        wireSortables();
    });
}

function renderGroups() {
    const container = $('#doctrineGroupList');
    container.empty();

    if (!DoctrineState.groups.length) {
        container.append(`<div class="fit-tree-empty">${dEscape(dI18n('workspacePoolEmptyHint'))}</div>`);
        return;
    }

    for (const group of DoctrineState.groups) {
        const actions = window.doctrineI18n.canCreate
            ? `<button class="btn btn-xs btn-link text-muted doctrine-group-rename" title="${dEscape(dI18n('workspaceRenameBtn'))}"><i class="fa fa-pen"></i></button>
               <button class="btn btn-xs btn-link text-danger doctrine-group-delete" title="${dEscape(dI18n('deleteDoctrineBtn'))}"><i class="fa fa-trash"></i></button>`
            : '';

        const items = group.fittings.map(fitCardHtml).join('');
        const emptyHint = `<div class="doctrine-group-empty">${dEscape(dI18n('workspaceGroupEmptyHint'))}</div>`;

        container.append(`<div class="doctrine-group" data-group-id="${group.id}">
            <div class="doctrine-group-header">
                <span class="doctrine-group-header-title">${dEscape(group.name)}</span>
                <span class="doctrine-group-count">(${group.fittings.length})</span>
                ${actions}
            </div>
            <div class="doctrine-group-body" data-group-id="${group.id}">
                ${items || emptyHint}
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
        pool.append(fitCardHtml(fit));
    }
}

function fitCardHtml(fit) {
    const iconUrl = `https://images.evetech.net/types/${fit.typeID}/icon?size=32`;
    return `<div class="fit-card" data-fitting-id="${fit.id}">
        <img class="fit-card-icon" src="${iconUrl}" alt="">
        <div class="fit-card-body">
            <div class="fit-card-name">${dEscape(fit.name)}</div>
            <div class="fit-card-ship">${dEscape(fit.shipType || '')}</div>
        </div>
        <button class="fit-card-remove" type="button" title="Remove"><i class="fa fa-times"></i></button>
    </div>`;
}

function wireSortables() {
    /* Destroy old sortable instances */
    for (const s of DoctrineState.sortables) {
        try { s.destroy(); } catch (e) { /* ignore */ }
    }
    DoctrineState.sortables = [];

    /* Pool — items can be cloned/pulled into groups; new items cannot be dropped here. */
    const pool = document.getElementById('doctrinePool');
    if (pool && window.doctrineI18n.canCreate) {
        DoctrineState.sortables.push(new Sortable(pool, {
            group: {name: 'fittings', pull: 'clone', put: false},
            sort: false,
            animation: 180,
            ghostClass: 'sortable-ghost',
            chosenClass: 'sortable-chosen',
            onClone: function (evt) {
                /* keep DOM card visible in the pool: replace the dragged ghost with its clone */
                evt.item.style.display = '';
            },
        }));
    }

    /* Each group body accepts drops from the pool, and rejects duplicates. */
    $('.doctrine-group-body').each(function () {
        const el = this;
        if (!window.doctrineI18n.canCreate) return;
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
                /* Duplicate guard: if the group already contains this fitting, remove the dropped clone. */
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
