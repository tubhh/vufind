<?php
/**
 * ILS Driver for VuFind to get information from PAIA
 *
 * PHP version 5
 *
 * Copyright (C) Oliver Goldschmidt, Magda Roos, Till Kinstler 2013, 2014.
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
 * @category VuFind
 * @package  ILS_Drivers
 * @author   Oliver Goldschmidt <o.goldschmidt@tuhh.de>
 * @author   Magdalena Roos <roos@gbv.de>
 * @author   Till Kinstler <kinstler@gbv.de>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org/wiki/building_an_ils_driver Wiki
 */

namespace TUBfind\ILS\Driver;
use DOMDocument, VuFind\Exception\ILS as ILSException;
use TUBfind\Auth\LDAP;

/**
 * Extends generic PAIA driver with methods to get additional data from PICA LBS systems
 *
 * Holding information is obtained by DAIA, so it's not necessary to implement those
 * functions here; we just need to extend the DAIA driver.
 *
 * @category VuFind
 * @package  ILS_Drivers
 * @author   Oliver Goldschmidt <o.goldschmidt@tuhh.de>
 * @author   Magdalena Roos <roos@gbv.de>
 * @author   Till Kinstler <kinstler@gbv.de>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org/wiki/building_an_ils_driver Wiki
 */
class PAIA extends \TUBfind\ILS\Driver\DAIA
{
    /**
     * Constructor
     *
     * @param \VuFind\Date\Converter $converter Date converter
     */
    public function __construct(\VuFind\Date\Converter $converter, \Zend\Session\SessionManager $sessionManager)
    {
        parent::__construct($converter, $sessionManager);
#        $this->recordLoader = $loader;
#        $this->ldapConfig = $config;
    }

    /**
     * Set Language
     *
     * This is responsible for setting the language we want to get from the DAIA server.
     *
     * @param string $lang The language code
     *
     * @return void
     */
    public function setLanguage($lang)
    {
        $this->language = $lang;
    }

    /**
     * Gets additional array fields for the item
     *
     * @param array $fee    The fee array from PAIA
     * @param array $patron The patron having these fees
     *
     * @return array Additional fee data for the item
     */
    protected function getAdditionalFeeData($fee, $patron = null)
    {
        $additionalData = parent::getAdditionalFeeData($fee);

        // Add the item title using the about field,
        // but only if this fee is caused by some item
        if (isset($fee['item'])) {
            $additionalData['title'] = $fee['about'];
        }

        // Special treatment for several feetypes
        // perhaps move this to customized driver as its pretty library specific
        $additionalData['driver']     = (isset($fee['edition'])
            ? $this->getRecordDriver($fee['edition']) : null);
        $additionalData['item']       = (isset($fee['item'])
            ? $fee['item'] : null);
        $additionalData['barcode']    = (isset($fee['item'])
            ? $this->getPaiaItemBarcode($fee['item']) : null);
        switch ($fee['feetypeid']) {
            case 'de-830:fee-type:2':
            case 'de-830:fee-type:3':
                $additionalData['title'] = $fee['about'];
                break;
            case 'de-830:fee-type:8':
                // the original duedate is in the about field inside [] (for GBV PAIA)
                // the return date is the date of creation of the fees
                $about = explode('[', $fee['about']);
                $additionalData['title'] = $about[0];
                // check the fee date: on 2016-04-01 the fine agreement changed
                if (isset($fee['date']) && $fee['date'] <= "2016-04-01") {
                    $additionalData['duedate'] = $this->convertDate(str_replace(']', '', $about[1]));
                    $additionalData['returndate'] = $this->convertDate($fee['date']);
                }
                break;
            default:
                // Title not necessary for this fee type
                $additionalData['title'] = null;
        }

        if (isset($fee['item'])) {
            $lent_items = $this->getMyTransactions($patron);
            // check if this item has been returned
            foreach ($lent_items as $lent_item) {
                if ($lent_item['item_id'] == $fee['item']) {
                    $additionalData['loandate'] = $this->convertDate($lent_item['startTime']);
                    // The item has been prolonged after its duedate
                    // Currently its not overdue
                    if ($lent_item['dueTime'] < date("Y-m-d")) {
                        $additionalData['overdue_duedate'] = $this->convertDate($lent_item['dueTime']);
                    }
                }
            }
        }

        // custom PAIA fields
        $additionalData['feetypeid']  = (isset($fee['feetypeid'])
            ? $fee['feetypeid'] : null);

        return $additionalData;
    }

    /** 
     * Get a record driver object by a PAIA item ID
     *
     * @param string $item The item ID string
     *
     * @return \VuFind\RecordDriver A record driver for the given item if applicable.
     */
    protected function getRecordDriver($item) {
        $ppn = $this->getPaiaItemPpn($item);
        $recordDriver = ($ppn) ? $this->recordLoader->load($ppn) : null;
        return $recordDriver;
    }

    /**
     * Get the barcode of a PAIA item
     *
     * @param string $item The item ID string
     *
     * @return string The barcode for the given item if applicable.
     */
    protected function getPaiaItemBarcode($item) {
        $itemArray = explode(':', $item);
        $barcode = (count($itemArray) >= 2 && $itemArray[(count($itemArray)-2)] == 'bar') ? $itemArray[(count($itemArray)-1)] : null;
        return $barcode;
    }

    /**
     * Get the PPN of a PAIA item
     *
     * @param string $item The item ID string
     *
     * @return string The PPN for the given item if applicable.
     */
    protected function getPaiaItemPpn($item) {
        $itemArray = explode(':', $item);
        $ppn = (count($itemArray) >= 2 && $itemArray[(count($itemArray)-2)] == 'ppn') ? $itemArray[(count($itemArray)-1)] : null;
        return $ppn;
    }

    /**
     * PAIA support method to retrieve needed ItemId in case PAIA-response does not
     * contain it
     *
     * @param string $id itemId
     *
     * @return string $id
     */
    protected function getAlternativeItemId($id)
    {
        return $this->getPaiaItemPpn($id);
    }

    /**
     * Get Patron Profile
     *
     * This is responsible for retrieving the profile for a specific patron.
     *
     * @param array $user The patron array
     *
     * @return array      Array of the patron's profile data
     *
     * @access public
     */
    public function getMyProfile($user)
    {
        $recordList = parent::getMyProfile($user);

        try {
            $ui = new LDAP();
            $ui->setConfig($this->ldapConfig);
            $userinfo = $ui->getUserdata($user['cat_username'], $user['cat_password']);
        }
        catch (Exception $e) {
            $recordList['error'] = 'Cannot get data from LDAP';
        }
        finally {
            $recordList['email'] = $userinfo['email'];
            $recordList['zip'] = $userinfo['zip'];
            $recordList['address1'] = $userinfo['street'];
            $recordList['city'] = $userinfo['city'];
            $recordList['language'] = $userinfo['language'];
            $recordList['phone'] = $userinfo['phone'];
//        $recordList['group'] = $userinfo['group'];
            if ($recordList['firstname'] === null) {
                $recordList = $user;
                // add a group
                $recordList['group'] = 'No library account';
            }
            $recordList['firstname'] = 'TEST'.$recordList['firstname'];
            $recordList['fullname'] = $recordList['firstname'].' '.$recordList['lastname'];
        // Get the LOANS-Page to extract a message for the user
/*        $URL = "/loan/DB=1/USERINFO";
        $POST = array(
            "ACT" => "UI_DATA",
            "LNG" => "DU",
            "BOR_U" => $_SESSION['picauser']->username,
            "BOR_PW" => $_SESSION['picauser']->cat_password
        );
        $postit = $this->_postit($URL, $POST);
        // How many messages are there?
        $messages = substr_count($postit, '<strong class="alert">');
        $position = 0;
        if ($messages === 2) {
            // ignore the first message (its only the message to close the window after finishing)
            for ($n = 0; $n<2; $n++) {
                $pos = strpos($postit, '<strong class="alert">', $position);
                $pos_close = strpos($postit, '</strong>', $pos);
                $value = substr($postit, $pos+22, ($pos_close-$pos-22));
                $position = $pos + 1;
            }
            $recordList['message'] = $value;
        }
*/
            return $recordList;
        }
    }

    /**
     * Public Function which changes the password in the library system
     * (not supported prior to VuFind 2.4)
     *
     * @param array $details Array with patron information, newPassword and
     *                       oldPassword.
     *
     * @return array An array with patron information.
     */
    public function changePassword($details)
    {
        // TODO: do the LDAP stuff needed locally
        return parent::changePassword($details);
    }

    /**
     * This PAIA helper function allows custom overrides for mapping of PAIA response
     * to getMyHolds data structure.
     *
     * @param array $items Array of PAIA items to be mapped.
     *
     * @return array
     */
    protected function myHoldsMapping($items)
    {
        $results = parent::myHoldsMapping($items);
        $newResults = [];
        foreach ($results as $result) {
            // Reset position for result data
            // position is in this context not supported properly by VZG PAIA
            $result['position'] = null;
            // if the user does not have write permission, cancelation is not possible
            $result['cancel_details'] = in_array('write_items', $this->getScope()) ? $result['cancel_details'] : '';
            $newResults[] = $result;
        }
        return $newResults;
    }

    /**
     * Get total numbers and counts from actions
     *
     * @param array  $patron Array of Patron data.
     * @param string $which  Which data is needed.
     *
     * @return array
     */
    public function getNumberOf($patron, $which = null)
    {
        $retval = [];
        if ($which !== null) {
            switch ($which) {
                case 'fines':
                    $totalDue = $this->getFinesTotal($patron);
                    $retval['fines'] = $totalDue;
                    break;
                default:
                    $retval[$which] = $this->getNumberOfMy($patron, $which);
                    break;
            }
        } else {
            $retval['transactions'] = $this->getNumberOfMy($patron, 'transactions');
            $retval['holds'] = $this->getNumberOfMy($patron, 'holds');
            $retval['fines'] = $this->getFinesTotal($patron);
            $retval['storageRetrievalRequests'] = $this->getNumberOfMy($patron, 'storageRetrievalRequests');
        }
        return $retval;
    }

    /**
     * Calculate the total fine
     *
     * @param array  $patron Array of Patron data.
     *
     * @return int
     */
    protected function getFinesTotal($patron)
    {
        $fees = $this->paiaGetAsArray(
            'core/'.$patron['cat_username'].'/fees'
        );

        // PAIA simple data type money: a monetary value with currency (format
        // [0-9]+\.[0-9][0-9] [A-Z][A-Z][A-Z]), for instance 0.80 USD.
        $feeConverter = function ($fee) {
            $paiaCurrencyPattern = "/^([0-9]+\.[0-9][0-9]) ([A-Z][A-Z][A-Z])$/";
            if (preg_match($paiaCurrencyPattern, $fee, $feeMatches)) {
                // VuFind expects fees in PENNIES
                return ($feeMatches[1]*100);
            }
            return $fee;
        };

        $totalDue = 0;
        if (isset($fees['fee'])) {
            foreach ($fees['fee'] as $fee) {
                $totalDue += $feeConverter($fee['amount']);
            }
        }
        return $feeConverter($totalDue);
    }

    /**
     * Get number of Patron Transactions/Requests/Holds
     *
     * This is responsible for retrieving the total number of something
     * by a specific patron.
     *
     * @param array  $patron The patron array from patronLogin
     * @param string $which  Which data is needed.
     *
     * @return int Total number of the patron's $which on success,
     */
    protected function getNumberOfMy($patron, $which)
    {
        switch ($which) {
            case 'transactions':
                // filters for getMyTransactions are:
                // status = 3 - held (the document is on loan by the patron)
                $filter = ['status' => [3]];
                break;
            case 'storageRetrievalRequests':
                // filters for getMyStorageRetrievalRequests are:
                // status = 2 - ordered (the document is ordered by the patron)
                $filter = ['status' => [2]];
                break;
            case 'holds':
                // filters for getMyHolds are:
                // status = 1 - reserved (the document is not accessible for the patron yet,
                //              but it will be)
                //          4 - provided (the document is ready to be used by the patron)
                $filter = ['status' => [1, 4]];
                break;
            default:
                // undefined option
                return null;
        }
        // get items-docs for given filters
        $items = $this->paiaGetItems($patron, $filter);

        return count($items);
    }

    /**
     * Get all PPNs, which are on loan or reserved by a patron
     *
     * @return array PPNs
     */
    public function getAllPpnsFrom($patron)
    {
        $ppnarr = [];
        $items = $this->paiaGetItems($patron);
        foreach ($items['doc'] as $item) {
            $ppn = $this->getPaiaItemPpn($item['edition']);
            $ppnarr[] = $ppn;
        }
        return $ppnarr;
    }

    /**
     * Returns an array with PAIA confirmations based on the given holdDetails which
     * will be used for a request.
     * Currently two condition types are supported:
     *  - http://purl.org/ontology/paia#StorageCondition to select a document
     *    location -- mapped to pickUpLocation
     *  - http://purl.org/ontology/paia#FeeCondition to confirm or select a document
     *    service causing a fee -- mapped to Reservation
     *
     * @param array $holdDetails An array of item and patron data
     *
     * @return array
     */
    protected function getConfirmations($holdDetails)
    {
        $confirmations = parent::getConfirmations($holdDetails);
        $confirmations['http://purl.org/ontology/paia#FeeCondition']
            = ['http://purl.org/ontology/dso#Reservation'];
        return $confirmations;
    }

    /********************* TODO **********************************/
    /* These methods are not working properly yet (or are using just dummy values) */

    /**
     * Get Default Request Group
     *
     * Returns the default request group
     *
     * @param array $patron      Patron information returned by the patronLogin
     * method.
     * @param array $holdDetails Optional array, only passed in when getting a list
     * in the context of placing a hold; contains most of the same values passed to
     * placeHold, minus the patron data.  May be used to limit the request group
     * options or may be ignored.
     *
     * @return false|string      The default request group for the patron.
     *
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
/*
    public function getDefaultRequestGroup($patron = false, $holdDetails = null)
    {
        $requestGroups = $this->getRequestGroups(0, 0);
        return $requestGroups[0]['id'];
    }
*/
    /**
     * Get request groups
     *
     * @param integer $bibId  BIB ID
     * @param array   $patron Patron information returned by the patronLogin
     * method.
     *
     * @return array  False if request groups not in use or an array of
     * associative arrays with id and name keys
     *
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
/*
    public function getRequestGroups($bibId = null, $patron = null)
    {
        return [
            [
                'id' => 1,
                'name' => 'Main Library'
            ],
            [
                'id' => 2,
                'name' => 'Branch Library'
            ]
        ];
    }
*/
    /**
     * Cancel Storage Retrieval Request
     *
     * Attempts to Cancel a Storage Retrieval Request on a particular item. The
     * data in $cancelDetails['details'] is determined by
     * getCancelStorageRetrievalRequestDetails().
     *
     * @param array $cancelDetails An array of item and patron data
     *
     * @return array               An array of data on each request including
     * whether or not it was successful and a system message (if available)
     */
/*
    public function cancelStorageRetrievalRequests($cancelDetails)
    {
        // Rewrite the items in the session, removing those the user wants to
        // cancel.
        $newRequests = new ArrayObject();
        $retVal = ['count' => 0, 'items' => []];
        $session = $this->getSession();
        foreach ($session->storageRetrievalRequests as $current) {
            if (!in_array($current['reqnum'], $cancelDetails['details'])) {
                $newRequests->append($current);
            } else {
                if (!$this->isFailing(__METHOD__, 50)) {
                    $retVal['count']++;
                    $retVal['items'][$current['item_id']] = [
                        'success' => true,
                        'status' => 'storage_retrieval_request_cancel_success'
                    ];
                } else {
                    $newRequests->append($current);
                    $retVal['items'][$current['item_id']] = [
                        'success' => false,
                        'status' => 'storage_retrieval_request_cancel_fail',
                        'sysMessage' =>
                            'Demonstrating failure; keep trying and ' .
                            'it will work eventually.'
                    ];
                }
            }
        }

        $session->storageRetrievalRequests = $newRequests;
        return $retVal;
    }
*/
    /**
     * Get Cancel Storage Retrieval Request Details
     *
     * In order to cancel a hold, Voyager requires the patron details an item ID
     * and a recall ID. This function returns the item id and recall id as a string
     * separated by a pipe, which is then submitted as form data in Hold.php. This
     * value is then extracted by the CancelHolds function.
     *
     * @param array $details An array of item data
     *
     * @return string Data for use in a form field
     */
/*
    public function getCancelStorageRetrievalRequestDetails($details)
    {
        return $details['reqnum'];
    }
*/
}
