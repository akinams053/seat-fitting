@extends('web::layouts.grids.12')

@section('title', trans('fitting::doctrine.report_page_title'))
@section('page_header', trans('fitting::doctrine.report_page_title'))

@section('full')
    <div class="card card-primary card-solid">
        <div class="card-header">
            <h3 class="card-title">{{trans('fitting::doctrine.report_page_title')}}</h3>
        </div>
        <div class="card-body">
            <div class="row">
                <div class="col-md-6 col-lg-3">
                    <div class="form-group">
                        <label for="alliances">{{trans('fitting::doctrine.report_alliance_label')}}:</label>
                        <select id="alliances" class="form-control" multiple>
                            @foreach ($alliances as $alliance)
                                <option value="{{ $alliance->alliance_id }}">{{ $alliance->name }}
                                    [{{ $alliance->ticker }}]
                                </option>
                            @endforeach
                        </select>
                        <p class="help-block">{!! trans('fitting::doctrine.report_alliance_note') !!}</p>
                    </div>
                </div>
                <div class="col-md-6 col-lg-3">
                    <div class="form-group">
                        <label for="corporations">{{trans('fitting::doctrine.report_corporation_label')}}:</label>
                        <select id="corporations" class="form-control" multiple>
                            @foreach ($corps as $corp)
                                <option value="{{ $corp->corporation_id }}">{{ $corp->name }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>
                <div class="col-md-6 col-lg-3">
                    <div class="form-group">
                        <label for="doctrines">{{trans('fitting::doctrine.report_doctrine_label')}}:</label>
                        <select id="doctrines" class="form-control">
                            @foreach ($doctrines as $doctrine)
                                <option value="{{ $doctrine->id }}">{{ $doctrine->name }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>
            </div>
            <div class="row">
                <div class="col-md-12">
                    <button type="button" id="runreport" class="btn btn-info btn-flat">
                        <span class="fa fa-sync"></span>
                        {{trans('fitting::doctrine.report_run_btn')}}
                    </button>
                </div>
            </div>
        </div>
    </div>

    <div class="card card-primary card-solid" id="reportbox">
        <div class="card-body">
            <div class="table-responsive" style="overflow: auto">
                <table id="report" class="table table-condensed table-striped no-footer">
                    <thead></thead>
                    <tbody></tbody>
                </table>
            </div>
        </div>
    </div>
@endsection

@push('javascript')
    <script type="application/javascript">
        const button = $('#runreport');
        let table;
        const report = $('#report');

        function escapeReportHtml(value) {
            return $('<div>').text(value || '').html();
        }

        $(document).ready(function () {
            $('#reportbox').hide();

            $('#alliances').select2({
                sorter: data => data.sort((a, b) => a.text.localeCompare(b.text)),
                multiple: true,
                placeholder: "{{trans('fitting::doctrine.report_alliance_placeholder')}}"
            });
            $('#corporations').select2({
                sorter: data => data.sort((a, b) => a.text.localeCompare(b.text)),
                multiple: true,
                placeholder: "{{trans('fitting::doctrine.report_corporation_placeholder')}}"
            });
            $('#doctrines').select2({sorter: data => data.sort((a, b) => a.text.localeCompare(b.text)),});
        });

        button.on('click', function () {
            const allianceids = $('#alliances').select2('data').map((e) => parseInt(e.id));
            const corpids = $('#corporations').select2('data').map((e) => parseInt(e.id));
            const doctrineid = parseInt($('#doctrines').find(":selected").val());

            button.prop("disabled", true);
            button.html(
                '<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span>' + '{{trans('fitting::doctrine.report_loading')}}'
            );

            $('#reportbox').hide();

            if (table) {
                table.clear();
                table.destroy();
                report.find("thead, tbody").empty();
            }

            report.find("thead, tbody").empty();

            $.ajax({
                headers: {
                    'X-CSRF-TOKEN': '{{csrf_token()}}'
                },
                url: "/fitting/runReport",
                type: "POST",
                dataType: 'json',
                contentType: 'application/json',
                data: JSON.stringify({
                    alliances: allianceids,
                    corporations: corpids,
                    doctrine: doctrineid,
                }),
                timeout: 60000,
            }).done(function (result) {
                try {
                    const fittings = result.fittingDetails || [];
                    let header = "";

                    for (const fitting of fittings) {
                        header += "<th style='text-align: center'>" + escapeReportHtml(fitting.name) + "</th>";
                    }

                    report.find("thead").append("<tr><th>{{trans('fitting::doctrine.report_character_header')}}</th><th>{{trans('fitting::doctrine.report_nickname_header')}}</th>" + header + "</tr>");

                    let totals = "<tr><td colspan='2'><label>{{trans('fitting::doctrine.report_totals_header')}}</label></td>";

                    for (const fitting of fittings) {
                        const fitTotals = result.totalsByFittingId[fitting.id];
                        const characterTotal = result.totalsByFittingId.chars || 0;
                        const minimumPercent = characterTotal > 0 ? Math.round((fitTotals.minimum / characterTotal) * 100) : 0;
                        const advancedPercent = characterTotal > 0 ? Math.round((fitTotals.advanced / characterTotal) * 100) : 0;

                        totals += "<td style='text-align: center; width: 12em;'>";
                        totals += "{{trans('fitting::doctrine.report_minimum_badge')}}: " + fitTotals.minimum + " / " + characterTotal + " (" + minimumPercent + "%)<br/>";
                        totals += "{{trans('fitting::doctrine.report_advanced_badge')}}: " + fitTotals.advanced + " / " + characterTotal + " (" + advancedPercent + "%)";
                        totals += "</td>";
                    }

                    totals += "</tr>";
                    report.find("tbody").prepend(totals);

                    for (const characterId in result.charsById) {
                        const character = result.charsById[characterId];
                        let body = "<tr><td style='position: sticky;'>" + escapeReportHtml(character.name) + "</td>";
                        body += "<td>" + escapeReportHtml(character.title || '') + "</td>";

                        for (const fitting of fittings) {
                            const check = character.fittings[fitting.id];
                            body += "<td style='text-align: center; width: 12em; min-width: 120px;'>";
                            body += check.ship ? "<span class='badge badge-success'>{{trans('fitting::doctrine.report_hull_badge')}}</span> " : "<span class='badge badge-danger'>{{trans('fitting::doctrine.report_hull_badge')}}</span> ";
                            body += check.minimum ? "<span class='badge badge-success'>{{trans('fitting::doctrine.report_minimum_badge')}}</span> " : "<span class='badge badge-danger'>{{trans('fitting::doctrine.report_minimum_badge')}}</span> ";
                            if (check.advanced === null) {
                                body += "<span class='badge badge-secondary'>{{trans('fitting::doctrine.report_not_configured_badge')}}</span>";
                            } else {
                                body += check.advanced ? "<span class='badge badge-success'>{{trans('fitting::doctrine.report_advanced_badge')}}</span>" : "<span class='badge badge-danger'>{{trans('fitting::doctrine.report_advanced_badge')}}</span>";
                            }
                            body += "</td>";
                        }

                        body += "</tr>";
                        report.find("tbody").append(body);
                    }

                    $('#reportbox').show();

                    button.html(`<span class="fa fa-sync"></span> {{trans('fitting::doctrine.report_run_btn')}}</button>`);
                    button.prop("disabled", false);
                } catch (error) {
                    button.html(`<span class="fa fa-sync"></span> {{trans('fitting::doctrine.report_run_btn_last_failed')}}</button>`);
                    button.addClass("bg-danger")
                    button.prop("disabled", false);
                }
            }).fail(function () {
                button.html(`<span class="fa fa-sync"></span> {{trans('fitting::doctrine.report_run_btn_last_timeout')}}</button>`);
                button.addClass("bg-danger")
                button.prop("disabled", false);
            });
        });
    </script>
@endpush
