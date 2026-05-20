@extends('web::layouts.grids.12')

@section('title', trans(($manage ?? false) ? 'fitting::fitting.manage_page_title' : 'fitting::fitting.page_title'))
@section('page_header', trans(($manage ?? false) ? 'fitting::fitting.manage_page_title' : 'fitting::fitting.page_title'))

@push('head')
    <link rel="stylesheet" href="{{ asset('web/css/fitting.css') }}">
@endpush

@section('full')
    <div class="row">
        <div class="col-lg-5 col-md-12">
            <div class="card fit-card-flat">
                <div class="card-header d-flex align-items-center">
                    <h3 class="card-title mb-0">{{trans(($manage ?? false) ? 'fitting::fitting.manage_page_title' : 'fitting::fitting.page_title')}}</h3>
                </div>
                <div class="card-body">
                    @if($manage ?? false)
                        @can('fitting.create')
                            <button type="button" class="fit-cta mb-3" id="addFitting">
                                <i class="fa fa-plus"></i>
                                {{trans('fitting::fitting.add_new_fitting_btn')}}
                            </button>
                        @endcan
                    @endif

                    <input type="text" id="fitTreeSearch" class="form-control form-control-sm fit-tree-search"
                           placeholder="{{trans('fitting::fitting.tree_search_placeholder')}}">

                    <div id="fitTree" class="fit-tree">
                        <div class="fit-tree-empty">{{trans('fitting::fitting.tree_empty_hint')}}</div>
                    </div>

                    <hr class="my-3">

                    <h6 class="text-muted">{{trans('fitting::fitting.fit_details_title')}}</h6>
                    @include('fitting::includes.display-fit')
                </div>
            </div>

            @include('fitting::includes.eft-export')
            @include('fitting::includes.edit-fit-modal')
            @include('fitting::includes.delete-fit-modal')
            @if($manage ?? false)
                @include('fitting::includes.fit-rename-modal')
                @include('fitting::includes.plan-edit-modal')
            @endif
        </div>

        <div class="col-lg-7 col-md-12">
            @include('fitting::includes.display-skills')
        </div>
    </div>

    @if($manage ?? false)
        <div class="row">
            <div class="col-12">
                <div class="card fit-card-flat" id="planPanel">
                    <div class="card-header d-flex align-items-center justify-content-between">
                        <h3 class="card-title mb-0">{{trans('fitting::fitting.plan_panel_title')}}</h3>
                        @can('fitting.create')
                            <button type="button" class="btn btn-sm btn-success" id="addPlanBtn">
                                <i class="fa fa-plus"></i> {{trans('fitting::fitting.plan_new_btn')}}
                            </button>
                        @endcan
                    </div>
                    <div class="card-body">
                        <small class="text-muted d-block mb-2">{{trans('fitting::fitting.plan_panel_hint')}}</small>
                        <input type="text" id="planSearch" class="form-control form-control-sm mb-2"
                               placeholder="{{trans('fitting::fitting.plan_search_placeholder')}}">
                        <div id="planList" class="plan-list"></div>
                    </div>
                </div>
            </div>
        </div>
    @endif
@endsection

@push('javascript')
    @if($manage ?? false)
        <script src="{{ asset('web/js/lib/Sortable.min.js') }}"></script>
    @endif
    <script src="{{ asset('web/js/fitting.js') }}"></script>
    <script src="{{ asset('web/js/fitting-jquery.js') }}"></script>
    <script src="{{ asset('web/js/plans.js') }}"></script>
    <script type="application/javascript">
        window.fittingManageMode = {{ ($manage ?? false) ? 'true' : 'false' }};
        window.fittingI18n = {
            statusFailed: "{{trans('fitting::fitting.status_failed')}}",
            statusEntry: "{{trans('fitting::fitting.status_entry_passed')}}",
            statusAdvanced: "{{trans('fitting::fitting.status_advanced_passed')}}",
            statusAdvancedNotSet: "{{trans('fitting::fitting.status_advanced_not_set')}}",
            tabEntry: "{{trans('fitting::fitting.tab_entry_label')}}",
            tabAdvanced: "{{trans('fitting::fitting.tab_advanced_label')}}",
            minimumTitle: "{{trans('fitting::fitting.minimum_requirements_title')}}",
            advancedTitle: "{{trans('fitting::fitting.advanced_requirements_title')}}",
            noCharacterSelected: "{{trans('fitting::fitting.no_character_selected')}}",
            noAdvancedRequirements: "{{trans('fitting::fitting.no_advanced_requirements')}}",
            skillPlaceholder: "{{trans('fitting::fitting.skill_select_placeholder')}}",
            skillLevelChipPrefix: "{{trans('fitting::fitting.skill_level_chip_prefix')}}",
            treeUngroupedLabel: "{{trans('fitting::fitting.tree_ungrouped_label')}}",
            treeCheckGroupBtn: "{{trans('fitting::fitting.tree_check_group_btn')}}",
            treeEmptyHint: "{{trans('fitting::fitting.tree_empty_hint')}}",
            viewFittingTooltip: "{{trans('fitting::fitting.view_fitting_tooltip')}}",
            editFittingTooltip: "{{trans('fitting::fitting.edit_fitting_tooltip')}}",
            deleteFittingTooltip: "{{trans('fitting::fitting.delete_fitting_tooltip')}}",
            copyFittingTooltip: "{{trans('fitting::fitting.copy_fitting_tooltip')}}",
            renameFittingTooltip: "{{trans('fitting::fitting.rename_fitting_tooltip')}}",
            planAttachedEmpty: "{{trans('fitting::fitting.plan_attached_empty')}}",
            planVia: "{{trans('fitting::fitting.plan_via_fitting')}}",
            planViaDoctrine: "{{trans('fitting::fitting.plan_via_doctrine')}}",
            planDetachBtn: "{{trans('fitting::fitting.plan_detach_btn')}}",
            planEditTooltip: "{{trans('fitting::fitting.plan_edit_tooltip')}}",
            planDeleteTooltip: "{{trans('fitting::fitting.plan_delete_tooltip')}}",
            planDeleteConfirm: "{{trans('fitting::fitting.plan_delete_confirm')}}",
            planEmptyHint: "{{trans('fitting::fitting.plan_panel_empty')}}",
            planNoMatches: "{{trans('fitting::fitting.plan_no_matches_in_text')}}",
            planSavedToast: "{{trans('fitting::fitting.plan_saved_toast')}}",
            planUnmatchedTitle: "{{trans('fitting::fitting.plan_unmatched_title')}}",
            trainingTimeTitle: "{{trans('fitting::fitting.training_time_title')}}",
            exportMissingBtn: "{{trans('fitting::fitting.export_missing_btn')}}",
            exportMissingTitle: "{{trans('fitting::fitting.export_missing_modal_title')}}",
            exportMissingEmpty: "{{trans('fitting::fitting.export_missing_empty')}}",
            exportCopyBtn: "{{trans('fitting::fitting.export_copy_btn')}}",
            exportCopyDone: "{{trans('fitting::fitting.export_copy_done')}}",
            confirmBtn: "{{trans('fitting::global.submit_btn')}}",
            cancelBtn: "{{trans('fitting::global.cancel_btn')}}"
        };
        window.fittingCsrf = "{{ csrf_token() }}";

        initializeFittingPage();
        if (window.fittingManageMode) {
            initializePlansPanel();
        }
    </script>
@endpush
