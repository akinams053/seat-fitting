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

            <div class="form-group">
                <label class="small text-muted">{{trans('fitting::doctrine.report_target_label')}}</label>
                <div class="btn-group btn-group-toggle report-target-toggle" data-toggle="buttons">
                    <label class="btn btn-sm btn-outline-secondary active">
                        <input type="radio" name="reportTarget" value="doctrine" checked>
                        {{trans('fitting::doctrine.report_target_doctrine')}}
                    </label>
                    <label class="btn btn-sm btn-outline-secondary">
                        <input type="radio" name="reportTarget" value="fitting">
                        {{trans('fitting::doctrine.report_target_fitting')}}
                    </label>
                </div>
            </div>

            <div class="form-row">
                <div class="col-md-6 col-lg-4">
                    <div class="form-group" id="doctrineSelectGroup">
                        <label for="doctrines" class="small text-muted">{{trans('fitting::doctrine.report_doctrine_label')}}</label>
                        <select id="doctrines" class="form-control form-control-sm">
                            @foreach ($doctrines as $doctrine)
                                <option value="{{ $doctrine->id }}">{{ $doctrine->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="form-group" id="fittingSelectGroup" style="display:none;">
                        <label for="reportFitting" class="small text-muted">{{trans('fitting::doctrine.report_fitting_label')}}</label>
                        <select id="reportFitting" class="form-control form-control-sm">
                            @foreach ($fittings as $fit)
                                <option value="{{ $fit['id'] }}">{{ $fit['name'] }} · {{ $fit['shipType'] }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>
                <div class="col-md-6 col-lg-2 align-self-end">
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
            <div class="table-responsive" style="overflow: auto">
                <table id="report" class="table table-sm table-striped table-hover no-footer">
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
        let lastFittings = [];
        let lastCharsById = {};

        $(document).ready(function () {
            $('#reportbox').hide();
            $('#doctrines').select2({sorter: data => data.sort((a, b) => a.text.localeCompare(b.text))});
            $('#reportFitting').select2({sorter: data => data.sort((a, b) => a.text.localeCompare(b.text))});

            $('input[name="reportTarget"]').on('change', function () {
                const v = $('input[name="reportTarget"]:checked').val();
                $('#doctrineSelectGroup').toggle(v === 'doctrine');
                $('#fittingSelectGroup').toggle(v === 'fitting');
            });
        });

        button.on('click', function () {
            const target = $('input[name="reportTarget"]:checked').val();
            const payload = {};
            if (target === 'doctrine') {
                payload.doctrine = parseInt($('#doctrines').val());
            } else {
                payload.fitting = parseInt($('#reportFitting').val());
            }

            button.prop('disabled', true).html(
                '<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span> ' + reportEscape(reportI18n.loading)
            );

            $('#reportbox').hide();
            if (table) { table.clear(); table.destroy(); }
            report.find('thead, tbody').empty();

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

        function renderReport(result) {
            const fittings = result.fittingDetails || [];
            lastFittings = fittings;
            lastCharsById = result.charsById || {};

            let header = `<th>${reportEscape(reportI18n.character)}</th><th>${reportEscape(reportI18n.nickname)}</th>`;
            for (const f of fittings) {
                header += `<th style="text-align:center;">${reportEscape(f.name)}<br><small class="text-muted">${reportEscape(f.shipType || '')}</small></th>`;
            }

            let filterRow = `<tr class="report-filter-row"><th></th><th></th>`;
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

            /* Totals row */
            const totalChars = (result.totalsByFittingId && result.totalsByFittingId.chars) || 0;
            let totals = `<tr class="font-weight-bold"><td colspan="2">${reportEscape(reportI18n.totals)} (${totalChars})</td>`;
            for (const f of fittings) {
                const t = (result.totalsByFittingId || {})[f.id] || {minimum: 0, advanced: 0};
                const minPct = totalChars > 0 ? Math.round((t.minimum / totalChars) * 100) : 0;
                const advPct = totalChars > 0 ? Math.round((t.advanced / totalChars) * 100) : 0;
                totals += `<td style="text-align:center;">
                    <span class="status-pill is-entry">${reportEscape(reportI18n.statusEntry)}: ${t.minimum}/${totalChars} (${minPct}%)</span><br>
                    <span class="status-pill is-advanced mt-1">${reportEscape(reportI18n.statusAdvanced)}: ${t.advanced}/${totalChars} (${advPct}%)</span>
                </td>`;
            }
            totals += '</tr>';
            report.find('tbody').append(totals);

            /* Character rows */
            for (const cid in result.charsById) {
                const c = result.charsById[cid];
                let row = `<tr><td>${reportEscape(c.name)}</td><td>${reportEscape(c.title || '')}</td>`;
                for (const f of fittings) {
                    const check = c.fittings ? c.fittings[f.id] : null;
                    const status = computeStatus(check);
                    row += `<td class="report-status-cell" data-status="${status}" data-fitting-id="${f.id}">${statusPill(status, false)}</td>`;
                }
                row += '</tr>';
                report.find('tbody').append(row);
            }

            $('#reportbox').show();

            /* DataTable with per-column sorting; first totals row is excluded by skipping the row marker. */
            table = report.DataTable({
                paging: false,
                ordering: true,
                searching: true,
                info: false,
                order: [],
                orderCellsTop: true,
                autoWidth: false,
            });

            $('.report-column-filter').off('change').on('change', function () {
                applyColumnFilters();
            });
        }

        function applyColumnFilters() {
            const filters = {};
            $('.report-column-filter').each(function () {
                filters[$(this).data('fittingId')] = $(this).val();
            });

            report.find('tbody tr').each(function (idx) {
                const tr = $(this);
                if (idx === 0) return; /* keep totals row */
                let visible = true;
                tr.find('.report-status-cell').each(function () {
                    const fid = $(this).data('fittingId');
                    const status = $(this).data('status');
                    const want = filters[fid] || 'all';
                    if (want !== 'all' && status !== want) visible = false;
                });
                tr.toggle(visible);
            });
        }
    </script>
@endpush
