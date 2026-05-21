<?php

Route::group([
    'namespace' => 'CryptaTech\Seat\Fitting\Http\Controllers',
    'middleware' => ['web', 'auth'],
    'prefix' => 'api/v2/fitting/web',
], function () {
    Route::get('/fitting/list', [
        'as' => 'cryptafitting::api.web.fitting.list',
        'uses' => 'ApiFittingController@getFittingList',
    ]);
    Route::get('/fitting/get/{id}', [
        'as' => 'cryptafitting::api.web.fitting.get',
        'uses' => 'ApiFittingController@getFittingById',
    ]);
    Route::get('/doctrine/list', [
        'as' => 'cryptafitting::api.web.doctrine.list',
        'uses' => 'ApiFittingController@getDoctrineList',
    ]);
    Route::get('/doctrine/get/{id}', [
        'as' => 'cryptafitting::api.web.doctrine.get',
        'uses' => 'ApiFittingController@getDoctrineById',
    ]);
});

Route::group([
    'namespace' => 'CryptaTech\Seat\Fitting\Http\Controllers',
    'prefix' => 'fitting',
], function () {
    Route::group([
        'middleware' => ['web', 'auth', 'locale'],
    ], function () {
        Route::get('/', [
            'as' => 'cryptafitting::view',
            'uses' => 'FittingController@getFittingView',
            'middleware' => 'can:fitting.view',
        ]);
        Route::post('/postfitting', [
            'as' => 'cryptafitting::postFitting',
            'uses' => 'FittingController@postFitting',
            'middleware' => 'can:fitting.view',
        ]);
        Route::post('/postskills', [
            'as' => 'cryptafitting::postSkills',
            'uses' => 'FittingController@postSkills',
            'middleware' => 'can:fitting.view',
        ]);
        Route::get('/manage', [
            'as' => 'cryptafitting::manage',
            'uses' => 'FittingController@getManageView',
            'middleware' => 'can:fitting.create',
        ]);
        Route::post('/savefitting', [
            'as' => 'cryptafitting::saveFitting',
            'uses' => 'FittingController@saveFitting',
            'middleware' => 'can:fitting.create',
        ]);
        Route::get('/tree', [
            'as' => 'cryptafitting::fitTree',
            'uses' => 'FittingController@getFittingTree',
            'middleware' => 'can:fitting.view',
        ]);
        Route::get('/skill-groups', [
            'as' => 'cryptafitting::skillGroups',
            'uses' => 'FittingController@getSkillGroups',
            'middleware' => 'can:fitting.create',
        ]);
        Route::get('/skills/search', [
            'as' => 'cryptafitting::skillSearch',
            'uses' => 'FittingController@searchSkills',
            'middleware' => 'can:fitting.create',
        ]);
        Route::get('/item-skills/{typeId}', [
            'as' => 'cryptafitting::itemSkills',
            'uses' => 'FittingController@getItemSkills',
            'middleware' => 'can:fitting.create',
        ]);
        Route::get('/{id}/requirements', [
            'as' => 'cryptafitting::requirements',
            'uses' => 'FittingController@getFittingRequirements',
            'middleware' => 'can:fitting.create',
        ]);
        Route::post('/{id}/requirements', [
            'as' => 'cryptafitting::saveRequirements',
            'uses' => 'FittingController@saveFittingRequirements',
            'middleware' => 'can:fitting.create',
        ]);
        Route::get('/getfittingbyid/{id}', [
            'uses' => 'FittingController@getFittingById',
            'middleware' => 'can:fitting.view',
        ]);
        Route::get('/getdoctrinebyid/{id}', [
            'as' => 'cryptafitting::getDoctrineById',
            'uses' => 'FittingController@getDoctrineById',
            'middleware' => 'can:fitting.doctrineview',
        ]);
        Route::get('/geteftfittingbyid/{id}', [
            'uses' => 'FittingController@getEftFittingById',
            'middleware' => 'can:fitting.view',
        ]);
        Route::get('/getskillsbyfitid/{id}', [
            'uses' => 'FittingController@getSkillsByFitId',
            'middleware' => 'can:fitting.view',
        ]);
        Route::get('/getskillsbydoctrineid/{id}', [
            'uses' => 'FittingController@getSkillsByDoctrineId',
            'middleware' => 'can:fitting.view',
        ]);
        Route::get('/delfittingbyid/{id}', [
            'uses' => 'FittingController@deleteFittingById',
            'middleware' => 'can:fitting.create',
        ]);
        Route::get('/doctrine', [
            'as' => 'cryptafitting::doctrineview',
            'uses' => 'FittingController@getDoctrineView',
            'middleware' => 'can:fitting.doctrineview',
        ]);
        Route::get('/doctrine/{doctrine_id}', [
            'as' => 'fitting.doctrineviewdetails',
            'uses' => 'FittingController@getDoctrineView',
            'middleware' => 'can:fitting.doctrineview',
        ]);
        Route::get('/fittinglist', [
            'as' => 'cryptafitting::fitlist',
            'uses' => 'FittingController@getFittingList',
            'middleware' => 'can:fitting.view',
        ]);
        Route::post('/addDoctrine', [
            'as' => 'cryptafitting::addDoctrine',
            'uses' => 'FittingController@saveDoctrine',
            'middleware' => 'can:fitting.create',
        ]);
        Route::get('/doctrine-workspace', [
            'as' => 'cryptafitting::doctrineWorkspace',
            'uses' => 'FittingController@getDoctrineWorkspace',
            'middleware' => 'can:fitting.doctrineview',
        ]);
        Route::post('/doctrine', [
            'as' => 'cryptafitting::createDoctrine',
            'uses' => 'FittingController@createDoctrine',
            'middleware' => 'can:fitting.create',
        ]);
        Route::patch('/doctrine/{id}', [
            'as' => 'cryptafitting::renameDoctrine',
            'uses' => 'FittingController@renameDoctrine',
            'middleware' => 'can:fitting.create',
        ]);
        Route::post('/doctrine/{id}/lock', [
            'as' => 'cryptafitting::toggleDoctrineLock',
            'uses' => 'FittingController@toggleDoctrineLock',
            'middleware' => 'can:fitting.lock_doctrine',
        ]);
        Route::delete('/doctrine/{id}', [
            'as' => 'cryptafitting::deleteDoctrine',
            'uses' => 'FittingController@deleteDoctrine',
            'middleware' => 'can:fitting.create',
        ]);
        Route::post('/doctrine/{id}/fittings/{fittingId}', [
            'as' => 'cryptafitting::attachFittingToDoctrine',
            'uses' => 'FittingController@attachFittingToDoctrine',
            'middleware' => 'can:fitting.create',
        ]);
        Route::delete('/doctrine/{id}/fittings/{fittingId}', [
            'as' => 'cryptafitting::detachFittingFromDoctrine',
            'uses' => 'FittingController@detachFittingFromDoctrine',
            'middleware' => 'can:fitting.create',
        ]);
        Route::get('/getdoctrineedit/{id}', [
            'as' => 'cryptafitting::getDoctrineEdit',
            'uses' => 'FittingController@getDoctrineEdit',
            'middleware' => 'can:fitting.create',
        ]);
        Route::get('/deldoctrinebyid/{id}', [
            'as' => 'cryptafitting::delDoctrineById',
            'uses' => 'FittingController@delDoctrineById',
            'middleware' => 'can:fitting.create',
        ]);
        Route::get('/doctrineReport', [
            'as' => 'cryptafitting::doctrinereport',
            'uses' => 'FittingController@viewDoctrineReport',
            'middleware' => 'can:fitting.reportview',
        ]);
        Route::post('/runReport', [
            'as' => 'cryptafitting::runreport',
            'uses' => 'FittingController@runReport',
            'middleware' => 'can:fitting.reportview',
        ]);
        Route::get('/fleetReview', [
            'as' => 'cryptafitting::fleetreview',
            'uses' => 'FittingController@viewFleetReview',
            'middleware' => 'can:fitting.fleet_review',
        ]);
        Route::post('/runFleetReview', [
            'as' => 'cryptafitting::runfleetreview',
            'uses' => 'FittingController@runFleetReview',
            'middleware' => 'can:fitting.fleet_review',
        ]);

        /* Auxiliary skill plans */
        Route::get('/plans', [
            'as' => 'cryptafitting::plans.list',
            'uses' => 'FittingController@listPlans',
            'middleware' => 'can:fitting.view',
        ]);
        Route::post('/plans/preview', [
            'as' => 'cryptafitting::plans.preview',
            'uses' => 'FittingController@previewPlan',
            'middleware' => 'can:fitting.create',
        ]);
        Route::post('/plans', [
            'as' => 'cryptafitting::plans.create',
            'uses' => 'FittingController@createPlan',
            'middleware' => 'can:fitting.create',
        ]);
        Route::get('/plans/{id}', [
            'as' => 'cryptafitting::plans.get',
            'uses' => 'FittingController@getPlan',
            'middleware' => 'can:fitting.view',
        ]);
        Route::patch('/plans/{id}', [
            'as' => 'cryptafitting::plans.update',
            'uses' => 'FittingController@updatePlan',
            'middleware' => 'can:fitting.create',
        ]);
        Route::delete('/plans/{id}', [
            'as' => 'cryptafitting::plans.delete',
            'uses' => 'FittingController@deletePlan',
            'middleware' => 'can:fitting.create',
        ]);
        Route::post('/plans/{id}/fittings/{fittingId}', [
            'as' => 'cryptafitting::plans.attachFitting',
            'uses' => 'FittingController@attachPlanToFitting',
            'middleware' => 'can:fitting.create',
        ]);
        Route::delete('/plans/{id}/fittings/{fittingId}', [
            'as' => 'cryptafitting::plans.detachFitting',
            'uses' => 'FittingController@detachPlanFromFitting',
            'middleware' => 'can:fitting.create',
        ]);
        Route::post('/plans/{id}/fittings/{fittingId}/doctrines/{doctrineId}', [
            'as' => 'cryptafitting::plans.attachFittingInDoctrine',
            'uses' => 'FittingController@attachPlanToFittingInDoctrine',
            'middleware' => 'can:fitting.create',
        ]);
        Route::delete('/plans/{id}/fittings/{fittingId}/doctrines/{doctrineId}', [
            'as' => 'cryptafitting::plans.detachFittingInDoctrine',
            'uses' => 'FittingController@detachPlanFromFittingInDoctrine',
            'middleware' => 'can:fitting.create',
        ]);
        Route::post('/plans/{id}/doctrines/{doctrineId}', [
            'as' => 'cryptafitting::plans.attachDoctrine',
            'uses' => 'FittingController@attachPlanToDoctrine',
            'middleware' => 'can:fitting.create',
        ]);
        Route::delete('/plans/{id}/doctrines/{doctrineId}', [
            'as' => 'cryptafitting::plans.detachDoctrine',
            'uses' => 'FittingController@detachPlanFromDoctrine',
            'middleware' => 'can:fitting.create',
        ]);

        /* Fitting copy / rename */
        Route::post('/fittings/{id}/copy', [
            'as' => 'cryptafitting::copyFitting',
            'uses' => 'FittingController@copyFitting',
            'middleware' => 'can:fitting.create',
        ]);
        Route::patch('/fittings/{id}', [
            'as' => 'cryptafitting::renameFitting',
            'uses' => 'FittingController@renameFitting',
            'middleware' => 'can:fitting.create',
        ]);
    });
});
