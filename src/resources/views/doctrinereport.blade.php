@extends('web::layouts.grids.12')

@section('title', trans('fitting::doctrine.report_page_title'))
@section('page_header', trans('fitting::doctrine.report_page_title'))

@push('head')
    <link rel="stylesheet" href="{{ asset('web/css/fitting.css') }}">
@endpush

@section('full')
    <div class="card fit-card-flat">
        <div class="card-header">
            <h3 class="card-title">{{trans('fitting::doctrine.report_page_title')}}</h3>
        </div>
        <div class="card-body">
            <div class="report-fixed-target">
                <div>
                    <div class="report-fixed-target-label">{{trans('fitting::doctrine.report_fixed_alliance_label')}}</div>
                    <div class="report-fixed-target-value">{{ $targetAlliance->name }} [{{ $targetAlliance->ticker }}]</div>
                </div>
                <div>
                    <div class="report-fixed-target-label">{{trans('fitting::doctrine.report_fixed_corporation_label')}}</div>
                    <div class="report-fixed-target-value">{{ $targetCorporation->name }}</div>
                </div>
            </div>

            <p class="text-muted small">{!! trans('fitting::doctrine.report_alliance_note') !!}</p>

            <div class="form-row">
                <div class="col-md-6">
                    <div class="form-group" id="doctrineSelectGroup">
                        <label for="doctrines" class="small text-muted">{{trans('fitting::doctrine.report_doctrine_label')}}</label>
                        <select id="doctrines" class="form-control form-control-sm">
                            @foreach ($doctrines as $doctrine)
                                <option value="{{ $doctrine->id }}">{{ $doctrine->name }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="form-group" id="fittingSelectGroup">
                        <label for="reportFitting" class="small text-muted">{{trans('fitting::doctrine.report_fitting_label')}}</label>
                        <select id="reportFitting" class="form-control form-control-sm">
                            <option value="">{{trans('fitting::doctrine.report_fitting_all')}}</option>
                        </select>
                    </div>
                </div>
                <div class="col-md-2 align-self-end">
                    <div class="form-group">
                        <button type="button" id="runreport" class="btn btn-info btn-block">
                            <span class="fa fa-sync"></span>
                            {{trans('fitting::doctrine.report_run_btn')}}
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="card fit-card-flat" id="reportbox" style="display:none;">
        <div class="card-body">
            <div id="reportTotalsSummary" class="report-totals-summary"></div>
            <div class="table-responsive" style="overflow: auto">
                <table id="report" class="table table-sm table-striped table-hover no-footer" style="width:100%;">
                    <thead></thead>
                    <tbody></tbody>
                </table>
            </div>
        </div>
    </div>
@endsection

@push('javascript')
    <script type="application/javascript">
        const reportI18n = {
            character: "{{trans('fitting::doctrine.report_character_header')}}",
            nickname: "{{trans('fitting::doctrine.report_nickname_header')}}",
            totals: "{{trans('fitting::doctrine.report_totals_header')}}",
            minimumBadge: "{{trans('fitting::doctrine.report_minimum_badge')}}",
            advancedBadge: "{{trans('fitting::doctrine.report_advanced_badge')}}",
            statusFailed: "{{trans('fitting::doctrine.report_status_failed')}}",
            statusEntry: "{{trans('fitting::doctrine.report_status_entry')}}",
            statusAdvanced: "{{trans('fitting::doctrine.report_status_advanced')}}",
            notSet: "{{trans('fitting::doctrine.report_not_configured_badge')}}",
            filterAll: "{{trans('fitting::doctrine.report_filter_all')}}",
            filterFailed: "{{trans('fitting::doctrine.report_filter_failed')}}",
            filterEntry: "{{trans('fitting::doctrine.report_filter_entry')}}",
            filterAdvanced: "{{trans('fitting::doctrine.report_filter_advanced')}}",
            loading: "{{trans('fitting::doctrine.report_loading')}}",
            runBtn: "{{trans('fitting::doctrine.report_run_btn')}}",
            runBtnLastFailed: "{{trans('fitting::doctrine.report_run_btn_last_failed')}}",
            runBtnLastTimeout: "{{trans('fitting::doctrine.report_run_btn_last_timeout')}}",
            mainCharacter: "{{trans('fitting::doctrine.report_main_character_header')}}",
            altCharacter: "{{trans('fitting::doctrine.report_alt_character_label')}}",
            orphanLabel: "{{trans('fitting::doctrine.report_orphan_label')}}",
        };

        function reportEscape(value) {
            return $('<div>').text(value == null ? '' : value).html();
        }

        function computeStatus(check) {
            if (!check) return 'failed';
            if (!check.minimum) return 'failed';
            if (check.advanced === true) return 'advanced';
            return 'entry';
        }

        function statusPill(status, large) {
            let cls = 'is-not-set';
            let label = reportI18n.notSet;
            if (status === 'failed') { cls = 'is-failed'; label = reportI18n.statusFailed; }
            else if (status === 'entry') { cls = 'is-entry'; label = reportI18n.statusEntry; }
            else if (status === 'advanced') { cls = 'is-advanced'; label = reportI18n.statusAdvanced; }
            return `<span class="status-pill ${cls} ${large ? 'is-lg' : ''}">${reportEscape(label)}</span>`;
        }

        const button = $('#runreport');
        const report = $('#report');
        let table;

        /* Custom search registered once at module scope; reads .report-column-filter values live. */
        $.fn.dataTable.ext.search.push(function (settings, _data, dataIndex) {
            if (settings.nTable && settings.nTable.id !== 'report') return true;
            const row = settings.aoData[dataIndex] && settings.aoData[dataIndex].nTr;
            if (!row) return true;
            let visible = true;
            $('.report-column-filter').each(function () {
                const fid = String($(this).data('fittingId'));
                const want = $(this).val();
                if (want === 'all') return;
                const cell = $(row).find(`.report-status-cell[data-fitting-id="${fid}"]`);
                if (!cell.length) return;
                if (cell.attr('data-status') !== want) {
                    visible = false;
                    return false;
                }
            });
            return visible;
        });

        $(document).ready(function () {
            $('#reportbox').hide();
            $('#doctrines').select2({
                width: '100%',
                dropdownAutoWidth: true,
                sorter: data => data.sort((a, b) => a.text.localeCompare(b.text)),
            });
            $('#reportFitting').select2({
                width: '100%',
                dropdownAutoWidth: true,
                minimumResultsForSearch: 0,
                sorter: data => data.sort((a, b) => a.text.localeCompare(b.text)),
            });

            $('input[name="reportTarget"]').on('change', function () {
                /* legacy radio is gone — kept here as a no-op so any cached browser
                   handlers from older asset versions don't break the page. */
            });

            /* Per-doctrine fitting list, populated from controller for the cascading dropdown. */
            const fittingsByDoctrine = @json($fittingsByDoctrine);
            const fittingAllLabel = "{{trans('fitting::doctrine.report_fitting_all')}}";

            function refreshFittingDropdown() {
                const doctrineId = parseInt($('#doctrines').val()) || 0;
                const fits = fittingsByDoctrine[doctrineId] || [];
                const sel = $('#reportFitting');
                sel.empty().append('<option value="">' + reportEscape(fittingAllLabel) + '</option>');
                for (const f of fits) {
                    sel.append('<option value="' + f.id + '">' + reportEscape(f.name + ' · ' + (f.shipType || '')) + '</option>');
                }
            }

            $('#doctrines').on('change', refreshFittingDropdown);
            refreshFittingDropdown();
        });

        button.on('click', function () {
            const payload = {
                doctrine: parseInt($('#doctrines').val()),
            };
            const fittingId = parseInt($('#reportFitting').val());
            if (fittingId) {
                payload.fitting = fittingId;
            }

            button.prop('disabled', true).html(
                '<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span> ' + reportEscape(reportI18n.loading)
            );

            $('#reportbox').hide();
            if (table) { table.clear(); table.destroy(); table = null; }
            report.find('thead, tbody').empty();
            $('#reportTotalsSummary').empty();

            $.ajax({
                headers: {'X-CSRF-TOKEN': '{{csrf_token()}}'},
                url: '/fitting/runReport',
                type: 'POST',
                dataType: 'json',
                contentType: 'application/json',
                data: JSON.stringify(payload),
                timeout: 60000,
            }).done(function (result) {
                try {
                    renderReport(result);
                    button.prop('disabled', false).removeClass('bg-danger')
                        .html(`<span class="fa fa-sync"></span> ${reportEscape(reportI18n.runBtn)}`);
                } catch (e) {
                    console.error(e);
                    button.prop('disabled', false).addClass('bg-danger')
                        .html(`<span class="fa fa-sync"></span> ${reportEscape(reportI18n.runBtnLastFailed)}`);
                }
            }).fail(function () {
                button.prop('disabled', false).addClass('bg-danger')
                    .html(`<span class="fa fa-sync"></span> ${reportEscape(reportI18n.runBtnLastTimeout)}`);
            });
        });

        function aggregateUserStatus(fd) {
            /* fd shape: {character_count, minimum_count, advanced_count|null, minimum: bool, advanced: bool|null}.
               Show the highest tier any alt reached, paired with the number of alts at that tier.
               If no advanced requirements are configured, advanced is null and we never escalate. */
            if (fd.advanced === true) return {status: 'advanced', count: fd.advanced_count || 0};
            if (fd.minimum) return {status: 'entry', count: fd.minimum_count || 0};
            return {status: 'failed', count: fd.character_count || 0};
        }

        function statusPillWithCount(status, count) {
            let cls = 'is-not-set';
            let label = reportI18n.notSet;
            if (status === 'failed') { cls = 'is-failed'; label = reportI18n.statusFailed; }
            else if (status === 'entry') { cls = 'is-entry'; label = reportI18n.statusEntry; }
            else if (status === 'advanced') { cls = 'is-advanced'; label = reportI18n.statusAdvanced; }
            const counter = count > 0
                ? `<span class="status-pill-count">×${count}</span>`
                : '';
            return `<span class="status-pill ${cls}">${reportEscape(label)}${counter}</span>`;
        }

        function renderAltDetail(user, fittings) {
            if (!user) return '';
            /* Main character first, then alts in alphabetical order */
            const alts = Object.values(user.characters).sort(function (a, b) {
                if (a.is_main !== b.is_main) return a.is_main ? -1 : 1;
                return (a.name || '').localeCompare(b.name || '');
            });
            let rows = '';
            for (const c of alts) {
                const star = c.is_main
                    ? '<i class="fa fa-star text-warning ml-1" title="main"></i>'
                    : '';
                let row = `<tr><td></td><td>${reportEscape(c.name)}${star}</td><td>${reportEscape(c.title || '')}</td>`;
                for (const f of fittings) {
                    const check = c.fittings ? c.fittings[f.id] : null;
                    const status = computeStatus(check);
                    row += `<td class="text-center">${statusPill(status, false)}</td>`;
                }
                row += '</tr>';
                rows += row;
            }
            return `<div class="report-alt-detail-wrap">
                <table class="table table-sm mb-0 report-alt-detail-table">
                    <tbody>${rows}</tbody>
                </table>
            </div>`;
        }

        function renderReport(result) {
            const fittings = result.fittingDetails || [];
            const usersById = result.usersById || {};
            const totalChars = (result.totalsByFittingId && result.totalsByFittingId.chars) || 0;

            /* Totals summary card */
            const totalsHtml = fittings.map(function (f) {
                const t = (result.totalsByFittingId || {})[f.id] || {minimum: 0, advanced: 0};
                const minPct = totalChars > 0 ? Math.round((t.minimum / totalChars) * 100) : 0;
                const advPct = totalChars > 0 ? Math.round((t.advanced / totalChars) * 100) : 0;
                return `<div class="report-totals-cell">
                    <div class="report-totals-cell-title">${reportEscape(f.name)}</div>
                    <div class="report-totals-cell-sub text-muted">${reportEscape(f.shipType || '')}</div>
                    <div class="mt-1"><span class="status-pill is-entry">${reportEscape(reportI18n.statusEntry)} · ${t.minimum}/${totalChars} (${minPct}%)</span></div>
                    <div class="mt-1"><span class="status-pill is-advanced">${reportEscape(reportI18n.statusAdvanced)} · ${t.advanced}/${totalChars} (${advPct}%)</span></div>
                </div>`;
            }).join('');
            $('#reportTotalsSummary').html(`
                <div class="report-totals-header">${reportEscape(reportI18n.totals)} <span class="text-muted">(${totalChars})</span></div>
                <div class="report-totals-grid">${totalsHtml}</div>
            `);

            /* thead: caret + main character + nickname + fittings */
            let header = `<th style="width:32px;"></th><th>${reportEscape(reportI18n.mainCharacter)}</th><th>${reportEscape(reportI18n.nickname)}</th>`;
            for (const f of fittings) {
                header += `<th style="text-align:center;">${reportEscape(f.name)}<br><small class="text-muted">${reportEscape(f.shipType || '')}</small></th>`;
            }

            let filterRow = `<tr class="report-filter-row"><th></th><th></th><th></th>`;
            for (const f of fittings) {
                filterRow += `<th>
                    <select class="form-control form-control-sm report-column-filter" data-fitting-id="${f.id}">
                        <option value="all">${reportEscape(reportI18n.filterAll)}</option>
                        <option value="failed">${reportEscape(reportI18n.filterFailed)}</option>
                        <option value="entry">${reportEscape(reportI18n.filterEntry)}</option>
                        <option value="advanced">${reportEscape(reportI18n.filterAdvanced)}</option>
                    </select>
                </th>`;
            }
            filterRow += '</tr>';

            report.find('thead').append(`<tr>${header}</tr>${filterRow}`);

            /* tbody — one parent row per user. Alts get rendered later as DataTables child rows. */
            for (const userKey in usersById) {
                const u = usersById[userKey];
                const altCount = u.characters ? Object.keys(u.characters).length : 0;
                const caret = altCount > 1
                    ? `<button class="btn btn-sm btn-link p-0 report-user-toggle" type="button" aria-label="expand"><i class="fa fa-caret-right"></i></button>`
                    : '';
                const orphanBadge = u.is_orphan
                    ? ` <small class="text-muted">(${reportEscape(reportI18n.orphanLabel)})</small>`
                    : '';
                const mainBadge = altCount > 1
                    ? ` <span class="text-muted small">(${altCount})</span>`
                    : '';

                let row = `<tr class="report-user-row" data-user-key="${reportEscape(userKey)}">`;
                row += `<td class="text-center">${caret}</td>`;
                row += `<td>${reportEscape(u.main_character_name || '')}${orphanBadge}${mainBadge}</td>`;
                row += `<td>${reportEscape(u.main_character_title || '')}</td>`;
                for (const f of fittings) {
                    const fd = (u.fittings || {})[f.id] || {character_count: altCount, minimum_count: 0, advanced_count: 0, minimum: false, advanced: null};
                    const agg = aggregateUserStatus(fd);
                    const order = agg.status === 'failed' ? 0 : agg.status === 'entry' ? 1 : agg.status === 'advanced' ? 2 : 3;
                    row += `<td class="report-status-cell" data-status="${agg.status}" data-order="${order}" data-fitting-id="${f.id}">${statusPillWithCount(agg.status, agg.count)}</td>`;
                }
                row += '</tr>';
                report.find('tbody').append(row);
            }

            $('#reportbox').show();

            table = report.DataTable({
                paging: false,
                ordering: true,
                searching: true,
                info: false,
                /* Default sort by main character name. Caret column (0) is not sortable. */
                order: [[1, 'asc']],
                orderCellsTop: true,
                autoWidth: false,
                columnDefs: [
                    {targets: 0, orderable: false, searchable: false},
                    {targets: '_all', className: 'align-middle'},
                ],
            });

            /* Expand/collapse alt detail via DataTables row().child() */
            $('#report tbody').off('click', '.report-user-toggle').on('click', '.report-user-toggle', function () {
                const tr = $(this).closest('tr');
                const dtRow = table.row(tr);
                const userKey = tr.data('userKey');
                const icon = $(this).find('i');
                if (dtRow.child.isShown()) {
                    dtRow.child.hide();
                    tr.removeClass('report-user-row-expanded');
                    icon.removeClass('fa-caret-down').addClass('fa-caret-right');
                } else {
                    dtRow.child(renderAltDetail(usersById[userKey], fittings), 'report-alt-detail').show();
                    tr.addClass('report-user-row-expanded');
                    icon.removeClass('fa-caret-right').addClass('fa-caret-down');
                }
            });

            $('.report-column-filter').off('change').on('change', function () {
                table.draw();
            });
        }
    </script>
@endpush
