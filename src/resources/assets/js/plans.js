/* global $, jQuery */

/* ============================================================
 *  Auxiliary skill plans — shared helpers
 *
 *  Used by:
 *    - fitting.blade.php (personal + manage modes): renderAttachedPlans for
 *      the read-only "Attached plans" block under fit details.
 *    - doctrine.blade.php (manage workspace): plan edit modal handlers,
 *      attach/detach helpers, parse preview. doctrine.js owns the panel
 *      rendering and Sortable wiring; this file just exposes the API.
 * ============================================================ */

function planI18n(key) {
    return (window.fittingI18n || window.doctrineI18n || {})[key] || key;
}

function planEscape(value) {
    return $('<div>').text(value == null ? '' : value).html();
}

/* Deterministic per-plan accent color so two different plans never look identical.
   10-color palette, hash by plan id so the assignment is stable across page reloads.
   Returns {border, bg} — bg is a low-alpha rgba so text stays readable on the tinted card. */
const PLAN_PALETTE = [
    {r: 59,  g: 130, b: 246}, /* blue */
    {r: 22,  g: 163, b: 74},  /* green */
    {r: 220, g: 38,  b: 38},  /* red */
    {r: 168, g: 85,  b: 247}, /* purple */
    {r: 234, g: 88,  b: 12},  /* orange */
    {r: 8,   g: 145, b: 178}, /* cyan */
    {r: 202, g: 138, b: 4},   /* gold */
    {r: 219, g: 39,  b: 119}, /* magenta */
    {r: 71,  g: 85,  b: 105}, /* slate */
    {r: 101, g: 163, b: 13},  /* lime */
];

function planAccentColor(planId) {
    const n = parseInt(planId) || 0;
    const c = PLAN_PALETTE[Math.abs(n) % PLAN_PALETTE.length];

    return {
        border: `rgb(${c.r}, ${c.g}, ${c.b})`,
        bg: `rgba(${c.r}, ${c.g}, ${c.b}, 0.16)`,
        dot: `rgb(${c.r}, ${c.g}, ${c.b})`,
    };
}

function planAccentStyle(planId) {
    const c = planAccentColor(planId);

    return `background-color: ${c.bg}; border-left-color: ${c.border};`;
}

/* Modal handlers are wired once on first DOMContentLoaded; safe to load on
   pages that don't include the modal — the selectors simply match nothing. */
$(function () {
    if ($('#planEditModal').length === 0) return;

    $(document).on('click', '#planParseBtn', parsePlanPreview);
    $(document).on('click', '#planSaveBtn', savePlan);
});

/* ============================================================
 *  Plan edit modal — open / parse / save
 * ============================================================ */
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
            _token: window.doctrineCsrf || window.fittingCsrf,
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
        _token: window.doctrineCsrf || window.fittingCsrf,
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
    }).done(function () {
        $('#planEditModal').modal('hide');
        /* Notify hosting page (doctrine.js subscribes) to refresh its caches. */
        $(document).trigger('plansChanged');
    }).fail(function (xhr) {
        alert((xhr.responseJSON && xhr.responseJSON.message) || 'Save failed');
    });
}

/* ============================================================
 *  Plan attach / detach API (used by doctrine.js)
 * ============================================================ */
function deletePlan(id) {
    $.ajax({
        url: '/fitting/plans/' + id,
        type: 'POST',
        dataType: 'json',
        data: {
            _token: window.doctrineCsrf || window.fittingCsrf,
            _method: 'DELETE',
        },
        timeout: 10000,
    }).done(function () {
        $(document).trigger('plansChanged');
    });
}

function attachPlanToFitting(planId, fittingId) {
    return $.ajax({
        url: '/fitting/plans/' + planId + '/fittings/' + fittingId,
        type: 'POST',
        dataType: 'json',
        data: {_token: window.doctrineCsrf || window.fittingCsrf},
        timeout: 10000,
    });
}

function detachPlanFromFitting(planId, fittingId) {
    return $.ajax({
        url: '/fitting/plans/' + planId + '/fittings/' + fittingId,
        type: 'POST',
        dataType: 'json',
        data: {_token: window.doctrineCsrf || window.fittingCsrf, _method: 'DELETE'},
        timeout: 10000,
    });
}

function attachPlanToFittingInDoctrine(planId, fittingId, doctrineId) {
    return $.ajax({
        url: '/fitting/plans/' + planId + '/fittings/' + fittingId + '/doctrines/' + doctrineId,
        type: 'POST',
        dataType: 'json',
        data: {_token: window.doctrineCsrf || window.fittingCsrf},
        timeout: 10000,
    });
}

function detachPlanFromFittingInDoctrine(planId, fittingId, doctrineId) {
    return $.ajax({
        url: '/fitting/plans/' + planId + '/fittings/' + fittingId + '/doctrines/' + doctrineId,
        type: 'POST',
        dataType: 'json',
        data: {_token: window.doctrineCsrf || window.fittingCsrf, _method: 'DELETE'},
        timeout: 10000,
    });
}

function attachPlanToDoctrine(planId, doctrineId) {
    return $.ajax({
        url: '/fitting/plans/' + planId + '/doctrines/' + doctrineId,
        type: 'POST',
        dataType: 'json',
        data: {_token: window.doctrineCsrf || window.fittingCsrf},
        timeout: 10000,
    });
}

function detachPlanFromDoctrine(planId, doctrineId) {
    return $.ajax({
        url: '/fitting/plans/' + planId + '/doctrines/' + doctrineId,
        type: 'POST',
        dataType: 'json',
        data: {_token: window.doctrineCsrf || window.fittingCsrf, _method: 'DELETE'},
        timeout: 10000,
    });
}

/* ============================================================
 *  Read-only attached-plans render — used on fitting page under fit details
 *  Called from fitting.js renderSingleSkillCheck after a fitting is loaded.
 * ============================================================ */
function renderAttachedPlans(plans) {
    const wrap = $('#attachedPlansBlock');
    const list = $('#attachedPlansList');
    const empty = $('#attachedPlansEmpty');

    if (wrap.length === 0) return;

    wrap.show();
    list.empty();

    if (!plans || !plans.length) {
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
        : (plan.via_name ? (planI18n('planVia') || 'attached') + `: ${planEscape(plan.via_name)}` : (planI18n('planVia') || 'attached'));
    const items = (plan.items || []).slice(0, 6).map(item => `<span class="plan-preview-chip">${planEscape(item.type_name)} <strong>${item.level}</strong></span>`).join('');
    const overflow = (plan.items || []).length > 6 ? `<span class="plan-preview-chip">…+${(plan.items || []).length - 6}</span>` : '';
    const accent = planAccentColor(plan.id);

    return `<div class="attached-plan-card" data-plan-id="${plan.id}" style="${planAccentStyle(plan.id)}">
        <div class="attached-plan-card-head">
            <span class="plan-accent-dot" style="background:${accent.dot};"></span>
            <span class="attached-plan-card-name">${planEscape(plan.name)}</span>
            <span class="plan-card-tier ${tierClass}">${tierLabel}</span>
        </div>
        <div class="attached-plan-card-via text-muted small">${viaLabel}</div>
        <div class="attached-plan-card-items">${items}${overflow}</div>
    </div>`;
}
