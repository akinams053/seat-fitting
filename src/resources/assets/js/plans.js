/* global $, jQuery, Sortable */

/* ============================================================
 *  Auxiliary skill plans
 * ============================================================ */
const PlansState = {
    plans: [],
    selectedFittingId: null,
};

function planI18n(key) {
    return (window.fittingI18n || {})[key] || key;
}

function planEscape(value) {
    return $('<div>').text(value == null ? '' : value).html();
}

function initializePlansPanel() {
    if ($('#planPanel').length === 0) return;

    loadPlans();

    $(document).on('input', '#planSearch', function () {
        filterPlanList($(this).val());
    });

    $(document).on('click', '#addPlanBtn', function () {
        openPlanEditModal(null);
    });

    $(document).on('click', '.plan-card-edit', function (evt) {
        evt.stopPropagation();
        const id = parseInt($(this).closest('.plan-card').data('id'));
        const plan = PlansState.plans.find(p => p.id === id);
        if (plan) openPlanEditModal(plan);
    });

    $(document).on('click', '.plan-card-delete', function (evt) {
        evt.stopPropagation();
        const id = parseInt($(this).closest('.plan-card').data('id'));
        const plan = PlansState.plans.find(p => p.id === id);
        if (!plan) return;
        if (!confirm((planI18n('planDeleteConfirm') || 'Delete plan?') + '\n\n' + plan.name)) return;
        deletePlan(id);
    });

    $(document).on('click', '#planParseBtn', parsePlanPreview);
    $(document).on('click', '#planSaveBtn', savePlan);

    $(document).on('click', '.attached-plan-card-remove', function (evt) {
        evt.stopPropagation();
        const planId = parseInt($(this).closest('.attached-plan-card').data('planId'));
        const fittingId = parseInt($('#fittingId').val());
        if (!planId || !fittingId) return;
        detachPlanFromFitting(planId, fittingId);
    });
}

function loadPlans() {
    $.ajax({
        url: '/fitting/plans',
        type: 'GET',
        dataType: 'json',
        timeout: 15000,
    }).done(function (plans) {
        PlansState.plans = Array.isArray(plans) ? plans : [];
        renderPlanList();
        wirePlanListSortable();
    });
}

function renderPlanList() {
    const container = $('#planList');
    container.empty();

    if (!PlansState.plans.length) {
        container.append(`<div class="plan-list-empty text-muted">${planEscape(planI18n('planEmptyHint'))}</div>`);
        return;
    }

    for (const plan of PlansState.plans) {
        container.append(renderPlanCard(plan));
    }
}

function renderPlanCard(plan) {
    const tierLabel = plan.tier === 'advanced'
        ? planEscape(planI18n('tabAdvanced'))
        : planEscape(planI18n('tabEntry'));
    const tierClass = plan.tier === 'advanced' ? 'plan-tier-advanced' : 'plan-tier-minimum';
    const itemCount = (plan.items || []).length;
    const attached = plan.attachments || {fittings: [], doctrines: []};
    const attachedSummary = (attached.fittings.length || attached.doctrines.length)
        ? `<span class="plan-card-attached text-muted small">★ ${attached.fittings.length} / ${attached.doctrines.length}</span>`
        : '';
    return `<div class="plan-card" data-id="${plan.id}" data-name="${planEscape(plan.name)}" data-tier="${plan.tier}">
        <div class="plan-card-head">
            <span class="plan-card-grip"><i class="fa fa-grip-vertical"></i></span>
            <span class="plan-card-name">${planEscape(plan.name)}</span>
            <span class="plan-card-tier ${tierClass}">${tierLabel}</span>
        </div>
        <div class="plan-card-meta">
            <span class="text-muted small">${itemCount}</span>
            ${attachedSummary}
            <span class="plan-card-actions">
                <button type="button" class="btn btn-xs btn-outline-secondary plan-card-edit" title="${planEscape(planI18n('planEditTooltip'))}"><i class="fa fa-pen"></i></button>
                <button type="button" class="btn btn-xs btn-outline-danger plan-card-delete" title="${planEscape(planI18n('planDeleteTooltip'))}"><i class="fa fa-trash"></i></button>
            </span>
        </div>
    </div>`;
}

function filterPlanList(query) {
    const q = (query || '').toLowerCase().trim();
    $('#planList .plan-card').each(function () {
        const name = ($(this).data('name') || '').toString().toLowerCase();
        $(this).toggle(!q || name.indexOf(q) !== -1);
    });
}

function wirePlanListSortable() {
    if (typeof Sortable === 'undefined') return;
    const list = document.getElementById('planList');
    if (!list || list.dataset.sortableBound === '1') return;
    list.dataset.sortableBound = '1';

    Sortable.create(list, {
        group: {name: 'plans', pull: 'clone', put: false},
        sort: false,
        animation: 120,
        ghostClass: 'plan-card-ghost',
        onEnd: function () { /* no-op — clone returns to origin */ },
    });

    /* Make the attached-plans area accept drops to attach a plan to the selected fitting. */
    const target = document.getElementById('attachedPlansList');
    if (target && target.dataset.sortableBound !== '1') {
        target.dataset.sortableBound = '1';
        Sortable.create(target, {
            group: {name: 'plans', pull: false, put: ['plans']},
            sort: false,
            animation: 120,
            onAdd: function (evt) {
                const dropped = evt.item;
                const planId = parseInt(dropped.dataset.id);
                /* The cloned node remains in the DOM — strip it; the canonical render comes from
                   the server after the attach API call. */
                dropped.remove();
                const fittingId = parseInt($('#fittingId').val());
                if (!planId || !fittingId) return;
                attachPlanToFitting(planId, fittingId);
            },
        });
    }
}

function openPlanEditModal(plan) {
    $('#planEditId').val(plan ? plan.id : '');
    $('#planEditName').val(plan ? plan.name : '');
    $('#planEditDescription').val(plan ? (plan.description || '') : '');
    $('input[name="planEditTier"][value="' + (plan && plan.tier === 'advanced' ? 'advanced' : 'minimum') + '"]').prop('checked', true);
    $('#planEditRaw').val(plan ? renderPlanItemsAsRaw(plan.items || []) : '');
    $('#planPreviewBlock').hide();
    $('#planPreviewItems').empty();
    $('#planPreviewUnmatched').empty();
    $('#planPreviewUnmatchedBlock').hide();
    $('#planEditModalTitle').text(plan ? planI18n('plan_modal_title_edit') !== 'plan_modal_title_edit'
        ? planI18n('plan_modal_title_edit')
        : ($('#planEditModalTitle').data('editLabel') || 'Edit plan')
        : ($('#planEditModalTitle').data('newLabel') || $('#planEditModalTitle').text()));
    $('#planEditModal').modal('show');
}

function renderPlanItemsAsRaw(items) {
    return items.map(item => `${item.type_name} ${item.level}`).join('\n');
}

function parsePlanPreview() {
    const raw = $('#planEditRaw').val();
    $.ajax({
        url: '/fitting/plans/preview',
        type: 'POST',
        dataType: 'json',
        data: {
            _token: window.fittingCsrf,
            raw: raw,
        },
        timeout: 10000,
    }).done(function (data) {
        renderPlanPreview(data.items || [], data.unmatched || []);
    });
}

function renderPlanPreview(items, unmatched) {
    $('#planPreviewBlock').show();
    $('#planPreviewCount').text(items.length);
    const list = $('#planPreviewItems').empty();
    if (!items.length) {
        list.append(`<div class="text-muted small">${planEscape(planI18n('planNoMatches'))}</div>`);
    } else {
        for (const item of items) {
            list.append(`<span class="plan-preview-chip">${planEscape(item.type_name)} <strong>${item.level}</strong></span>`);
        }
    }

    const unmatchedList = $('#planPreviewUnmatched').empty();
    if (unmatched.length) {
        $('#planPreviewUnmatchedBlock').show();
        for (const line of unmatched) {
            unmatchedList.append(`<li>${planEscape(line)}</li>`);
        }
    } else {
        $('#planPreviewUnmatchedBlock').hide();
    }
}

function savePlan() {
    const id = $('#planEditId').val();
    const name = ($('#planEditName').val() || '').trim();
    const tier = $('input[name="planEditTier"]:checked').val() || 'minimum';
    const description = $('#planEditDescription').val() || '';
    const raw = $('#planEditRaw').val() || '';

    if (!name) {
        $('#planEditName').focus();
        return;
    }
    if (!raw.trim() && !id) {
        $('#planEditRaw').focus();
        return;
    }

    const payload = {
        _token: window.fittingCsrf,
        name: name,
        tier: tier,
        description: description,
    };
    if (raw.trim()) payload.raw = raw;

    const isUpdate = !!id;
    const url = isUpdate ? '/fitting/plans/' + id : '/fitting/plans';

    if (isUpdate) {
        payload._method = 'PATCH';
    }

    $.ajax({
        url: url,
        type: 'POST',
        dataType: 'json',
        data: payload,
        timeout: 15000,
    }).done(function (plan) {
        $('#planEditModal').modal('hide');
        loadPlans();
        const fittingId = parseInt($('#fittingId').val());
        if (fittingId && typeof loadSkillCheckForFitting === 'function') loadSkillCheckForFitting(fittingId);
    }).fail(function (xhr) {
        alert((xhr.responseJSON && xhr.responseJSON.message) || 'Save failed');
    });
}

function deletePlan(id) {
    $.ajax({
        url: '/fitting/plans/' + id,
        type: 'POST',
        dataType: 'json',
        data: {
            _token: window.fittingCsrf,
            _method: 'DELETE',
        },
        timeout: 10000,
    }).done(function () {
        loadPlans();
        const fittingId = parseInt($('#fittingId').val());
        if (fittingId && typeof loadSkillCheckForFitting === 'function') loadSkillCheckForFitting(fittingId);
    });
}

function attachPlanToFitting(planId, fittingId) {
    $.ajax({
        url: '/fitting/plans/' + planId + '/fittings/' + fittingId,
        type: 'POST',
        dataType: 'json',
        data: {_token: window.fittingCsrf},
        timeout: 10000,
    }).done(function () {
        if (typeof loadSkillCheckForFitting === 'function') loadSkillCheckForFitting(fittingId);
        loadPlans();
    });
}

function detachPlanFromFitting(planId, fittingId) {
    $.ajax({
        url: '/fitting/plans/' + planId + '/fittings/' + fittingId,
        type: 'POST',
        dataType: 'json',
        data: {_token: window.fittingCsrf, _method: 'DELETE'},
        timeout: 10000,
    }).done(function () {
        if (typeof loadSkillCheckForFitting === 'function') loadSkillCheckForFitting(fittingId);
        loadPlans();
    });
}

function renderAttachedPlans(plans) {
    const wrap = $('#attachedPlansBlock');
    const list = $('#attachedPlansList');
    const empty = $('#attachedPlansEmpty');

    wrap.show();
    list.empty();

    if (!plans.length) {
        empty.show();
        return;
    }
    empty.hide();

    for (const plan of plans) {
        list.append(renderAttachedPlanCard(plan));
    }
}

function renderAttachedPlanCard(plan) {
    const tierLabel = plan.tier === 'advanced'
        ? planEscape(planI18n('tabAdvanced'))
        : planEscape(planI18n('tabEntry'));
    const tierClass = plan.tier === 'advanced' ? 'plan-tier-advanced' : 'plan-tier-minimum';
    const viaLabel = plan.via === 'doctrine'
        ? (planI18n('planViaDoctrine') || 'via group') + (plan.via_name ? `: ${planEscape(plan.via_name)}` : '')
        : (planI18n('planVia') || 'attached');
    const removable = window.fittingManageMode && plan.via === 'fitting';
    const removeBtn = removable
        ? `<button type="button" class="attached-plan-card-remove btn btn-xs btn-outline-danger" title="${planEscape(planI18n('planDetachBtn'))}"><i class="fa fa-times"></i></button>`
        : '';
    const items = (plan.items || []).slice(0, 6).map(item => `<span class="plan-preview-chip">${planEscape(item.type_name)} <strong>${item.level}</strong></span>`).join('');
    const overflow = (plan.items || []).length > 6 ? `<span class="plan-preview-chip">…+${(plan.items || []).length - 6}</span>` : '';

    return `<div class="attached-plan-card" data-plan-id="${plan.id}">
        <div class="attached-plan-card-head">
            <span class="attached-plan-card-name">${planEscape(plan.name)}</span>
            <span class="plan-card-tier ${tierClass}">${tierLabel}</span>
            ${removeBtn}
        </div>
        <div class="attached-plan-card-via text-muted small">${viaLabel}</div>
        <div class="attached-plan-card-items">${items}${overflow}</div>
    </div>`;
}
