<?php
/**
 * Related record plugin manager
 *
 * PHP version 7
 *
 * Copyright (C) Villanova University 2010.
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License version 2,
 * as published by the Free Software Foundation.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program; if not, write to the Free Software
 * Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301  USA
 *
 * @category VuFind
 * @package  Related_Records
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:plugins:related_records_modules Wiki
 */
namespace TUBfind\Related;

use Laminas\ServiceManager\Factory\InvokableFactory;

/**
 * Related record plugin manager
 *
 * @category VuFind
 * @package  Related_Records
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:plugins:related_records_modules Wiki
 */
class PluginManager extends \VuFind\Related\PluginManager
{
    /**
     * Default plugin aliases.
     *
     * @var array
     */
    protected $aliases = [
        'channels' => \VuFind\Related\Channels::class,
        'editions' => \VuFind\Related\Deprecated::class,
        'similar' => \VuFind\Related\Similar::class,
        'worldcateditions' => \VuFind\Related\Deprecated::class,
        'worldcatsimilar' => \VuFind\Related\WorldCatSimilar::class,
        'primofrbr' => \TUBfind\Related\PrimoFrbr::class,
        'similarindex' => \TUBfind\Related\SimilarIndex::class,
    ];

    /**
     * Default plugin factories.
     *
     * @var array
     */
    protected $factories = [
        \VuFind\Related\Channels::class => \VuFind\Related\InvokableFactory::class,
        \VuFind\Related\Deprecated::class => \VuFind\Related\InvokableFactory::class,
        \VuFind\Related\Similar::class => \VuFind\Related\SimilarFactory::class,
        \VuFind\Related\WorldCatSimilar::class => \VuFind\Related\SimilarFactory::class,
        \TUBfind\Related\PrimoFrbr::class => \VuFind\Related\SimilarFactory::class,
        \TUBfind\Related\SimilarIndex::class => \TUBfind\Related\SimilarFactory::class,
    ];

    /**
     * Constructor
     *
     * Make sure plugins are properly initialized.
     *
     * @param mixed $configOrContainerInstance Configuration or container instance
     * @param array $v3config                  If $configOrContainerInstance is a
     * container, this value will be passed to the parent constructor.
     */
    public function __construct($configOrContainerInstance = null,
        array $v3config = []
    ) {
        // These objects are not meant to be shared -- every time we retrieve one,
        // we are building a brand new object.
        $this->sharedByDefault = false;
        $this->addAbstractFactory(\VuFind\Related\PluginFactory::class);
        parent::__construct($configOrContainerInstance, $v3config);
    }

    /**
     * Return the name of the base class or interface that plug-ins must conform
     * to.
     *
     * @return string
     */
    protected function getExpectedInterface()
    {
        return \VuFind\Related\RelatedInterface::class;
    }
}
