<div class="card fit-card-flat" id="skills-box" style="display:none;">
    <div class="card-header">
        <div class="skill-check-header">
            <h3 class="card-title mb-0">{{trans('fitting::fitting.required_skills_title')}}</h3>
            <span id="skillCheckOverallStatus"></span>
        </div>
        <select id="characterSpinner" class="form-control form-control-sm mt-2"></select>
    </div>
    <div class="card-body">
        <div id="singleSkillView">
            <div class="skill-check-tabs">
                <button type="button" class="skill-check-tab is-active" data-tier="minimum">
                    {{trans('fitting::fitting.tab_entry_label')}}
                    <span class="status-pill is-not-set ml-2" id="tabBadgeMinimum" style="display:none;"></span>
                </button>
                <button type="button" class="skill-check-tab" data-tier="advanced">
                    {{trans('fitting::fitting.tab_advanced_label')}}
                    <span class="status-pill is-not-set ml-2" id="tabBadgeAdvanced" style="display:none;"></span>
                </button>
            </div>
            <div id="skillTabContent" class="mt-3"></div>
        </div>

        <div id="groupSkillResults"></div>
    </div>
</div>

@if($manage ?? false)
    <div class="card fit-card-flat" id="requirements-box" style="display:none;">
        <div class="card-header">
            <h3 class="card-title">{{trans('fitting::fitting.manage_requirements_title')}}</h3>
        </div>
        <div class="card-body">
            <input type="hidden" id="requirementsFittingId" value="">

            <div class="skill-check-tabs">
                <button type="button" class="skill-check-tab is-active req-tab" data-tier="minimum">
                    {{trans('fitting::fitting.tab_entry_label')}}
                </button>
                <button type="button" class="skill-check-tab req-tab" data-tier="advanced">
                    {{trans('fitting::fitting.tab_advanced_label')}}
                </button>
            </div>

            @foreach(['minimum', 'advanced'] as $tier)
                <div class="req-tier-panel mt-3" data-tier="{{$tier}}" @if($tier === 'advanced') style="display:none;" @endif>
                    <div class="form-row align-items-end mb-2">
                        <div class="col-md-3">
                            <label for="{{$tier}}SkillGroup" class="small mb-1">{{trans('fitting::fitting.skill_group_label')}}</label>
                            <select id="{{$tier}}SkillGroup" class="form-control form-control-sm skillGroupSelect" data-tier="{{$tier}}"></select>
                        </div>
                        <div class="col-md-5">
                            <label for="{{$tier}}SkillSelect" class="small mb-1">{{trans('fitting::fitting.skill_select_label')}}</label>
                            <select id="{{$tier}}SkillSelect" class="form-control form-control-sm requirementSkillSelect" data-tier="{{$tier}}"></select>
                        </div>
                        <div class="col-md-2">
                            <label for="{{$tier}}SkillLevel" class="small mb-1">{{trans('fitting::fitting.skill_required_level_header')}}</label>
                            <select id="{{$tier}}SkillLevel" class="form-control form-control-sm requirementAddLevel" data-tier="{{$tier}}">
                                @for($level = 1; $level <= 5; $level++)
                                    <option value="{{$level}}">{{$level}}</option>
                                @endfor
                            </select>
                        </div>
                        <div class="col-md-2">
                            <button type="button" class="btn btn-sm btn-primary btn-block addRequirement" data-tier="{{$tier}}">
                                {{trans('fitting::fitting.add_requirement_btn')}}
                            </button>
                        </div>
                    </div>
                    <table class="table table-sm table-striped">
                        <thead>
                        <tr>
                            <th>{{trans('fitting::fitting.skill_name_header')}}</th>
                            <th style="width: 80px">{{trans('fitting::fitting.skill_required_level_header')}}</th>
                            <th style="width: 32px"></th>
                        </tr>
                        </thead>
                        <tbody id="{{$tier}}RequirementsBody"></tbody>
                    </table>
                </div>
            @endforeach

            <button type="button" class="btn btn-success" id="saveRequirements">
                <i class="fa fa-save"></i> {{trans('fitting::fitting.save_requirements_btn')}}
            </button>
        </div>
    </div>
@endif
