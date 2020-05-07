<?php
/**
 * "Get Item Status" AJAX handler
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
 * @author   Chris Delis <cedelis@uillinois.edu>
 * @author   Tuan Nguyen <tuan@yorku.ca>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development Wiki
 */
namespace TUBfind\AjaxHandler;

use VuFind\Exception\ILS as ILSException;
use VuFind\Auth\ILSAuthenticator;
use VuFind\I18n\Translator\TranslatorAwareInterface;
use VuFind\ILS\Connection;
use VuFind\ILS\Logic\Holds;
use VuFind\Session\Settings as SessionSettings;
use Zend\Config\Config;
use Zend\Mvc\Controller\Plugin\Params;
use Zend\View\Renderer\RendererInterface;

/**
 * "Get Item Status" AJAX handler
 *
 * This is responsible for printing the holdings information for a
 * collection of records in JSON format.
 *
 * @category VuFind
 * @package  AJAX
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @author   Chris Delis <cedelis@uillinois.edu>
 * @author   Tuan Nguyen <tuan@yorku.ca>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development Wiki
 */
class GetItemStatuses extends \VuFind\AjaxHandler\AbstractBase implements TranslatorAwareInterface
{
    use \VuFind\I18n\Translator\TranslatorAwareTrait;

    /**
     * Top-level configuration
     *
     * @var Config
     */
    protected $config;

    /**
     * ILS connection
     *
     * @var Connection
     */
    protected $ils;

    /**
     * View renderer
     *
     * @var RendererInterface
     */
    protected $renderer;

    /**
     * Holds logic
     *
     * @var Holds
     */
    protected $holdLogic;

    protected $ilsAuthenticator;
    protected $loggedInUser;

    protected $auxDriver;
    protected $auxLoader;

    /**
     * Constructor
     *
     * @param SessionSettings   $ss        Session settings
     * @param Config            $config    Top-level configuration
     * @param Connection        $ils       ILS connection
     * @param RendererInterface $renderer  View renderer
     * @param Holds             $holdLogic Holds logic
     */
    public function __construct(SessionSettings $ss, Config $config, Connection $ils,
        RendererInterface $renderer, Holds $holdLogic, ILSAuthenticator $ilsAuthenticator, $user
    ) {
        $this->sessionSettings = $ss;
        $this->config = $config;
        $this->ils = $ils;
        $this->renderer = $renderer;
        $this->holdLogic = $holdLogic;
        $this->ilsAuthenticator = $ilsAuthenticator;
        $this->loggedInUser = $user;
    }

    public function setAuxPrimoDriver(\TUBfind\RecordDriver\Primo $d) {
        $this->auxDriver = $d;
    }

    public function setAuxRecordLoader(\VuFind\Record\Loader $l) {
        $this->auxLoader = $l;
    }

    /**
     * Support method for getItemStatuses() -- filter suppressed locations from the
     * array of item information for a particular bib record.
     *
     * @param array $record Information on items linked to a single bib record
     *
     * @return array        Filtered version of $record
     */
    protected function filterSuppressedLocations($record)
    {
        static $hideHoldings = false;
        if ($hideHoldings === false) {
            $hideHoldings = $this->holdLogic->getSuppressedLocations();
        }

        $filtered = [];
        foreach ($record as $current) {
            if (!in_array($current['location'] ?? null, $hideHoldings)) {
                $filtered[] = $current;
            }
        }
        return $filtered;
    }

    /**
     * Translate an array of strings using a prefix.
     *
     * @param string $transPrefix Translation prefix
     * @param array  $list        List of values to translate
     *
     * @return array
     */
    protected function translateList($transPrefix, $list)
    {
        $transList = [];
        foreach ($list as $current) {
            $transList[] = $this->translateWithPrefix($transPrefix, $current);
        }
        return $transList;
    }

    /**
     * Support method for getItemStatuses() -- when presented with multiple values,
     * pick which one(s) to send back via AJAX.
     *
     * @param array  $rawList     Array of values to choose from.
     * @param string $mode        config.ini setting -- first, all or msg
     * @param string $msg         Message to display if $mode == "msg"
     * @param string $transPrefix Translator prefix to apply to values (false to
     * omit translation of values)
     *
     * @return string
     */
    protected function pickValue($rawList, $mode, $msg, $transPrefix = false)
    {
        // Make sure array contains only unique values:
        $list = array_unique($rawList);

        // If there is only one value in the list, or if we're in "first" mode,
        // send back the first list value:
        if ($mode == 'first' || count($list) == 1) {
            if ($transPrefix) {
                return $this->translateWithPrefix($transPrefix, $list[0]);
            }
            return $list[0];
        } elseif (count($list) == 0) {
            // Empty list?  Return a blank string:
            return '';
        } elseif ($mode == 'all') {
            // All values mode?  Return comma-separated values:
            return implode(
                ",\t",
                $transPrefix ? $this->translateList($transPrefix, $list) : $list
            );
        } else {
            // Message mode?  Return the specified message, translated to the
            // appropriate language.
            return $this->translate($msg);
        }
    }

    /**
     * Based on settings and the number of callnumbers, return callnumber handler
     * Use callnumbers before pickValue is run.
     *
     * @param array  $list           Array of callnumbers.
     * @param string $displaySetting config.ini setting -- first, all or msg
     *
     * @return string
     */
    protected function getCallnumberHandler($list = null, $displaySetting = null)
    {
        if ($displaySetting == 'msg' && count($list) > 1) {
            return false;
        }
        return isset($this->config->Item_Status->callnumber_handler)
            ? $this->config->Item_Status->callnumber_handler
            : false;
    }

    /**
     * Reduce an array of service names to a human-readable string.
     *
     * @param array $rawServices Names of available services.
     *
     * @return string
     */
    protected function reduceServices(array $rawServices)
    {
        // Normalize, dedup and sort available services
        $normalize = function ($in) {
            return strtolower(preg_replace('/[^A-Za-z]/', '', $in));
        };
        $services = array_map($normalize, array_unique($rawServices));
        sort($services);

        // Do we need to deal with a preferred service?
        $preferred = isset($this->config->Item_Status->preferred_service)
            ? $normalize($this->config->Item_Status->preferred_service) : false;
        if (false !== $preferred && in_array($preferred, $services)) {
            $services = [$preferred];
        }

        return $this->renderer->render(
            'ajax/status-available-services.phtml',
            ['services' => $services]
        );
    }

    /**
     * Support method for getItemStatuses() -- process a single bibliographic record
     * for location settings other than "group".
     *
     * @param array  $record            Information on items linked to a single bib
     *                                  record
     * @param array  $messages          Custom status HTML
     *                                  (keys = available/unavailable)
     * @param string $locationSetting   The location mode setting used for
     *                                  pickValue()
     * @param string $callnumberSetting The callnumber mode setting used for
     *                                  pickValue()
     *
     * @return array                    Summarized availability information
     */
    protected function getItemStatus($record, $messages, $locationSetting,
        $callnumberSetting
    ) {
        // Summarize call number, location and availability info across all items:
        $callNumbers = $locations = [];
        $use_unknown_status = $available = false;
        $services = [];

        foreach ($record as $info) {
            // Find an available copy
            if ($info['availability']) {
                $available = true;
            }
            // Check for a use_unknown_message flag
            if (isset($info['use_unknown_message'])
                && $info['use_unknown_message'] == true
            ) {
                $use_unknown_status = true;
            }
            // Store call number/location info:
            $callNumbers[] = $info['callnumber'];
            $locations[] = $info['location'];
            // Store all available services
            if (isset($info['services'])) {
                $services = array_merge($services, $info['services']);
            }
        }

        $callnumberHandler = $this->getCallnumberHandler(
            $callNumbers, $callnumberSetting
        );

        // Determine call number string based on findings:
        $callNumber = $this->pickValue(
            $callNumbers, $callnumberSetting, 'Multiple Call Numbers'
        );

        // Determine location string based on findings:
        $location = $this->pickValue(
            $locations, $locationSetting, 'Multiple Locations', 'location_'
        );

        if (!empty($services)) {
            $availability_message = $this->reduceServices($services);
        } else {
            $availability_message = $use_unknown_status
                ? $messages['unknown']
                : $messages[$available ? 'available' : 'unavailable'];
        }

        // Send back the collected details:
        return [
            'id' => $record[0]['id'],
            'availability' => ($available ? 'true' : 'false'),
            'availability_message' => $availability_message,
            'location' => htmlentities($location, ENT_COMPAT, 'UTF-8'),
            'locationList' => false,
            'reserve' =>
                ($record[0]['reserve'] == 'Y' ? 'true' : 'false'),
            'reserve_message' => $record[0]['reserve'] == 'Y'
                ? $this->translate('on_reserve')
                : $this->translate('Not On Reserve'),
            'callnumber' => htmlentities($callNumber, ENT_COMPAT, 'UTF-8'),
            'callnumber_handler' => $callnumberHandler
        ];
    }

    /**
     * Support method for getItemStatuses() -- process a single bibliographic record
     * for "group" location setting.
     *
     * @param array  $record            Information on items linked to a single
     *                                  bib record
     * @param array  $messages          Custom status HTML
     *                                  (keys = available/unavailable)
     * @param string $callnumberSetting The callnumber mode setting used for
     *                                  pickValue()
     *
     * @return array                    Summarized availability information
     */
    protected function getItemStatusGroup($record, $messages, $callnumberSetting)
    {
        // Summarize call number, location and availability info across all items:
        $locations = [];
        $use_unknown_status = $available = false;
        foreach ($record as $info) {
            // Find an available copy
            if ($info['availability']) {
                $available = $locations[$info['location']]['available'] = true;
            }
            // Check for a use_unknown_message flag
            if (isset($info['use_unknown_message'])
                && $info['use_unknown_message'] == true
            ) {
                $use_unknown_status = true;
                $locations[$info['location']]['status_unknown'] = true;
            }
            // Store call number/location info:
            $locations[$info['location']]['callnumbers'][] = $info['callnumber'];
        }

        // Build list split out by location:
        $locationList = false;
        foreach ($locations as $location => $details) {
            $locationCallnumbers = array_unique($details['callnumbers']);
            // Determine call number string based on findings:
            $callnumberHandler = $this->getCallnumberHandler(
                $locationCallnumbers, $callnumberSetting
            );
            $locationCallnumbers = $this->pickValue(
                $locationCallnumbers, $callnumberSetting, 'Multiple Call Numbers'
            );
            $locationInfo = [
                'availability' =>
                    $details['available'] ?? false,
                'location' => htmlentities(
                    $this->translateWithPrefix('location_', $location),
                    ENT_COMPAT, 'UTF-8'
                ),
                'callnumbers' =>
                    htmlentities($locationCallnumbers, ENT_COMPAT, 'UTF-8'),
                'status_unknown' => $details['status_unknown'] ?? false,
                'callnumber_handler' => $callnumberHandler
            ];
            $locationList[] = $locationInfo;
        }

        $availability_message = $use_unknown_status
            ? $messages['unknown']
            : $messages[$available ? 'available' : 'unavailable'];

        // Send back the collected details:
        return [
            'id' => $record[0]['id'],
            'availability' => ($available ? 'true' : 'false'),
            'availability_message' => $availability_message,
            'location' => false,
            'locationList' => $locationList,
            'reserve' =>
                ($record[0]['reserve'] == 'Y' ? 'true' : 'false'),
            'reserve_message' => $record[0]['reserve'] == 'Y'
                ? $this->translate('on_reserve')
                : $this->translate('Not On Reserve'),
            'callnumber' => false
        ];
    }

    /**
     * Support method for getItemStatuses() -- process a failed record.
     *
     * @param array  $record Information on items linked to a single bib record
     * @param string $msg    Availability message
     *
     * @return array Summarized availability information
     */
    protected function getItemStatusError($record, $msg = '')
    {
        return [
            'id' => $record[0]['id'],
            'error' => $this->translate($record[0]['error']),
            'availability' => false,
            'availability_message' => $msg,
            'location' => false,
            'locationList' => [],
            'reserve' => false,
            'reserve_message' => '',
            'callnumber' => false
        ];
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
        $this->disableSessionWrites();  // avoid session write timing bug
        $this->params = $params;
        $lang = $params->fromPost('lang', $params->fromQuery('lang')) ? $params->fromPost('lang', $params->fromQuery('lang')) : 'en';
        $ids = $params->fromPost('id', $params->fromQuery('id', []));
        $statuses = $this->getItemStatusesArray(
            $ids,
            $lang
        );
//var_dump($statuses);
        // Output the results
/*
        return $this->formatResponse(compact($statuses));
*/
/*
        try {
            $results = $this->ils->getStatuses($ids);
var_dump($results);
        } catch (ILSException $e) {
            // If the ILS fails, send an error response instead of a fatal
            // error; we don't want to confuse the end user unnecessarily.
            error_log($e->getMessage());
            foreach ($ids as $id) {
                $results[] = [
                    [
                        'id' => $id,
                        'error' => 'An error has occurred'
                    ]
                ];
            }
        }

        if (!is_array($results)) {
            // If getStatuses returned garbage, let's turn it into an empty array
            // to avoid triggering a notice in the foreach loop below.
            $results = [];
        }

        // In order to detect IDs missing from the status response, create an
        // array with a key for every requested ID.  We will clear keys as we
        // encounter IDs in the response -- anything left will be problems that
        // need special handling.
        $missingIds = array_flip($ids);

        // Load messages for response:
        $messages = [
            'available' => $this->renderer->render('ajax/status-available.phtml'),
            'unavailable' =>
                $this->renderer->render('ajax/status-unavailable.phtml'),
            'unknown' => $this->renderer->render('ajax/status-unknown.phtml')
        ];

        // Load callnumber and location settings:
        $callnumberSetting = isset($this->config->Item_Status->multiple_call_nos)
            ? $this->config->Item_Status->multiple_call_nos : 'msg';
        $locationSetting = isset($this->config->Item_Status->multiple_locations)
            ? $this->config->Item_Status->multiple_locations : 'msg';
        $showFullStatus = isset($this->config->Item_Status->show_full_status)
            ? $this->config->Item_Status->show_full_status : false;

        // Loop through all the status information that came back
        $statuses = [];
        foreach ($results as $recordNumber => $record) {
            // Filter out suppressed locations:
            $record = $this->filterSuppressedLocations($record);

            // Skip empty records:
            if (count($record)) {
                // Check for errors
                if (!empty($record[0]['error'])) {
                    $current = $this
                        ->getItemStatusError($record, $messages['unknown']);
                } elseif ($locationSetting === 'group') {
                    $current = $this->getItemStatusGroup(
                        $record, $messages, $callnumberSetting
                    );
                } else {
                    $current = $this->getItemStatus(
                        $record, $messages, $locationSetting, $callnumberSetting
                    );
                }
                // If a full status display has been requested and no errors were
                // encountered, append the HTML:
                if ($showFullStatus && empty($record[0]['error'])) {
                    $current['full_status'] = $this->renderer->render(
                        'ajax/status-full.phtml', [
                            'statusItems' => $record,
                            'callnumberHandler' => $this->getCallnumberHandler()
                         ]
                    );
                }
                $current['record_number'] = array_search($current['id'], $ids);
                $statuses[] = $current;

                // The current ID is not missing -- remove it from the missing list.
                unset($missingIds[$current['id']]);
            }
        }

        // If any IDs were missing, send back appropriate dummy data
        foreach ($missingIds as $missingId => $recordNumber) {
            $statuses[] = [
                'id'                   => $missingId,
                'availability'         => 'false',
                'availability_message' => $messages['unavailable'],
                'location'             => $this->translate('Unknown'),
                'locationList'         => false,
                'reserve'              => 'false',
                'reserve_message'      => $this->translate('Not On Reserve'),
                'callnumber'           => '',
                'missing_data'         => true,
                'record_number'        => $recordNumber
            ];
        }
*/
        // Done
        return $this->formatResponse(compact('statuses'));
    }












    /**
     * support method for getItemStatuses()
     *
     * @todo 2015-10-13
     * - When getItemStatusTUBFullAjax() is fine, remove the redundant parts here
     *   => "// Load callnumber and location settings: ..."
     *   => "if ($locationSetting == "group") { ..."
     *   => "if ($showFullStatus) {..."
     * - Changing these config options would break displayHoldingGuide() in
     *   check_item_statuses.js anyway (since a long time now)
     *
     * @param Array $ids
     *
     * @return Array
     */
    protected function getItemStatusesArray($ids, $lang = 'en')
    {
        $statuses = [];
        $statusesFromCatalog = [];
        foreach ($ids as $id) {
            // Special treatment for national licenses and DOAJ
            if ((substr($id, 0, 2) === 'NL' && substr($id, 0, 3) != 'NLM') || substr($id, 0, 4) === 'DOAJ') {
                $statuses[] = $this->getNationalLicenseInformation($id);
            }
            // Special treatment for Primo records (it must be a PrimoRecord, if it starts non-numerically and is not a NL)
            else if (is_numeric(substr($id, 0, 2)) === false && substr($id, 0, 3) != 'OLC' && substr($id, 0, 3) != 'NLM') {
                $statuses[] = $this->getPrintedStatusArray($id);
            }
            else {
            /*    if (substr($id, 0, 3) == 'OLC') {
                    $id = substr($id, 3);
                } */
                $statusesFromCatalog[] = $id;
            }
        }
        if (count($statusesFromCatalog) > 0) {
            $holdings = $this->getAvailabilities($statusesFromCatalog, $lang);
            if (empty($holdings)) {
                foreach ($statusesFromCatalog as $missingId) {
                    $statuses[] = $this->getMissingItemInformation($missingId);
                }
            }
            else {
                $nu_holdings = [];
                foreach ($holdings as $holding) {
                    if (array_key_exists($holding['id'], $nu_holdings) && is_array($nu_holdings[$holding['id']]) === false) $nu_holdings[$holding['id']] = [];
                    $nu_holdings[$holding['id']][] = $holding;
                }
                foreach ($nu_holdings as $nu_holding) {
                    $statuses[] = $this->getTUBItemStatus( $nu_holding );
                }
            }
            //$statuses = array_merge($statuses, $this->getStatusesFromCatalog($statusesFromCatalog));
        }
        return $statuses;
    }

    /**
     * support method for getItemStatuses to get the record(s) from the driver
     *
     * @param Array $ids
     *
     * @return Array
     */
    protected function getFromDriver($ids) {
        $this->disableSessionWrites();  // avoid session write timing bug
        $catalog = $this->getILS();
        $language = $this->params()->fromPost('lang', $this->params()->fromQuery('lang'));
        $catalog->setLanguage($language);

        // Ask the catalog for details
        $results = $catalog->getStatuses($ids);
        return $results;
    }


    /**
     * support method for getItemStatuses()
     *
     * @param Array $ids
     *
     * @return Array
     */
    protected function getStatusesFromCatalog($ids)
    {
        $results = $this->getFromDriver($ids);

        if (!is_array($results)) {
            // If getStatus returned garbage, let's turn it into an empty array
            // to avoid triggering a notice in the foreach loop below.
            $results = [];
        }

        // In order to detect IDs missing from the status response, create an
        // array with a key for every requested ID.  We will clear keys as we
        // encounter IDs in the response -- anything left will be problems that
        // need special handling.
        $missingIds = array_flip($ids);

        // Load callnumber and location settings:
        $config = $this->getConfig();
        $callnumberSetting = isset($config->Item_Status->multiple_call_nos)
            ? $config->Item_Status->multiple_call_nos : 'msg';
        $locationSetting = isset($config->Item_Status->multiple_locations)
            ? $config->Item_Status->multiple_locations : 'msg';
        $showFullStatus = isset($config->Item_Status->show_full_status)
            ? $config->Item_Status->show_full_status : false;

        // Loop through all the status information that came back
        foreach ($results as $recordNumber => $record) {
            // Filter out suppressed locations:
            $record = $this->filterSuppressedLocations($record);

            // Skip empty records:
            if (count($record)) {
                if ($locationSetting == "group") {
                    $current = $this->getItemStatusGroup(
                        $record, $messages, $callnumberSetting
                    );
                } else {
                    $current = $this->getItemStatus(
                        $record, $this->getAvailabilityMessages(), $locationSetting, $callnumberSetting
                    );
                }
                // If a full status display has been requested, append the HTML:
                if ($showFullStatus) {
                    $current['full_status'] = $renderer->render(
                        'ajax/status-full.phtml', ['statusItems' => $record]
                    );
                }
                $current['record_number'] = array_search($current['id'], $ids);

                // Push the current record to the statuses array
                $statuses[] = $current;

                // The current ID is not missing -- remove it from the missing list.
                unset($missingIds[$current['id']]);
            }
        }

        // If any IDs were missing, send back appropriate dummy data
        foreach ($missingIds as $missingId => $recordNumber) {
            $statuses[] = $this->getMissingItemInformation($missingId);
        }

        return $statuses;
    }

    /**
     * support method for getItemStatusesArray()
     *
     * @param String $id
     *
     * @return Array
     */
    protected function getNationalLicenseInformation($id)
    {
/*        $driver = $this->getRecordLoader()->load(
            $id,
            $this->params()->fromPost('source', 'Solr')
        );

        $urls = $driver->getUrls();
*/
        if (isset($urls[0]['url'])) {
            $bestOptionLocation = 'Web';
        } else {
            $bestOptionLocation = 'Unknown';
        }

        $status = [
            'id'                   => $id,
            'patronBestOption'     => 'e_only',
            'bestOptionHref'       => $urls[0]['url'],
            'locHref'              => $urls[0]['url'],
            'bestOptionLocation'   => $bestOptionLocation,
            'availability'         => true,
            'availability_message' => $this->getAvailabilityMessages()['electronic'],
            'location'             => $bestOptionLocation,
            'locationList'         => false,
            'reserve'              => 'false',
            'reserve_message'      => 'Not On Reserve',
            'callnumber'           => '',
            'missing_data'         => false,
            'link_printed'         => null,
            'link_printed_href'    => null,
            'parentlink'           => false,
            'record_number'        => $this->params->fromPost('record_number', $this->params->fromQuery('record_number')),
            'reference_location'   => 'false',
            'reference_callnumber' => 'false',
            'multiVols'            => false
        ];
        return $status;
    }


    /**
     * Get information for printed items
     *
     * @param String $id
     *
     * @return Array
     */
    protected function getPrintedStatusArray($id)
    {
/*
        $queryString = $params->fromQuery('querystring');
        $queryString = urldecode(
            str_replace('&amp;', '&',
                substr_replace(
                    trim($queryString), '', 0, 1
                )
            )
        );

        $queryArray = explode('&', $queryString);
        $searchParams = [];
        foreach ($queryArray as $queryItem) {
            $arrayKey = false;
            list($key, $value) = explode('=', $queryItem, 2);
            if (preg_match('/[0-9](\[\]$)/', $key, $matches)) {
                $key = str_replace($matches[1], '', $key);
                $arrayKey = true;
            }
            if ($arrayKey) {
                $searchParams[$key][] = $value;
            } else {
                $searchParams[$key] = $value;
            }
        }

        $backend = $params->fromQuery('source', DEFAULT_SEARCH_BACKEND);
        $results = $this->resultsManager->get($backend);
        $paramsObj = $results->getParams();
        $paramsObj->getOptions()->disableHighlighting();
        $paramsObj->getOptions()->spellcheckEnabled(false);
        $paramsObj->initFromRequest(new Parameters($searchParams));

        $total = $results->getResultTotal();

        $data = [
            'total' => $total,
        ];
        return $this->formatResponse($data);
*/

        try {
            $driver = $this->auxLoader->load(
                $id,
                $this->params->fromPost('source', 'Primo')
            );
            $containerID = $driver->getContainerRecordID();
            $ebookLink = $driver->getPrintedEbookRecordID();
        } catch (\Exception $e) {
            // Do nothing -- just return null
            return null;
        }


        $refId = null;
        if(!empty($containerID)) {
            $refId = $containerID;
        }
        else if (!empty($ebookLink)) {
            $refId = $ebookLink;
        }

        // add printed item if its available
        $link_printed = $refId;
        $linkPrintedHtml = null;
        $parentLinkHtml = null;
        if ($link_printed) {
            $view = ['refId' => $link_printed];
            $linkPrintedHtml = $this->renderer->render('ajax/link_printed.phtml', $view);
            $parentLinkHtml = $this->renderer->render('ajax/parentlink.phtml', $view);
        }
        return [
            'id'                   => $id,
            'parentlink'           => $parentLinkHtml,
            'link_printed'         => $linkPrintedHtml,
            'link_printed_href'    => $link_printed,
            'patronBestOption'     => 'false',
            'bestOptionHref'       => 'false',
            'bestOptionLocation'   => 'false',
            'availability'         => 'false',
            'availability_message' => $this->getAvailabilityMessages()['unavailable'],
            'location'             => 'Unknown',
            'locationList'         => false,
            'reserve'              => 'false',
            'reserve_message'      => 'Not On Reserve',
            'callnumber'           => '',
        ];
    }

    /**
     * support method for getItemStatusesArray()
     * Get information for missing items
     *
     * @param String $id
     *
     * @return Array
     */
    protected function getMissingItemInformation($missingId)
    {
        return [
            'id'                   => $missingId,
            'patronBestOption'     => 'false',
            'bestOptionHref'       => 'false',
            'bestOptionLocation'   => 'false',
            'availability'         => 'false',
            'availability_message' => $this->getAvailabilityMessages()['unavailable'],
            'location'             => 'Unknown',
            'locationList'         => false,
            'reserve'              => 'false',
            'reserve_message'      => 'Not On Reserve',
            'callnumber'           => '',
            'missing_data'         => true
        ];
    }

    /**
     * Get displayable availability messages
     *
     * @return Array
     */
    protected function getAvailabilityMessages()
    {
        // Get access to PHP template renderer for partials:
//        $renderer = $this->getViewRenderer();

        // Load messages for response:
        $messages = [
            'available' => $this->renderer->render('ajax/status-available.phtml'),
            'unavailable' => $this->renderer->render('ajax/status-unavailable.phtml'),
            'unknown' => $this->renderer->render('ajax/status-unknown.phtml'),
            'notforloan' => $this->renderer->render('ajax/status-notforloan.phtml'),
            'electronic' => $this->renderer->render('ajax/status-electronic.phtml')
        ];

        return $messages;
    }

    /**
     * Get holdings from driver
     *
     * @param string  $id Id to get from driver
     *
     * @return Array
     */
    protected function getRealTimeHoldings($id, $lang = 'en')
    {
        return $this->ils ? $this->holdLogic->getHoldings(
            $id, null, $lang
        ) : [];
//        $driver = $this->getRecordLoader()->load($id);
        $ids = [ $id ];
        $holdings = $this->ils->getStatuses($ids);
        return $holdings;
    }

    /**
     * Get holdings from driver
     *
     * @param string  $id Id to get from driver
     *
     * @return Array
     */
    protected function getAvailabilities($ids, $lang = 'en')
    {
        $returnItems = [];
        foreach ($ids as $id) {
            $holdings = $this->getRealTimeHoldings($id, $lang);
            foreach ($holdings['holdings'] as $holding) {
                foreach ($holding['items'] as $row) {
                    $returnItems[] = $row;
                }
            }
        }
        return $returnItems;
    }


    /**
     * Support method for getItemStatuses() -- process a single bibliographic record
     * for location settings other than "group".
     *
     * @note: 2015-09-13
     * -  The aim is: get only the location which serves the patron best
     *
     * @todo: 2015-09-19
     * -  CD-Roms might be categorized as e_only - this is nearly ok, since no
     *    location or call number is available. But we'd want to give the patron
     *    some clue - currently there is nothing. Example (search for)
     *    http://lincl1.b.tu-harburg.de:81/vufind2-test/Record/268707642
     *
     * @todo: 2015-10-09
     * -  Logic for $bestOptionLocation is really bad and got even worse by
     *    adding "TUBHH-Hack for bestLocation" (search for comment). Think about
     *    a better way to pry the information from the data.
     *    What multiple_locations and show_full_status do _might_ be a much
     *    better way for handling this.
     *
     * @param array  $record            Information on items linked to a single bib
     *                                  record
     * @param array  $messages          Custom status HTML
     *                                  (keys = available/unavailable)
     * @param string $locationSetting   The location mode setting used for
     *                                  pickValue()
     * @param string $callnumberSetting The callnumber mode setting used for
     *                                  pickValue()
     *
     * @return array                    Summarized availability information
     */
    protected function getTUBItemStatus($record, $messages = null, $locationSetting = null, $callnumberSetting = null) {
        $tmp = ''; // for quick debugging output via json

        // Initialize counts for this record
        $counts = [
            'total'      => count($record), // All item (available and not available)
            'available'  => 0,              // Total items available (Reference only + Borrowable + Closed Stack Order); reservce collection is implicitly Ref only
            'reference'  => 0,              // > Subset thereof: available but reference only
            'lent'       => 0,              // > Subset thereof: total of all available items being on loan (can be RESERVED aka "Recall this")
            'borrowable' => 0,              // > Subset thereof: items immediatly available for take-away (including $stackorderCount)
            'stackorder' => 0,              // (not subset; part of $availableCount calculation): total of all available items that have to be ORDERED from a closed stack (aka "Place a hold")
            'electronic' => 0,
            'dienstapp'  => 0,
            'reserved_without_link' => 0,
            'completely_unavailable' => 0
        ];

        // Initialize patron options
        // The order is important to determine the best option
        // Note: This should give a very reasonable range of possible options for
        // further processing. Maybe value can be added by using actual values
        // instead of boolean indicators
        // Note2: These options only refer to physical copies UNLESS there are ONLY electronic items
        $patronOptions = [
             'e_only'             => false,
             'shelf'              => false,
             'order'              => false,
             'reserve_or_local'   => false,
             'reserve'            => false,
             'local'              => false,
             'acquired'           => false, // Added 2015-10-14; title bought but not yet arrived
             'service_desk'       => false,
             'false'              => false,
        ];

        // Initialize best options
        // These variables should provide everything needed to create a useful html output in the result
        $options = [];
/*
            'option'           => false,
            'href'             => false, // Href is really a little misleading - it's more like "action url". Either 'order' or 'reserve' which is specified via $patronBestOption
            'location'         => false, // Try to only show the best (not the first) location; test example: DAC-372 & DAG-046
        ];
*/
        // Summarize call number, location and availability info across all items:
        $available = false;                     // Track and set to true if at least one item is available
        $availability = false;                  // Human readable detail for the $available status
        $availability_message = false;          // Hmm... (note: available really is more like item status)
        $additional_availability_message = '';  // Hmm...
        $electronic = false;
        $use_unknown_status = false;            // Hmm...
        $reservationLink = '';                  // If possible - either reserve or order
        $placeaholdLink = '';
        $placeaholdArray = [];
        $locHref = '';

        // Some special tracking with arrays
        $callNumbers = $locations = [];
        $use_unknown_status = $available = false;
        $services = [];
        $timestamp = $tr = $trOptions = $timestamps = [];
        $duedate = '';

        // Remember some special copies for the $patronOptions['reserve_or_local'] case
        $referenceCallnumber = false;
        $referenceLocation   = false;

        // if this record is already on loan or reserved by the current patron, we can save a lot of time and stop here
        $patron = $this->loggedInUser;
        if ($patron) {
            $id = (isset($record[0]) && isset($record[0]['id'])) ? $record[0]['id'] : 'no_id';
            //$catalog = $this->getILS();
            //$patron = $this->catalogLogin();
            $patronsPpns = $this->ils->getAllPpnsFrom($patron);
            if (in_array($id, $patronsPpns)) {
                return [
                    'id' => $id,
                    'patronBestOption' => 'already_taken_by_patron',
                    'bestOptionLocation' => 'irrelevant',
                    'availability' => 'false',
                    'callnumber' => 'irrelevant'
                ];
            }
        }

        // Analyze each item of the record (title)
        foreach ($record as $key => $info) {

            // All available services are limited
            if (count($info['services']) == count($info['limitation_types']) && count($info['limitation_types']) > 0) {
                $info['availability'] = false;
                if (substr($info['callnumber'], 0, 4) == 'rara') {
                    $info['location'] = 'rara';
                }
                elseif (substr($info['callnumber'], 0, 5) == 'MagLs') {
                    $info['location'] = 'rara';
                }
                else {
                    $info['location'] = 'Unknown';
                }
            }

            // Keep track of the due dates to finally return the one with the least waiting time
            if (isset($info['duedate'])) {
                $tr[] = $info;
                $timestamp[$key]  = strtotime($info['duedate']);
            }

            // cleanup callnumber if there is a colon inside (callnumber from VZG DAIA2 have the location indication inside, which should not get displayed in our context)
            if (strstr($info['callnumber'], ':') !== false) {
                $cnarray = explode(':', $info['callnumber']);
                $info['callnumber'] = $cnarray[(count($cnarray)-1)];
            }

            // Find an available copy
            if (isset($info['availability']) && $info['availability']) {
                $item_resolved = false;
                $available = true;
                $counts['available']++;

                // item is requestable from closed stack
                if (isset($info['addStorageRetrievalRequestLink']) && isset($info['storageRetrievalRequestLink'])) {
                    $item_resolved = true;
                    $counts['stackorder']++;
                    $view = ['storageRetrievalRequestLink' => $info['storageRetrievalRequestLink']];
                    $placeaholdhref = $this->renderer->render('ajax/option_storageretrievalrequestlink.phtml', $view);
                    $options[] = [ 'option'   => 'storageretrieval',
                          'location' => 'Magazin',
                          'href'     => $placeaholdhref,
                          'record'   => $info
                    ];
                }

                // Check if this item is only for use in the library
                // An item is considered to be for presentation use only, if it has only one available service, which is the presentation service
                // or if it has also a limited loan service
                if (isset($info['services']) && in_array('presentation', $info['services']) === true
                    && (count($info['services']) == '1'
                        || (count($info['services']) > 1 && in_array('loan', $info['services']) === true && count($info['limitation_types']) == 1))
                    && $info['callnumber'] != 'Unknown') {
                    // is this item a DA or a normal local copy for presentation use?
                    if (strlen($info['callnumber']) ==7 && substr($info['callnumber'], 0, 1) == 'D') {
                        $counts['dienstapp']++;
                        if ($item_resolved == false) {
                            $options[] = [ 'option' => 'da', 'location' => isset($info['location']) ? $info['location'] : false, 'href' => false, 'record' => $info ];
                        }
                    } else {
                        $counts['reference']++;
                        if ($item_resolved == false) {
                            $options[] = [ 'option' => 'local', 'location' => isset($info['location']) ? $info['location'] : false, 'href' => false, 'record' => $info ];
                        }
                    }
                    $item_resolved = true;
                }

                // Check is this is a purely electronic item
                // TODO: improve detection as callnumber:Unknown does not seem to be very reliable
                if (((count($info['services']) >= 1 && in_array('remote', $info['services']) === true) || $info['callnumber'] == 'Unknown') && $item_resolved == false) {
                    $counts['electronic']++;
                    $electronic = true;
                    if ($item_resolved == false) {
                        $options[] = [ 'option' => 'electronic', 'location' => isset($info['location']) ? $info['location'] : false, 'href' => isset($info['weblink']) ? $info['weblink'] : false, 'record' => $info ];
                    }
                    $item_resolved = true;
                }

                // if the item is available and has no request link, it must be on shelf
                // Our best option = get it from the shelf. If it is a shelf
                // item we can only determine implicitly. So this only sticks
                // if this copy isn't to be ordered/reserved/reference only/e-only
                if ($item_resolved == false) {
// Woher kommt an dieser Stelle schon patronBestOption? Das wird doch erst spaeter gesetzt?!
                    $options[] = [ 'option' => isset($info['patronBestOption']) ? $info['patronBestOption'] : 'shelf', 'location' => isset($info['location']) ? $info['location'] : false, 'href' => false, 'record' => $info ];
//                    $options[] = [ 'option' => 'shelf', 'location' => isset($info['location']) ? $info['location'] : false, 'href' => false, 'record' => $info ];
                }
            }
            // Not available cases
            else {
                // Dienstapparate are the only special case
                if (strlen($info['callnumber']) ==7 && substr($info['callnumber'], 0, 1) == 'D') {
                    $counts['dienstapp']++;
                    $options[] = [ 'option'   => 'da',
                          'location' => $info['location'],
                          'href'     => false,
                          'record' => $info
                    ];
                }
                // approval required
                else if($info['location'] == 'rara') {
                    $options[] = [ 'option'   => 'rara',
                          'location' => 'Rara',
                          'href'     => false,
                          'record' => $info
                    ];
                }
                // here is something strange: this item has no location, it cannot be lent
                else if($info['location'] == 'Unknown') {
                    $counts['completely_unavailable']++;
                    $options[] = [ 'option'   => 'local',
                          'location' => 'Sonderstandort',
                          'href'     => false,
                          'record' => $info
                    ];
                }
                else if($info['location'] == 'Internet') {
                    $counts['electronic']++;
                    $options[] = [ 'option'   => 'electronic',
                          'location' => 'Internet',
                          'href'     => false,
                          'record'   => $info
                    ];
                }
                // normal case: item is not available as its lent
                else {
                    // Check if this copy has a recallhref
                    if ($info['addLink'] && isset($info['link'])) {
                        // if it has one, it can be cosidered as lent
                        $counts['lent']++;
                        $view = ['storageRetrievalRequestLink' => $info['link']];
                        $placeaholdhref = $this->renderer->render('ajax/option_storageretrievalrequestlink.phtml', $view);

                        // Keep track of the due dates to finally return the one with the least waiting time
                        // Do not add the recall now to the options, it has to get sorted later
                        $trOptions[] =  [ 'option'   => 'recall',
                              'location' => false,
                              'href'     => $placeaholdhref,
                              'record'   => $info
                        ];
                        if (isset($info['duedate'])) {
                            $timestamps[$key]  = strtotime($info['duedate']);
                        }
                        else {
                            // Item does not have a duedate although its unavailable
                            // Thus we dont know when to expect it
                            // Just add 39 days to today for date expected
                            $timestamps[$key] = time()+$info['requests_placed']*39*24*60*60;
                        }
                        // Add reservations/recalls for this item to the duedate
                        $timestamps[$key] += $info['requests_placed']*28*24*60*60;
                        // if the duedate plus reservation time is smaller than today (because the item is overdue),
                        // take todays timestamp as the basis to calculate the new duedate
                        if ($timestamps[$key] <= time()) {
                            $timestamps[$key] = time()+$info['requests_placed']*28*24*60*60;
                        }
                    }
                    // This item is completely unavailable for some reason. Check with staff is needed.
                    else {
                        if (isset($info['requests_placed']) && $info['requests_placed'] > 0) {
                            $counts['reserved_without_link']++;
                            $options[] = [ 'option'   => 'reserved_without_link',
                                  'location' => 'false',
                                  'href'     => false,
                                  'record'   => $info
                            ];
                        }
                        else {
                            $counts['completely_unavailable']++;
                            $options[] = [ 'option'   => 'askstaff',
                                  'location' => 'false',
                                  'href'     => false,
                                  'record'   => $info
                            ];
                        }
                    }
                }
            }

            // TODO: Find cases, see what happens
            if (isset($info['status'])) {
                if      ($info['status'] === 'missing') {$availability = 'missing';}
                elseif  ($info['status'] === 'lost')    {$availability = 'lost';}
            }

            // Check for a use_unknown_message flag
            if (isset($info['use_unknown_message']) && $info['use_unknown_message'] == true) {
                $use_unknown_status = true;
            }

            // Store call number/location info:
            $callNumbers[] = isset($info['callnumber']) ? $info['callnumber'] : null;
            $locations[] = isset($info['location']) ? $info['location'] : null;

            // Add locationhref for Marc21 link (one of them)
            $locHref = isset($info['locationhref']) ? $info['locationhref'] : null;
            // As a fallback, use weblink from DAIA here
            $locHref = isset($info['weblink']) ? $info['weblink'] : $locHref;

            // Store all available services
            if (isset($info['services'])) {
                $services = array_merge($services, $info['services']);
            }
        }

        $counts['borrowable'] = $this->getBorrowableCount($counts);
        $referenceIndicator = $this->getReferenceIndicator($counts);

        // Sort the recalled books and add them to the options in the correct sort order
        array_multisort($timestamps, SORT_ASC, $trOptions);
        foreach ($trOptions as $opt) {
            $options[] = $opt;
        }

        $bestOpt = $this->getTUBBestOption($counts, $options, $available);
        if ($bestOpt == null) {
            error_log("Falling back to old routine checking status for ".$options[0]['record']['id']);
            // forget what we have done so far and start again with our old routine as this is not that simple
            //$statuses = $this->getItemStatusesArray([$options[0]['record']['id']]);
            $statuses = $this->getStatusesFromCatalog([$options[0]['record']['id']]);
            return $statuses[0];
        }
        $callNumber = isset($bestOpt['fullrecord']['callnumber']) ? $bestOpt['fullrecord']['callnumber'] : $this->pickValue($callNumbers, $callnumberSetting, 'Multiple Call Numbers');

//        if (strpos(strtolower($callNumber), 'bestellt') !== false && $bestOpt['patronBestOption'] !== 'e_only') {
        // option acquired is also possible for e_only media, so ignore $bestOpt['patronBestOption'] !== 'e_only'
        if (strpos(strtolower($callNumber), 'bestellt') !== false) {
            $bestOpt['patronBestOption']   = 'acquired';
            $bestOpt['bestOptionHref']     = false;
            $bestOpt['bestOptionLocation'] = 'Shipping';
        }

        // Collect details about links to show in result list
        $availability_message = $use_unknown_status
            ? $messages['unknown']
            : $messages[$available ? 'available' : 'unavailable'];

        if (!empty($services)) {
            $availability_message = $this->reduceServices($services);
        } else {
            $availability_message = $use_unknown_status
                ? $messages['unknown']
                : $messages[$available ? 'available' : 'unavailable'];
        }

        if ($available) {
            // TZ: It's unimportant what is set, as long as something is set (see json return)
            $availability = 'available';
        }
        else if ($duedate === '') {
            $availability_message = $messages['notforloan'];
        }
        else {
            // (TZ: Here availability is "missing" or "lost" as seen above?)
            $additional_availability_message = $availability;
        }

        $id = (isset($record[0]) && isset($record[0]['id'])) ? $record[0]['id'] : 'no_id';

        $multiVol = $this->getMultiVolumes($id);

        // Quick check if all calculation are valid
        $tmp .= "totalCount: $counts[total]
                availableCount: $counts[available]
                borrowableCount: $counts[borrowable]
                referenceCount: $counts[reference]
                lentCount: $counts[lent]
                stackorderCount: $counts[stackorder]
                electronicCount: $counts[electronic]
                dienstappCount: $counts[dienstapp]";
        // */

        $placed_requests = (isset($bestOpt['fullrecord']) && isset($bestOpt['fullrecord']['requests_placed'])) ? $bestOpt['fullrecord']['requests_placed'] : 0;
        // Send back the collected details:
//TZ: Todo: take advantage of patronBestOption in check_item_statuses.js
// Note: reference_location and reference_callnumber are false unless $patronOptions['reserve_or_local'] is true
// TODO: These can be removed, since the best options imply their information: 
// - 'availability', 'location', 'reserve', 'reserve_message', 'reservationUrl'      
// TODO: For these I don't know what they ever where good for
// - 'locationList', 'availability_message' (might be important)       
// TODO: Finally chose better naming (instead of "best")
        return [
            'id' => $id,
            'patronBestOption' => $bestOpt['patronBestOption'],
            'bestOptionHref' => $bestOpt['bestOptionHref'],
            'bestOptionLocation' => $bestOpt['bestOptionLocation'],
            'placed_requests' => $placed_requests,
            'availability' => ($available ? 'true' : 'false'),
            'availability_message' => $availability_message,
            'additional_availability_message' => $additional_availability_message,
            'locHref' => $locHref,
            'callnumber' => htmlentities($callNumber, ENT_COMPAT, 'UTF-8'),
            'duedate' => isset($bestOpt['fullrecord']) ? $this->getFirstDuedate($bestOpt['fullrecord']) : null,
            'presenceOnly' => $referenceIndicator,
            'electronic' => $electronic,
            'reference_location' => $referenceLocation,
            'reference_callnumber' => $referenceCallnumber,
            'multiVols' => $multiVol,
            'tmp' => $tmp //implode('  --  ', $bestLocationPriority)
        ];
    }

    /**
     * Check if all available items can be used reference only.
     * TODO: Maybe it makes sense to use more speaking values 
     * instead of numbers (like 'Only', 'Some', 'OnlyIntimeChoice', ...)
     * $borrowableCount means "it can be fetched immediatly by a patron (a
     * closed stack item we count as "very soon" = immediatly - so we don't
     * substract $stackorderCount as well)
     *
     * @param array  $counts            Information on different counts for this record
     *
     * @return array                    Summarized availability information
     */
    protected function getReferenceIndicator($counts)
    {
        $referenceIndicator = '0';              // Some info about the ratio referenceOnly:borrowable
        if ($counts['reference'] > 0 && $counts['reference'] != $counts['electronic']) {
            // Case a) Yes, ALL items are reference only
            if ($counts['reference'] === $counts['available'] && $counts['available'] == $counts['total']) {
                $referenceIndicator = '1';
            }
            // Case b) No, not all items are reference only and just SOME of the available items are loaned
            elseif ($counts['reference'] !== $counts['available'] && $counts['borrowable'] !== 0 && $counts['lent'] > 0) {
                $referenceIndicator = '2';
            }
            // Case c) No, not all items are reference only but ALL available
            // items are borrowable (btw. that available != borrowable makes it
            // really hard :))
            // Note: For now I keep '2', because currently I don't see a point
            // for giving different messages for b) and c)
            elseif ($counts['reference'] !== $counts['available'] && $counts['borrowable'] > 0 && $counts['lent'] === 0) {
                $referenceIndicator = '2';
            }
            // Case d) No, not all items are reference BUT ALL borrowable items are loaned
            // Important case. This way you can guide patrons to copies when all items are loaned
            else {
                $referenceIndicator = '3';
            }
        }
        return $referenceIndicator;
    }

    /**
     * Get the number of borrowable items.
     *
     * @param array $counts Information on different counts for this record
     *
     * @return int          Number of borrowable items
     */
    protected function getBorrowableCount($counts)
    {
        return $counts['total'] - $counts['reference'] - $counts['lent'] - $counts['dienstapp'] - $counts['electronic'] - $counts['reserved_without_link'];
    }

    /**
     * Get the duedate of this record (if applicable)
     *
     * @param array $record the record
     *
     * @return date         Duedate
     */
    protected function getFirstDuedate($record)
    {
        return $record['duedate'];
    }

    /**
     * Get the best option for this item
     *
     * @param array  $counts        Information on different counts for this record
     * @param array  $options       Current options
     *
     * @return array                Suggestion for best option
     */
    protected function getTUBBestOption($counts, $options, $available)
    {
        $bestOpt = [];
        $reference_copy = false;
        $storage_retrieval = false;
        $recall = false;
        $bestOptionLocation = '';
        $bestOptionHrefStorageRetrieval = '';
        $bestOptionLocationStorageRetrieval = '';

        if (empty($options) || $counts['total'] == $counts['completely_unavailable']) {
            $bestOpt['patronBestOption']   = 'none';
            $bestOpt['bestOptionHref']     = false;
            $bestOpt['bestOptionLocation'] = 'no options for this item';
            return $bestOpt;
        }
        elseif ($available == false && $counts['dienstapp'] == 0 && $counts['reserved_without_link'] == 0 && $counts['lent'] == 0 && $counts['electronic'] < $counts['total'] && $counts['completely_unavailable'] < $counts['total']) {
            $bestOpt['patronBestOption']   = 'shelf';
            $bestOpt['bestOptionHref']     = false;
            $bestOpt['bestOptionLocation'] = 'Benutzung nur nach Voranmeldung';
            return $bestOpt;
        }
        if ($counts['total'] == $counts['dienstapp']) {
            $bestOpt['patronBestOption']   = 'da';
            $bestOpt['bestOptionHref']     = false;
            $bestOpt['bestOptionLocation'] = 'TU-Arbeitsbereich';
            return $bestOpt;
        }
        /*
        if ($counts['electronic'] > 0 && $counts['electronic'] === $counts['total']) {
            // Fallback to old routine for now
            return null;
        }
        */
        foreach ($options as $option) {
            // if we have at least one copy on shelf, return this one as the best and only option
            if ($option['option'] == 'shelf') {
                $bestOpt['patronBestOption'] = 'shelf';
                $bestOpt['bestOptionLocation'] = $option['location'];
                $bestOpt['bestOptionHref']  = $option['href'];
                $bestOpt['fullrecord'] = $option['record'];
                // TODO: prefer LBS books: if there is one, return that copy
                return $bestOpt;
            }
            // we have a reference copy
            if ($option['option'] == 'local') {
                $bestOpt['patronBestOption']   = 'local';
                $bestOpt['bestOptionLocation'] = $option['location'];
                $bestOptionLocation = $option['location'];
                if (!isset($bestOpt['fullrecord'])) {
                    $bestOpt['fullrecord'] = $option['record'];
                }
                // do not return immidediately, just set a token
                $reference_copy = true;
            }
            // we have something in closed stack
            if ($option['option'] == 'storageretrieval') {
                $bestOpt['patronBestOption'] = 'storageretrieval';
                $bestOptionHrefStorageRetrieval  = $option['href'];
                $bestOpt['bestOptionHref']  = $option['href'];
                $bestOptionLocation = $option['location'];
                $bestOptionLocationStorageRetrieval = $option['location'];
                // only set the fullrecord of a closed stack item, if we have nothing yet
                if (!isset($bestOpt['fullrecord'])) {
                    $bestOpt['fullrecord'] = $option['record'];
                }
                // do not return immidediately, just set a token
                $storage_retrieval = true;
            }
            // recallable item currently on loan
            if ($option['option'] == 'recall') {
                $bestOpt['patronBestOption'] = 'recall';
                $duedateTimestamp = strtotime($option['record']['duedate']);
                if (!isset($bestOpt['fullrecord_recall'])
                    || (
                        isset($bestOpt['fullrecord_recall'])
                        && isset($bestOpt['fullrecord']['duedate'])
                        && $duedateTimestamp <= strtotime($bestOpt['fullrecord']['duedate'])
                        && isset($bestOpt['fullrecord']['requests_placed'])
                        && $option['record']['requests_placed'] < $bestOpt['fullrecord']['requests_placed']
                    )
                ) {
                    $bestOpt['bestOptionRecallHref']  = $option['href'];
                    $bestOptionLocation = $option['location'];
                    $bestOpt['fullrecord_recall'] = $option['record'];
                    $bestOpt['fullrecord'] = $option['record'];
                }
                // do not return immidediately, just set a token
                $recall = true;
            }
            // DA should only be the best option if it is the only option
            if ($option['option'] == 'da' && $counts['total'] == ($counts['dienstapp']+$counts['completely_unavailable']) && $available == false) {
                $bestOpt['patronBestOption'] = 'service_desk';
                $bestOpt['bestOptionHref']  = false;
                $bestOpt['bestOptionLocation'] = $option['location'];
                $bestOpt['fullrecord'] = $option['record'];
                // return now as we do not have a better option
                return $bestOpt;
            }
            // reserved, but no new reservation link
            if ($option['option'] == 'reserved_without_link' && $counts['total'] == ($counts['reserved_without_link']+$counts['dienstapp']+$counts['completely_unavailable']) && $available == false) {
                $bestOpt['patronBestOption'] = 'reserved_without_link';
                $bestOpt['bestOptionHref']  = false;
                $bestOpt['bestOptionLocation'] = $option['location'];
                $bestOpt['fullrecord'] = $option['record'];
                // return now as we do not have a better option
                return $bestOpt;
            }
            // Ask staff should only be the best option if it is the only option
            if ($option['option'] == 'askstaff' && $counts['total'] == ($counts['dienstapp']+$counts['completely_unavailable']) && $available == false) {
                $bestOpt['patronBestOption'] = 'askstaff';
                $bestOpt['bestOptionHref']  = false;
                $bestOpt['bestOptionLocation'] = 'Unknown';
                $bestOpt['fullrecord'] = $option['record'];
                // return now as we do not have a better option
                return $bestOpt;
            }
            // if we have a weblink, lets consider using it
            if ($option['option'] == 'electronic' && $counts['total'] == $counts['electronic']) {
                $bestOpt['patronBestOption'] = 'e_only';
                $bestOpt['bestOptionHref']  = false;
                $bestOpt['fullrecord'] = $option['record'];
                $bestOpt['bestOptionLocation'] = 'Internet';
                return $bestOpt;
            }
        }

        // Request from Storage or local are the best options
        if ($storage_retrieval == true && $reference_copy == true && $counts['total'] > 1) {
            $bestOpt['patronBestOption'] = 'request_or_local';
            return $bestOpt;
        }
        // Detection of Journal volumes with volumes bound in parts
        else if ($storage_retrieval == true && $counts['total'] > 1) {
            $bestOpt['bestOptionLocation']  = $bestOptionLocationStorageRetrieval;
            $bestOpt['patronBestOption'] = 'see_copies';
            return $bestOpt;
        }
        // Request from Storage is the best option
        else if ($storage_retrieval == true) {
            $bestOpt['patronBestOption'] = 'storageretrieval';
            $bestOpt['bestOptionHref']  = $bestOptionHrefStorageRetrieval;
            $bestOpt['bestOptionLocation']  = $bestOptionLocationStorageRetrieval;
            return $bestOpt;
        }
        // Recall or local is the best option
        if ($recall == true && $reference_copy == true && $counts['total'] > 1 && $counts['lent'] > 0) {
            $bestOpt['patronBestOption'] = 'reserve_or_local';
            if ($bestOptionLocation !== false && isset($bestOptionLocation)) {
                $bestOpt['bestOptionLocation'] = $bestOptionLocation;
            }
            $bestOpt['bestOptionHref'] = $bestOpt['bestOptionRecallHref'];
            if (!isset($bestOpt['fullrecord'])) {
                $bestOpt['fullrecord'] = $option['record'];
            }
            return $bestOpt;
        }
        if ($reference_copy === true || $storage_retrieval == true || $recall == true) {
            $bestOpt['bestOptionHref'] = isset($bestOpt['bestOptionHref']) ? $bestOpt['bestOptionHref'] : isset($bestOpt['bestOptionRecallHref']) ? $bestOpt['bestOptionRecallHref'] : false;
            if ($bestOptionLocation !== false && $bestOptionLocation !== '' && isset($bestOptionLocation)) {
                $bestOpt['bestOptionLocation'] = $bestOptionLocation;
            }
            // Set bestOptionLocation if it has not been set
            if (!isset($bestOpt['bestOptionLocation'])) {
                $bestOpt['bestOptionLocation'] = "false";
            }
            if (!isset($bestOpt['fullrecord'])) {
                $bestOpt['fullrecord'] = $option['record'];
            }
            return $bestOpt;
        }
        // return, if we did not return until this point
        return null;
    }

    /**
     * Get FULL Item Status (single item)
     *
     * This is responsible for printing the holdings information for a
     * collection of records in JSON format.
     *
     * @todo 2015-10-13
     * - chose a better method name
     * @todo 2015-12-11
     * - replace with rendering recordTabs/holdingsils.phtml
     *
     * @return \Zend\Http\Response
     */
    protected function getItemStatusTUBFullAjax()
    {
        $this->disableSessionWrites();  // avoid session write timing bug
        $catalog = $this->getILS();
        $language = $this->params()->fromQuery('lang');
        $catalog->setLanguage($language);
        $ids = $this->params()->fromQuery('id');
        $results = $catalog->getStatuses($ids);

// START This should be enough IF I knew what var is missing for DAIA and where to get it
    //$current['full_status'] = $renderer->render('recordTabs/holdingsils.phtml', ['statusItems' => $ids]);
    //return $current;
// END

        // Load callnumber and location settings:
        // Overrides for config settings (as used in getItemStatusAjax())
        $showFullStatus = true;
        $locationSetting = 'group';

        // Loop through all the status information that came back
        $statuses = [];
        foreach ($results as $recordNumber => $record) {
            // Filter out suppressed locations:
            $record = $this->filterSuppressedLocations($record);

            // Skip empty records:
            if (count($record)) {
                if ($locationSetting == "group") {
                    $current = $this->getItemStatusGroup(
                        $record, $this->getAvailabilityMessages(), $callnumberSetting
                    );
                };

                // If a full status display has been requested, append the HTML:
                if ($showFullStatus) {
                    $current['full_status'] = $renderer->render(
                        'ajax/status-full.phtml', ['statusItems' => $record]
//                    $current['full_status'] = $renderer->render(
//                        'record/view-tabs.phtml', ['statusItems' => $record]
                    );
                }
                $current['record_number'] = array_search($current['id'], $ids);
                $statuses[] = $current;
            }
        }

        // Done
        return $this->output($statuses, self::STATUS_OK);
    }

    /**
     * Get number of matches for a certain tab
     *
     * @return \Zend\Http\Response
     */
    public function getNumberOfMatchesAjax() {
        if ($_REQUEST['idx'] == 'gbv') {
            $results = $this->getResultsManager()->get('Solr');
            $params = $results->getParams();
            $params->initFromRequest($this->getRequest()->getQuery());
            $recordCount = $results->getResultTotal();
        }
        if ($_REQUEST['idx'] == 'primo') {
            $results = $this->getResultsManager()->get('Primo');
            $params = $results->getParams();
            $params->initFromRequest($this->getRequest()->getQuery());
            $recordCount = $results->getResultTotal();
        }

        return $this->output(array('matches' => $recordCount), self::STATUS_OK);
    }


    /**
     * Load information about multivolumes for this item
     *
     * @return bool
     */
    protected function getMultiVolumes($id)
    {
        try {
            $driver = $this->auxLoader->load(
                $id,
                $this->params->fromPost('source', 'Solr')
            );
            return $driver->isMultipartChildren();
        } catch (\Exception $e) {
            // Do nothing -- just return null
            return null;
        }
    }

    /**
     * Load information about multivolumes for this item
     *
     * @return bool
     */
    protected function loadVolumeListAjax()
    {
        $mpList = new MultipartList($_REQUEST['id']);
        if (!$mpList->hasList()) {
            $driver = $this->getRecordLoader()->load(
                $_REQUEST['id']
            );
            $driver->cacheMultipartChildren();
        }
        return true;
    }

    /**
     * Get the content of this tab page by page.
     *
     * @return \Zend\Http\Response
     */
    public function getMultipartAjax()
    {
        $retval = array();
        $mpList = new MultipartList($_REQUEST['id']);
        if ($mpList->hasList()) {
            $retval = $mpList->getCachedMultipartChildren();
        }
        else {
            $this->loadVolumeListAjax();
            // call this method recursively - now we should have the cached result
            return $this->getMultipartAjax();
        }
        return $this->output($retval, self::STATUS_OK);
    }

    /**
     * Get the content of this tab page by page.
     *
     * @return \Zend\Http\Response
     */
    public function getNumberOfMyResearchAjax()
    {
        $catalog = $this->getILS();
        $patron = $this->catalogLogin();
        $retval = $catalog->getNumberOf($patron);
        return $this->output($retval, self::STATUS_OK);
    }

    /**
     * Get the content of this tab page by page.
     *
     * @return \Zend\Http\Response
     */
    public function getNumberOfTransactionsAjax()
    {
        $catalog = $this->getILS();
        $patron = $this->catalogLogin();
        $retval = $catalog->getNumberOf($patron, 'transactions');
        return $this->output($retval, self::STATUS_OK);
    }

    /**
     * Get the content of this tab page by page.
     *
     * @return \Zend\Http\Response
     */
    public function getNumberOfHoldsAjax()
    {
        $catalog = $this->getILS();
        $patron = $this->catalogLogin();
        $retval = $catalog->getNumberOf($patron, 'holds');
        return $this->output($retval, self::STATUS_OK);
    }

    /**
     * Get the content of this tab page by page.
     *
     * @return \Zend\Http\Response
     */
    public function getNumberOfStorageRetrievalRequestsAjax()
    {
        $catalog = $this->getILS();
        $patron = $this->catalogLogin();
        $retval = $catalog->getNumberOf($patron, 'storageRetrievalRequests');
        return $this->output($retval, self::STATUS_OK);
    }

    /**
     * Get the content of this tab page by page.
     *
     * @return \Zend\Http\Response
     */
    public function getFinesTotalAjax()
    {
        $catalog = $this->getILS();
        $patron = $this->catalogLogin();
        $fine = $catalog->getNumberOf($patron, 'fines');
        $retval = $fine;
        return $this->output($retval, self::STATUS_OK);
    }

    /**
     * Check write permission of patron
     *
     * @return \Zend\Http\Response
     */
    public function getProfileWritePermissionAjax()
    {
        $catalog = $this->getILS();
        $patron = $this->catalogLogin();
        $retval = $catalog->getMyProfile($patron);
        $canWrite = $retval['canWrite'];
        return $this->output($canWrite, self::STATUS_OK);
    }

}
