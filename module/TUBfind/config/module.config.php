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
            'TUBfind\ILS\Driver\PluginManager' => 'VuFind\ServiceManager\AbstractPluginManagerFactory',
            'TUBfind\Content\Covers\PluginManager' => 'VuFind\ServiceManager\AbstractPluginManagerFactory',
            'TUBfind\Cover\Loader' => 'TUBfind\Cover\LoaderFactory',
            'VuFind\Cover\Loader' => 'TUBfind\Cover\LoaderFactory',
         ],
        'aliases' => [
            'VuFind\ILSDriverPluginManager' => 'TUBfind\ILS\Driver\PluginManager',
            'VuFind\ContentCoversPluginManager' => 'TUBfind\Content\Covers\PluginManager',
            'TUBfind\ContentCoversPluginManager' => 'TUBfind\Content\Covers\PluginManager',
        ]
    ],
    // This section contains all VuFind-specific settings (i.e. configurations
    // unrelated to specific Zend Framework 2 components).
    'vufind' => [
        // The config reader is a special service manager for loading .ini files:
        'config_reader' => [ /* see VuFind\Config\PluginManager for defaults */ ],
        // This section contains service manager configurations for all VuFind
        // pluggable components:
        'plugin_managers' => [
            'ajaxhandler' => [ /* see VuFind\AjaxHandler\PluginManager for defaults */ ],
            'content_covers' => [ /* see VuFind\Content\Covers\PluginManager for defaults */ ],
        ]
    ]
 ];

return $config;
