<?php
/**
 * Covers content loader plugin manager
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
 * @package  Content
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:plugins:hierarchy_components Wiki
 */
namespace TUBfind\Content\Covers;

use Laminas\ServiceManager\Factory\InvokableFactory;

/**
 * Covers content loader plugin manager
 *
 * @category VuFind
 * @package  Content
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:plugins:hierarchy_components Wiki
 */
class PluginManager extends \VuFind\Content\Covers\PluginManager
{
    /**
     * Default plugin aliases.
     *
     * @var array
     */
    protected $aliases = [
        'gbv' => 'TUBfind\Content\Covers\GBV',
        'google' => 'VuFind\Content\Covers\Google',
        'librarything' => 'VuFind\Content\Covers\LibraryThing',
        'localfile' => 'VuFind\Content\Covers\LocalFile',
        'openlibrary' => 'VuFind\Content\Covers\OpenLibrary'
    ];

    /**
     * Default plugin factories.
     *
     * @var array
     */
    protected $factories = [
        'TUBfind\Content\Covers\GBV' => 'TUBfind\Content\Covers\GBVFactory',
        'VuFind\Content\Covers\Google' =>
            'Laminas\ServiceManager\Factory\InvokableFactory',
        'VuFind\Content\Covers\LibraryThing' =>
            'Laminas\ServiceManager\Factory\InvokableFactory',
        'VuFind\Content\Covers\LocalFile' =>
            'Laminas\ServiceManager\Factory\InvokableFactory',
        'VuFind\Content\Covers\OpenLibrary' =>
            'Laminas\ServiceManager\Factory\InvokableFactory',
    ];

    /**
     * Return the name of the base class or interface that plug-ins must conform
     * to.
     *
     * @return string
     */
    protected function getExpectedInterface()
    {
        return \VuFind\Content\AbstractCover::class;
    }
}
