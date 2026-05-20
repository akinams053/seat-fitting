@extends('web::layouts.grids.4-4-4')

@section('title', trans(($manage ?? false) ? 'fitting::fitting.manage_page_title' : 'fitting::fitting.page_title'))
@section('page_header', trans(($manage ?? false) ? 'fitting::fitting.manage_page_title' : 'fitting::fitting.page_title'))

@section('left')
    <div class="card card-primary card-solid">
        <div class="card-header">
            <h3 class="card-title">{{trans(($manage ?? false) ? 'fitting::fitting.manage_page_title' : 'fitting::fitting.page_title')}}</h3>
            @if($manage ?? false)
                @can('fitting.create')
                    <div class="card-tools pull-right">
                        <button type="button" class="btn btn-xs btn-tool" id="addFitting" data-toggle="tooltip"
                                data-placement="top" title="{{trans('fitting::fitting.add_new_fitting_tooltip')}}">
                            <span class="fa fa-plus-square"></span>
                        </button>
                    </div>
                @endcan
            @endif
        </div>
        <div class="card-body px-2">
            @unless($manage ?? false)
                <div class="form-group">
                    <label for="personalCheckMode">{{trans('fitting::fitting.check_mode_label')}}</label>
                    <select id="personalCheckMode" class="form-control form-control-sm">
                        <option value="single">{{trans('fitting::fitting.check_mode_single')}}</option>
                        <option value="group">{{trans('fitting::fitting.check_mode_group')}}</option>
                    </select>
                </div>
                <div class="form-group" id="personalDoctrineCheckBox">
                    <label for="personalDoctrineCheck">{{trans('fitting::fitting.check_group_label')}}</label>
                    <select id="personalDoctrineCheck" class="form-control form-control-sm">
                        <option value="">{{trans('fitting::fitting.check_group_placeholder')}}</option>
                        @foreach($doctrine_list as $doctrine)
                            <option value="{{$doctrine['id']}}">{{$doctrine['name']}}</option>
                        @endforeach
                    </select>
                    <button type="button" id="runPersonalDoctrineCheck" class="btn btn-info btn-sm btn-block mt-2">
                        {{trans('fitting::fitting.run_group_check_btn')}}
                    </button>
                </div>
            @endunless

            <!-- Search Filters -->
            <div class="row mb-3" id="fittingSearchFilters">
                <div class="col-md-6">
                    <div class="input-group input-group-sm">
                        <div class="input-group-prepend">
                            <span class="input-group-text"><i class="fas fa-rocket"></i></span>
                        </div>
                        <input type="text" id="searchShip" class="form-control" placeholder="{{trans('fitting::fitting.search_ship_placeholder')}}">
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="input-group input-group-sm">
                        <div class="input-group-prepend">
                            <span class="input-group-text"><i class="fas fa-file-signature"></i></span>
                        </div>
                        <input type="text" id="searchFitName" class="form-control" placeholder="{{trans('fitting::fitting.search_fit_placeholder')}}">
                    </div>
                </div>
            </div>
            
            <table id='fitlist' class="table table-hover table-sm" style="vertical-align: top; width: 100%;">
                <thead>
                <tr>
                    <th style="width: 40px;"></th>
                    <th>{{trans('fitting::fitting.col_ship_type')}}</th>
                    <th>{{trans('fitting::fitting.col_fit_name')}}</th>
                    <th class="text-right" style="width: 100px;">{{trans('fitting::fitting.col_options')}}</th>
                </tr>
                </thead>
                <tbody>
                @if (count($fitlist) > 0)
                    @foreach($fitlist as $fit)
                        <tr class="fitid" data-id="{{ $fit['id'] }}">
                            <td><img src='https://images.evetech.net/types/{{$fit['typeID']}}/icon?size=32'
                                     height='24' alt="{{trans('fitting::fitting.fitting_icon_alt')}}"/>
                            </td>
                            <td>{{ $fit['shiptype'] }}</td>
                            <td>{{ $fit['fitname'] }}</td>
                            <td class="no-hover text-right" style="min-width:80px">
                                <button type="button" id="viewfit" class="btn btn-xs btn-success"
                                        data-id="{{ $fit['id'] }}" data-toggle="tooltip" data-placement="top"
                                        title="{{trans('fitting::fitting.view_fitting_tooltip')}}">
                                    <span class="fa fa-eye text-white"></span>
                                </button>
                                @if($manage ?? false)
                                    @can('fitting.create')
                                        <button type="button" id="editfit" class="btn btn-xs btn-warning"
                                                data-id="{{ $fit['id'] }}" data-toggle="tooltip" data-placement="top"
                                                title="{{trans('fitting::fitting.edit_fitting_tooltip')}}">
                                            <span class="fas fa-edit text-white"></span>
                                        </button>
                                        <button type="button" id="deletefit" class="btn btn-xs btn-danger"
                                                data-id="{{ $fit['id'] }}" data-toggle="tooltip" data-placement="top"
                                                title="{{trans('fitting::fitting.delete_fitting_tooltip')}}">
                                            <span class="fa fa-trash text-white"></span>
                                        </button>
                                    @endcan
                                @endif
                            </td>
                        </tr>
                    @endforeach
                @endif
                </tbody>
            </table>
        </div>
    </div>

    @include('fitting::includes.eft-export')
    @include('fitting::includes.edit-fit-modal')
    @include('fitting::includes.delete-fit-modal')

@endsection
@section('center')
    @include('fitting::includes.display-fit')
@endsection
@section('right')
    @include('fitting::includes.display-skills')
@endsection

@push('javascript')
    <script src="{{ asset('web/js/fitting.js') }}"></script>
    <script src="{{ asset('web/js/fitting-jquery.js') }}"></script>
    <script type="application/javascript">
        $('#exportLinks').hide();

        window.fittingManageMode = {{ ($manage ?? false) ? 'true' : 'false' }};
        window.fittingCustomSkillLabel = "{{trans('fitting::fitting.custom_skill_type_id_label')}}";
        window.fittingMinimumLabel = "{{trans('fitting::fitting.minimum_requirements_title')}}";
        window.fittingAdvancedLabel = "{{trans('fitting::fitting.advanced_requirements_title')}}";
        window.fittingNoAdvancedLabel = "{{trans('fitting::fitting.no_advanced_requirements')}}";

        // Initialize DataTable with search functionality
        var fittingTable = $('#fitlist').DataTable({
            "paging": true,
            "pageLength": 25,
            "lengthMenu": [[10, 25, 50, 100, -1], [10, 25, 50, 100, "All"]],
            "searching": true,
            "ordering": true,
            "info": true,
            "autoWidth": false,
            "order": [[1, "asc"]], // Sort by ship type by default
            "columnDefs": [
                {
                    "targets": 0, // Icon column
                    "orderable": false,
                    "searchable": false
                },
                {
                    "targets": 3, // Options column
                    "orderable": false,
                    "searchable": false
                }
            ],
            "language": {
                "search": "{{trans('fitting::fitting.datatable_search')}}",
                "lengthMenu": "{{trans('fitting::fitting.datatable_length')}}",
                "info": "{{trans('fitting::fitting.datatable_info')}}",
                "infoEmpty": "{{trans('fitting::fitting.datatable_info_empty')}}",
                "infoFiltered": "{{trans('fitting::fitting.datatable_info_filtered')}}",
                "zeroRecords": "{{trans('fitting::fitting.datatable_zero_records')}}",
                "emptyTable": "{{trans('fitting::fitting.datatable_empty')}}"
            },
            "dom": '<"row"<"col-sm-6"l><"col-sm-6"f>>rt<"row"<"col-sm-6"i><"col-sm-6"p>>'
        });

        // Custom search for ship type column (column index 1)
        $('#searchShip').on('keyup change', function() {
            fittingTable.column(1).search(this.value).draw();
        });

        // Custom search for fit name column (column index 2)
        $('#searchFitName').on('keyup change', function() {
            fittingTable.column(2).search(this.value).draw();
        });

        // Click handlers for fitting actions
        $('#fitlist tbody')
            .on('click', '#deletefit', function () {
                $('#fitConfirmModal').modal('show');
                $('#fitSelection').val($(this).closest('tr').data('id'));
            });

        $('#deleteConfirm').on('click', function () {
            const id = $('#fitSelection').val();
            
            $.ajax({
                headers: function () {},
                url: "/fitting/delfittingbyid/" + id,
                type: "GET",
                datatype: 'json',
                timeout: 10000
            }).done(function (result) {
                // Remove the row from DataTable
                fittingTable.row($('#fitlist .fitid[data-id="' + id + '"]')).remove().draw();
            }).fail(function (xmlHttpRequest, textStatus, errorThrown) {
                console.error('Delete failed:', errorThrown);
            });
        });
    </script>
@endpush
