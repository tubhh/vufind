<?php
/**
 * Ajax Controller for Libraries Extension
 *
 * PHP version 5
 *
 * Copyright (C) Staats- und Universitätsbibliothek 2017.
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
 * Foundation, Inc., 59 Temple Place, Suite 330, Boston, MA  02111-1307  USA
 *
 * @category VuFind2
 * @package  Controller
 * @author   Hajo Seng <hajo.seng@sub.uni-hamburg.de>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://github.com/subhh/beluga
 */
namespace TUBfind\AjaxHandler;

use VuFind\AjaxHandler\AbstractBase;
use VuFind\Search\Results\PluginManager as ResultsManager;
use TUBfind\Content\MultipartList;
use Zend\Mvc\Controller\Plugin\Params;
use Zend\Stdlib\Parameters;
use Zend\Config\Config;

/**
 * This controller handles global AJAX functionality
 *
 * @category VuFind2
 * @package  Controller
 * @author   Chris Hallberg <challber@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org/wiki/vufind2:building_a_controller Wiki
 */
class LoadVolumeList extends AbstractBase
{
    /**
     * ResultsManager
     *
     * @var ResultsManager
     */
    protected $loader;

    /**
     * Constructor
     *
     * @param ServiceLocatorInterface $sm Service locator
     */
    public function __construct(\VuFind\Record\Loader $loader)
    {
        $this->loader = $loader;
    }

    /**
     * Handle a request.
     *
     * @param Params $params Parameter helper from controller
     *
     * @return array [response data, HTTP status code]
     */
    public function handleRequest(Params $params)
    {
        $retval = array();
        $id = $params->fromPost('id', $params->fromQuery('id', ''));
            $driver = $this->loader->load($id);
            $retval = $driver->getMultipartChildrenArray();
        $mpList = new MultipartList($id);
/*        if ($mpList->hasList()) {
            $retval = $mpList->getCachedMultipartChildren();
        }
        else {
            $this->loadVolumeListAjax($id);
            // call this method recursively - now we should have the cached result
            return $this->handleRequest($params);
        }
*/
        return $this->formatResponse($retval);
    }

    /**
     * Load information about multivolumes for this item
     *
     * @return bool
     */
    protected function loadVolumeListAjax($id)
    {
        $mpList = new MultipartList($id);
        if (!$mpList->hasList()) {
            $driver = $this->loader->load($id);
            $driver->cacheMultipartChildren();
        }
        return true;
    }

}
