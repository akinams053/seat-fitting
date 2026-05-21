<?php

return [
    'doctrine' => [
        'name' => 'Fitting Checks',
        'label' => 'fitting::config.menu_title',
        'permission' => 'fitting.view',
        'route_segment' => 'fitting',
        'icon' => 'fas fa-rocket',
        'entries' => [
            'fitting' => [
                'label' => 'fitting::config.menu_fitting',
                'name' => 'Personal Fitting Check',
                'icon' => 'fas fa-rocket',
                'route_segment' => 'fitting',
                'route' => 'cryptafitting::view',
                'permission' => 'fitting.view',
            ],
            'manage' => [
                'label' => 'fitting::config.menu_fitting_manage',
                'name' => 'Fitting Management',
                'icon' => 'fas fa-edit',
                'route_segment' => 'fitting',
                'route' => 'cryptafitting::manage',
                'permission' => 'fitting.create',
            ],
            'doctrine' => [
                'label' => 'fitting::config.menu_doctrines',
                'name' => 'Fitting Groups',
                'icon' => 'fas fa-list',
                'route_segment' => 'fitting',
                'route' => 'cryptafitting::doctrineview',
                'permission' => 'fitting.doctrineview',
            ],
            'doctrinereport' => [
                'label' => 'fitting::config.menu_doctrine_report',
                'name' => 'Corporation Skill Check',
                'icon' => 'fas fa-chart-pie',
                'route_segment' => 'fitting',
                'route' => 'cryptafitting::doctrinereport',
                'permission' => 'fitting.reportview',
            ],
            'fleetreview' => [
                'label' => 'fitting::config.menu_fleet_review',
                'name' => 'Fleet Review',
                'icon' => 'fas fa-users',
                'route_segment' => 'fitting',
                'route' => 'cryptafitting::fleetreview',
                'permission' => 'fitting.fleet_review',
            ],
        ],
    ],
];
