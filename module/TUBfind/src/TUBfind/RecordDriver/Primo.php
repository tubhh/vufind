<?php
/**
 * Model for Primo Central records.
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
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org/wiki/vufind2:record_drivers Wiki
 */
namespace TUBfind\RecordDriver;

use DOMDocument;

use VuFindSearch\Backend\Solr\Backend;
use VuFindSearch\Query\Query;
use VuFindSearch\ParamBag;

use VuFind\Search\Factory\PrimoBackendFactory;
use VuFind\Search\Factory\SolrDefaultBackendFactory;

/**
 * Model for Primo Central records.
 *
 * @category VuFind2
 * @package  RecordDrivers
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org/wiki/vufind2:record_drivers Wiki
 */
class Primo extends \VuFind\RecordDriver\Primo
{
    /**
     * Date converter
     *
     * @var \VuFind\Date\Converter
     */
    protected $dateConverter = null;

    /**
     * Get the short (pre-subtitle) title of the record.
     *
     * @return string
     */
    public function getShortTitle()
    {
        return $this->getTitle();
    }

    /**
     * Get the full title of the record.
     *
     * @return string
     */
    public function getTitle()
    {
        return isset($this->fields['title'])
            ? $this->fields['title'] : '';
    }

    /**
     * Get the main author of the record.
     *
     * @return string
     */
    public function getPrimaryAuthor()
    {
        return isset($this->fields['creator'][0]) ?
            $this->fields['creator'][0] : '';
    }

    /**
     * Get the date of publication of the record.
     *
     * @return string
     */
    public function getPublicationDate()
    {
        return isset($this->fields['publicationDate']) ?
            $this->fields['publicationDate'] : '';
    }

    /**
     * Get an array of all secondary authors (complementing getPrimaryAuthor()).
     *
     * @return array
     */
    public function getSecondaryAuthors()
    {
        $authors = [];
        if (isset($this->fields['creator'])) {
            for ($i = 1; $i < count($this->fields['creator']); $i++) {
                if (isset($this->fields['creator'][$i])) {
                    $authors[] = $this->fields['creator'][$i];
                }
            }
        }
        return $authors;
    }

    /**
     * Get the authors of the record.
     *
     * @return array
     */
    public function getCreators()
    {
        return isset($this->fields['creator'])
            ? $this->fields['creator'] : [];
    }

    /**
     * Get an array of all subject headings associated with the record
     * (may be empty).
     *
     * @return array
     */
    public function getAllSubjectHeadings($extended = false)
    {
        $base = isset($this->fields['subjects'])
            ? $this->fields['subjects'] : [];
        $callback = function ($str) {
            return array_map('trim', explode(' -- ', $str));
        };
        return array_map($callback, $base);
    }

    /**
     * Get a full, free-form reference to the context of the item that contains this
     * record (i.e. volume, year, issue, pages).
     *
     * @return string
     */
    public function getContainerReference()
    {
        $parts = explode(',', $this->getIsPartOf(), 2);
        return isset($parts[1]) ? trim($parts[1]) : '';
    }

    /**
     * Get the end page of the item that contains this record.
     *
     * @return string
     */
    public function getContainerEndPage()
    {
        return isset($this->fields['container_end_page'])
            ? $this->fields['container_end_page'] : '';
    }

    /**
     * Get an array of all the formats associated with the record.
     *
     * @return array
     */
    public function getFormats()
    {
        return isset($this->fields['format'])
            ? (array)$this->fields['format'] : [];
    }

    /**
     * Get the item's "is part of".
     *
     * @return string
     */
    public function getIsPartOf()
    {
        return isset($this->fields['ispartof'])
            ? $this->fields['ispartof'] : '';
    }

    /**
     * Get the item's description.
     *
     * @return array
     */
    public function getDescription()
    {
        return isset($this->fields['description'])
            ? $this->fields['description'] : [];
    }

    /**
     * Get the item's source.
     *
     * @return array
     */
    public function getSource()
    {
        $base = isset($this->fields['source']) ? $this->fields['source'] : '';
        // Trim off unwanted image and any other tags:
        return strip_tags($base);
    }

    /**
     * Get an array of all ISSNs associated with the record (may be empty).
     *
     * @return array
     */
    public function getISSNs()
    {
        $issns = [];
        if (isset($this->fields['issn'])) {
            $issns = $this->fields['issn'];
        }
        return $issns;
    }

    /**
     * Get the language associated with the record.
     *
     * @return String
     */
    public function getLanguages()
    {
        return isset($this->fields['language'])
            ? (array)$this->fields['language'] : [];
    }

    /**
     * Get the series title associated with the record.
     *
     * @return String
     */
    public function getSeriesTitle()
    {
        return isset($this->fields['seriesTitle'])
            ? (string)$this->fields['seriesTitle'] : '';
    }

    /**
     * Get the FRBR id for with the record to get related items.
     *
     * @return String
     */
    public function getFrbrId()
    {
        return isset($this->fields['frbrid'])
            ? (string)$this->fields['frbrid'] : '';
    }

    /**
     * Returns one of three things: a full URL to a thumbnail preview of the record
     * if an image is available in an external system; an array of parameters to
     * send to VuFind's internal cover generator if no fixed URL exists; or false
     * if no thumbnail can be generated.
     *
     * @param string $size Size of thumbnail (small, medium or large -- small is
     * default).
     *
     * @return string|array|bool
     */
    public function getThumbnail($size = 'medium')
    {
        if (isset($this->fields['thumbnail']) && $this->fields['thumbnail']) {
            return $this->fields['thumbnail'];
        }
        $arr = [
            'author'     => mb_substr($this->getPrimaryAuthor(), 0, 300, 'utf-8'),
            'callnumber' => $this->getCallNumber(),
            'size'       => $size,
            'title'      => mb_substr($this->getTitle(), 0, 300, 'utf-8'),
            'contenttype' => 'JournalArticle'
        ];
        if ($isbn = $this->getCleanISBN()) {
            $arr['isbn'] = $isbn;
        }
        if ($issn = $this->getCleanISSN()) {
            $arr['issn'] = $issn;
        }
        // If an ILS driver has injected extra details, check for IDs in there
        // to fill gaps:
        if ($ilsDetails = $this->getExtraDetail('ils_details')) {
            foreach (['isbn', 'issn', 'oclc', 'upc'] as $key) {
                if (!isset($arr[$key]) && isset($ilsDetails[$key])) {
                    $arr[$key] = $ilsDetails[$key];
                }
            }
        }
        return $arr;
    }

    /**
     * Return an array of associative URL arrays with one or more of the following
     * keys:
     *
     * <li>
     *   <ul>desc: URL description text to display (optional)</ul>
     *   <ul>url: fully-formed URL (required if 'route' is absent)</ul>
     *   <ul>route: VuFind route to build URL with (required if 'url' is absent)</ul>
     *   <ul>routeParams: Parameters for route (optional)</ul>
     *   <ul>queryString: Query params to append after building route (optional)</ul>
     * </li>
     *
     * @return array
     */
    public function getURLs()
    {
        $retVal = [];

        if (isset($this->fields['url'])) {
            $retVal[] = [];
            $retVal[0]['url'] = $this->fields['url'];
            if (isset($this->fields['fulltext'])) {
                $desc = $this->fields['fulltext'] == 'fulltext'
                    ? 'Get full text' : 'Request full text';
                $retVal[0]['desc'] = $this->translate($desc);
            }
        }
        if (isset($this->fields['directurl'])) {
            $retVal[1]['url'] = (string)$this->fields['directurl'];
            $retVal[1]['desc'] = $this->fields['directurl'];
        }

        return $retVal;
    }

    /**
     * Get the direct url to the record fulltext (if available).
     *
     * @return String
     */
    public function getDirectUrl()
    {
        return isset($this->fields['directurl'])
            ? (string)$this->fields['directurl'] : '';
    }

    /**
     * Return the unique identifier of this record within the Solr index;
     * useful for retrieving additional information (like tags and user
     * comments) from the external MySQL database.
     *
     * @return string Unique identifier.
     */
    public function getUniqueID()
    {
        return $this->fields['recordid'];
    }

    /**
     * Get an array of strings representing citation formats supported
     * by this record's data (empty if none).  For possible legal values,
     * see /application/themes/root/helpers/Citation.php, getCitation()
     * method.
     *
     * @return array Strings representing citation formats.
     */
    protected function getSupportedCitationFormats()
    {
        return [];
    }

    /**
     * Indicate whether export is disabled for a particular format.
     *
     * @param string $format Export format
     *
     * @return bool
     *
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function exportDisabled($format)
    {
        // Nothing is diabled by default
        return false;
        // Only allow export for EndNote and RefWorks
        return !in_array($format, ['EndNote', 'RefWorks']);
    }



    public function getFrbrRecords($id, $frbrid) {
        // cannot work without frbr-ID
        if ($frbrid === null) return null;

        $oncampus = true;
/*        if (isset($searchSettings['AuthorizedMode']['enabled'])) {
            if (substr($_SERVER['REMOTE_ADDR'], 0, strlen($this->authorizedIPRange)) == $this->authorizedIPRange && $searchSettings['AuthorizedMode']['enabled'] != false) {
                $oncampus = 'true';
            }
        }
*/

        $params = new ParamBag();
        $params->set('onCampus', $oncampus);

        $query = new \VuFindSearch\Query\Query($frbrid);
        $query->setHandler('frbr');
        $q1 = $this->searchService->search('Primo', $query, 0, 0, $params);
        $all = $q1->getTotal();
        if ($all > 0) {
            $results = $this->searchService->search('Primo', $query, 0, $all, $params);
        }

        if ($results) {
            $resArr = $results->getRecords();
            foreach ($resArr as $rec) {
                if ($rec->getUniqueId() != $id) {
                    $return[] = $rec;
                }
            }
        }

        return $return;
    }

    /**
     * Search for the journal, which is containing this item
     * and check if it is available printed in local stock.
     *
     * @return mixed (SolrRecords or false if nothing has been found)
     * @access public
     */
    public function searchArticleVolume($fieldref)
    {
        if (in_array('Article', $this->getFormats()) === true || in_array('Reference Entry', $this->getFormats()) === true) {
            $f1info = false;
            $f2info = false;
            $results = null;

            $queryparts = array();

            if (count($fieldref['issn']) > 0) {
                $queryparts[] = 'issn:('.implode(' OR ', str_replace(' ', '', $fieldref['issn'])).')';
            }
            else {
                $queryparts[] = '"'.$fieldref['title'].'"';
            }
            $fieldsToSearch = '';
            if ($fieldref['volume']) {
                $f1info = true;
                $fieldsToSearch .= $fieldref['volume'].'.';
            }
            if ($fieldref['issue']) {
                $queryparts[] = 'series:'.$fieldref['issue'];
            }
            if ($fieldref['date']) {
                $f2info = true;
                $fieldsToSearch .= $fieldref['date'];
                // the given year should always match in publishDate
                $queryparts[] = 'publishDate:'.$fieldref['date'];
            }
            if ($fieldsToSearch) {
                $queryparts[] = $fieldsToSearch;
            }
            if ($f1info && $f2info) {
                $queryparts[] = 'format:(Book OR "Serial Volume")';
            }
            else {
                // not sure what to search since the volume and year reference are missing, so just search the main journal record
                $queryparts[] = 'format:Journal';
            }
            // Assemble the query parts and filter out current record:
            $query = implode(" AND ", $queryparts);
            $searchQ = '('.$query.')';

            $hiddenFilters = null;
            // Get filters from config file
            if (isset($this->recordConfig->Printed->local_filters)) {
                $hiddenFilters = $this->recordConfig->Printed->local_filters->toArray();
            }

            $params = new ParamBag();
            if ($hiddenFilters) {
                $params->set('fq', $hiddenFilters);
            }

            $query = new \VuFindSearch\Query\Query($searchQ);
            $q1 = $this->searchService->search('Solr', $query, 0, 0, $params);
            $all = $q1->getTotal();
            if ($all > 0) {
                $results = $this->searchService->search('Solr', $query, 0, $all, $params);
            }

            // If we got no results, do another query with the title instead of ISSN - but only if we have volume information
            $all2 = 0;
            if ($all == 0 && $f2info == true && $f1info == true) {
                $altqueryparts = array();
                $altqueryparts[] = '"'.$fieldref['title'].'"';
                // the given year should always match in publishDate
                $altqueryparts[] = 'publishDate:'.$fieldref['date'];
                $altqueryparts[] = '"'.$fieldsToSearch.'"';
                $altqueryparts[] = 'format:(Book OR "Serial Volume")';
                // Assemble the query parts and filter out current record:
                $altquery = implode(" AND ", $altqueryparts);
                $altquery = '('.$altquery.')';

                // We need new ParamBags for each query! If we use the old ParamBag, the result is taken from Cache
                $p2 = new ParamBag();
                if ($hiddenFilters) {
                    $p2->set('fq', $hiddenFilters);
                }

                $aquery = new \VuFindSearch\Query\Query($altquery);
                $q2 = $this->searchService->search('Solr', $aquery, 0, 0, $p2);
                $all2 = $q2->getTotal();
                if ($all2 > 0) {
                    $results = $this->searchService->search('Solr', $aquery, 0, $all2, $p2);
                }
            }

            // If we STILL got no results, do another query with the ISSN and format:Journal, just to show, that we have the Journal
            $all3 = 0;
            if ($all == 0 && $all2 == 0) {
                $naltqueryparts = array();
                if (count($fieldref['issn']) > 0) {
                    $naltqueryparts[] = 'issn:('.implode(' OR ', str_replace(' ', '', $fieldref['issn'])).')';
                    $naltqueryparts[] = 'format:Journal';
                    // Assemble the query parts and filter out current record:
                    $naltquery = implode(" AND ", $naltqueryparts);
                    $naltquery = '('.$naltquery.')';

                    $p3 = new ParamBag();
                    if ($hiddenFilters) {
                        $p3->set('fq', $hiddenFilters);
                    }

                    $naquery = new \VuFindSearch\Query\Query($naltquery);
                    $q3 = $this->searchService->search('Solr', $naquery, 0, 0, $p3);
                    $all3 = $q3->getTotal();
                    if ($all3 > 0) {
                        $results = $this->searchService->search('Solr', $naquery, 0, $all3, $p3);
                    }
                }
            }

            // And now try to narrow it down again: if we have found the journal's PPN, try to find it as ppnlink in connection with the volume information
            // This is necessary if the journal volume has no ISSN in catalog
            if ($all3 > 0) {
                $parentID = $results->getRecords()[0]->getUniqueID();

                $nnaltqueryparts = array();
                $nnaltqueryparts[] = 'ppnlink:'.$parentID;
                if ($fieldsToSearch) {
                    $nnaltqueryparts[] = $fieldsToSearch;
                }
                if ($fieldref['issue']) {
                    $nnaltqueryparts[] = 'series:'.$fieldref['issue'];
                }
                $nnaltqueryparts[] = 'format:(Book OR "Serial Volume")';
                // Assemble the query parts and filter out current record:
                $nnaltquery = implode(" AND ", $nnaltqueryparts);
                $nnaltquery = '('.$nnaltquery.')';

                $p4 = new ParamBag();
                if ($hiddenFilters) {
                    $p4->set('fq', $hiddenFilters);
                }

                $nnaquery = new \VuFindSearch\Query\Query($nnaltquery);
                $q4 = $this->searchService->search('Solr', $nnaquery, 0, 0, $p4);
                $all4 = $q4->getTotal();
                if ($all4 > 0) {
                    $results = $this->searchService->search('Solr', $nnaquery, 0, $all4, $p4);
                }
            }

            // And now try to narrow it down again: if we have found the journal's PPN, try to find it as ppnlink in connection with the volume information
            // As we found nothing with the issue information, omit it in the next step
            if ($all3 > 0 && $all4 == 0) {
                $parentID = $results->getRecords()[0]->getUniqueID();

                $nnaltqueryparts = array();
                $nnaltqueryparts[] = 'ppnlink:'.$parentID;
                if ($fieldsToSearch) {
                    $nnaltqueryparts[] = $fieldsToSearch;
                }
                $nnaltqueryparts[] = 'format:(Book OR "Serial Volume")';
                // Assemble the query parts and filter out current record:
                $nnaltquery = implode(" AND ", $nnaltqueryparts);
                $nnaltquery = '('.$nnaltquery.')';

                $p5 = new ParamBag();
                if ($hiddenFilters) {
                    $p5->set('fq', $hiddenFilters);
                }

                $nnaquery = new \VuFindSearch\Query\Query($nnaltquery);
                $q5 = $this->searchService->search('Solr', $nnaquery, 0, 0, $p5);
                $all5 = $q5->getTotal();
                if ($all5 > 0) {
                    $results = $this->searchService->search('Solr', $nnaquery, 0, $all5, $p5);
                }
            }
            if ($results) {
                // Now that we got something, we can check printed license in holdingsfile
                $yeartocheck = $fieldref['date'];
                // But this will work only with an ISSN
                if (count($fieldref['issn']) > 100) {
                    foreach ($fieldref['issn'] as $iss) {
                        $issntocheck = str_replace('-', '', $iss);

                        $printedholdings = file_get_contents('https://www.tub.tuhh.de/ext/holdings/sfxprinted.xml');
                        $dom = new DomDocument();
                        $dom->loadXML($printedholdings);
                        $items = $dom->documentElement->getElementsByTagName('item');
                        foreach ($items as $item) {
                            $issnArray = $item->getElementsByTagName('issn');
                            foreach ($issnArray as $issnVar) {
                                if ($issnVar->nodeValue == $issntocheck || $issnVar->nodeValue == $iss) {
                                    $coverages = $item->getElementsByTagName('coverage');
                                    foreach ($coverages as $coverage) {
                                        if (
                                            $yeartocheck >= $coverage->getElementsByTagName('from')->item(0)->getElementsByTagName('year')->item(0)->nodeValue
                                            && (
                                                $yeartocheck <= $coverage->getElementsByTagName('to')->item(0)->getElementsByTagName('year')->item(0)->nodeValue
                                                || $coverage->getElementsByTagName('to')->item(0)->getElementsByTagName('year')->item(0)->nodeValue == null
                                            )
                                        ) {
                                            return $results->getRecords();
                                        }
                                    }
                                }
                            }
                        }
                    }
                }
                else {
                    // No ISSN, so we cant check, just return best guess
                    return $results->getRecords();
                }
            }

            return false;

        }
        return false;
    }

    /**
     * Check if the ebook is available printed in local stock.
     *
     * @return bool
     * @access protected
     */
    public function searchPrintedEbook($fieldref)
    {
        if (in_array('Book', $this->getFormats()) === true || in_array('book_chapter', $this->getFormats()) === true) {
            $isbnsearch = false;
            $results = null;

            $queryparts = array();
            $queryparts[] = trim(addslashes($fieldref['title']));
            if (count($fieldref['isbn']) > 0) {
                $isbnsearch = true;
                $queryparts[] = 'isbn:('.implode(' OR ', $fieldref['isbn']).')';
            }
            if (count($fieldref['issn']) > 0) {
                $isbnsearch = true;
                $queryparts[] = 'issn:('.implode(' OR ', $fieldref['issn']).')';
                if ($fieldref['date']) {
                    $queryparts[] = 'publishDate:'.$fieldref['date'];
                }
            }
            if ($isbnsearch === false) {
                $queryparts[] = 'title:("'.trim(addslashes($fieldref['title'])).'")';

                if ($fieldref['date']) {
                    $queryparts[] = 'publishDate:'.$fieldref['date'];
                }
                if ($fieldref['author']) {
                    $queryparts[] = 'author:"'.addslashes($fieldref['author']).'"';
                }
            }
            $queryparts[] = '(format:Book OR format:"Serial Volume")';
            // Assemble the query parts and filter out current record:
            $query = implode(" AND ", $queryparts);
            $searchQ = '('.$query.')';
            //$query = '(ppnlink:'.$rid.' AND '.$fieldref.')';

            $hiddenFilters = null;
            // Get filters from config file
            if (isset($this->recordConfig->Printed->local_filters)) {
                $hiddenFilters = $this->recordConfig->Printed->local_filters->toArray();
            }

            $params = new ParamBag();
            if ($hiddenFilters) {
                $params->set('fq', $hiddenFilters);
            }

            $query = new \VuFindSearch\Query\Query($searchQ);
            $q1 = $this->searchService->search('Solr', $query, 0, 0, $params);
            $all = $q1->getTotal();
            if ($all > 0) {
                $results = $this->searchService->search('Solr', $query, 0, $all, $params);
            }

            return ($results) ? $results->getRecords() : false;
        }
        return false;
    }

    /**
     * Check if at least one article for this item exists.
     * Method to keep performance lean in core.tpl.
     *
     * @return bool
     * @access protected
     */
    public function searchGBVPPN($ppn)
    {
        $index = $this->getIndexEngine();

        $query = 'id:'.$ppn;

        $result = $index->search($query, null, null, 0, 1, null, '', null, null, '',  HTTP_REQUEST_METHOD_POST, false, false, false);

        return ($result['response'] > 0) ? $result['response'] : false;
    }

    /**
     * Get the container record id.
     *
     * @return string Container record id (empty string if none)
     */
    public function getContainerRecordID() {
        $articleFieldedRef = $this->getArticleFieldedReference();
        $vol = $this->searchArticleVolume($articleFieldedRef);
        $containerID = null;
        if ($vol && count($vol) >= 1) {
            $containerID = $vol[0]->getUniqueID();
        }
        return $containerID;
    }

    /**
     * Get the container record id.
     *
     * @return string Container record id (empty string if none)
     */
    public function getPrintedEbookRecordID() {
        $fieldedRef = $this->getEbookFieldedReference();
        $ref = $this->searchPrintedEbook($fieldedRef);
        $ebookID = null;
        if ($ref && count($ref) >= 1) {
            $ebookID = $ref[0]->getUniqueID();
        }
        return $ebookID;
    }

    /**
     * TUBHH Enhancement for GBV Discovery
     * Return the reference of one article
     * An array will be returned with keys=volume, issue, startpage [spage], endpage [epage] and publication year [date].
     *
     * @access  public
     * @return  array
     */
    public function getArticleFieldedReference()
    {
        $retVal = array();
        $retVal['volume'] = $this->getContainerVolume();
        $retVal['issue'] = str_replace('-', '/', $this->getContainerIssue());
        $retVal['spage'] = $this->getContainerStartPage();
        $retVal['epage'] = $this->getContainerEndPage();
        $retVal['date'] = $this->getPublicationDate();
        $retVal['title'] = $this->getContainerTitle();
        $retVal['issn'] = $this->getISSNs();
#        $retVal['edition'] = $this->fields['edition'];
        return $retVal;
    }

    /**
      * Get the DOI of the item that contains this record
      *
      * @return string
      */
     public function getContainerDoi()
     {
         return isset($this->fields['doi'])
             ? $this->fields['doi'] : '';
     }

    /** 
     * TUBHH Enhancement for GBV Discovery
     * Return the reference of an eBook
     * An array will be returned with keys=title, publication year [date], isbn and author.
     *
     * @access  public
     * @return  array
     */
    public function getEbookFieldedReference()
    {
        $retVal = array();
        $retVal['title'] = $this->getTitle();
        $retVal['date'] = $this->getPublicationDate();
        $retVal['isbn'] = $this->getISBNs();
        $retVal['issn'] = $this->getISSNs();
        $retVal['author'] = $this->getPrimaryAuthor();
        return $retVal;
    }

    /**
     * Returns true if the record supports real-time AJAX status lookups.
     *
     * @return bool
     */
    public function supportsAjaxStatus()
    {
        return true;
    }

    /**
     * Returns the PPN (ID) of a GBV record.
     *
     * @return string
     */
    public function getGbvPpn() {
        $ppn = null;
        if (substr($this->getUniqueId(), 0, 3) == 'gbv') {
            $ppn = substr($this->getUniqueId(), 3);
        }
        return $ppn;
    }

    /**
     * Checks whether this is a GBV record or not.
     *
     * @return bool
     */
    public function isGbvRecord() {
        if ($this->getGbvPpn() !== null) return true;
        return false;
    }

    /**
     * Get the OpenURL parameters to represent this record (useful for the
     * title attribute of a COinS span tag).
     *
     * @param bool $overrideSupportsOpenUrl Flag to override checking
     * supportsOpenUrl() (default is false)
     *
     * @return string OpenURL parameters.
     */
    public function getOpenUrl($overrideSupportsOpenUrl = false)
    {
        if (isset($this->fields['url'])) {
            if (strpos($this->fields['url'], 'sfx.gbv.de') !== false) {
                $urlarr = explode('?', $this->fields['url']);
                return $urlarr[1];
            }
        }

        return parent::getOpenUrl($overrideSupportsOpenUrl);
    }

}
