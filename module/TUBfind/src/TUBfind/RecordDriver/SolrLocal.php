<?php
/**
 * Model for GBV MARC records in Solr.
 *
 * PHP version 5
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
 * Foundation, Inc., 59 Temple Place, Suite 330, Boston, MA  02111-1307  USA
 *
 * @category VuFind2
 * @package  RecordDrivers
 * @author   Oliver Goldschmidt <o.goldschmidt@tuhh.de>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org/wiki/vufind2:record_drivers Wiki
 */
namespace TUBfind\RecordDriver;
use VuFind\Exception\ILS as ILSException,
    VuFind\View\Helper\Root\RecordLink,
    VuFind\XSLT\Processor as XSLTProcessor;

//use Zend\ServiceManager\ServiceLocatorAwareInterface;
//use Zend\ServiceManager\ServiceLocatorInterface;

use VuFindSearch\Backend\Solr\Backend;
use VuFindSearch\Query\Query;
use VuFindSearch\ParamBag;

use VuFind\MultipartList;

use VuFind\Search\Factory\PrimoBackendFactory;
use VuFind\Search\Factory\SolrDefaultBackendFactory;

/**
 * Model for MARC records in Solr.
 *
 * @category VuFind2
 * @package  RecordDrivers
 * @author   Oliver Goldschmidt <o.goldschmidt@tuhh.de>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org/wiki/vufind2:record_drivers Wiki
 */
class SolrLocal extends \TUBfind\RecordDriver\SolrGBV
{
    /**
     * determines mylib setting
     *
     * @access  protected
     * @return  string
     */
    protected function getMyLibraryCode()
    {
        $mylib = "GBV_ILN_23";
    }

    /**
     * determines if this item is in the local stock
     *
     * @access protected
     * @return boolean
     */
    public function checkLocalStock()
    {
        return true;
    }

    /**
     * determines if this item is in the local stock by checking the index
     *
     * @access protected
     * @return boolean
     */
    public function checkLocalStockInIndex()
    {
        return true;
    }

    /**
     * checks if this item needs interlibrary loan
     *
     * @access protected
     * @return string
     */
    public function checkInterlibraryLoan()
    {
        return '0';
    }

    /**
     * checks if this item needs interlibrary loan
     *
     * @access protected
     * @return string
     */
    public function checkAcquisitionProposal()
    {
        return '0';
    }

    /**
     * checks if this item is licensed
     *
     * @access protected
     * @return boolean
     */
    public function licenseAvailable()
    {
        return true;
    }

    /**
     * checks if this item needs to be licensed
     *
     * @access protected
     * @return boolean
     */
    public function needsLicense()
    {
        // Is this item in local stock?
        if (in_array('eBook', $this->getFormats()) === true || in_array('eJournal', $this->getFormats()) === true) {
            return true;
        }

        return false;
    }

    /**
     * Check if at least one volume for this item exists.
     * Used to detect wheter or not the volume tab needs to be displayed
     *
     * @return bool
     * @access public
     */
    public function isMultipartChildren()
    {
        return false;
    }

    /**
     * Determine if we have a national license hit
     *
     * @return boolean is this a national license hit?
     * @access protected
     */
    protected function isNLZ() {
        return false;
    }

}
