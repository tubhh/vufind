<?php
namespace TUBfind\Module\Configuration;

$config = [
    'controllers' => [
        'factories' => [
            'TUBfind\Controller\AjaxController' => 'TUBfind\Controller\AjaxControllerFactory',
        ],
        'aliases' => [
            'AJAX' => 'TUBfind\Controller\AjaxController',
            'ajax' => 'TUBfind\Controller\AjaxController'
        ]
    ],
    'service_manager' => [
        'allow_override' => true,
        'factories' => [
            'TUBfind\AjaxHandler\PluginManager' => 'VuFind\ServiceManager\AbstractPluginManagerFactory',
            'TUBfind\ILS\Connection' => 'TUBfind\ILS\ConnectionFactory',
            'TUBfind\ILS\Driver\PluginManager' => 'VuFind\ServiceManager\AbstractPluginManagerFactory',
            'TUBfind\RecordDriver\PluginManager' => 'VuFind\ServiceManager\AbstractPluginManagerFactory',
          ],
        'aliases' => [
            'VuFind\ILSDriverPluginManager' => 'TUBfind\ILS\Driver\PluginManager',
            'VuFind\ILS\Driver\PluginManager' => 'TUBfind\ILS\Driver\PluginManager',
            'VuFind\RecordDriver\PluginManager' => 'TUBfind\RecordDriver\PluginManager',
        ]
    ],
 ];

return $config;
