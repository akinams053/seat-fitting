/* global $, jQuery */

function escapeHtml(value) {
    return $('<div>').text(value == null ? '' : value).html();
}

function fitI18n(key) {
    return (window.fittingI18n || {})[key] || key;
}

/* ============================================================
 *  State container — keeps the most recent fetched payloads so
 *  switching character / tab / fitting does not re-hit the server.
 * ============================================================ */
const FittingState = {
    treeGroups: [],
    selectedFittingId: null,
    selectedFittingName: '',
    selectedShipName: '',
    skillsPayload: null,
    activeTier: 'minimum',
};

/* ============================================================
 *  Status computation — shared by personal check & corp report.
 * ============================================================ */
function computeFittingStatus(check) {
    if (!check) return 'failed';
    const minimum = !!check.minimum;
    const advanced = check.advanced;
    if (!minimum) return 'failed';
    if (advanced === true) return 'advanced';
    if (advanced === null || advanced === undefined) return 'entry';
    return 'entry';
}

function statusPillHtml(status, options) {
    options = options || {};
    const sizeClass = options.large ? 'is-lg' : '';
    const i18n = window.fittingI18n || {};
    let cls = 'is-not-set';
    let label = i18n.statusAdvancedNotSet || '';
    if (status === 'failed') {
        cls = 'is-failed';
        label = i18n.statusFailed || 'Not Met';
    } else if (status === 'entry') {
        cls = 'is-entry';
        label = i18n.statusEntry || 'Baseline Met';
    } else if (status === 'advanced') {
        cls = 'is-advanced';
        label = i18n.statusAdvanced || 'Advanced Met';
    }
    return `<span class="status-pill ${cls} ${sizeClass}">${escapeHtml(label)}</span>`;
}

/* ============================================================
 *  Fitting tree (left column)
 * ============================================================ */
function initializeFittingPage() {
    if ($('#fitTree').length === 0) return;
    loadFittingTree();

    $(document).on('input', '#fitTreeSearch', function () {
        filterFitTree($(this).val());
    });

    $(document).on('click', '.fit-tree-group-header', function (evt) {
        if ($(evt.target).closest('.fit-tree-group-action').length) return;
        const group = $(this).closest('.fit-tree-group');
        group.attr('data-expanded', group.attr('data-expanded') === 'true' ? 'false' : 'true');
    });

    $(document).on('click', '.fit-tree-group-action[data-action="check-group"]', function (evt) {
        evt.stopPropagation();
        const doctrineId = $(this).data('doctrineId');
        if (!doctrineId) return;
        runGroupCheck(doctrineId);
    });

    $(document).on('click', '.fit-tree-item', function (evt) {
        if ($(evt.target).closest('.fit-tree-item-actions').length) return;
        const id = $(this).data('id');
        const name = $(this).data('name');
        const ship = $(this).data('ship');
        selectFitting(id, name, ship);
    });

    $(document).on('click', '.fit-tree-item-action-edit', function (evt) {
        evt.stopPropagation();
        const id = $(this).closest('.fit-tree-item').data('id');
        $('#fitEditModal').modal('show');
        $('#fitSelection').val(id);
        $.ajax({
            url: '/fitting/geteftfittingbyid/' + id,
            type: 'GET',
            timeout: 10000,
        }).done(function (result) {
            $('textarea#eftfitting').val(result);
        });
    });

    $(document).on('click', '.fit-tree-item-action-delete', function (evt) {
        evt.stopPropagation();
        const id = $(this).closest('.fit-tree-item').data('id');
        $('#fitConfirmModal').modal('show');
        $('#fitSelection').val(id);
    });

    $(document).on('click', '.fit-tree-item-action-rename', function (evt) {
        evt.stopPropagation();
        const item = $(this).closest('.fit-tree-item');
        $('#fitRenameId').val(item.data('id'));
        $('#fitRenameName').val(item.data('name'));
        $('#fitRenameModal').modal('show');
    });

    $(document).on('click', '#fitRenameSaveBtn', function () {
        const id = $('#fitRenameId').val();
        const name = ($('#fitRenameName').val() || '').trim();
        if (!id || !name) return;
        $.ajax({
            url: '/fitting/fittings/' + id,
            type: 'POST',
            dataType: 'json',
            data: {_token: window.fittingCsrf, _method: 'PATCH', name: name},
            timeout: 10000,
        }).done(function () {
            $('#fitRenameModal').modal('hide');
            loadFittingTree();
            if (parseInt(FittingState.selectedFittingId) === parseInt(id)) {
                FittingState.selectedFittingName = name;
                loadFittingDetails(id);
            }
        });
    });

    $(document).on('click', '.fit-tree-item-action-copy', function (evt) {
        evt.stopPropagation();
        const id = $(this).closest('.fit-tree-item').data('id');
        $.ajax({
            url: '/fitting/fittings/' + id + '/copy',
            type: 'POST',
            dataType: 'json',
            data: {_token: window.fittingCsrf},
            timeout: 15000,
        }).done(function (newFit) {
            loadFittingTree();
            if (newFit && newFit.id) {
                /* select the new copy so the user can immediately rename / edit */
                setTimeout(() => selectFitting(newFit.id, newFit.name, ''), 200);
            }
        });
    });

    /* Detail slot expand/collapse */
    $(document).on('click', '.fit-detail-slot-header', function () {
        const slot = $(this).closest('.fit-detail-slot');
        slot.attr('data-expanded', slot.attr('data-expanded') === 'true' ? 'false' : 'true');
    });

    /* Tabs */
    $(document).on('click', '.skill-check-tab', function () {
        const tier = $(this).data('tier');
        if (!tier) return;
        $('.skill-check-tab:not(.req-tab)').removeClass('is-active');
        $(this).addClass('is-active');
        FittingState.activeTier = tier;
        renderActiveSkillTab();
    });

    $(document).on('click', '.req-tab', function () {
        const tier = $(this).data('tier');
        $('.req-tab').removeClass('is-active');
        $(this).addClass('is-active');
        $('.req-tier-panel').hide();
        $('.req-tier-panel[data-tier="' + tier + '"]').show();
    });

    $(document).on('change', '#characterSpinner', function () {
        if (FittingState.skillsPayload) renderSkillCheck(FittingState.skillsPayload);
    });

    /* Skill group expand */
    $(document).on('click', '.skill-group-header', function () {
        const group = $(this).closest('.skill-group');
        group.attr('data-expanded', group.attr('data-expanded') === 'true' ? 'false' : 'true');
    });

    /* Requirement editor */
    $(document).on('change', '.skillGroupSelect', function () {
        resetRequirementSkillSelect($(this).data('tier'));
    });
    $(document).on('click', '.addRequirement', function () {
        addRequirementToEditor($(this).data('tier'));
    });
    $(document).on('click', '.removeRequirement', function () {
        $(this).closest('tr').remove();
    });
    $(document).on('click', '#saveRequirements', saveRequirements);

    $('#addFitting').on('click', function () {
        $('#fitEditModal').modal('show');
        $('#fitSelection').val('0');
        $('textarea#eftfitting').val('');
    });

    /* Delete confirm inside modal */
    $('#deleteConfirm').on('click', function () {
        const id = $('#fitSelection').val();
        if (!id || id === '0') return;
        $.ajax({
            url: '/fitting/delfittingbyid/' + id,
            type: 'GET',
            timeout: 10000,
        }).done(function () {
            loadFittingTree();
            if (FittingState.selectedFittingId === parseInt(id)) {
                FittingState.selectedFittingId = null;
                $('#skills-box').hide();
                $('#requirements-box').hide();
                $('#fitDetail').hide();
                $('#fitDetailEmpty').show();
            }
        });
    });

    if (window.fittingManageMode) {
        initializeRequirementSelectors();
    }

    /* Export-missing button — find the cached skill list for this panel,
       look up the active character, build the plain-English plan text, open the modal. */
    $(document).on('click', '.export-missing-btn', function () {
        const exportId = $(this).closest('.training-time-summary').data('exportId');
        const skills = exportId ? (ExportCache.map[exportId] || []) : [];
        const characterId = $('#characterSpinner').val();
        const character = FittingState.skillsPayload
            ? (FittingState.skillsPayload.characters || {})[characterId]
            : null;
        const text = buildExportText(skills, character);
        $('#exportMissingTextarea').val(text || (fitI18n('exportMissingEmpty') || ''));
        $('#exportMissingModal').modal('show');
    });

    $(document).on('click', '#exportMissingCopyBtn', function () {
        const ta = document.getElementById('exportMissingTextarea');
        if (!ta) return;
        ta.focus();
        ta.select();
        try {
            document.execCommand('copy');
            const btn = $(this);
            const original = btn.html();
            btn.html('<i class="fa fa-check"></i> ' + escapeHtml(fitI18n('exportCopyDone') || 'Copied'));
            setTimeout(() => btn.html(original), 1200);
        } catch (e) { /* ignore */ }
    });
}

function loadFittingTree() {
    $.ajax({
        url: '/fitting/tree',
        type: 'GET',
        dataType: 'json',
        timeout: 15000,
    }).done(function (groups) {
        FittingState.treeGroups = groups || [];
        renderFitTree();
    });
}

function renderFitTree() {
    const container = $('#fitTree');
    container.empty();

    if (!FittingState.treeGroups.length) {
        container.append(`<div class="fit-tree-empty">${escapeHtml(fitI18n('treeEmptyHint'))}</div>`);
        return;
    }

    for (const group of FittingState.treeGroups) {
        const groupId = group.id;
        const isUngrouped = !groupId;
        const checkBtn = (!isUngrouped && group.fittings.length > 0)
            ? `<button type="button" class="fit-tree-group-action" data-action="check-group" data-doctrine-id="${groupId}">${escapeHtml(fitI18n('treeCheckGroupBtn'))}</button>`
            : '';

        const items = group.fittings.map(function (fit) {
            const iconUrl = `https://images.evetech.net/types/${fit.typeID}/icon?size=32`;
            const manageBtns = window.fittingManageMode
                ? `<button class="btn btn-xs btn-outline-secondary fit-tree-item-action-rename" title="${escapeHtml(fitI18n('renameFittingTooltip'))}"><i class="fa fa-i-cursor"></i></button>
                   <button class="btn btn-xs btn-outline-secondary fit-tree-item-action-copy" title="${escapeHtml(fitI18n('copyFittingTooltip'))}"><i class="fa fa-copy"></i></button>
                   <button class="btn btn-xs btn-warning fit-tree-item-action-edit" title="${escapeHtml(fitI18n('editFittingTooltip'))}"><i class="fas fa-edit"></i></button>
                   <button class="btn btn-xs btn-danger fit-tree-item-action-delete" title="${escapeHtml(fitI18n('deleteFittingTooltip'))}"><i class="fa fa-trash"></i></button>`
                : '';
            return `<div class="fit-tree-item" data-id="${fit.id}" data-name="${escapeHtml(fit.name)}" data-ship="${escapeHtml(fit.shipType)}">
                <img class="fit-tree-item-icon" src="${iconUrl}" alt="">
                <span class="fit-tree-item-name">${escapeHtml(fit.name)} <span class="fit-tree-item-ship">· ${escapeHtml(fit.shipType)}</span></span>
                <span class="fit-tree-item-actions">${manageBtns}</span>
            </div>`;
        }).join('');

        container.append(`<div class="fit-tree-group" data-group-id="${groupId || ''}" data-expanded="true">
            <div class="fit-tree-group-header">
                <span class="fit-tree-group-chevron">▶</span>
                <span class="fit-tree-group-title">${escapeHtml(group.name)}</span>
                <span class="fit-tree-group-count">(${group.fittings.length})</span>
                ${checkBtn}
            </div>
            <div class="fit-tree-group-body">${items}</div>
        </div>`);
    }
}

function filterFitTree(query) {
    const q = (query || '').toLowerCase().trim();
    $('.fit-tree-group').each(function () {
        const groupEl = $(this);
        let visible = 0;
        groupEl.find('.fit-tree-item').each(function () {
            const item = $(this);
            const text = (item.data('name') + ' ' + item.data('ship')).toLowerCase();
            const match = !q || text.indexOf(q) !== -1;
            item.toggle(match);
            if (match) visible++;
        });
        groupEl.toggle(visible > 0 || !q);
        if (q && visible > 0) groupEl.attr('data-expanded', 'true');
    });
}

function selectFitting(fittingId, fittingName, shipName) {
    if (!fittingId) return;
    FittingState.selectedFittingId = fittingId;
    FittingState.selectedFittingName = fittingName || '';
    FittingState.selectedShipName = shipName || '';

    $('.fit-tree-item').removeClass('is-selected');
    $(`.fit-tree-item[data-id="${fittingId}"]`).addClass('is-selected');

    $('#fittingId').val(fittingId);
    $('#requirementsFittingId').val(fittingId);

    loadFittingDetails(fittingId);
    loadSkillCheckForFitting(fittingId);

    if (window.fittingManageMode) {
        loadRequirementEditor(fittingId);
    }
}

/* ============================================================
 *  Fit details (left bottom)
 * ============================================================ */
function loadFittingDetails(fittingId) {
    $.ajax({
        url: '/fitting/getfittingbyid/' + fittingId,
        type: 'GET',
        dataType: 'json',
        timeout: 10000,
    }).done(renderFitDetails);
}

function renderFitDetails(result) {
    if (!result) return;
    $('#fitDetailEmpty').hide();
    $('#fitDetail').show().removeClass('fit-fade-in').addClass('fit-fade-in');
    $('#fitDetailTitle').text(`${result.shipname || ''} · ${result.fitname || ''}`);

    /* Clear all slots first */
    const slotIds = ['highSlots', 'midSlots', 'lowSlots', 'rigs', 'subSlots', 'drones', 'cargo'];
    for (const sid of slotIds) {
        $(`#${sid}Items`).empty();
        const slotEl = $(`.fit-detail-slot[data-slot="${sid}"]`);
        slotEl.attr('data-expanded', 'false');
        slotEl.find('.fit-detail-slot-count').text('');
    }

    const counts = Object.fromEntries(slotIds.map(s => [s, 0]));

    for (const slotType in result) {
        const slot = result[slotType];
        let slotId = null;
        if (slotType.startsWith('HiSlot')) slotId = 'highSlots';
        else if (slotType.startsWith('MedSlot')) slotId = 'midSlots';
        else if (slotType.startsWith('LoSlot')) slotId = 'lowSlots';
        else if (slotType.startsWith('RigSlot')) slotId = 'rigs';
        else if (slotType.startsWith('SubSlot')) slotId = 'subSlots';
        else if (slotType === 'dronebay') slotId = 'drones';
        else if (slotType === 'cargo') slotId = 'cargo';
        if (!slotId) continue;

        const iconBase = 'https://images.evetech.net/types';

        if (slotId === 'drones' || slotId === 'cargo') {
            for (const itemId in slot) {
                const item = slot[itemId];
                $(`#${slotId}Items`).append(`<div class="fit-detail-item">
                    <img src="${iconBase}/${itemId}/icon?size=32" alt="">
                    <span>${escapeHtml(item.name)}</span>
                    <span class="fit-detail-item-qty">x${item.qty}</span>
                </div>`);
                counts[slotId] += item.qty || 1;
            }
        } else {
            $(`#${slotId}Items`).append(`<div class="fit-detail-item">
                <img src="${iconBase}/${slot.id}/icon?size=32" alt="">
                <span>${escapeHtml(slot.name)}</span>
            </div>`);
            counts[slotId]++;
        }
    }

    /* Slots default to collapsed; user can click header to expand. Showing the count
       keeps the panel informative without taking vertical space. */
    for (const sid of slotIds) {
        const slotEl = $(`.fit-detail-slot[data-slot="${sid}"]`);
        if (counts[sid] > 0) {
            slotEl.attr('data-expanded', 'false');
            slotEl.find('.fit-detail-slot-count').text(`(${counts[sid]})`);
            slotEl.show();
        } else {
            slotEl.hide();
        }
    }
}

/* ============================================================
 *  Skill check (right column)
 * ============================================================ */
function loadSkillCheckForFitting(fittingId) {
    $.ajax({
        url: '/fitting/getskillsbyfitid/' + fittingId,
        type: 'GET',
        dataType: 'json',
        timeout: 15000,
    }).done(function (result) {
        FittingState.skillsPayload = result;
        renderSkillCheck(result);
    });
}

function runGroupCheck(doctrineId) {
    $.ajax({
        url: '/fitting/getskillsbydoctrineid/' + doctrineId,
        type: 'GET',
        dataType: 'json',
        timeout: 20000,
    }).done(function (result) {
        FittingState.skillsPayload = result;
        FittingState.selectedFittingId = null;
        $('.fit-tree-item').removeClass('is-selected');
        $('#fitDetail').hide();
        $('#fitDetailEmpty').show();
        /* Group check has no single target — wipe any lingering requirement editor state
           so the manage-mode save button can't accidentally overwrite the last-selected fit. */
        $('#fittingId').val('');
        $('#requirementsFittingId').val('');
        $('#requirements-box').hide();
        renderSkillCheck(result);
    });
}

function renderSkillCheck(result) {
    if (!result) return;
    clearExportCache();
    $('#skills-box').show();
    const spinner = $('#characterSpinner');
    if (!spinner.find('option').length) {
        spinner.empty();
        for (const cid in result.characters) {
            spinner.append(`<option value="${result.characters[cid].id}">${escapeHtml(result.characters[cid].name)}</option>`);
        }
    }
    if (result.fittings) {
        renderGroupSkillCheck(result);
    } else {
        renderSingleSkillCheck(result);
    }
}

function renderSingleSkillCheck(result) {
    $('#singleSkillView').show();
    $('#groupSkillResults').hide().empty();

    const characterId = $('#characterSpinner').val();
    const character = (result.characters || {})[characterId];
    const requirements = result.requirements || {minimum: result.skills || [], advanced: []};
    const minimumSkills = requirements.minimum || [];
    const advancedSkills = requirements.advanced || [];

    const minMet = character ? skillsAllMet(minimumSkills, character) : false;
    const advMet = advancedSkills.length ? (character ? skillsAllMet(advancedSkills, character) : false) : null;

    const status = computeFittingStatus({minimum: minMet, advanced: advMet});
    $('#skillCheckOverallStatus').html(statusPillHtml(status, {large: true}));

    /* Inline tier badges next to tab labels */
    renderTabBadge('minimum', minMet ? 'passed' : 'failed');
    if (advancedSkills.length === 0) {
        renderTabBadge('advanced', 'not-set');
    } else {
        renderTabBadge('advanced', advMet ? 'passed' : 'failed');
    }

    if (typeof renderAttachedPlans === 'function') {
        renderAttachedPlans(result.plans || []);
    }

    renderActiveSkillTab();
}

function renderActiveSkillTab() {
    const result = FittingState.skillsPayload;
    if (!result || result.fittings) return;
    const characterId = $('#characterSpinner').val();
    const character = (result.characters || {})[characterId];
    if (!character) {
        $('#skillTabContent').html(`<div class="text-muted">${escapeHtml(fitI18n('noCharacterSelected'))}</div>`);
        return;
    }
    const requirements = result.requirements || {minimum: result.skills || [], advanced: []};
    const tier = FittingState.activeTier;
    const list = (tier === 'advanced' ? requirements.advanced : requirements.minimum) || [];
    if (!list.length) {
        const empty = tier === 'advanced' ? fitI18n('noAdvancedRequirements') : '—';
        $('#skillTabContent').html(`<div class="text-muted">${escapeHtml(empty)}</div>`);
        return;
    }
    $('#skillTabContent').html(renderSkillRequirementPanel(list, character));
}

function renderTabBadge(tier, state) {
    const badge = $('#tabBadge' + tier.charAt(0).toUpperCase() + tier.slice(1));
    badge.removeClass('is-failed is-entry is-advanced is-not-set').show();
    const i18n = window.fittingI18n || {};
    if (state === 'passed') {
        /* Use is-entry (green) as the universal "tab passed" cue — both tiers should look
           identical when met. is-advanced is reserved for the overall fitting status pill. */
        badge.addClass('is-entry').text('✓');
    } else if (state === 'failed') {
        badge.addClass('is-failed').text('✕');
    } else {
        badge.addClass('is-not-set').text(i18n.statusAdvancedNotSet || '—');
    }
}

function renderGroupSkillCheck(result) {
    $('#singleSkillView').hide();
    /* Group check has no single fitting; clear the per-fitting attached-plans block
       so we don't show stale cards from a previously-selected single fitting. */
    $('#attachedPlansBlock').hide();
    const target = $('#groupSkillResults').show().empty();
    const characterId = $('#characterSpinner').val();
    const character = (result.characters || {})[characterId];

    if (!character) {
        target.html(`<div class="text-muted">${escapeHtml(fitI18n('noCharacterSelected'))}</div>`);
        $('#skillCheckOverallStatus').empty();
        return;
    }

    let allEntry = true;
    let anyAdvanced = false;
    let anyConfigured = false;

    for (const fitting of result.fittings || []) {
        const requirements = fitting.requirements || {minimum: [], advanced: []};
        const minimumSkills = requirements.minimum || [];
        const advancedSkills = requirements.advanced || [];
        const minMet = skillsAllMet(minimumSkills, character);
        const advMet = advancedSkills.length ? skillsAllMet(advancedSkills, character) : null;
        if (!minMet) allEntry = false;
        if (advMet === true) anyAdvanced = true;
        if (advancedSkills.length) anyConfigured = true;

        const status = computeFittingStatus({minimum: minMet, advanced: advMet});
        const headerTitle = `${escapeHtml(fitting.shipType || '')} · ${escapeHtml(fitting.name || '')}`;

        const tierKey = (advancedSkills.length && advMet === false) ? 'advanced'
            : (minMet ? (advancedSkills.length ? 'advanced' : 'minimum') : 'minimum');
        const visibleList = tierKey === 'advanced' ? advancedSkills : minimumSkills;

        const planChips = (fitting.plans || []).map(p => {
            const tierClass = p.tier === 'advanced' ? 'plan-tier-advanced' : 'plan-tier-minimum';
            const viaTag = p.via === 'doctrine' ? '(grp)' : '';
            return `<span class="plan-card-tier ${tierClass}" title="${escapeHtml(p.name)}">${escapeHtml(p.name)} ${viaTag}</span>`;
        }).join(' ');
        const plansLine = planChips
            ? `<div class="attached-plan-card-items mb-2">${planChips}</div>`
            : '';

        target.append(`<div class="card fit-card-flat mb-3 fit-fade-in">
            <div class="card-header skill-check-header">
                <h6 class="mb-0">${headerTitle}</h6>
                ${statusPillHtml(status, {large: false})}
            </div>
            <div class="card-body">
                ${plansLine}
                <div class="skill-check-tabs">
                    <button type="button" class="skill-check-tab group-tier-tab ${tierKey === 'minimum' ? 'is-active' : ''}" data-tier="minimum" data-fitting-id="${fitting.id}">${escapeHtml(fitI18n('tabEntry'))}</button>
                    <button type="button" class="skill-check-tab group-tier-tab ${tierKey === 'advanced' ? 'is-active' : ''}" data-tier="advanced" data-fitting-id="${fitting.id}">${escapeHtml(fitI18n('tabAdvanced'))}</button>
                </div>
                <div class="mt-3 group-tier-content" data-fitting-id="${fitting.id}">${renderSkillRequirementPanel(visibleList, character) || `<div class="text-muted">${escapeHtml(fitI18n('noAdvancedRequirements'))}</div>`}</div>
            </div>
        </div>`);
    }

    /* Group-level tab switching */
    target.off('click', '.group-tier-tab').on('click', '.group-tier-tab', function () {
        const fid = $(this).data('fittingId');
        const tier = $(this).data('tier');
        target.find(`.group-tier-tab[data-fitting-id="${fid}"]`).removeClass('is-active');
        $(this).addClass('is-active');
        const fitting = (result.fittings || []).find(f => f.id === fid);
        if (!fitting) return;
        const list = (tier === 'advanced' ? fitting.requirements.advanced : fitting.requirements.minimum) || [];
        const html = list.length
            ? renderSkillRequirementPanel(list, character)
            : `<div class="text-muted">${escapeHtml(fitI18n('noAdvancedRequirements'))}</div>`;
        target.find(`.group-tier-content[data-fitting-id="${fid}"]`).html(html);
    });

    const overall = !allEntry ? 'failed' : (anyConfigured && anyAdvanced ? 'advanced' : 'entry');
    $('#skillCheckOverallStatus').html(statusPillHtml(overall, {large: true}));
}

/* ============================================================
 *  Skill requirement panel (categories + bars)
 * ============================================================ */
function skillsAllMet(skills, character) {
    if (!skills || !skills.length || !character) return true;
    for (const skill of skills) {
        const cs = character.skill[skill.typeId] || {level: 0};
        if (parseInt(cs.level || 0) < parseInt(skill.level || 0)) return false;
    }
    return true;
}

/* Per-panel skill-list cache so the export button can recover the list it should iterate.
   Cleared whenever we re-render the whole skill check, capped per render so memory stays bounded. */
const ExportCache = {map: {}, nextId: 1};
function stashExportSkills(skills) {
    const id = 'ec' + (ExportCache.nextId++);
    ExportCache.map[id] = skills;
    return id;
}
function clearExportCache() {
    ExportCache.map = {};
}

function renderSkillRequirementPanel(skills, character) {
    if (!skills || !skills.length) return '';
    const summary = renderTrainingSummary(skills, character);
    const groups = groupSkillsByCategory(skills);
    return summary + groups.map(group => renderSkillGroup(group, character)).join('');
}

function renderTrainingSummary(skills, character) {
    if (!character) return '';
    let totalPoints = 0;
    let missingCount = 0;
    for (const skill of skills) {
        const cs = character.skill[skill.typeId] || {level: 0, rank: 1};
        const cur = parseInt(cs.level || 0);
        const req = parseInt(skill.level || 0);
        if (cur >= req) continue;
        const rank = parseInt(cs.rank || 1);
        const need = rank * 250 * Math.pow(5.66, req - 1);
        const have = cur > 0 ? rank * 250 * Math.pow(5.66, cur - 1) : 0;
        totalPoints += (need - have);
        missingCount++;
    }

    const exportId = stashExportSkills(skills);

    if (missingCount === 0) {
        return `<div class="training-time-summary" data-export-id="${exportId}">
            <strong>${escapeHtml(fitI18n('trainingTimeTitle'))}:</strong> 0
            <button type="button" class="btn btn-xs btn-outline-secondary export-missing-btn" disabled>
                <i class="fa fa-download"></i> ${escapeHtml(fitI18n('exportMissingBtn'))}
            </button>
        </div>`;
    }

    const timeStr = formatTime(totalPoints) || '';
    return `<div class="training-time-summary" data-export-id="${exportId}">
        <strong>${escapeHtml(fitI18n('trainingTimeTitle'))}:</strong> ${escapeHtml(timeStr)} <span class="text-muted">(${missingCount})</span>
        <button type="button" class="btn btn-xs btn-outline-secondary export-missing-btn">
            <i class="fa fa-download"></i> ${escapeHtml(fitI18n('exportMissingBtn'))}
        </button>
    </div>`;
}

function buildExportText(skills, character) {
    const lines = [];
    if (!character) return '';
    for (const skill of skills) {
        const cs = character.skill[skill.typeId] || {level: 0};
        const cur = parseInt(cs.level || 0);
        const req = parseInt(skill.level || 0);
        if (cur >= req) continue;
        for (let lvl = cur + 1; lvl <= req; lvl++) {
            lines.push(`${skill.typeName} ${lvl}`);
        }
    }
    return lines.join('\n');
}

function groupSkillsByCategory(skills) {
    const groups = {};
    for (const skill of skills) {
        const gid = skill.groupId || 'other';
        if (!groups[gid]) groups[gid] = {id: gid, name: skill.groupName || 'Other', skills: []};
        groups[gid].skills.push(skill);
    }
    return Object.values(groups).sort((a, b) => a.name.localeCompare(b.name));
}

function renderSkillGroup(group, character) {
    const rows = [];
    let hasMissing = false;
    group.skills.sort((a, b) => a.typeName.localeCompare(b.typeName));
    for (const skill of group.skills) {
        const cs = character.skill[skill.typeId] || {level: 0, rank: 1};
        const currentLevel = parseInt(cs.level || 0);
        const requiredLevel = parseInt(skill.level || 0);
        const rank = parseInt(cs.rank || 1);
        const missing = currentLevel < requiredLevel;
        if (missing) hasMissing = true;
        rows.push(renderSkillRow(skill, currentLevel, requiredLevel, rank, missing));
    }
    const stateClass = hasMissing ? 'is-missing' : 'is-passing';
    const expanded = hasMissing ? 'true' : 'false';
    return `<div class="skill-group ${stateClass}" data-expanded="${expanded}">
        <div class="skill-group-header">
            <span class="skill-group-chevron">▶</span>
            <span class="skill-group-title">${escapeHtml(group.name)}</span>
            <span class="text-muted small">${group.skills.length}</span>
        </div>
        <div class="skill-group-body">${rows.join('')}</div>
    </div>`;
}

function renderSkillRow(skill, currentLevel, requiredLevel, rank, missing) {
    let trainingTime = '';
    if (missing && requiredLevel > 0) {
        const need = rank * 250 * Math.pow(5.66, requiredLevel - 1);
        const have = currentLevel > 0 ? rank * 250 * Math.pow(5.66, currentLevel - 1) : 0;
        trainingTime = formatTime(need - have) || '';
    }
    return `<div class="skill-row ${missing ? 'is-missing' : ''}">
        <div class="skill-row-head">
            <span class="skill-row-name">${escapeHtml(skill.typeName)} <small>(x${rank})</small></span>
            <span class="skill-row-meta">${currentLevel} / ${requiredLevel}${trainingTime ? ' · ' + escapeHtml(trainingTime) : ''}</span>
        </div>
        ${renderSkillBar(currentLevel, requiredLevel)}
    </div>`;
}

function renderSkillBar(currentLevel, requiredLevel) {
    const segments = [];
    const passed = currentLevel >= requiredLevel;
    for (let lvl = 1; lvl <= 5; lvl++) {
        const filled = lvl <= currentLevel;
        const isMissingTarget = !passed && lvl > currentLevel && lvl <= requiredLevel;
        let cls = '';
        if (filled && lvl <= requiredLevel) cls = 'is-filled';
        else if (filled) cls = 'is-overflow';
        else if (isMissingTarget) cls = 'is-missing';
        segments.push(`<div class="skill-bar-segment ${cls}"></div>`);
    }
    const targetCls = requiredLevel === 0 ? '' : (passed ? 'is-passing' : 'is-missing');
    const target = requiredLevel > 0
        ? `<span class="skill-bar-target ${targetCls}">${escapeHtml(fitI18n('skillLevelChipPrefix') || 'Lv')} ${requiredLevel}</span>`
        : '';
    return `<div class="skill-bar-wrap"><div class="skill-bar">${segments.join('')}</div>${target}</div>`;
}

function formatTime(points) {
    if (!points || points <= 0) return '';
    const totalHours = points / 1800;
    const days = Math.floor(totalHours / 24);
    const remHours = Math.floor(totalHours - days * 24);
    const remMins = Math.floor((totalHours - days * 24 - remHours) * 60);
    return `${days}d ${remHours}h ${remMins}m`;
}

/* ============================================================
 *  Requirement editor (manage mode)
 * ============================================================ */
function loadRequirementEditor(fittingId) {
    $.ajax({
        url: '/fitting/' + fittingId + '/requirements',
        type: 'GET',
        dataType: 'json',
        timeout: 10000,
    }).done(function (data) {
        $('#requirements-box').show();
        renderRequirementsEditor(data);
    });
}

function renderRequirementsEditor(data) {
    $('#minimumRequirementsBody').empty();
    $('#advancedRequirementsBody').empty();
    for (const skill of data.minimum || []) {
        $('#minimumRequirementsBody').append(drawRequirementEditorRow(skill, skill.source || 'manual'));
    }
    for (const skill of data.advanced || []) {
        $('#advancedRequirementsBody').append(drawRequirementEditorRow(skill, skill.source || 'manual'));
    }
}

function drawRequirementEditorRow(skill, source) {
    const levelOptions = [1, 2, 3, 4, 5].map(function (level) {
        const selected = parseInt(skill.level) === level ? 'selected' : '';
        return `<option value="${level}" ${selected}>${level}</option>`;
    }).join('');
    return `<tr data-skill-type-id="${skill.typeId}" data-source="${source}">
        <td>${escapeHtml(skill.typeName)} <small class="text-muted">(${skill.typeId})</small></td>
        <td><select class="form-control form-control-sm requirementLevel">${levelOptions}</select></td>
        <td><button type="button" class="btn btn-xs btn-outline-danger removeRequirement"><i class="fa fa-trash"></i></button></td>
    </tr>`;
}

function initializeRequirementSelectors() {
    if (!$('#requirements-box').length) return;

    $.getJSON('/fitting/skill-groups').done(function (groups) {
        $('.skillGroupSelect').each(function () {
            const sel = $(this);
            sel.empty();
            sel.append('<option value=""></option>');
            for (const g of groups) {
                sel.append(`<option value="${g.id}">${escapeHtml(g.text)}</option>`);
            }
        });
    });

    $('.requirementSkillSelect').each(function () {
        const skillSelect = $(this);
        const tier = skillSelect.data('tier');
        if ($.fn.select2) {
            skillSelect.select2({
                width: '100%',
                placeholder: fitI18n('skillPlaceholder'),
                ajax: {
                    url: '/fitting/skills/search',
                    dataType: 'json',
                    delay: 250,
                    data: params => ({
                        q: params.term || '',
                        group_id: $('#' + tier + 'SkillGroup').val() || '',
                    }),
                    processResults: data => data,
                },
            });
        }
    });
}

function resetRequirementSkillSelect(tier) {
    const sel = $('#' + tier + 'SkillSelect');
    if ($.fn.select2 && sel.data('select2')) {
        sel.val(null).trigger('change');
        return;
    }
    sel.empty();
    $.getJSON('/fitting/skills/search', {
        group_id: $('#' + tier + 'SkillGroup').val() || '',
    }).done(function (data) {
        for (const s of data.results || []) {
            sel.append(`<option value="${s.id}">${escapeHtml(s.text)}</option>`);
        }
    });
}

function selectedRequirementSkill(tier) {
    const sel = $('#' + tier + 'SkillSelect');
    if ($.fn.select2 && sel.data('select2')) {
        const selected = sel.select2('data')[0];
        if (!selected) return null;
        return {typeId: parseInt(selected.id), typeName: selected.text};
    }
    const opt = sel.find(':selected');
    if (!opt.length || !opt.val()) return null;
    return {typeId: parseInt(opt.val()), typeName: opt.text()};
}

function addRequirementToEditor(tier) {
    const skill = selectedRequirementSkill(tier);
    const level = parseInt($('#' + tier + 'SkillLevel').val());
    const body = $('#' + tier + 'RequirementsBody');
    if (!skill || !skill.typeId || !level) return;
    const existing = body.find(`tr[data-skill-type-id="${skill.typeId}"]`);
    if (existing.length) {
        existing.find('.requirementLevel').val(level);
        return;
    }
    body.append(drawRequirementEditorRow({
        typeId: skill.typeId,
        typeName: skill.typeName,
        level: level,
    }, 'custom'));
}

function collectRequirements(body, defaultSource) {
    const out = [];
    body.find('tr[data-skill-type-id]').each(function () {
        out.push({
            skill_type_id: parseInt($(this).data('skill-type-id')),
            level: parseInt($(this).find('.requirementLevel').val()),
            source: defaultSource || $(this).data('source') || 'manual',
        });
    });
    return out;
}

function saveRequirements() {
    const fittingId = $('#requirementsFittingId').val();
    if (!fittingId) return;
    $.ajax({
        url: '/fitting/' + fittingId + '/requirements',
        type: 'POST',
        dataType: 'json',
        data: {
            _token: $('meta[name="csrf-token"]').attr('content'),
            minimum: collectRequirements($('#minimumRequirementsBody'), 'manual'),
            advanced: collectRequirements($('#advancedRequirementsBody')),
        },
        timeout: 10000,
    }).done(function (data) {
        renderRequirementsEditor(data);
        loadSkillCheckForFitting(fittingId);
    });
}
