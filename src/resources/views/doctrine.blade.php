@extends('web::layouts.grids.12')

@section('title', trans('fitting::doctrine.page_title'))
@section('page_header', trans('fitting::doctrine.page_title'))

@push('head')
    <link rel="stylesheet" href="{{ asset('web/css/fitting.css') }}">
@endpush

@section('full')
    <div class="doctrine-workspace-hint">
        <i class="fa fa-info-circle"></i> {{trans('fitting::doctrine.workspace_hint')}}
    </div>

    <div class="row">
        <div class="col-lg-5 col-md-12 doctrine-groups-col">
            <div class="card fit-card-flat">
                <div class="card-header d-flex align-items-center">
                    <h3 class="card-title mb-0">{{trans('fitting::doctrine.workspace_groups_title')}}</h3>
                    @can('fitting.create')
                        <button type="button" class="btn btn-sm btn-success ml-3" id="createDoctrineBtn">
                            <i class="fa fa-plus"></i> {{trans('fitting::doctrine.workspace_new_group_btn')}}
                        </button>
                    @endcan
                </div>
                <div class="card-body" id="doctrineGroupList"></div>
            </div>
        </div>

        <div class="col-lg-7 col-md-12 doctrine-pool-col">
            <div class="card fit-card-flat">
                <div class="card-header d-flex align-items-center">
                    <h3 class="card-title mb-0">{{trans('fitting::doctrine.workspace_pool_title')}}</h3>
                </div>
                <div class="card-body">
                    <input type="text" id="doctrinePoolSearch" class="form-control form-control-sm mb-3"
                           placeholder="{{trans('fitting::doctrine.workspace_pool_search_placeholder')}}">
                    <div id="doctrinePool" class="doctrine-pool"></div>
                </div>
            </div>

            <div class="card fit-card-flat">
                <div class="card-header d-flex align-items-center">
                    <h3 class="card-title mb-0">{{trans('fitting::doctrine.workspace_plan_pool_title')}}</h3>
                    @can('fitting.create')
                        <button type="button" class="btn btn-sm btn-success ml-3" id="addPlanBtn">
                            <i class="fa fa-plus"></i> {{trans('fitting::fitting.plan_new_btn')}}
                        </button>
                    @endcan
                </div>
                <div class="card-body">
                    <small class="text-muted d-block mb-2">{{trans('fitting::doctrine.workspace_plan_pool_hint')}}</small>
                    <div id="doctrinePlanPool" class="doctrine-plan-pool"></div>
                </div>
            </div>
        </div>
    </div>

    @can('fitting.create')
        @include('fitting::includes.plan-edit-modal')
    @endcan
@endsection

@push('javascript')
    <script src="{{ asset('web/js/lib/Sortable.min.js') }}"></script>
    <script src="{{ asset('web/js/plans.js') }}"></script>
    <script src="{{ asset('web/js/doctrine.js') }}"></script>
    <script type="application/javascript">
        window.doctrineI18n = {
            workspaceNewGroupPlaceholder: "{{trans('fitting::doctrine.workspace_new_group_placeholder')}}",
            workspaceGroupEmptyHint: "{{trans('fitting::doctrine.workspace_group_empty_hint')}}",
            workspacePoolEmptyHint: "{{trans('fitting::doctrine.workspace_pool_empty_hint')}}",
            workspaceGroupsEmptyHint: "{{trans('fitting::doctrine.workspace_groups_empty_hint')}}",
            workspaceRenameBtn: "{{trans('fitting::doctrine.workspace_rename_btn')}}",
            deleteDoctrineBtn: "{{trans('fitting::doctrine.delete_doctrine_btn')}}",
            workspaceRemoveFitBtn: "{{trans('fitting::doctrine.workspace_remove_fit_btn')}}",
            workspacePlanPoolEmpty: "{{trans('fitting::doctrine.workspace_plan_pool_empty')}}",
            workspaceGroupPlansEmpty: "{{trans('fitting::doctrine.workspace_group_plans_empty')}}",
            workspaceFitPlansEmpty: "{{trans('fitting::doctrine.workspace_fit_plans_empty')}}",
            workspaceRemovePlanBtn: "{{trans('fitting::doctrine.workspace_remove_plan_btn')}}",
            workspaceLockBtn: "{{trans('fitting::doctrine.workspace_lock_btn')}}",
            workspaceUnlockBtn: "{{trans('fitting::doctrine.workspace_unlock_btn')}}",
            workspaceLockedHint: "{{trans('fitting::doctrine.workspace_locked_hint')}}",
            deleteDoctrineConfirm: "{{trans('fitting::doctrine.delete_doctrine_modal_body')}}",
            tierEntry: "{{trans('fitting::fitting.tab_entry_label')}}",
            tierAdvanced: "{{trans('fitting::fitting.tab_advanced_label')}}",
            planEditTooltip: "{{trans('fitting::fitting.plan_edit_tooltip')}}",
            planDeleteTooltip: "{{trans('fitting::fitting.plan_delete_tooltip')}}",
            planDeleteConfirm: "{{trans('fitting::fitting.plan_delete_confirm')}}",
            planNoMatches: "{{trans('fitting::fitting.plan_no_matches_in_text')}}",
            canCreate: {{ auth()->user()->can('fitting.create') ? 'true' : 'false' }},
            canLock: {{ auth()->user()->can('fitting.lock_doctrine') ? 'true' : 'false' }}
        };
        window.doctrineCsrf = "{{ csrf_token() }}";
        initializeDoctrineWorkspace();
    </script>
@endpush
