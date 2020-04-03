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
            'TUBfind\RecordTab\PluginManager' => 'VuFind\ServiceManager\AbstractPluginManagerFactory',
            'TUBfind\ILS\Logic\Holds' => 'VuFind\ILS\Logic\LogicFactory',
            'TUBfind\Content\Covers\PluginManager' => 'VuFind\ServiceManager\AbstractPluginManagerFactory',
            'VuFind\Cover\Loader' => 'TUBfind\Cover\LoaderFactory',
            'VuFind\Record\Loader' => 'VuFind\Record\LoaderFactory',
            'TUBfind\Recommend\PluginManager' => 'VuFind\ServiceManager\AbstractPluginManagerFactory',
          ],
        'aliases' => [
            'VuFind\ILSDriverPluginManager' => 'TUBfind\ILS\Driver\PluginManager',
            'VuFind\ILS\Driver\PluginManager' => 'TUBfind\ILS\Driver\PluginManager',
            'VuFind\RecordDriver\PluginManager' => 'TUBfind\RecordDriver\PluginManager',
            'VuFind\RecordTab\PluginManager' => 'TUBfind\RecordTab\PluginManager',
            'TUBfind\ILSHoldLogic' => 'TUBfind\ILS\Logic\Holds',
            'VuFind\ContentCoversPluginManager' => 'TUBfind\Content\Covers\PluginManager',
            'TUBfind\ContentCoversPluginManager' => 'TUBfind\Content\Covers\PluginManager',
            'VuFind\RecommendPluginManager' => 'TUBfind\Recommend\PluginManager',
            'VuFind\Recommend\PluginManager' => 'TUBfind\Recommend\PluginManager',
        ]
    ],
 ];

return $config;
