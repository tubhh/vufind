<?php
/**
 * AJAX handler plugin manager
 *
 * PHP version 7
 *
 * Copyright (C) Villanova University 2018.
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
 * @package  AJAX
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development Wiki
 */
namespace TUBfind\AjaxHandler;

/**
 * AJAX handler plugin manager
 *
 * @category VuFind
 * @package  AJAX
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development Wiki
 */
class PluginManager extends \VuFind\AjaxHandler\PluginManager
{
    /**
     * Default plugin aliases.
     *
     * @var array
     */
    protected $aliases = [
        'getItemStatuses' => 'TUBfind\AjaxHandler\GetItemStatuses',
        'getResultCount' => 'TUBfind\AjaxHandler\GetResultCount',
        'getUserFines' => 'VuFind\AjaxHandler\GetUserFines',
        'getUserTransactions' => 'VuFind\AjaxHandler\GetUserTransactions',
        'getUserHolds' => 'VuFind\AjaxHandler\GetUserHolds',
        'getUserStorageRetrievalRequests' => 'VuFind\AjaxHandler\GetUserStorageRetrievalRequests',
        'getSaveStatuses' => 'VuFind\AjaxHandler\GetSaveStatuses',
        'loadVolumeList' => 'TUBfind\AjaxHandler\LoadVolumeList'
    ];

    /**
     * Default plugin factories.
     *
     * @var array
     */
    protected $factories = [
        'TUBfind\AjaxHandler\GetItemStatuses' => 'TUBfind\AjaxHandler\GetItemStatusesFactory',
        'TUBfind\AjaxHandler\GetResultCount' => 'TUBfind\AjaxHandler\GetResultCountFactory',
        'VuFind\AjaxHandler\GetUserFines' => 'VuFind\AjaxHandler\GetUserFinesFactory',
        'VuFind\AjaxHandler\GetUserTransactions' => 'VuFind\AjaxHandler\AbstractIlsAndUserActionFactory',
        'VuFind\AjaxHandler\GetUserHolds' => 'VuFind\AjaxHandler\AbstractIlsAndUserActionFactory',
        'VuFind\AjaxHandler\GetUserStorageRetrievalRequests' => 'VuFind\AjaxHandler\AbstractIlsAndUserActionFactory',
        'VuFind\AjaxHandler\GetSaveStatuses' => 'VuFind\AjaxHandler\GetSaveStatusesFactory',
        'TUBfind\AjaxHandler\LoadVolumeList' => 'TUBfind\AjaxHandler\LoadVolumeListFactory'
    ];

}
