function escapeHtml(value) {
    return $('<div>').text(value || '').html();
}

function fillFittingWindow(result) {
    if (result) {
        $('#fitting-window').show();
        $('#middle-header').text(result.shipname + ', ' + result.fitname);
        $('#showeft').val(result.eft);

        const exportLinks = $('#exportLinks');
        exportLinks.empty();
        for (const link of result.exportLinks) {
            exportLinks.append(`<a href="${link.url}" class="list-group-item list-group-item-action">${escapeHtml(link.name)}</a>`);
        }

        if (window.fittingManageMode) {
            $('#eftexport').show();
            exportLinks.show();
        } else {
            $('#eftexport').hide();
            exportLinks.hide();
        }

        const eveTechUrl = 'https://images.evetech.net/types';
        $('#fitting-window .collapse').removeClass('show');

        for (const slotType in result) {
            const slot = result[slotType];
            const iconUrl = `${eveTechUrl}/${slot.id}/icon?size=32`;
            const row = `<tr><td><img src="${iconUrl}" height='24' />${escapeHtml(slot.name)}</td></tr>`;

            let slotId = null;

            if (slotType.startsWith('HiSlot')) {
                slotId = 'highSlots';
            } else if (slotType.startsWith('MedSlot')) {
                slotId = 'midSlots';
            } else if (slotType.startsWith('LoSlot')) {
                slotId = 'lowSlots';
            } else if (slotType.startsWith('RigSlot')) {
                slotId = 'rigs';
            } else if (slotType.startsWith('SubSlot')) {
                slotId = 'subSlots';
            } else if (slotType.startsWith('dronebay')) {
                slotId = 'drones';
            } else if (slotType.startsWith('cargo')) {
                slotId = 'cargo';
            }

            if (slotId && slotId !== 'drones' && slotId !== 'cargo') {
                $('#' + slotId).find('tbody').append(row);
                $('#' + slotId + 'Collapse').addClass('show');
            } else if (slotId === 'drones' || slotId === 'cargo') {
                for (const itemId in result[slotType]) {
                    const item = result[slotType][itemId];
                    const itemRow = `<tr><td><img src="${eveTechUrl}/${itemId}/icon?size=32" height="24" />${escapeHtml(item.name)}</td><td>${item.qty}</td></tr>`;
                    $('#' + slotId).find('tbody').append(itemRow);
                    $('#' + slotId + 'Collapse').addClass('show');
                }
            }
        }
    }
}

function fillSkills(result) {
    const characterId = $('#characterSpinner').find(":selected").val();

    if (!characterId || !result.characters) {
        return;
    }

    const character = result.characters[characterId];

    if (!character) {
        return;
    }

    if (result.fittings) {
        fillDoctrineSkills(result, character);
        return;
    }

    $('#singleSkillResults').show();
    $('#groupSkillResults').hide().empty();

    const requirements = result.requirements || {minimum: result.skills || [], advanced: []};

    drawSkillRequirementPanel(requirements.minimum || [], character, $('#minimumSkillPanel'));
    drawSkillRequirementPanel(requirements.advanced || [], character, $('#advancedSkillPanel'));
}

function fillDoctrineSkills(result, character) {
    const groupSkillResults = $('#groupSkillResults');

    $('#singleSkillResults').hide();
    groupSkillResults.show().empty();

    for (const fitting of result.fittings) {
        const minimumPanelId = 'groupMinimumSkillPanel' + fitting.id;
        const advancedPanelId = 'groupAdvancedSkillPanel' + fitting.id;
        const requirements = fitting.requirements || {minimum: [], advanced: []};
        const fittingTitle = escapeHtml(fitting.shipType + ', ' + fitting.name);

        groupSkillResults.append(`<div class="card card-outline card-secondary mb-3">
            <div class="card-header"><h5 class="card-title mb-0">${fittingTitle}</h5></div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <h6>${escapeHtml(window.fittingMinimumLabel || 'Minimum Requirements')}</h6>
                        <div id="${minimumPanelId}"></div>
                    </div>
                    <div class="col-md-6 mb-3">
                        <h6>${escapeHtml(window.fittingAdvancedLabel || 'Advanced Requirements')}</h6>
                        <div id="${advancedPanelId}"></div>
                    </div>
                </div>
            </div>
        </div>`);

        drawSkillRequirementPanel(requirements.minimum || [], character, $('#' + minimumPanelId));
        drawSkillRequirementPanel(requirements.advanced || [], character, $('#' + advancedPanelId));
    }
}

function groupSkillsByCategory(skills) {
    const groups = {};

    for (const skill of skills) {
        const groupId = skill.groupId || 'other';
        const groupName = skill.groupName || 'Other';

        if (!groups[groupId]) {
            groups[groupId] = {
                id: groupId,
                name: groupName,
                skills: [],
                missing: false
            };
        }

        groups[groupId].skills.push(skill);
    }

    return Object.values(groups).sort(function (left, right) {
        return left.name.localeCompare(right.name);
    });
}

function drawSkillRequirementPanel(skills, character, container) {
    container.empty();

    if (!skills.length) {
        container.append('<div class="text-muted">—</div>');
        return;
    }

    const groups = groupSkillsByCategory(skills);

    for (const group of groups) {
        const groupDomId = 'skillGroup' + group.id + Math.random().toString(36).slice(2);
        const rows = [];
        let hasMissing = false;

        group.skills.sort(function (left, right) {
            return left.typeName.localeCompare(right.typeName);
        });

        for (const skill of group.skills) {
            const characterSkill = character.skill[skill.typeId] || {level: 0, rank: 1};
            const currentLevel = parseInt(characterSkill.level || 0);
            const requiredLevel = parseInt(skill.level || 0);
            const rank = parseInt(characterSkill.rank || 1);
            const missing = currentLevel < requiredLevel;

            hasMissing = hasMissing || missing;
            rows.push(drawSkillRequirementRow(skill, currentLevel, requiredLevel, rank, missing));
        }

        const showClass = hasMissing ? 'show' : '';
        const statusClass = hasMissing ? 'text-danger' : 'text-success';

        container.append(`<div class="card mb-2">
            <div class="card-header p-2">
                <button class="btn btn-link btn-block text-left p-0 ${statusClass}" type="button" data-toggle="collapse" data-target="#${groupDomId}" aria-expanded="${hasMissing ? 'true' : 'false'}">
                    ${escapeHtml(group.name)}
                </button>
            </div>
            <div id="${groupDomId}" class="collapse ${showClass}">
                <div class="card-body p-2">
                    ${rows.join('')}
                </div>
            </div>
        </div>`);
    }
}

function drawSkillRequirementRow(skill, currentLevel, requiredLevel, rank, missing) {
    let trainingTime = '';

    if (missing && requiredLevel > 0) {
        const requiredPoints = rank * 250 * Math.pow(5.66, requiredLevel - 1);
        const currentPoints = currentLevel > 0 ? rank * 250 * Math.pow(5.66, currentLevel - 1) : 0;
        trainingTime = formatTime(requiredPoints - currentPoints) || '';
    }

    return `<div class="mb-2 p-2 ${missing ? 'bg-red' : ''}">
        <div class="d-flex justify-content-between align-items-center">
            <div>${escapeHtml(skill.typeName)} <small>(x${rank})</small></div>
            <div><small>${currentLevel}/${requiredLevel} ${escapeHtml(trainingTime)}</small></div>
        </div>
        ${drawSegmentedSkillBar(currentLevel, requiredLevel)}
    </div>`;
}

function drawSegmentedSkillBar(currentLevel, requiredLevel) {
    const segments = [];
    const passed = currentLevel >= requiredLevel;

    for (let level = 1; level <= 5; level++) {
        const filled = currentLevel >= level;
        const required = requiredLevel === level;
        let colorClass = 'bg-light';

        if (filled && passed) {
            colorClass = 'bg-success';
        } else if (filled) {
            colorClass = 'bg-success';
        } else if (level <= requiredLevel) {
            colorClass = 'bg-danger';
        }

        segments.push(`<div class="${colorClass}" style="flex:1; height:12px; border:1px solid #6c757d; ${required ? 'box-shadow: inset -3px 0 0 #000;' : ''}"></div>`);
    }

    return `<div class="d-flex mt-1" style="gap:2px">${segments.join('')}</div>`;
}

function fillRequirementEditor(result) {
    $('#minimumRequirementsBody').empty();
    $('#advancedRequirementsBody').empty();

    for (const skill of result.minimum || []) {
        $('#minimumRequirementsBody').append(drawRequirementEditorRow(skill, skill.source || 'manual'));
    }

    for (const skill of result.advanced || []) {
        $('#advancedRequirementsBody').append(drawRequirementEditorRow(skill, skill.source || 'manual'));
    }
}

function drawRequirementEditorRow(skill, source) {
    const levelOptions = [1, 2, 3, 4, 5].map(function (level) {
        const selected = parseInt(skill.level) === level ? 'selected' : '';
        return `<option value="${level}" ${selected}>${level}</option>`;
    }).join('');

    return `<tr data-skill-type-id="${skill.typeId}" data-source="${source}">
        <td>${escapeHtml(skill.typeName)} <small>(${skill.typeId})</small></td>
        <td><select class="form-control form-control-sm requirementLevel">${levelOptions}</select></td>
        <td><button type="button" class="btn btn-xs btn-danger removeRequirement"><span class="fa fa-trash"></span></button></td>
    </tr>`;
}

function initializeRequirementSelectors() {
    if (!$('#requirements-box').length) {
        return;
    }

    $.getJSON('/fitting/skill-groups').done(function (groups) {
        $('.skillGroupSelect').each(function () {
            const groupSelect = $(this);
            groupSelect.empty();
            groupSelect.append('<option value=""></option>');

            for (const group of groups) {
                groupSelect.append(`<option value="${group.id}">${escapeHtml(group.text)}</option>`);
            }
        });
    });

    $('.requirementSkillSelect').each(function () {
        const skillSelect = $(this);
        const tier = skillSelect.data('tier');

        if ($.fn.select2) {
            skillSelect.select2({
                width: '100%',
                placeholder: window.fittingSkillPlaceholder || '',
                ajax: {
                    url: '/fitting/skills/search',
                    dataType: 'json',
                    delay: 250,
                    data: function (params) {
                        return {
                            q: params.term || '',
                            group_id: $('#' + tier + 'SkillGroup').val() || ''
                        };
                    },
                    processResults: function (data) {
                        return data;
                    }
                }
            });
        }
    });
}

function resetRequirementSkillSelect(tier) {
    const skillSelect = $('#' + tier + 'SkillSelect');

    if ($.fn.select2 && skillSelect.data('select2')) {
        skillSelect.val(null).trigger('change');
        return;
    }

    skillSelect.empty();

    $.getJSON('/fitting/skills/search', {
        group_id: $('#' + tier + 'SkillGroup').val() || ''
    }).done(function (data) {
        for (const skill of data.results || []) {
            skillSelect.append(`<option value="${skill.id}">${escapeHtml(skill.text)}</option>`);
        }
    });
}

function selectedRequirementSkill(tier) {
    const skillSelect = $('#' + tier + 'SkillSelect');

    if ($.fn.select2 && skillSelect.data('select2')) {
        const selected = skillSelect.select2('data')[0];

        if (!selected) {
            return null;
        }

        return {
            typeId: parseInt(selected.id),
            typeName: selected.text
        };
    }

    const option = skillSelect.find(':selected');

    if (!option.length || !option.val()) {
        return null;
    }

    return {
        typeId: parseInt(option.val()),
        typeName: option.text()
    };
}

function addRequirementToEditor(tier) {
    const skill = selectedRequirementSkill(tier);
    const level = parseInt($('#' + tier + 'SkillLevel').val());
    const body = $('#' + tier + 'RequirementsBody');

    if (!skill || !skill.typeId || !level) {
        return;
    }

    const existing = body.find(`tr[data-skill-type-id="${skill.typeId}"]`);

    if (existing.length) {
        existing.find('.requirementLevel').val(level);
        return;
    }

    body.append(drawRequirementEditorRow({
        typeId: skill.typeId,
        typeName: skill.typeName,
        level: level,
    }, 'custom'));
}

function collectRequirements(body, defaultSource) {
    const requirements = [];

    body.find('tr[data-skill-type-id]').each(function () {
        requirements.push({
            skill_type_id: parseInt($(this).data('skill-type-id')),
            level: parseInt($(this).find('.requirementLevel').val()),
            source: defaultSource || $(this).data('source') || 'manual'
        });
    });

    return requirements;
}

function formatTime(points) {
    if (!points) {
        return;
    }

    let totalHours = points / 1800;
    let days = Math.floor(totalHours / 24);
    let remainingHours = Math.floor(totalHours - (days * 24));
    let remainingMinutes = Math.floor((totalHours - (days * 24) - remainingHours) * 60);

    return `${days}d ${remainingHours}h ${remainingMinutes}m`;
}

function drawLevelBox(neededLevel, currentLevel, skillName, rank) {
    let trainingtime = '';
    let row = '';
    let pointdiff = 0;

    if (currentLevel === 0) {
        row = '<tr class="bg-red">';
        trainingtime = formatTime(rank * 250 * Math.pow(5.66, (neededLevel - 1)));
    } else if ((neededLevel - currentLevel) > 0) {
        row = '<tr class="bg-orange">';
        pointdiff = (rank * 250 * Math.pow(5.66, (neededLevel - 1))) - (rank * 250 * Math.pow(5.66, (currentLevel - 1)));
        trainingtime = formatTime(pointdiff);
    } else {
        row = '<tr>';
    }

    let graph = '<td>' + escapeHtml(skillName) + ' <small>(x' + rank + ')</small></td>';
    graph += '<td style="width: 11em"><div style="background-color: transparent; width: 5.5em; text-align: center; height: 1.35em; letter-spacing: 2.25px;" class="pull-right">';

    if (currentLevel >= neededLevel) {
        for (let i = 0; i < neededLevel; i++) {
            graph = graph + '<span class="fa fa-square " style="vertical-align: text-top; color: #5ac597;"></span>';
        }
        for (let i = neededLevel; i < currentLevel; i++) {
            graph = graph + '<span class="fa fa-square text-green" style="vertical-align: text-top"></span>';
        }
        for (let i = 0; i < (5 - currentLevel); i++) {
            graph = graph + '<span class="fa fa-circle text-green" style="vertical-align: text-top"></span>';
        }
    } else {
        for (let i = 0; i < currentLevel; i++) {
            graph = graph + '<span class="fa fa-square " style="vertical-align: text-top; color: #5ac597;"></span>';
        }
        for (let i = 0; i < (neededLevel - currentLevel); i++) {
            graph = graph + '<span class="fa fa-circle text-danger" style="vertical-align: text-top"></span>';
        }
        for (let i = 0; i < (5 - neededLevel); i++) {
            graph = graph + '<span class="fa fa-circle text-green" style="vertical-align: text-top"></span>';
        }
    }

    graph += '</div><span class="pull-right"><small>' + (trainingtime || '') + '</small> </span></td></tr>';
    graph += '</td>';

    return row + graph + '</tr>';
}
