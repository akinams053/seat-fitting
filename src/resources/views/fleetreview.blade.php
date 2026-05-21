@extends('web::layouts.grids.12')

@section('title', trans('fitting::doctrine.fleet_page_title'))
@section('page_header', trans('fitting::doctrine.fleet_page_title'))

@push('head')
    <link rel="stylesheet" href="{{ asset('web/css/fitting.css') }}">
@endpush

@section('full')
    <div class="card fit-card-flat">
        <div class="card-header">
            <h3 class="card-title">{{trans('fitting::doctrine.fleet_page_title')}}</h3>
        </div>
        <div class="card-body">
            <p class="text-muted small">{!! trans('fitting::doctrine.fleet_note') !!}</p>

            <div class="form-row">
                <div class="col-md-5">
                    <div class="form-group" id="doctrineSelectGroup">
                        <label for="doctrines" class="small text-muted">{{trans('fitting::doctrine.report_doctrine_label')}}</label>
                        <select id="doctrines" class="form-control form-control-sm">
                            @foreach ($doctrines as $doctrine)
                                <option value="{{ $doctrine->id }}">{{ $doctrine->name }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>
                <div class="col-md-5">
                    <div class="form-group" id="fittingSelectGroup">
                        <label for="reportFitting" class="small text-muted">{{trans('fitting::doctrine.report_fitting_label')}}</label>
                        <select id="reportFitting" class="form-control form-control-sm">
                            <option value="">{{trans('fitting::doctrine.report_fitting_all')}}</option>
                        </select>
                    </div>
                </div>
                <div class="col-md-2 align-self-end">
                    <div class="form-group">
                        <button type="button" id="runFleetReview" class="btn btn-info btn-block">
                            <span class="fa fa-sync"></span>
                            {{trans('fitting::doctrine.fleet_run_btn')}}
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="card fit-card-flat" id="reportbox" style="display:none;">
        <div class="card-body">
            <div id="fleetReviewMessage"></div>
            <div id="reportTotalsSummary" class="report-totals-summary"></div>
            <div class="table-responsive" style="overflow: auto">
                <table id="fleetReviewReport" class="table table-sm table-striped table-hover no-footer" style="width:100%;">
                    <thead></thead>
                    <tbody></tbody>
                </table>
            </div>
        </div>
    </div>
@endsection

@push('javascript')
    <script type="application/javascript">
        const fleetI18n = {
            character: "{{trans('fitting::doctrine.fleet_character_header')}}",
            nickname: "{{trans('fitting::doctrine.report_nickname_header')}}",
            role: "{{trans('fitting::doctrine.fleet_role_header')}}",
            ship: "{{trans('fitting::doctrine.fleet_ship_header')}}",
            fitting: "{{trans('fitting::doctrine.fleet_fitting_header')}}",
            checkType: "{{trans('fitting::doctrine.fleet_check_type_header')}}",
            result: "{{trans('fitting::doctrine.fleet_result_header')}}",
            totals: "{{trans('fitting::doctrine.report_totals_header')}}",
            statusFailed: "{{trans('fitting::doctrine.report_status_failed')}}",
            statusEntry: "{{trans('fitting::doctrine.report_status_entry')}}",
            statusAdvanced: "{{trans('fitting::doctrine.report_status_advanced')}}",
            notSet: "{{trans('fitting::doctrine.report_not_configured_badge')}}",
            filterAll: "{{trans('fitting::doctrine.report_filter_all')}}",
            filterFailed: "{{trans('fitting::doctrine.report_filter_failed')}}",
            filterEntry: "{{trans('fitting::doctrine.report_filter_entry')}}",
            filterAdvanced: "{{trans('fitting::doctrine.report_filter_advanced')}}",
            loading: "{{trans('fitting::doctrine.report_loading')}}",
            runBtn: "{{trans('fitting::doctrine.fleet_run_btn')}}",
            runBtnLastFailed: "{{trans('fitting::doctrine.fleet_run_btn_last_failed')}}",
            runBtnLastTimeout: "{{trans('fitting::doctrine.fleet_run_btn_last_timeout')}}",
        };

        function fleetEscape(value) {
            return $('<div>').text(value == null ? '' : value).html();
        }

        function statusPill(status, large) {
            let cls = 'is-not-set';
            let label = fleetI18n.notSet;
            if (status === 'failed') { cls = 'is-failed'; label = fleetI18n.statusFailed; }
            else if (status === 'entry') { cls = 'is-entry'; label = fleetI18n.statusEntry; }
            else if (status === 'advanced') { cls = 'is-advanced'; label = fleetI18n.statusAdvanced; }
            return `<span class="status-pill ${cls} ${large ? 'is-lg' : ''}">${fleetEscape(label)}</span>`;
        }

        const button = $('#runFleetReview');
        const report = $('#fleetReviewReport');
        let table;

        $.fn.dataTable.ext.search.push(function (settings, _data, dataIndex) {
            if (settings.nTable && settings.nTable.id !== 'fleetReviewReport') return true;
            const row = settings.aoData[dataIndex] && settings.aoData[dataIndex].nTr;
            if (!row) return true;
            const want = $('.report-column-filter').val();
            if (!want || want === 'all') return true;
            const cell = $(row).find('.fleet-status-cell');
            if (!cell.length) return true;
            return cell.attr('data-status') === want;
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

            const fittingsByDoctrine = @json($fittingsByDoctrine);
            const fittingAllLabel = "{{trans('fitting::doctrine.report_fitting_all')}}";

            function refreshFittingDropdown() {
                const doctrineId = parseInt($('#doctrines').val()) || 0;
                const fits = fittingsByDoctrine[doctrineId] || [];
                const sel = $('#reportFitting');
                sel.empty().append('<option value="">' + fleetEscape(fittingAllLabel) + '</option>');
                for (const f of fits) {
                    sel.append('<option value="' + f.id + '">' + fleetEscape(f.name + ' · ' + (f.shipType || '')) + '</option>');
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
                '<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span> ' + fleetEscape(fleetI18n.loading)
            );

            resetReport();

            $.ajax({
                headers: {'X-CSRF-TOKEN': '{{csrf_token()}}'},
                url: '/fitting/runFleetReview',
                type: 'POST',
                dataType: 'json',
                contentType: 'application/json',
                data: JSON.stringify(payload),
                timeout: 60000,
            }).done(function (result) {
                try {
                    renderReport(result);
                    button.prop('disabled', false).removeClass('bg-danger')
                        .html(`<span class="fa fa-sync"></span> ${fleetEscape(fleetI18n.runBtn)}`);
                } catch (e) {
                    console.error(e);
                    button.prop('disabled', false).addClass('bg-danger')
                        .html(`<span class="fa fa-sync"></span> ${fleetEscape(fleetI18n.runBtnLastFailed)}`);
                }
            }).fail(function (xhr) {
                const message = xhr.responseJSON && xhr.responseJSON.message ? xhr.responseJSON.message : fleetI18n.runBtnLastTimeout;
                renderError(message);
                button.prop('disabled', false).addClass('bg-danger')
                    .html(`<span class="fa fa-sync"></span> ${fleetEscape(fleetI18n.runBtnLastFailed)}`);
            });
        });

        function resetReport() {
            $('#reportbox').hide();
            $('#fleetReviewMessage').empty();
            $('#reportTotalsSummary').empty();
            if (table) { table.clear(); table.destroy(); table = null; }
            report.find('thead, tbody').empty();
        }

        function renderError(message) {
            resetReport();
            $('#fleetReviewMessage').html(`<div class="alert alert-danger mb-0">${fleetEscape(message)}</div>`);
            $('#reportbox').show();
        }

        function renderReport(result) {
            const rows = result.rows || [];
            const totals = result.totals || {members: 0, failed: 0, entry: 0, advanced: 0};

            $('#reportTotalsSummary').html(`
                <div class="report-totals-header">${fleetEscape(fleetI18n.totals)} <span class="text-muted">(${totals.members || 0})</span></div>
                <div class="report-totals-grid">
                    <div class="report-totals-cell"><div class="report-totals-cell-title">${fleetEscape(fleetI18n.filterFailed)}</div><div class="mt-1">${statusPill('failed', false)} <span class="text-muted">${totals.failed || 0}</span></div></div>
                    <div class="report-totals-cell"><div class="report-totals-cell-title">${fleetEscape(fleetI18n.filterEntry)}</div><div class="mt-1">${statusPill('entry', false)} <span class="text-muted">${totals.entry || 0}</span></div></div>
                    <div class="report-totals-cell"><div class="report-totals-cell-title">${fleetEscape(fleetI18n.filterAdvanced)}</div><div class="mt-1">${statusPill('advanced', false)} <span class="text-muted">${totals.advanced || 0}</span></div></div>
                </div>
            `);

            const header = `<th>${fleetEscape(fleetI18n.character)}</th><th>${fleetEscape(fleetI18n.nickname)}</th><th>${fleetEscape(fleetI18n.role)}</th><th>${fleetEscape(fleetI18n.ship)}</th><th>${fleetEscape(fleetI18n.fitting)}</th><th>${fleetEscape(fleetI18n.checkType)}</th><th>${fleetEscape(fleetI18n.result)}</th>`;
            const filterRow = `<tr class="report-filter-row"><th></th><th></th><th></th><th></th><th></th><th></th><th>
                <select class="form-control form-control-sm report-column-filter">
                    <option value="all">${fleetEscape(fleetI18n.filterAll)}</option>
                    <option value="failed">${fleetEscape(fleetI18n.filterFailed)}</option>
                    <option value="entry">${fleetEscape(fleetI18n.filterEntry)}</option>
                    <option value="advanced">${fleetEscape(fleetI18n.filterAdvanced)}</option>
                </select>
            </th></tr>`;
            report.find('thead').append(`<tr>${header}</tr>${filterRow}`);

            for (const row of rows) {
                const order = row.status === 'failed' ? 0 : row.status === 'entry' ? 1 : row.status === 'advanced' ? 2 : 3;
                report.find('tbody').append(`<tr>
                    <td>${fleetEscape(row.character_name)}</td>
                    <td>${fleetEscape(row.nickname)}</td>
                    <td>${fleetEscape(row.role_name || row.role)}</td>
                    <td>${fleetEscape(row.ship_type)}</td>
                    <td>${fleetEscape(row.fitting_name)}</td>
                    <td>${fleetEscape(row.check_type)}</td>
                    <td class="fleet-status-cell" data-status="${fleetEscape(row.status)}" data-order="${order}">${statusPill(row.status, false)}</td>
                </tr>`);
            }

            $('#reportbox').show();

            table = report.DataTable({
                paging: false,
                ordering: true,
                searching: true,
                info: false,
                order: [[0, 'asc']],
                orderCellsTop: true,
                autoWidth: false,
                columnDefs: [
                    {targets: '_all', className: 'align-middle'},
                    {targets: 6, className: 'align-middle text-center'},
                ],
            });

            $('.report-column-filter').off('change').on('change', function () {
                table.draw();
            });
        }
    </script>
@endpush
