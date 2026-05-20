let skills_informations;

$('#fitting-box').hide();
$('#skills-box').hide();
$('#requirements-box').hide();
$('#personalDoctrineCheckBox').hide();
$('#eftexport').hide();
$('#showeft').val('');

function showSkillResult(result) {
    if (!result) {
        return;
    }

    skills_informations = result;

    $('#skills-box').show();
    $('#minimumSkillPanel, #advancedSkillPanel').empty();
    $('#groupSkillResults').empty();
    $('#characterSpinner').empty();

    for (var toons in result.characters) {
        $('#characterSpinner').append('<option value="' + result.characters[toons].id + '">' + result.characters[toons].name + '</option>');
    }

    fillSkills(result);
}

$('#personalCheckMode').on('change', function () {
    const groupMode = $(this).val() === 'group';

    $('#personalDoctrineCheckBox').toggle(groupMode);
    $('#fittingSearchFilters').toggle(!groupMode);
    $('#fitlist_wrapper').toggle(!groupMode);
    $('#fitting-box, #skills-box, #requirements-box').hide();
});

$('#runPersonalDoctrineCheck').on('click', function () {
    const doctrineId = $('#personalDoctrineCheck').val();

    if (!doctrineId) {
        return;
    }

    $('#fitting-box').hide();
    $('#fittingId').text('');

    $.ajax({
        url: "/fitting/getskillsbydoctrineid/" + doctrineId,
        type: "GET",
        dataType: 'json',
        timeout: 10000
    }).done(function (result) {
        showSkillResult(result);
    });
});

$('#addFitting').on('click', function () {
    $('#fitEditModal').modal('show');
    $('#fitSelection').val('0');
    $('textarea#eftfitting').val('');
});

$('#fitlist').on('click', '#editfit', function () {
    $('#fitEditModal').modal('show');
    let id = $(this).data('id');
    $('#fitSelection').val(id);

    $.ajax({
        headers: function () {
        },
        url: "/fitting/geteftfittingbyid/" + id,
        type: "GET",
        datatype: 'string',
        timeout: 10000
    }).done(function (result) {
        $('textarea#eftfitting').val(result);
    }).fail(function (xmlHttpRequest, textStatus, errorThrown) {
    });
});

$('#fitlist').on('click', '#viewfit', function () {
    const fittingId = $(this).data('id');

    $('#highSlots, #midSlots, #lowSlots, #rigs, #cargo, #drones, #subSlots')
        .find('tbody')
        .empty();
    $('#fittingId').text(fittingId);
    $('#requirementsFittingId').val(fittingId);

    $.ajax({
        headers: function () {
        },
        url: "/fitting/getfittingbyid/" + fittingId,
        type: "GET",
        dataType: 'json',
        timeout: 10000
    }).done(function (result) {
        $('#highSlots, #midSlots, #lowSlots, #rigs, #cargo, #drones, #subSlots')
            .find('tbody')
            .empty();
        $('#showeft').val('');
        $('#fitting-box').show();
        fillFittingWindow(result);
    });

    $.ajax({
        headers: function () {
        },
        url: "/fitting/getskillsbyfitid/" + fittingId,
        type: "GET",
        dataType: 'json',
        timeout: 10000
    }).done(function (result) {
        showSkillResult(result);
    });

    if (window.fittingManageMode) {
        $.ajax({
            url: "/fitting/" + fittingId + "/requirements",
            type: "GET",
            dataType: 'json',
            timeout: 10000
        }).done(function (result) {
            $('#requirements-box').show();
            fillRequirementEditor(result);
        });
    }
});

$('#characterSpinner').change(function () {
    if (skills_informations) {
        $('#skills-box').show();
        $('#minimumSkillPanel, #advancedSkillPanel').empty();

        fillSkills(skills_informations);
    }
});

$('#requirements-box').on('change', '.skillGroupSelect', function () {
    resetRequirementSkillSelect($(this).data('tier'));
});

$('#requirements-box').on('click', '.addRequirement', function () {
    addRequirementToEditor($(this).data('tier'));
});

$('#requirements-box').on('click', '.removeRequirement', function () {
    $(this).closest('tr').remove();
});

$('#saveRequirements').on('click', function () {
    const fittingId = $('#requirementsFittingId').val();

    if (!fittingId) {
        return;
    }

    $.ajax({
        url: "/fitting/" + fittingId + "/requirements",
        type: "POST",
        dataType: 'json',
        data: {
            _token: $('meta[name="csrf-token"]').attr('content'),
            minimum: collectRequirements($('#minimumRequirementsBody'), 'manual'),
            advanced: collectRequirements($('#advancedRequirementsBody'))
        },
        timeout: 10000
    }).done(function (result) {
        fillRequirementEditor(result);
        $.ajax({
            url: "/fitting/getskillsbyfitid/" + fittingId,
            type: "GET",
            dataType: 'json',
            timeout: 10000
        }).done(function (skills) {
            skills_informations = skills;
            $('#minimumSkillPanel, #advancedSkillPanel').empty();
            fillSkills(skills);
        });
    });
});
