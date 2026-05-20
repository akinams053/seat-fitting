<div class="card card-primary card-solid" id="skills-box">
    <div class="card-header form-group">
        <h3 class="box-title" id="skill-title">{{trans('fitting::fitting.required_skills_title')}}</h3>
    </div>
    <div class="card-body">
        <div id="skills-window">
            <select id="characterSpinner" class="form-control mb-3"></select>

            <div id="singleSkillResults" class="row">
                <div class="col-md-6 mb-3">
                    <h5>{{trans('fitting::fitting.minimum_requirements_title')}}</h5>
                    <div id="minimumSkillPanel" class="skillRequirementPanel">
                        {{trans('fitting::fitting.no_character_selected')}}
                    </div>
                </div>
                <div class="col-md-6 mb-3">
                    <h5>{{trans('fitting::fitting.advanced_requirements_title')}}</h5>
                    <div id="advancedSkillPanel" class="skillRequirementPanel">
                        {{trans('fitting::fitting.no_advanced_requirements')}}
                    </div>
                </div>
            </div>

            <div id="groupSkillResults"></div>
        </div>
    </div>
</div>

@if($manage ?? false)
    <div class="card card-warning card-solid" id="requirements-box">
        <div class="card-header form-group">
            <h3 class="box-title">{{trans('fitting::fitting.manage_requirements_title')}}</h3>
        </div>
        <div class="card-body">
            <input type="hidden" id="requirementsFittingId" value="">

            <div class="row">
                @foreach(['minimum' => trans('fitting::fitting.minimum_requirements_title'), 'advanced' => trans('fitting::fitting.advanced_requirements_title')] as $tier => $title)
                    <div class="col-md-6 mb-3">
                        <h5>{{$title}}</h5>
                        <div class="form-row align-items-end mb-2">
                            <div class="col-md-3">
                                <label for="{{$tier}}SkillGroup">{{trans('fitting::fitting.skill_group_label')}}</label>
                                <select id="{{$tier}}SkillGroup" class="form-control skillGroupSelect" data-tier="{{$tier}}"></select>
                            </div>
                            <div class="col-md-5">
                                <label for="{{$tier}}SkillSelect">{{trans('fitting::fitting.skill_select_label')}}</label>
                                <select id="{{$tier}}SkillSelect" class="form-control requirementSkillSelect" data-tier="{{$tier}}"></select>
                            </div>
                            <div class="col-md-2">
                                <label for="{{$tier}}SkillLevel">{{trans('fitting::fitting.skill_required_level_header')}}</label>
                                <select id="{{$tier}}SkillLevel" class="form-control requirementAddLevel" data-tier="{{$tier}}">
                                    @for($level = 1; $level <= 5; $level++)
                                        <option value="{{$level}}">{{$level}}</option>
                                    @endfor
                                </select>
                            </div>
                            <div class="col-md-2">
                                <button type="button" class="btn btn-secondary btn-block addRequirement" data-tier="{{$tier}}">
                                    {{trans('fitting::fitting.add_requirement_btn')}}
                                </button>
                            </div>
                        </div>
                        <table class="table table-condensed table-striped">
                            <thead>
                            <tr>
                                <th>{{trans('fitting::fitting.skill_name_header')}}</th>
                                <th style="width: 90px">{{trans('fitting::fitting.skill_required_level_header')}}</th>
                                <th style="width: 40px"></th>
                            </tr>
                            </thead>
                            <tbody id="{{$tier}}RequirementsBody"></tbody>
                        </table>
                    </div>
                @endforeach
            </div>

            <button type="button" class="btn btn-primary" id="saveRequirements">
                {{trans('fitting::fitting.save_requirements_btn')}}
            </button>
        </div>
    </div>
@endif
