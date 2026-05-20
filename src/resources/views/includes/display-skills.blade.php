<div class="card card-primary card-solid" id="skills-box">
    <div class="card-header form-group">
        <h3 class="box-title" id="skill-title">{{trans('fitting::fitting.required_skills_title')}}</h3>
    </div>
    <div class="card-body">
        <div id="skills-window">
            <select id="characterSpinner" class="form-control mb-3"></select>

            <div id="singleSkillResults">
                <h5>{{trans('fitting::fitting.minimum_requirements_title')}}</h5>
                <table style="width: 100%" class="table table-condensed table-striped">
                <thead>
                <tr>
                    <th>{{trans('fitting::fitting.skill_name_header')}}</th>
                    <th style="width: 80px">{{trans('fitting::fitting.skill_level_header')}}</th>
                </tr>
                </thead>
                <tbody id="skillbody">
                <tr>
                    <td colspan="2">{{trans('fitting::fitting.no_character_selected')}}</td>
                </tr>
                </tbody>
            </table>

            <h5>{{trans('fitting::fitting.advanced_requirements_title')}}</h5>
            <table style="width: 100%" class="table table-condensed table-striped">
                <thead>
                <tr>
                    <th>{{trans('fitting::fitting.skill_name_header')}}</th>
                    <th style="width: 80px">{{trans('fitting::fitting.skill_level_header')}}</th>
                </tr>
                </thead>
                <tbody id="advancedskillbody">
                <tr>
                    <td colspan="2">{{trans('fitting::fitting.no_advanced_requirements')}}</td>
                </tr>
                </tbody>
            </table>
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

            <h5>{{trans('fitting::fitting.minimum_requirements_title')}}</h5>
            <table class="table table-condensed table-striped">
                <thead>
                <tr>
                    <th>{{trans('fitting::fitting.skill_name_header')}}</th>
                    <th style="width: 90px">{{trans('fitting::fitting.skill_required_level_header')}}</th>
                </tr>
                </thead>
                <tbody id="minimumRequirementsBody"></tbody>
            </table>

            <h5>{{trans('fitting::fitting.advanced_requirements_title')}}</h5>
            <table class="table table-condensed table-striped">
                <thead>
                <tr>
                    <th>{{trans('fitting::fitting.skill_name_header')}}</th>
                    <th style="width: 90px">{{trans('fitting::fitting.skill_required_level_header')}}</th>
                    <th style="width: 40px"></th>
                </tr>
                </thead>
                <tbody id="advancedRequirementsBody"></tbody>
            </table>

            <div class="form-row align-items-end">
                <div class="col">
                    <label for="customSkillTypeId">{{trans('fitting::fitting.custom_skill_type_id_label')}}</label>
                    <input type="number" class="form-control" id="customSkillTypeId" min="1">
                </div>
                <div class="col">
                    <label for="customSkillLevel">{{trans('fitting::fitting.skill_required_level_header')}}</label>
                    <select id="customSkillLevel" class="form-control">
                        @for($level = 1; $level <= 5; $level++)
                            <option value="{{$level}}">{{$level}}</option>
                        @endfor
                    </select>
                </div>
                <div class="col-auto">
                    <button type="button" class="btn btn-secondary" id="addAdvancedRequirement">
                        {{trans('fitting::fitting.add_custom_skill_btn')}}
                    </button>
                </div>
            </div>

            <button type="button" class="btn btn-primary mt-3" id="saveRequirements">
                {{trans('fitting::fitting.save_requirements_btn')}}
            </button>
        </div>
    </div>
@endif
