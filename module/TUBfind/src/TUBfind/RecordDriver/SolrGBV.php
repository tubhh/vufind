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

//use Laminas\ServiceManager\ServiceLocatorAwareInterface;
//use Laminas\ServiceManager\ServiceLocatorInterface;

use VuFindSearch\Backend\Solr\Backend;
use VuFindSearch\Query\Query;
use VuFindSearch\ParamBag;

use TUBfind\Content\MultipartList;

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
class SolrGBV extends \VuFind\RecordDriver\SolrMarc
{
    /**
     * Support method for getOpenURL() -- pick the OpenURL format.
     *
     * @return string
     */
    protected function getOpenURLFormat()
    {
        // If we have multiple formats, Book, Journal and Article are most
        // important...
        $formats = $this->getFormats();
        if ($this->isHSS() === true) {
            return 'dissertation';
        }
        if (in_array('Book', $formats) || in_array('eBook', $formats)) {
            return 'book';
        } else if (in_array('Article', $formats) || in_array('Aufsätze', $formats) || in_array('Elektronische Aufsätze', $formats) || in_array('electronic Article', $formats)) {
            return 'article';
        } else if (in_array('Journal', $formats) || in_array('eJournal', $formats)) {
            return 'journal';
        } else if (in_array('Serial Volume', $formats)) {
            return 'SerialVolume';
        } else if (isset($formats[0])) {
            return $formats[0];
        } else if (strlen($this->getCleanISSN()) > 0) {
            return 'journal';
        }
        return 'book';
    }

    /**
     * Get default OpenURL parameters.
     *
     * @return array
     */
    protected function getDefaultOpenURLParams()
    {
        // Get a representative publication date:
        $pubDate = $this->getPublicationDates();
        $pubDate = empty($pubDate) ? '' : $pubDate[0];

        $urls = $this->getUrls();
        $doi = null;
        if ($urls) {
            foreach ($urls as $url => $desc) {
                // check if we have a doi
                if (strstr($url, 'http://dx.doi.org/') !== false) {
                    $doi = 'info:doi/'.substr($url, 18);
                }
            }
        }

        // Start an array of OpenURL parameters:
        return [
            'url_ver' => 'Z39.88-2004',
            'ctx_ver' => 'Z39.88-2004',
            'ctx_enc' => 'info:ofi/enc:UTF-8',
            'rfr_id' => 'info:sid/' . $this->getCoinsID(),
            'rft.title' => $this->getShortTitle(),
            'rft.date' => $pubDate,
            'rft_id' => $doi,
            'rft.genre' => $this->getOpenURLFormat()
        ];
    }

    /**
     * Get OpenURL parameters for a book.
     *
     * @return array
     */
    protected function getBookOpenURLParams()
    {
        $params = $this->getDefaultOpenURLParams();
        $params['rft_val_fmt'] = 'info:ofi/fmt:kev:mtx:book';
//        $params['rft.genre'] = 'book';
        $params['rft.btitle'] = $params['rft.title'];
        $series = $this->getSeries();
        if (count($series) > 0) {
            // Handle both possible return formats of getSeries:
            $params['rft.series'] = is_array($series[0]) ?
                $series[0]['name'] : $series[0];
        }
        $params['rft.au'] = $this->getPrimaryAuthor();
        $publishers = $this->getPublishers();
        if (count($publishers) > 0) {
            $params['rft.pub'] = $publishers[0];
        }
        $params['rft.edition'] = $this->getEdition();
        $params['rft.isbn'] = (string)$this->getCleanISBN();
        return $params;
    }

    /**
     * Get OpenURL parameters for a serial volume.
     *
     * @return array
     */
    protected function getSerialVolumeOpenURLParams()
    {
        $params = $this->getUnknownFormatOpenURLParams('Journal');
        /* This is probably the most technically correct way to represent
         * a journal run as an OpenURL; however, it doesn't work well with
         * Zotero, so it is currently commented out -- instead, we just add
         * some extra fields and to the "unknown format" case. */
        $params['rft_val_fmt'] = 'info:ofi/fmt:kev:mtx:book';
//        $params['rft.genre'] = 'journal';
        $params['rft.jtitle'] = $params['rft.title'];
        $params['rft.issn'] = $this->getCleanISSN();
        $params['rft.au'] = $this->getPrimaryAuthor();

        //$params['rft.issn'] = (string)$this->getCleanISSN();

        // Including a date in a title-level Journal OpenURL may be too
        // limiting -- in some link resolvers, it may cause the exclusion
        // of databases if they do not cover the exact date provided!
        //unset($params['rft.date']);

        // If we're working with the SFX resolver, we should add a
        // special parameter to ensure that electronic holdings links
        // are shown even though no specific date or issue is specified:
        if (isset($this->mainConfig->OpenURL->resolver)
            && strtolower($this->mainConfig->OpenURL->resolver) == 'sfx'
        ) {
            //$params['sfx.ignore_date_threshold'] = 1;
            $params['disable_directlink'] = "true";
            $params['sfx.directlink'] = "off";
        }
        return $params;
    }


    /**
     * Get OpenURL parameters for an article.
     *
     * @return array
     */
    protected function getArticleOpenURLParams()
    {
        $params = $this->getDefaultOpenURLParams();
        unset($params['rft.date']);
        $params['rft_val_fmt'] = 'info:ofi/fmt:kev:mtx:journal';
//        $params['rft.genre'] = 'article';
        $params['rft.issn'] = (string)$this->getCleanISSN();
        // an article may have also an ISBN:
        $params['rft.isbn'] = (string)$this->getCleanISBN();
        $articleFields = $this->getArticleFieldedReference();
        if ($articleFields['volume']) $params['rft.volume'] = $articleFields['volume'];
        if ($articleFields['issue']) $params['rft.issue'] = $articleFields['issue'];
        if ($articleFields['spage']) $params['rft.spage'] = $articleFields['spage'];
        if (isset($articleFields['epage'])) $params['rft.epage'] = $articleFields['epage'];
        if ($articleFields['date']) $params['rft.date'] = $articleFields['date'];
        $journalTitle = $this->getArticleHReference();
        if (isset($journalTitle['jref'])) $params['rft.jtitle'] = $journalTitle['jref'];
        // unset default title -- we only want jtitle/atitle here:
        unset($params['rft.title']);
        $params['rft.au'] = $this->getPrimaryAuthor();
        if (isset($params['rft.title'])) {
            $params['rft.atitle'] = $params['rft.title'];
        }

        $params['rft.format'] = 'Article';
        $langs = $this->getLanguages();
        if (count($langs) > 0) {
            $params['rft.language'] = $langs[0];
        }
        return $params;
    }

    /**
     * Get OpenURL parameters for an electronic resource.
     *
     * @return array
     */
    protected function getEresOpenURLParams()
    {
        $params = $this->getDefaultOpenURLParams();
//        $params['rft.genre'] = 'book';
        $params['rft.isbn'] = $this->getCleanISBN();
        $params['rft_val_fmt'] = 'info:ofi/fmt:kev:mtx:dc';
        $params['rft.creator'] = $this->getPrimaryAuthor();
        $params['rft.au'] = $this->getPrimaryAuthor();
        $publishers = $this->getPublishers();
        if (count($publishers) > 0) {
            $params['rft.pub'] = $publishers[0];
        }
        $params['rft.format'] = $format;
        $langs = $this->getLanguages();
        if (count($langs) > 0) {
            $params['rft.language'] = $langs[0];
        }
        return $params;
    }

    /**
     * Get OpenURL parameters for an unknown format.
     *
     * @param string $format Name of format
     *
     * @return array
     */
    protected function getUnknownFormatOpenURLParams($format = 'UnknownFormat')
    {
        $params = $this->getDefaultOpenURLParams();
        $params['rft_val_fmt'] = 'info:ofi/fmt:kev:mtx:dc';
        $params['rft.creator'] = $this->getPrimaryAuthor();
        $params['rft.au'] = $this->getPrimaryAuthor();
        $publishers = $this->getPublishers();
        if (count($publishers) > 0) {
            $params['rft.pub'] = $publishers[0];
        }
        $params['rft.format'] = $format;
        $langs = $this->getLanguages();
        if (count($langs) > 0) {
            $params['rft.language'] = $langs[0];
        }
        return $params;
    }

    /**
     * Get OpenURL parameters for a journal.
     *
     * @return array
     */
    protected function getJournalOpenURLParams()
    {
        $params = $this->getUnknownFormatOpenURLParams('Journal');
        /* This is probably the most technically correct way to represent
         * a journal run as an OpenURL; however, it doesn't work well with
         * Zotero, so it is currently commented out -- instead, we just add
         * some extra fields and to the "unknown format" case. */
        $params['rft_val_fmt'] = 'info:ofi/fmt:kev:mtx:dc';
//        $params['rft.genre'] = 'journal';
        $params['rft.jtitle'] = $params['rft.title'];
        $params['rft.issn'] = $this->getCleanISSN();
        $params['rft.au'] = $this->getPrimaryAuthor();

        $params['rft.issn'] = (string)$this->getCleanISSN();

        // Including a date in a title-level Journal OpenURL may be too
        // limiting -- in some link resolvers, it may cause the exclusion
        // of databases if they do not cover the exact date provided!
        unset($params['rft.date']);

        // If we're working with the SFX resolver, we should add a
        // special parameter to ensure that electronic holdings links
        // are shown even though no specific date or issue is specified:
        if (isset($this->mainConfig->OpenURL->resolver)
            && strtolower($this->mainConfig->OpenURL->resolver) == 'sfx'
        ) {
            $params['sfx.ignore_date_threshold'] = 1;
            $params['disable_directlink'] = "true";
            $params['sfx.directlink'] = "off";
        }

//        unset($params['rft.creator'], $params['rft.format'], $params['rft.language']);

        return $params;
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
        // stop here if this record does not support OpenURLs
        if (!$overrideSupportsOpenUrl && !$this->supportsOpenUrl()) {
            return false;
        }
        // Return false if this is a parent record
        if ($this->getMultipartLevel() === 'parent') {
            return false;
        }
        // Set up parameters based on the format of the record:
        $format = $this->getOpenUrlFormat();
        $method = "get{$format}OpenUrlParams";
        if (method_exists($this, $method)) {
            $params = $this->$method();
        } else {
            $params = $this->getUnknownFormatOpenUrlParams($format);
        }
        // Assemble the URL:
        return http_build_query($params);
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
        $retVal['volume'] = $this->getVolume();
        $retVal['issue'] = $this->getIssue();
        $pages = $this->getPages();
        $pagesArr = explode('-', $pages);
        $retVal['spage'] = $pagesArr[0];
        if (isset($pagesArr[1])) {
            $retVal['epage'] = $pagesArr[1];
        }
        $retVal['date'] = $this->getRefYear();
        return $retVal;
    }

    /**
     * Get the reference of the article including its link.
     *
     * @access  protected
     * @return  array
     */
    protected function getArticleHReference()
    {
        if (in_array('Article', $this->getFormats()) === true) {
            $vs = null;
            $vs = $this->marcRecord->getFields('773');
            if (count($vs) > 0) {
                $refs = array();
                foreach($vs as $v) {
                    $journalRef = null;
                    $articleRef = null;
                    $inRefField = $v->getSubfields('i');
                    if (count($inRefField) > 0) {
                        $inRef = $inRefField[0]->getData();
                    }
                    else {
                        $inRef = "in:";
                    }
                    $journalRefField = $v->getSubfields('t');
                    if (count($journalRefField) > 0) {
                        $journalRef = $journalRefField[0]->getData();
                    }
                    $articleRefField = $v->getSubfields('g');
                    if (count($articleRefField) > 0) {
                        $articleRef = $articleRefField[0]->getData();
                    }
                    $a_names = $v->getSubfields('w');
                    if (count($a_names) > 0) {
                        $idArr = explode(')', $a_names[0]->getData());
                        $hrefId = $this->addNLZ($idArr[1]);
                    }
                    if ($journalRef || $articleRef) {
                        $refs[] = array('inref' => $inRef, 'jref' => $journalRef, 'aref' => $articleRef, 'hrefId' => $hrefId);
                    }
                }
                return $refs;
            }
        }
        return null;
    }

    /**
     * Get the reference of the parent including its link.
     *
     * @access  protected
     * @return  array
     */
    protected function getParentHReference()
    {
        if (!empty($this->fields['hierarchy_top_id']) && is_array($this->fields['hierarchy_top_id'])) {
            $refs = [];
            $hrefId = $this->addNLZ($this->fields['hierarchy_top_id'][0]);
            $refs[] = $this->getSourceIdentifier().'|'.$hrefId;
            if ($hrefId !== $this->fields['id']) {
                $d = $this->recordLoader->load(
                    $hrefId,
                    'Solr'
                );
                return $d;
            }
        }
        $vs = null;
        $vs = $this->marcRecord->getFields('773');
        if (count($vs) > 0) {
            $refs = array();
            foreach($vs as $v) {
                $a_names = $v->getSubfields('w');
                if (count($a_names) > 0) {
                    $idArr = explode(')', $a_names[0]->getData());
                    $hrefId = $this->addNLZ($idArr[1]);
                }
                if ($hrefId) {
/*                    $d = $this->recordLoader->load(
                        $hrefId,
                        $this->params()->fromPost('source', 'Solr')
                    );
*/
                    $refs[] = $this->getSourceIdentifier().'|'.$hrefId;
                }
            }
            return $refs;
        }
        return null;
    }

    protected $recordLoader;

    public function setRecordLoader(\VuFind\Record\Loader $loader) {
        $this->recordLoader = $loader;
    }

    /**
     * Get information about the volume stocks.
     *
     * @access  public
     * @return  array
     */
    public function getVolumeStock()
    {
        $iln = isset($this->recordConfig->Library->iln)
            ? $this->recordConfig->Library->iln : null;
        $vs = null;
        $stock = array();
        $vs = $this->marcRecord->getFields('980');
        if (count($vs) > 0) {
            $refs = array();
            foreach($vs as $v) {
                $stockInfo = [];
                $idx = '';
                $libField = $v->getSubfields('2');
                if (count($libField) > 0) {
                    $lib = $libField[0]->getData();
                }
                if ($lib == $iln) {
                    $epnArr = $v->getSubfields('b');
                    $epn = $epnArr[0]->getData();
                    $idx = $epn;
                    $stockField = $v->getSubfields('g');
                    if (count($stockField) > 0) {
                        $stockInfo[] = $stockField[0]->getData();
                    }
                    $noteField = $v->getSubfields('k');
                    if (count($noteField) > 0) {
                        $stockInfo[] = $noteField[0]->getData();
                    }
                    // only take subfield d into account if we do not have an EPN
                    //if (empty($epn)) {
                        $callnoField = $v->getSubfields('d');
                        if (count($callnoField) > 0) {
                            foreach ($callnoField as $cnField) {
                                $idxCn = $cnField->getData();
                                if (strlen($idxCn) == 9) {
                                    $idxBarCode = '830$'.str_replace('-','',$idxCn);
                                    $stock[$idxBarCode] = $stockInfo;
                                }
                                $stock[$idxCn] = $stockInfo;
                            }
                        }
                    //}
                    $stock[$idx] = $stockInfo;
                }
            }
        }
        return $stock;
    }

    /**
     * Get note(s) about the volume stocks.
     *
     * @access  public
     * @return  string
     */
    public function getVolumeStockNote()
    {
        $iln = isset($this->recordConfig->Library->iln)
            ? $this->recordConfig->Library->iln : null;
        $vs = null;
        $stock = '';
        $vs = $this->marcRecord->getFields('980');
        if (count($vs) > 0) {
            $refs = array();
            foreach($vs as $v) {
                $libField = $v->getSubfields('2');
                if (count($libField) > 0) {
                    $lib = $libField[0]->getData();
                }
                if ($lib == $iln) {
                    $stockField = $v->getSubfields('k');
                    if (count($stockField) > 0) {
                        $stock .= $stockField[0]->getData();
                    }
                }
            }
        }
        return $stock;
    }

    /**
     * Get journal including supplements.
     *
     * @access  public
     * @return  string
     */
    public function getSupplementMainJournal()
    {
        $vs = null;
        $journal = [];
        $vs = $this->marcRecord->getFields('772');
        if (count($vs) > 0) {
            $refs = array();
            foreach($vs as $v) {
                $journalField = $v->getSubfield('w');
                $idArr = explode(')', $journalField->getData());
                if ($idArr[0] == '(DE-601') {
                    $journal['id'] = $idArr[1];
                }
                $journal['name'] = $v->getSubfield('t')->getData();
                $journal['label'] = $v->getSubfield('i')->getData();
            }
        }
        return $journal;
    }

    /**
     * TUBHH Enhancement
     * Return the title (period) and the signature of a volume
     * An array will be returned with key=signature, value=title.
     *
     * @access  public
     * @return  array
     */
    public function getVolume()
    {
        return $this->getFirstFieldValue('952', array('d'));
    }

    /**
     * Determines if this record is a scholarly paper
     *
     * @return boolean
     */
    protected function isHSS()
    {
        return (count($this->marcRecord->getFields('502')) > 0) ? true : false;
    }

    /**
     * Get the title of the item
     *
     * @access  public
     * @return  string
     */
    public function getTitle() {
         return $this->getTitleAdvanced();
    }

    /**
     * Get the title of the item
     *
     * @access  protected
     * @return  array
     */
    public function getTitleAdvanced() {
        $return = '';
        if ($this->getFirstFieldValue('245', array('a'))) $return = $this->getFirstFieldValue('245', array('a'));
        if ($this->getFirstFieldValue('245', array('a')) && $this->getFirstFieldValue('245', array('b')) && substr(trim($this->getFirstFieldValue('245', array('a'))), -1) !== ':' && substr(trim($this->getFirstFieldValue('245', array('b'))), 0, 1) !== ':') $return .= " :";
        if ($this->getFirstFieldValue('245', array('b'))) $return .= " ".$this->getFirstFieldValue('245', array('b'));
        if ($this->getFirstFieldValue('245', array('n')) || $this->getFirstFieldValue('245', array('p'))) $return .= " (";
        if ($this->getFirstFieldValue('245', array('n'))) $return .= $this->getFirstFieldValue('245', array('n'));
        if ($this->getFirstFieldValue('245', array('n')) && $this->getFirstFieldValue('245', array('p'))) $return .= ";";
        if ($this->getFirstFieldValue('245', array('p'))) $return .= " ".$this->getFirstFieldValue('245', array('p'));
        if ($this->getFirstFieldValue('245', array('n')) || $this->getFirstFieldValue('245', array('p'))) $return .= ")";
        if ($return !== '') return $return;
        if ($this->getFirstFieldValue('490', array('a'))) $return = $this->getFirstFieldValue('490', array('a'));
        if ($this->getFirstFieldValue('490', array('v'))) $return .= " (".$this->getFirstFieldValue('490', array('v')).")";
        if ($return !== '') return $return;
        if ($this->getFirstFieldValue('773', array('t'))) $return = $this->getFirstFieldValue('773', array('t'));
        return $return;
    }

    /**
     * TUBHH Enhancement
     * Return the title (period) and the signature of a volume
     * An array will be returned with key=signature, value=title.
     *
     * @access  public
     * @return  array
     */
    public function getIssue()
    {
        return $this->getFirstFieldValue('952', array('e'));
    }

    /**
     * TUBHH Enhancement
     * Return the title (period) and the signature of a volume
     * An array will be returned with key=signature, value=title.
     *
     * @access  public
     * @return  array
     */
    public function getPages()
    {
        return $this->getFirstFieldValue('952', array('h'));
    }

    /**
     * TUBHH Enhancement
     * Return the title (period) and the signature of a volume
     * An array will be returned with key=signature, value=title.
     *
     * @access  public
     * @return  array
     */
    public function getRefYear()
    {
        return $this->getFirstFieldValue('952', array('j'));
    }

    /**
     * Set raw data to initialize the object.
     *
     * @param mixed $data Raw data representing the record; Record Model
     * objects are normally constructed by Record Driver objects using data
     * passed in from a Search Results object.  In this case, $data is a Solr record
     * array containing MARC data in the 'fullrecord' field.
     *
     * @return void
     */
    public function setRawData($data)
    {
        // Call the parent's set method...
        parent::setRawData($data);

        // Also process the MARC record:
        $marc = trim($data['fullrecord']);

        // check if we are dealing with MARCXML
        if (substr($marc, 0, 1) == '<') {
            $marc = new \File_MARCXML($marc, \File_MARCXML::SOURCE_STRING);
        } else {
            // When indexing over HTTP, SolrMarc may use entities instead of certain
            // control characters; we should normalize these:
            $marc = str_replace(
                ['#29;', '#30;', '#31;'], ["\x1D", "\x1E", "\x1F"], $marc
            );
            $marc = new \File_MARC($marc, \File_MARC::SOURCE_STRING);
        }

        $this->marcRecord = $marc->next();
        if (!$this->marcRecord) {
            throw new \File_MARC_Exception('Cannot Process MARC Record');
        }
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

        // Which fields/subfields should we check for URLs?
        $fieldsToCheck = [
            '856' => ['3', 'y'],   // Standard URL
            '555' => ['a']                   // Cumulative index/finding aids
        ];

        foreach ($fieldsToCheck as $field => $subfields) {
            $urls = $this->marcRecord->getFields($field);
            if ($urls) {
                foreach ($urls as $url) {
                    // Is there an address in the current field?
                    $address = $url->getSubfield('u');
                    if ($address) {
                        $address = $address->getData();

                        // Exit, if this is from Ciando - we do not want Ciando!
                        $m_field = $url->getSubfield('m');
                        if ($m_field && $m_field->getData() == 'CIANDO') {
                            break;
                        }

                        // Is there a description?  If not, just use the URL itself.
                        foreach ($subfields as $current) {
                            $desc = $url->getSubfield($current);
                            if ($current == 'y' && $desc && $desc->getData() == 'c') {
                                $desc = null;
                            }
                            if ($desc) {
                                break;
                            }
                        }
                        if ($desc) {
                            $desc = $desc->getData();
                        } else {
                            $desc = $address;
                        }

                        $uselinks = [ 'Inhaltstext', 'Kurzbeschreibung',
                            'Ausführliche Beschreibung', 'Inhaltsverzeichnis',
                            'Rezension', 'Beschreibung für den Leser',
                            'Autorenbiografie' ];

                        // Take the link, if it has a description defined in $uselinks
                        // or if its a non-numeric (i.e. a non-CBS) match.
                        if (in_array($desc, $uselinks) === true
                            || is_numeric($this->getUniqueId()) === false
                            || $desc == $address
                        ) {
                            $retVal[] = ['url' => $address, 'desc' => $desc];
                        }
                    }
                }
            }
        }

        return $retVal;
    }

    /**
     * determines mylib setting
     *
     * @access  protected
     * @return  string
     */
    protected function getMyLibraryCode()
    {
        $iln = isset($this->recordConfig->Library->iln)
            ? $this->recordConfig->Library->iln : null;
        $mylib = isset($this->recordConfig->Library->mylibId)
            ? $this->recordConfig->Library->mylibId : null;

        if ($mylib === null && $iln !== null) {
            $mylib = "GBV_ILN_".$iln;
        }
        return $mylib;
    }

    /**
     * determines if this item is in the local stock
     *
     * @access protected
     * @return boolean
     */
    public function checkLocalStock()
    {
        // Return null if we have no table of contents:
        $fields = $this->marcRecord->getFields('912');
        if (!$fields) {
            return null;
        }

        $mylib = $this->getMyLibraryCode();

        // If we got this far, we have libraries owning this item -- check if we have it locally
        foreach ($fields as $field) {
            $subfields = $field->getSubfields();
            foreach ($subfields as $subfield) {
                if ($subfield->getCode() === 'a') {
                    if ($subfield->getData() === $mylib) {
                        return true;
                    }
                }
            }
        }
        return false;
    }

    /**
     * determines if this item is in the local stock by checking the index
     *
     * @access protected
     * @return boolean
     */
    public function checkLocalStockInIndex()
    {
        return in_array($this->getMyLibraryCode(), $this->fields['collection_details']);
    }

    /**
     * checks if this item needs interlibrary loan
     *
     * @access protected
     * @return string
     */
    public function checkInterlibraryLoan()
    {
        // Is this item in local stock?
        if ($this->checkLocalStockInIndex() === true) {
            return '0';
        }
        // Is this item an e-ressource?
        if (in_array('eBook', $this->getFormats()) === true || in_array('eJournal', $this->getFormats()) === true || in_array('Article', $this->getFormats()) === true || in_array('electronic Article', $this->getFormats()) === true || $this->isNLZ() === true) {
            return '0';
        }

        return '1';
    }

    /**
     * checks if this item needs interlibrary loan
     *
     * @access protected
     * @return string
     */
    public function checkAcquisitionProposal()
    {
        // Is this item in local stock?
        if ($this->checkLocalStockInIndex() === true) {
            return '0';
        }
        // Is this item an e-ressource?
        if (in_array('eJournal', $this->getFormats()) === true || $this->isNLZ() === true || in_array('Journal', $this->getFormats()) === true || in_array('Article', $this->getFormats()) === true || in_array('electronic Article', $this->getFormats()) === true || in_array('Serial Volume', $this->getFormats()) === true) {
            return '0';
        }
        // Is this item a national license?
        if ($this->isNLZ() === true) {
            return '0';
        }

        return '1';
    }

    /**
     * checks if this item is licensed
     *
     * @access protected
     * @return boolean
     */
    public function licenseAvailable()
    {
        // Is this item in local stock?
        if ((in_array('eBook', $this->getFormats()) === true || in_array('eJournal', $this->getFormats()) === true || $this->isNLZ() === true) && $this->checkLocalStockInIndex() === true) {
            return true;
        }

        return false;
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
        if ((in_array('eBook', $this->getFormats()) === true || in_array('eJournal', $this->getFormats()) === true) && $this->isNLZ() === false) {
            return true;
        }

        return false;
    }

    /**
     * Caches multipart children.
     *
     * @return array
     * @access protected
     */
    public function cacheMultipartChildren()
    {
        $mpList = new MultipartList($this->getUniqueId());
        $mpList->setMultipartList($this->getMultipartChildrenArray());
        $mpList->cacheList();
        return true;
    }

    /**
     * Get multipart children.
     *
     * @return array
     * @access protected
     */
    public function getMultipartChildrenArray()
    {
        $cnt=0;
        $retval = array();
        $sort = array();
        $result = $this->searchMultipart();

        // Sort the results
        foreach($result as $doc) {
            $retval[$cnt] = array();
            $partVol = $doc->getVolumeInformation($this->getUniqueId());
            // Do not use anything behind a comma for sorting
            if (strstr($partVol,',') !== false) {
                $part = substr($partVol, 0, strpos($partVol,','));
            }
            else {
                $part = $partVol;
            }
            //$retval[$cnt]['sort']=$doc['sort'];
            $retval[$cnt]['title']   = $doc->getTitle();
            $retval[$cnt]['id']      = $doc->getUniqueId();
            $retval[$cnt]['date']    = preg_replace("/[^0-9]/","", $doc->getPublicationDates()[0]);
            $retval[$cnt]['part']    = $partVol;
            $retval[$cnt]['partNum'] = preg_replace("/[^0-9]/","", $doc->getPartNum($this->getUniqueId()));
//            $retval[$cnt]['object'] = $doc;
            $cnt++;
        }

        $part0 = array();
        $part1 = array();
        $part2 = array();

        foreach ($retval as $key => $row) {
            $part0[$key] = (isset($row['title'])) ? $row['title'] : 0;
            $part1[$key] = (isset($row['partNum'])) ? $row['partNum'] : 0;
            $part2[$key] = (isset($row['date'])) ? $row['date'] : 0;
        }
        array_multisort($part1, SORT_DESC, $part2, SORT_DESC, $part0, SORT_ASC, $retval );

        return $retval;
    }

    /**
     * Get multipart children.
     *
     * @return array
     * @access protected
     */
    public function getMultipartChildren()
    {
        $cnt=0;
        $retval = array();
        $sort = array();
        $result = $this->searchMultipart();

        // Sort the results
        foreach($result as $doc) {
            $retval[$cnt] = array();
            $partVol = $doc->getVolumeInformation($this->getUniqueId());
            // Do not use anything behind a comma for sorting
            if (strstr($partVol,',') !== false) {
                $part = substr($partVol, 0, strpos($partVol,','));
            }
            else {
                $part = $partVol;
            }
            //$retval[$cnt]['sort']=$doc['sort'];
            $retval[$cnt]['title']   = $doc->getTitle()[0];
            $retval[$cnt]['id']      = $doc->getUniqueId();
            $retval[$cnt]['date']    = preg_replace("/[^0-9]/","", $doc->getPublicationDates()[0]);
            $retval[$cnt]['part']    = $partVol;
            $retval[$cnt]['partNum'] = preg_replace("/[^0-9]/","", $doc->getPartNum($this->getUniqueId()));
            $retval[$cnt]['object'] = $doc;
            $cnt++;
        }

        $part0 = array();
        $part1 = array();
        $part2 = array();

        foreach ($retval as $key => $row) {
            $part0[$key] = (isset($row['title'])) ? $row['title'] : 0;
            $part1[$key] = (isset($row['partNum'])) ? $row['partNum'] : 0;
            $part2[$key] = (isset($row['date'])) ? $row['date'] : 0;
        }
        array_multisort($part1, SORT_DESC, $part2, SORT_DESC, $part0, SORT_ASC, $retval );

        // $retval has now the correct order, now set the objects into the same order
        $returnObjects = array();
        foreach ($retval as $object) {
            $returnObjects[] = $object['object'];
        }
        return $returnObjects;
    }

    /**
     * Search for multipart records of this record
     *
     * @return bool
     * @access protected
     */
    protected function searchMultipart()
    {
        $limit = 10000;
        $page = 0;
        $rid=$this->fields['id'];
        if(strlen($rid)<2) {
            return false;
        }
        $rid=str_replace(":","\:",$rid);

        // Assemble the query parts and filter out current record:
#        $searchQ = '(hierarchy_parent_id:'.$this->stripNLZ($rid).' AND NOT (format:Article OR format:"electronic Article"))';
        $searchQ = '(hierarchy_parent_id:'.$this->stripNLZ($rid).' AND ppnlink:'.$this->stripNLZ($rid).' AND NOT (format:Article OR format:"electronic Article"))';

        $hiddenFilters = null;
        // Get filters from config file
        if (isset($this->recordConfig->Filter->hiddenFilters)) {
            $hiddenFilters = $this->recordConfig->Filter->hiddenFilters->toArray();
        }

        $query = new \VuFindSearch\Query\Query($searchQ);
        $params = new ParamBag();
        $params->set('fq', $hiddenFilters);

        $all = $this->searchService->search('Solr', $query, 0, 0, $params)->getTotal();
        if ($limit < $all) {
            $pages = ceil($all/$limit);
        }
        $results = $this->searchService->search('Solr', $query, $page, $limit, $params);

        $frbrItems = $this->searchFRBRitems();
        $frbrItemIds = [ ];
        if (count($frbrItems) > 0) {
            foreach ($frbrItems as $frbrItem) {
                $frbrItemIds[] = $frbrItem->getUniqueId();
            }
        }
        $return = [ ];
        if (count($results) > 0) {
            foreach ($results as $result) {
                if (in_array($result->getUniqueId(), $frbrItemIds) === false) {
                    $return[] = $result;
                }
            }
        }
        else {
            $return = $results;
        }

        return $return;
    }

    /**
     * Get the count of volumes for this record
     *
     * @return int
     * @access public
     */
    public function getVolsCount()
    {
        $rid=$this->fields['id'];
        if(strlen($rid)<2) {
            return false;
        }
        $rid=str_replace(":","\:",$rid);

        // Assemble the query parts and filter out current record:
        $searchQ = '(hierarchy_parent_id:'.$this->stripNLZ($rid).' AND ppnlink:'.$this->stripNLZ($rid).' AND NOT (format:Article OR format:"electronic Article"))';

        $hiddenFilters = null;
        // Get filters from config file
        if (isset($this->recordConfig->Filter->hiddenFilters)) {
            $hiddenFilters = $this->recordConfig->Filter->hiddenFilters->toArray();
        }

        $query = new \VuFindSearch\Query\Query($searchQ);
        $params = new ParamBag();
        $params->set('fq', $hiddenFilters);

        $all = $this->searchService->search('Solr', $query, 0, 0, $params)->getTotal();
        return $all;
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
        $limit = 2;
        $rid=$this->fields['id'];
        if(strlen($rid)<2) {
            return false;
        }
        $rid=str_replace(":","\:",$rid);

        // Assemble the query parts and filter out current record:
        $searchQ = '(hierarchy_parent_id:'.$this->stripNLZ($rid).' AND ppnlink:'.$this->stripNLZ($rid).' AND NOT (format:Article OR format:"electronic Article"))';

        $hiddenFilters = null;
        // Get filters from config file
        if (isset($this->recordConfig->Filter->hiddenFilters)) {
            $hiddenFilters = $this->recordConfig->Filter->hiddenFilters->toArray();
        }

        $query = new \VuFindSearch\Query\Query($searchQ);
        $params = new ParamBag();
        $params->set('fq', $hiddenFilters);

        $all = $this->searchService->search('Solr', $query, 0, 0, $params)->getTotal();

        // Assemble the query parts and filter out current record:
        $searchQFRBR = '(ppnlink:'.$this->stripNLZ($rid).' AND NOT (format:Article OR format:"electronic Article")';
        if ($this->fields['remote_bool'] == 'true') {
            $searchQFRBR .= ' AND remote_bool:false';
        }
        else {
            $searchQFRBR .= ' AND remote_bool:true';
        }
        $searchQFRBR .= ')';

        $queryFRBR = new \VuFindSearch\Query\Query($searchQFRBR);
        $paramsFRBR = new ParamBag();
        $paramsFRBR->set('fq', $hiddenFilters);
        $allFRBR = $this->searchService->search('Solr', $queryFRBR, 0, 0, $paramsFRBR)->getTotal();

        $count = ($all-$allFRBR);

        if ($count > 0) {
            return true;
        }

        return false;
    }

    /**
     * Search for FRBR items of this item.
     *
     * @return array
     * @access public
     */
    public function searchFRBRitems()
    {
        $rid=$this->fields['id'];
        if(strlen($rid)<2) {
            return array();
        }
        $rid=str_replace(":","\:",$rid);

        // Assemble the query parts and filter out current record:
        $searchQ = '(ppnlink:'.$this->stripNLZ($rid).' AND NOT (format:Article OR format:"electronic Article")';
        if ($this->fields['remote_bool'] == 'true') {
            $searchQ .= ' AND remote_bool:false';
        }
        else {
            $searchQ .= ' AND remote_bool:true';
        }
        $searchQ .= ')';
//echo $searchQ;
        $hiddenFilters = null;
        // Get filters from config file
        if (isset($this->recordConfig->Filter->hiddenFilters)) {
            $hiddenFilters = $this->recordConfig->Filter->hiddenFilters->toArray();
        }

        $query = new \VuFindSearch\Query\Query($searchQ);
        $params = new ParamBag();
        $params->set('fq', $hiddenFilters);

        $all = $this->searchService->search('Solr', $query, 0, 0, $params)->getTotal();
        $results = $this->searchService->search('Solr', $query, 0, $all, $params);

        return $results;
/*
        foreach ($result['response']['docs'] as $resp) {
            if (($this->_isNLZ($resp['id']) && $this->_isNLZ($rid)) || (!$this->_isNLZ($resp['id']) && !$this->_isNLZ($rid))) {
                $resultArray['response']['docs'][] = $resp;
            }
        }

        return (count($resultArray['response']['docs']) > 0) ? $resultArray['response'] : false;
*/
    }

    /**
     * Search for other editions of this item.
     *
     * @return array
     * @access public
     */
    public function searchItemEditions()
    {
        $rid=$this->fields['id'];
        if(strlen($rid)<2) {
            return array();
        }
        $rid=str_replace(":","\:",$rid);

        // Assemble the query parts and filter out current record:
        $searchQ = '(ppnlink:'.$this->stripNLZ($rid).' AND NOT (format:Article OR format:"electronic Article")';
        if ($this->fields['remote_bool'] == 'true') {
            $searchQ .= ' AND remote_bool:true';
        }
        else {
            $searchQ .= ' AND remote_bool:false';
        }
        $searchQ .= ')';
//echo $searchQ;
        $hiddenFilters = null;
        // Get filters from config file
        if (isset($this->recordConfig->Filter->hiddenFilters)) {
            $hiddenFilters = $this->recordConfig->Filter->hiddenFilters->toArray();
        }

        $query = new \VuFindSearch\Query\Query($searchQ);
        $params = new ParamBag();
        $params->set('fq', $hiddenFilters);

        $all = $this->searchService->search('Solr', $query, 0, 0, $params)->getTotal();
        $results = $this->searchService->search('Solr', $query, 0, $all, $params);

        return $results;
/*
        foreach ($result['response']['docs'] as $resp) {
            if (($this->_isNLZ($resp['id']) && $this->_isNLZ($rid)) || (!$this->_isNLZ($resp['id']) && !$this->_isNLZ($rid))) {
                $resultArray['response']['docs'][] = $resp;
            }
        }

        return (count($resultArray['response']['docs']) > 0) ? $resultArray['response'] : false;
*/
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
    public function getThumbnail($size = 'small')
    {
        if (isset($this->fields['thumbnail']) && $this->fields['thumbnail']) {
            return $this->fields['thumbnail'];
        }
        $arr = [
            'author'     => mb_substr($this->getPrimaryAuthor(), 0, 300, 'utf-8'),
            'callnumber' => $this->getCallNumber(),
            'size'       => $size,
            'title'      => mb_substr($this->getTitle(), 0, 300, 'utf-8')
        ];
        if ($isbn = $this->getCleanISBN()) {
            $arr['isbn'] = $isbn;
        }
        if ($issn = $this->getCleanISSN()) {
            $arr['issn'] = $issn;
        }
        if ($oclc = $this->getCleanOCLCNum()) {
            $arr['oclc'] = $oclc;
        }
        if ($upc = $this->getCleanUPC()) {
            $arr['upc'] = $upc;
        }
        if ($ppn = $this->getUniqueId()) {
            $arr['ppn'] = $ppn;
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

/* deprecated
    public function searchMultipartChildren()
    {
        $result = $this->searchMultipart();
        return $result;
        //return ($result['docs'] > 0) ? $result['docs'] : false;
    }
*/
    public function searchArticleChildren()
    {
        $result = $this->searchArticles();

        return ($result['docs'] > 0) ? $result['docs'] : false;
    }

    /**
     * Get the content of MARC field 246
     *
     * @return array
     * @access protected
     */
    public function getSubseries() {
        return array('label' => $this->getLegacyFieldArray('246', ['i']), 'value' => $this->getLegacyFieldArray('246', ['a']));
    }

    /**
     * Get an array of physical descriptions of the item from MARC data.
     *
     * @return array
     */
    public function getPhysicalDescriptions()
    {
        $fields = [];
        $result = [];
        if ($this->getFirstFieldValue('300') !== null) {
            $fields[] = $this->getFirstFieldValue('300', ['a']);
            $fields[] = $this->getFirstFieldValue('300', ['b']);
            $result[] = implode('; ', $fields);
        }
        return $result;
    }

    public function getHss() {
        $hssElements = [];
        if ($this->getFirstFieldValue('502', ['a'])) { $hssElements[] = $this->getFirstFieldValue('502', ['a']); }
        if ($this->getFirstFieldValue('502', ['b'])) { $hssElements[] = $this->getFirstFieldValue('502', ['b']); }
        if ($this->getFirstFieldValue('502', ['c'])) { $hssElements[] = $this->getFirstFieldValue('502', ['c']); }
        if ($this->getFirstFieldValue('502', ['d'])) { $hssElements[] = $this->getFirstFieldValue('502', ['d']); }
        $hss = implode('; ', $hssElements);
        return $hss;
    }

    public function getEditionsFromMarc() {
        return $this->getFieldArray('250');
    }

    /**
     * Get the main authors of the record.
     *
     * @return array
     */
    public function getPrimaryAuthors()
    {
        return isset($this->fields['author-letter'])
            ? (array) $this->fields['author-letter'] : [];
    }

    /**
     * Get an array of all secondary authors roles (complementing
     * getPrimaryAuthorsRoles()).
     *
     * @return array
     */
    public function getSecondaryAuthorsRoles()
    {
        $functions = $this->getFieldArray('700', ['4']);
        return $functions;
    }

    /**
     * Get an array of all secondary authors roles (complementing
     * getPrimaryAuthorsRoles()).
     *
     * @return array
     */
    public function getSecondaryAuthors()
    {
        $functions = $this->getFieldArray('700', ['a']);
        return $functions;
    }

    public function getCorporateAuthors() {
        $corporate_authors = array_merge(
            $this->getFieldArray('110', ['a', 'b']),
            $this->getFieldArray('710', ['a', 'b'])
        );
        return $corporate_authors;
    }

    public function getCorporateAuthorsRoles() {
        $functions = array_merge(
            $this->getFieldArray('110', ['4']),
            $this->getFieldArray('710', ['4'])
        );
        return $functions;
    }

    public function getMoreContributors() {
        $authors = $this->getDeduplicatedAuthors();
        $names = $this->getFieldArray('700', ['a']);
        $functions = $this->getFieldArray('700', ['e']);
        $return = [ 'names' => [], 'functions' => [] ];
        // remove secondary authors from array
        foreach ($names as $position => $name) {
            if (!isset($authors['secondary'][$name])) {
                $return['names'][] = $name;
                if (!empty($functions[$position])) {
                    $return['functions'][] = $functions[$position];
                }
                else {
                    $return['functions'][] = '';
                }
            }
        }
        return $return;
    }

    public function getPartNum($contextId) {
        if ($val = $this->getFirstFieldValue('830',['9'])) {
            return $val;
        }
        if ($partVol = $this->getVolumeInformation($contextId)) {
            if (strstr($partVol,',') !== false) {
                $part = substr($partVol, 0, strpos($partVol,','));
            }
            else {
                $part = $partVol;
            }
            $val = preg_replace("/[^0-9]/","", $part);
            return $val;
        }
        return null;
    }

    public function getVolumeInformation($contextId) {
        if ($val = $this->getSpecificFieldValue('800',$contextId)) {
            return $val;
        }
        if ($val = $this->getSpecificFieldValue('830',$contextId)) {
            return $val;
        }
        if ($val = $this->getSpecificFieldValue('810',$contextId)) {
            return $val;
        }
        if ($val = $this->getFirstFieldValue('245', ['n'])) {
            return $val;
        }
        return null;
    }

    /**
     * Get an array of information about record holdings, obtained in real-time
     * from the ILS.
     *
     * @return array
     * @access protected
     */
/* deprecated
    protected function getTomes()
    {
        $result = $this->searchMultipartChildren();

        return $result;

        //$picaConfigArray = parse_ini_file('conf/PICA.ini', true);
        //$record_url = $picaConfigArray['Catalog']['ppnUrl'];

        $onlyTopLevel = 0;
        $checkMore = false;
        $showAssociated = false;
        $leader = $this->marcRecord->getLeader();
        $indicator = substr($leader, 19, 1);
        switch ($indicator) {
            case 'a':
                $checkMore = 0;
                $showAssociated = 1;
                break;
            case 'c':
                $onlyTopLevel = 1;
                $showAssociated = 2;
                break;
            case 'b':
            case ' ':
            default:
                //$checkMore = 0;
                $showAssociated = 0;
                break;
        }
        if ($checkMore !== 0) {
            $journalIndicator = substr($leader, 7, 1);
            switch ($journalIndicator) {
                case 's':
                    $showAssociated = 1;
                    break;
                case 'b':
                case 'm':
                    //$onlyTopLevel = 1;
                    $showAssociated = 3;
                    break;
            }
        }
        if ($onlyTopLevel === 1) {
            // only look for the parent of this record, all other associated publications can be ignored
            $vs = $this->marcRecord->getFields('773');
            if ($vs) {
                foreach($vs as $v) {
                    $a_names = $v->getSubfields('w');
                    if (count($a_names) > 0) {
                        $idArr = explode(')', $a_names[0]->getData());
                        $parentId = $idArr[1];
                    }
                    $v_names = $v->getSubfields('v');
                    if (count($v_names) > 0) {
                        $volNumber = $v_names[0]->getData();
                    }
                }
            }
            if (!$parentId) {
                $vs = $this->marcRecord->getFields('830');
                if ($vs) {
                    foreach($vs as $v) {
                        $a_names = $v->getSubfields('w');
                        if (count($a_names) > 0) {
                            $idArr = explode(')', $a_names[0]->getData());
                            if ($idArr[0] === '(DE-601') {
                                $parentId = $idArr[1];
                            }
                        }
                        $v_names = $v->getSubfields('v');
                        if (count($v_names) > 0) {
                            $volNumber = $v_names[0]->getData();
                        }
                    }
                }
                else {
                    $vs = $this->marcRecord->getFields('800');
                    if ($vs) {
                        foreach($vs as $v) {
                            $a_names = $v->getSubfields('w');
                            if (count($a_names) > 0) {
                                $idArr = explode(')', $a_names[0]->getData());
                                if ($idArr[0] === '(DE-601') {
                                    $parentId = $idArr[1];
                                }
                            }
                            $v_names = $v->getSubfields('v');
                            if (count($v_names) > 0) {
                                $volNumber = $v_names[0]->getData();
                            }
                        }
                    }
                }
            }

            $subrecord = array('id' => $parentId);
            $subrecord['number'] = $volNumber;
            $subrecord['title_full'] = array();
            $subrecord['record_url'] = $record_url.$parentId;
*/
/*
            $m = trim($subr['fullrecord']);
            // check if we are dealing with MARCXML
            $xmlHead = '<?xml version';
            if (strcasecmp(substr($m, 0, strlen($xmlHead)), $xmlHead) === 0) {
                $m = new File_MARCXML($m, File_MARCXML::SOURCE_STRING);
            } else {
                $m = preg_replace('/#31;/', "\x1F", $m);
                $m = preg_replace('/#30;/', "\x1E", $m);
                $m = new File_MARC($m, File_MARC::SOURCE_STRING);
            }
            $marcRecord = $m->next();
            if (is_a($marcRecord, 'File_MARC_Record') === true || is_a($marcRecord, 'File_MARCXML_Record') === true) {
                $vs = $marcRecord->getFields('245');
                if ($vs) {
                    foreach($vs as $v) {
                        $a_names = $v->getSubfields('a');
                        if (count($a_names) > 0) {
                            $subrecord['title_full'][] = " ".$a_names[0]->getData();
                        }
                    }
                }
            }
*/
/*
            if (!$parentId) {
                $showAssociated = 0;
            }
            $subrecords[] = $subrecord;
            //print_r($subrecord);
            $parentRecord = $subrecord;
            return $subrecords;
        }

        // Get Holdings Data
//        $id = $this->getUniqueID();

    }
*/
    /**
     * Get an array of information about record holdings, obtained in real-time
     * from the ILS.
     *
     * @return array
     * @access protected
     */
    protected function getArticles()
    {
        global $configArray, $interface;
        // only get associted volumes if this is a top level journal
        $class = $configArray['Index']['engine'];
        $url = $configArray['Index']['url'];
        $this->db = new $class($url);
        $picaConfigArray = parse_ini_file('conf/PICA.ini', true);
        $record_url = $picaConfigArray['Catalog']['ppnUrl'];
        
        $onlyTopLevel = 0;
        $leader = $this->marcRecord->getLeader();
        $indicator = substr($leader, 19, 1);
        switch ($indicator) {
            case 'a':
                $checkMore = 0;
                $interface->assign('showAssociated', '1');
                break;
            case 'c':
                $onlyTopLevel = 1;
                $interface->assign('showAssociated', '2');
                break;
            case 'b':
            case ' ':
            default:
                //$checkMore = 0;
                $interface->assign('showAssociated', '0');
                break;
        }
        if ($checkMore !== 0) {
        $journalIndicator = substr($leader, 7, 1);
        switch ($journalIndicator) {
            case 's':
                $interface->assign('showAssociated', '1');
                break;
            case 'b':
            case 'm':
                #$onlyTopLevel = 1;
                $interface->assign('showAssociated', '3');
                break;
        }
        }
        if ($onlyTopLevel === 1) {
            // only look for the parent of this record, all other associated publications can be ignored
            $vs = $this->marcRecord->getFields('773');
            if ($vs) {
                foreach($vs as $v) {
                    $a_names = $v->getSubfields('w');
                    if (count($a_names) > 0) {
                        $idArr = explode(')', $a_names[0]->getData());
                        $parentId = $idArr[1];
                    }
                    $v_names = $v->getSubfields('v');
                    if (count($v_names) > 0) {
                        $volNumber = $v_names[0]->getData();
                    }
                }
            }
            if (!$parentId) {
                $vs = $this->marcRecord->getFields('810');
                if ($vs) {
                    foreach($vs as $v) {
                        $a_names = $v->getSubfields('w');
                        if (count($a_names) > 0) {
                            $idArr = explode(')', $a_names[0]->getData());
                            $parentId = $idArr[1];
                        }
                        $v_names = $v->getSubfields('v');
                        if (count($v_names) > 0) {
                            $volNumber = $v_names[0]->getData();
                        }
                    }
                }
            }
            if (!$parentId) {
                $vs = $this->marcRecord->getFields('830');
                if ($vs) {
                    foreach($vs as $v) {
                        $a_names = $v->getSubfields('w');
                        if (count($a_names) > 0) {
                            $idArr = explode(')', $a_names[0]->getData());
                            if ($idArr[0] === '(DE-601') {
                                $parentId = $idArr[1];
                            }
                        }
                        $v_names = $v->getSubfields('v');
                        if (count($v_names) > 0 && $parentId === $id) {
                            $volNumber = $v_names[0]->getData();
                        }
                    }
                }
                else {
                    $vs = $this->marcRecord->getFields('800');
                    if ($vs) {
                        foreach($vs as $v) {
                            $a_names = $v->getSubfields('w');
                            if (count($a_names) > 0) {
                                $idArr = explode(')', $a_names[0]->getData());
                                if ($idArr[0] === '(DE-601') {
                                    $parentId = $idArr[1];
                                }
                            }
                            $v_names = $v->getSubfields('v');
                            if (count($v_names) > 0 && $parentId === $id) {
                                $volNumber = $v_names[0]->getData();
                            }
                        }
                    }
                }
            }
            $subr = $this->db->getRecord($parentId);
            $subrecord = array('id' => $parentId);
            $subrecord['number'] = $volNumber;
            $subrecord['title_full'] = array();
            if (!$subr) {
                $subrecord['record_url'] = $record_url.$parentId;
            }
            $m = trim($subr['fullrecord']);
            // check if we are dealing with MARCXML
            $xmlHead = '<?xml version';
            if (strcasecmp(substr($m, 0, strlen($xmlHead)), $xmlHead) === 0) {
                $m = new File_MARCXML($m, File_MARCXML::SOURCE_STRING);
            } else {
                $m = preg_replace('/#31;/', "\x1F", $m);
                $m = preg_replace('/#30;/', "\x1E", $m);
                $m = new File_MARC($m, File_MARC::SOURCE_STRING);
            }
            $marcRecord = $m->next();
            if (is_a($marcRecord, 'File_MARC_Record') === true || is_a($marcRecord, 'File_MARCXML_Record') === true) {
                $vs = $marcRecord->getFields('245');
                if ($vs) {
                    foreach($vs as $v) {
                        $a_names = $v->getSubfields('a');
                        if (count($a_names) > 0) {
                            $subrecord['title_full'][] = " ".$a_names[0]->getData();
                        }
                    }
                }
            }
            if (!$parentId) {
                $interface->assign('showAssociated', '0');
            }
            $subrecords[] = $subrecord;
            $interface->assign('parentRecord', $subrecord);
            return $subrecords;
        }
        // Get Holdings Data
        $id = $this->getUniqueID();
        #$catalog = ConnectionManager::connectToCatalog();
        #if ($catalog && $catalog->status) {
            #$result = $this->db->getRecordsByPPNLink($id);
            $result = $this->searchArticleChildren();
            #$result = $catalog->getJournalHoldings($id);
            if (PEAR::isError($result)) {
                PEAR::raiseError($result);
            }

            foreach ($result as $subId) {
                /*if (!($subrecord = $this->db->getRecord($subId))) {
                    $subrecord = array('id' => $subId, 'title_full' => array("Title not found"), 'record_url' => $record_url.$subId);
                }*/

                $subr = $subId;
                $subrecord = array('id' => $subId['id']);
                $subrecord['title_full'] = array();
                $subrecord['publishDate'] = array();
                if (!$subr) {
                    $subrecord['record_url'] = $record_url.$subId;
                }
                $m = trim($subr['fullrecord']);
                // check if we are dealing with MARCXML
                $xmlHead = '<?xml version';
                if (strcasecmp(substr($m, 0, strlen($xmlHead)), $xmlHead) === 0) {
                    $m = new File_MARCXML($m, File_MARCXML::SOURCE_STRING);
                } else {
                    $m = preg_replace('/#31;/', "\x1F", $m);
                    $m = preg_replace('/#30;/', "\x1E", $m);
                    $m = new File_MARC($m, File_MARC::SOURCE_STRING);
                }
                $marcRecord = $m->next();
                if (is_a($marcRecord, 'File_MARC_Record') === true || is_a($marcRecord, 'File_MARCXML_Record') === true) {
                // 800$t$v -> 773$q -> 830$v -> 245$a$b -> "Title not found"
                    $leader = $marcRecord->getLeader();
                    $indicator = substr($leader, 19, 1);
                    $journalIndicator = substr($leader, 7, 1);
                    switch ($indicator) {
                        case 'a':
                            $vs = $marcRecord->getFields('245');
                            if ($vs) {
                                foreach($vs as $v) {
                                    $a_names = $v->getSubfields('a');
                                    if (count($a_names) > 0) {
                                        $subrecord['title_full'][] = " ".$a_names[0]->getData();
                                    }
                                }
                            }
                            unset($vs);
                            $vs = $marcRecord->getFields('260');
                            if ($vs) {
                                foreach($vs as $v) {
                                    $a_names = $v->getSubfields('c');
                                    if (count($a_names) > 0) {
                                        $subrecord['publishDate'][0] = " ".$a_names[0]->getData();
                                    }
                                }
                            }
                            unset($vs);
                            $vs = $marcRecord->getFields('800');
                            $thisHasBeenSet = 0;
                            if ($vs) {
                                foreach($vs as $v) {
                                    $a_names = $v->getSubfields('w');
                                    if (count($a_names) > 0) {
                                        $idArr = explode(')', $a_names[0]->getData());
                                        if ($idArr[0] === '(DE-601') {
                                            $parentId = $idArr[1];
                                        }
                                    }
                                    $v_names = $v->getSubfields('v');
                                    if (count($v_names) > 0 && $parentId === $id) {
                                        $subrecord['volume'] = $v_names[0]->getData();
                                        $thisHasBeenSet = 1;
                                    }
                                }
                            }
                            if ($thisHasBeenSet === 0) {
                                $vs = $marcRecord->getFields('810');
                                if ($vs) {
                                    foreach($vs as $v) {
                                        $a_names = $v->getSubfields('w');
                                        if (count($a_names) > 0) {
                                            $idArr = explode(')', $a_names[0]->getData());
                                            if ($idArr[0] === '(DE-601') {
                                                $parentId = $idArr[1];
                                            }
                                        }
                                        $v_names = $v->getSubfields('v');
                                        if (count($v_names) > 0 && $parentId === $id) {
                                            $subrecord['volume'] = $v_names[0]->getData();
                                            $thisHasBeenSet = 1;
                                        }
                                        $e_names = $v->getSubfields('9');
                                        if (count($e_names) > 0 && $parentId === $id) {
                                            $subrecord['sort'] = $e_names[0]->getData();
                                        }
                                    }
                                }
                            }
                            if ($thisHasBeenSet === 0) {
                                $vs = $marcRecord->getFields('830');
                                if ($vs) {
                                    foreach($vs as $v) {
                                        $a_names = $v->getSubfields('w');
                                        if (count($a_names) > 0) {
                                            $idArr = explode(')', $a_names[0]->getData());
                                            if ($idArr[0] === '(DE-601') {
                                                $parentId = $idArr[1];
                                            }
                                        }
                                        $v_names = $v->getSubfields('v');
                                        if (count($v_names) > 0 && $parentId === $id) {
                                            $subrecord['volume'] = $v_names[0]->getData();
                                        }
                                        $e_names = $v->getSubfields('9');
                                        if (count($e_names) > 0 && $parentId === $id) {
                                            $subrecord['sort'] = $e_names[0]->getData();
                                        }
                                    }
                                }
                            }
                            break;
                        case 'b':
                            $vs = $marcRecord->getFields('800');
                            $thisHasBeenSet = 0;
                            if ($vs) {
                                foreach($vs as $v) {
                                    $a_names = $v->getSubfields('w');
                                    if (count($a_names) > 0) {
                                        $idArr = explode(')', $a_names[0]->getData());
                                        if ($idArr[0] === '(DE-601') {
                                            $parentId = $idArr[1];
                                        }
                                    }
                                    $v_names = $v->getSubfields('v');
                                    if (count($v_names) > 0 && $parentId === $id) {
                                        $subrecord['volume'] = $v_names[0]->getData();
                                        $thisHasBeenSet = 1;
                                    }
                                }
                            }
                            if ($thisHasBeenSet === 0) {
                                $vs = $marcRecord->getFields('810');
                                if ($vs) {
                                    foreach($vs as $v) {
                                        $a_names = $v->getSubfields('w');
                                        if (count($a_names) > 0) {
                                            $idArr = explode(')', $a_names[0]->getData());
                                            if ($idArr[0] === '(DE-601') {
                                                $parentId = $idArr[1];
                                            }
                                        }
                                        $v_names = $v->getSubfields('v');
                                        if (count($v_names) > 0 && $parentId === $id) {
                                            $subrecord['volume'] = $v_names[0]->getData();
                                            $thisHasBeenSet = 1;
                                        }
                                        $e_names = $v->getSubfields('9');
                                        if (count($e_names) > 0 && $parentId === $id) {
                                            $subrecord['sort'] = $e_names[0]->getData();
                                        }
                                    }
                                }
                            }
                            if ($thisHasBeenSet === 0) {
                                $vs = $marcRecord->getFields('830');
                                if ($vs) {
                                    foreach($vs as $v) {
                                        $a_names = $v->getSubfields('w');
                                        if (count($a_names) > 0) {
                                            $idArr = explode(')', $a_names[0]->getData());
                                            if ($idArr[0] === '(DE-601') {
                                                $parentId = $idArr[1];
                                            }
                                        }
                                        $v_names = $v->getSubfields('v');
                                        if (count($v_names) > 0 && $parentId === $id) {
                                            $subrecord['volume'] = $v_names[0]->getData();
                                        }
                                        $e_names = $v->getSubfields('9');
                                        if (count($e_names) > 0 && $parentId === $id) {
                                            $subrecord['sort'] = $e_names[0]->getData();
                                        }
                                    }
                                }
                            }
                            unset($vs);
                            $vs = $marcRecord->getFields('245');
                            if ($vs) {
                                foreach($vs as $v) {
                                    $a_names = $v->getSubfields('a');
                                    if (count($a_names) > 0) {
                                        $subrecord['title_full'][0] .= " ".$a_names[0]->getData();
                                    }
                                }
                            }
                            unset($vs);
                            $vs = $marcRecord->getFields('250');
                            if ($vs) {
                                foreach($vs as $v) {
                                    $a_names = $v->getSubfields('a');
                                    if (count($a_names) > 0) {
                                        $subrecord['title_full'][0] .= " ".$a_names[0]->getData();
                                    }
                                }
                            }
                            unset($vs);
                            $vs = $marcRecord->getFields('260');
                            if ($vs) {
                                foreach($vs as $v) {
                                    $a_names = $v->getSubfields('c');
                                    if (count($a_names) > 0) {
                                        $subrecord['publishDate'][0] = " ".$a_names[0]->getData();
                                    }
                                }
                            }
                            /*
                            $ves = $marcRecord->getFields('900');
                            if ($ves) {
                                foreach($ves as $ve) {
                                    $libArr = $ve->getSubfields('b');
                                    $lib = $libArr[0]->getData();
                                    if ($lib === 'TUB Hamburg <830>') {
                                        // Is there an address in the current field?
                                        $ve_names = $ve->getSubfields('c');
                                        if (count($ve_names) > 0) {
                                            foreach($ve_names as $ve_name) {
                                                $subrecord['title_full'][] = $ve_name->getData();
                                            }
                                        }
                                    }
                                }
                            }
                            */
                            break;
                        case 'c':
                            $vs = $marcRecord->getFields('773');
                            if ($vs) {
                                foreach($vs as $v) {
                                    $q_names = $v->getSubfields('q');
                                    if ($q_names[0]) {
                                        $subrecord['title_full'][] = $q_names[0]->getData();
                                    }
                                }
                            }
                            unset($vs);
                            $vs = $marcRecord->getFields('260');
                            if ($vs) {
                                foreach($vs as $v) {
                                    $a_names = $v->getSubfields('c');
                                    if (count($a_names) > 0) {
                                        $subrecord['publishDate'][0] = " ".$a_names[0]->getData();
                                    }
                                }
                            }
                            unset($vs);
                            $vs = $marcRecord->getFields('800');
                            $thisHasBeenSet = 0;
                            if ($vs) {
                                foreach($vs as $v) {
                                    $a_names = $v->getSubfields('w');
                                    if (count($a_names) > 0) {
                                        $idArr = explode(')', $a_names[0]->getData());
                                        if ($idArr[0] === '(DE-601') {
                                            $parentId = $idArr[1];
                                        }
                                    }
                                    $v_names = $v->getSubfields('v');
                                    if (count($v_names) > 0 && $parentId === $id) {
                                        $subrecord['volume'] = $v_names[0]->getData();
                                        $thisHasBeenSet = 1;
                                    }
                                }
                            }
                            if ($thisHasBeenSet === 0) {
                                $vs = $marcRecord->getFields('810');
                                if ($vs) {
                                    foreach($vs as $v) {
                                        $a_names = $v->getSubfields('w');
                                        if (count($a_names) > 0) {
                                            $idArr = explode(')', $a_names[0]->getData());
                                            if ($idArr[0] === '(DE-601') {
                                                $parentId = $idArr[1];
                                            }
                                        }
                                        $v_names = $v->getSubfields('v');
                                        if (count($v_names) > 0 && $parentId === $id) {
                                            $subrecord['volume'] = $v_names[0]->getData();
                                            $thisHasBeenSet = 1;
                                        }
                                        $e_names = $v->getSubfields('9');
                                        if (count($e_names) > 0 && $parentId === $id) {
                                            $subrecord['sort'] = $e_names[0]->getData();
                                        }
                                    }
                                }
                            }
                            if ($thisHasBeenSet === 0) {
                                $vs = $marcRecord->getFields('830');
                                if ($vs) {
                                    foreach($vs as $v) {
                                        $a_names = $v->getSubfields('w');
                                        if (count($a_names) > 0) {
                                            $idArr = explode(')', $a_names[0]->getData());
                                            if ($idArr[0] === '(DE-601') {
                                                $parentId = $idArr[1];
                                            }
                                        }
                                        $v_names = $v->getSubfields('v');
                                        if (count($v_names) > 0 && $parentId === $id) {
                                            $subrecord['volume'] = $v_names[0]->getData();
                                        }
                                        $e_names = $v->getSubfields('9');
                                        if (count($e_names) > 0 && $parentId === $id) {
                                            $subrecord['sort'] = $e_names[0]->getData();
                                        }
                                    }
                                }
                            }
                            break;
                        case ' ':
                        default:
                            $thisHasBeenSet = 0;
                            $vs = $marcRecord->getFields('810');
                            if ($vs) {
                                foreach($vs as $v) {
                                    $a_names = $v->getSubfields('w');
                                    if (count($a_names) > 0) {
                                        $idArr = explode(')', $a_names[0]->getData());
                                        if ($idArr[0] === '(DE-601') {
                                            $parentId = $idArr[1];
                                        }
                                    }
                                    $v_names = $v->getSubfields('v');
                                    if (count($v_names) > 0 && $parentId === $id) {
                                        $subrecord['volume'] = $v_names[0]->getData();
                                        $thisHasBeenSet = 1;
                                    }
                                    $e_names = $v->getSubfields('9');
                                    if (count($e_names) > 0 && $parentId === $id) {
                                        $subrecord['sort'] = $e_names[0]->getData();
                                    }
                                }
                            }
                            if ($thisHasBeenSet === 0) {
                                $vs = $marcRecord->getFields('830');
                                if ($vs) {
                                    foreach($vs as $v) {
                                        $a_names = $v->getSubfields('w');
                                        if (count($a_names) > 0) {
                                            $idArr = explode(')', $a_names[0]->getData());
                                            if ($idArr[0] === '(DE-601') {
                                                $parentId = $idArr[1];
                                            }
                                        }
                                        $v_names = $v->getSubfields('v');
                                        if (count($v_names) > 0 && $parentId === $id) {
                                            $subrecord['volume'] = $v_names[0]->getData();
                                        }
                                        $e_names = $v->getSubfields('9');
                                        if (count($e_names) > 0 && $parentId === $id) {
                                            $subrecord['sort'] = $e_names[0]->getData();
                                        }
                                    }
                                }
                            }
                            if (count($subrecord['title_full']) === 0 || $journalIndicator === 'm' || $journalIndicator === 's') {
                                unset($vs);
                                $vs = $marcRecord->getFields('245');
                                if ($vs) {
                                    foreach($vs as $v) {
                                        $a_names = $v->getSubfields('a');
                                        if (count($a_names) > 0) {
                                            $subrecord['title_full'][0] .= " ".$a_names[0]->getData();
                                        }
                                    }
                                }
                                unset($vs);
                                $vs = $marcRecord->getFields('250');
                                if ($vs) {
                                    foreach($vs as $v) {
                                        $a_names = $v->getSubfields('a');
                                        if (count($a_names) > 0) {
                                            $subrecord['title_full'][0] .= " ".$a_names[0]->getData();
                                        }
                                    }
                                }
                                /*
                                unset($vs);
                                if ($journalIndicator === 's') {
                                    $vs = $marcRecord->getFields('362');
                                    if ($vs) {
                                        foreach($vs as $v) {
                                            $a_names = $v->getSubfields('a');
                                            if (count($a_names) > 0) {
                                                $subrecord['title_full'][0] .= " ".$a_names[0]->getData();
                                            }
                                        }
                                    }
                                }
                                else {
                                    $vs = $marcRecord->getFields('260');
                                    if ($vs) {
                                        foreach($vs as $v) {
                                            $a_names = $v->getSubfields('c');
                                            if (count($a_names) > 0) {
                                                $subrecord['title_full'][0] .= " ".$a_names[0]->getData();
                                            }
                                        }
                                    }
                                }
                                */
                            }
                            unset($vs);
                            $vs = $marcRecord->getFields('260');
                            if ($vs) {
                                foreach($vs as $v) {
                                    $a_names = $v->getSubfields('c');
                                    if (count($a_names) > 0) {
                                        $subrecord['publishDate'][0] = " ".$a_names[0]->getData();
                                    }
                                }
                            }
                            break;
                    }
                }
                $afr = $marcRecord->getFields('952');
                if ($afr) {
                    foreach($afr as $articlefieldedref) {
                        $a_names = $articlefieldedref->getSubfields('d');
                        if (count($a_names) > 0) {
                            $subrecord['volume'] = $a_names[0]->getData();
                        }
                        $e_names = $articlefieldedref->getSubfields('e');
                        if (count($e_names) > 0) {
                            $subrecord['issue'] = $e_names[0]->getData();
                        }
                        $h_names = $articlefieldedref->getSubfields('h');
                        if (count($h_names) > 0) {
                            $subrecord['pages'] = $h_names[0]->getData();
                        }
                        $j_names = $articlefieldedref->getSubfields('j');
                        if (count($j_names) > 0) {
                            $subrecord['publishDate'][] = $j_names[0]->getData();
                        }
                    }
                }
                if (count($subrecord['title_full']) === 0) {
                    $subrecord['title_full'][] = '';
                }

                $subrecords[] = $subrecord;
            }
            #print_r($subrecords);
            return $subrecords;
        #}
    }

    /**
     * Get the ID of a record without NLZ prefix
     *
     * @return string ID without NLZ-prefix (if this is an NLZ record)
     * @access protected
     */
    protected function stripNLZ($rid = false) {
        if ($rid === false) $rid = $this->fields['id'];
        // if this is a national licence record, strip NLZ prefix since this is not indexed as ppnlink
        if (substr($this->fields['id'], 0, 4) === 'NLEB' || substr($this->fields['id'], 0, 4) === 'NLEJ') {
            $rid = substr($rid, 4);
        }
        if (substr($this->fields['id'], 0, 3) === 'NLM') {
            $rid = substr($rid, 3);
        }
        return $rid;
    }

    /**
     * Get the ID of a record with NLZ prefix, if this is appropriate
     *
     * @return string ID with NLZ-prefix (if this is an NLZ record)
     * @access protected
     */
    protected function addNLZ($rid = false) {
        if ($rid === false) $rid = $this->fields['id'];
        $prefix = '';
        if (substr($this->fields['id'], 0, 4) === 'NLEB') {
            $prefix = 'NLEB';
        }
        if (substr($this->fields['id'], 0, 4) === 'NLEJ') {
            $prefix = 'NLEJ';
        }
        if (substr($this->fields['id'], 0, 3) === 'NLM') {
            $prefix = 'NLM';
        }
        // return unmodified ID
        return $rid;
        // This duplicates the prefix and cannot work properly, though...
        return $prefix.$rid;
    }

    /**
     * Determine if we have a national license hit
     *
     * @return boolean is this a national license hit?
     * @access protected
     */
    protected function isNLZ() {
        return ($this->_isNLZ($this->fields['id']));
    }

    /**
     * Determine if we have a national license hit
     *
     * @return boolean is this a national license hit?
     * @access protected
     */
    private function _isNLZ($id) {
        if (substr($id, 0, 3) === 'NLM' || substr($id, 0, 4) === 'NLEJ' || substr($id, 0, 4) === 'NLEB' || substr($id, 0, 4) === 'DOAJ') {
            return true;
        }
        return false;
    }

    /**
     * Get an array of all series names containing the record.  Array entries may
     * be either the name string, or an associative array with 'name' and 'number'
     * keys.
     *
     * @return array
     * @access protected
     */
    public function getSeriesShort()
    {
        $matches = array();

        // First check the 440, 800 and 830 fields for series information:
        $primaryFields = array(
            '440' => array('a', 'p'),
            '800' => array('a', 'b', 'c', 'd', 'f', 'p', 'q', 't'),
            '830' => array('a', 'p'));
        $matches = $this->getSeriesFromMARC($primaryFields);

        return $matches;
    }

    public function getVolumeName($record = null) {
        if ($this->getFirstFieldValue('245', array('n', 'p'))) return array($this->getFirstFieldValue('245', array('n', 'p')));
        return null;
    }

    public function getDateSpan() {
        $spanArray = parent::getDateSpan();
        $span = implode(' ', $spanArray);
        return($span);
    }

    public function getSeriesLink()
    {
        $parentIds = array();
        $onlyTopLevel = 0;
        $leader = $this->marcRecord->getLeader();
        $indicator = substr($leader, 19, 1);
        $checkMore = null;
        switch ($indicator) {
            case 'a':
                $checkMore = 0;
                $parentIds['showAssociated'] = '1';
                break;
            case 'c':
                $onlyTopLevel = 1;
                $parentIds['showAssociated'] = '2';
                break;
            case 'b':
            case ' ':
            default:
                //$checkMore = 0;
                $parentIds['showAssociated'] = '0';
                break;
        }
        if ($checkMore !== 0) {
        $journalIndicator = substr($leader, 7, 1);
        switch ($journalIndicator) {
            case 's':
                $parentIds['showAssociated'] = '1';
                break;
            case 'b':
            case 'm':
                #$onlyTopLevel = 1;
                $parentIds['showAssociated'] = '3';
                break;
        }
        }
        $onlyTopLevel = 1;
        $parentIds['ids'] = array();
        $volNumber = array();
        if ($onlyTopLevel === 1) {
            // only look for the parent of this record, all other associated publications can be ignored
            $vs = $this->marcRecord->getFields('773');
            if ($vs) {
                foreach($vs as $v) {
                    $a_names = $v->getSubfields('w');
                    if (count($a_names) > 0) {
                        $idArr = explode(')', $a_names[0]->getData());
                        $parentIds['ids'][] = $this->addNLZ($idArr[1]);
                    }
                    $v_names = $v->getSubfields('v');
                    if (count($v_names) > 0) {
                        $volNumber[$idArr[1]] = $v_names[0]->getData();
                    }
                }
            }
            if (count($parentIds['ids']) === 0) {
                $vs = $this->marcRecord->getFields('830');
                $eighthundred = $this->marcRecord->getFields('800');
                $eighthundredten = $this->marcRecord->getFields('810');
                if ($vs) {
                    foreach($vs as $v) {
                        $a_names = $v->getSubfields('w');
                        if (count($a_names) > 0) {
                            $idArr = explode(')', $a_names[0]->getData());
                            if ($idArr[0] === '(DE-601') {
                                $parentIds['ids'][] = $idArr[1];
                            }
                        }
                        $v_names = $v->getSubfields('v');
                        if (count($v_names) > 0) {
                            $volNumber[$idArr[1]] = $v_names[0]->getData();
                        }
                    }
                }
                else if ($eighthundred) {
                    foreach($eighthundred as $v) {
                        $a_names = $v->getSubfields('w');
                        if (count($a_names) > 0) {
                            $idArr = explode(')', $a_names[0]->getData());
                            if ($idArr[0] === '(DE-601') {
                                $parentIds['ids'][] = $idArr[1];
                            }
                        }
                        $v_names = $v->getSubfields('v');
                        if (count($v_names) > 0) {
                            $volNumber[$idArr[1]] = $v_names[0]->getData();
                        }
                    }
                }
                else if ($eighthundredten) {
                    foreach($eighthundredten as $v) {
                        $a_names = $v->getSubfields('w');
                        if (count($a_names) > 0) {
                            $idArr = explode(')', $a_names[0]->getData());
                            if ($idArr[0] === '(DE-601') {
                                $parentIds['ids'][] = $idArr[1];
                            }
                        }
                        $v_names = $v->getSubfields('v');
                        if (count($v_names) > 0) {
                            $volNumber[$idArr[1]] = $v_names[0]->getData();
                        }
                    }
                }
            }
            return $parentIds;
        }
    }

    /**
     * Check if at least one article for this item exists.
     * Method to keep performance lean in core.tpl.
     *
     * @return bool
     * @access protected
     */
    public function searchArticles()
    {
        $rid=$this->fields['id'];
        if(strlen($rid)<2) {
            return array();
        }
        $rid=str_replace(":","\:",$rid);
        $index = $this->getIndexEngine();

        // Assemble the query parts and filter out current record:
        $query = '(ppnlink:'.$this->stripNLZ($rid).' AND (format:Article OR format:"electronic Article"))';

        // Perform the search and return either results or an error:
        $this->setHiddenFilters();

        $result = $index->search($query, null, $this->hiddenFilters, 0, 1000, null, '', null, null, '',  HTTP_REQUEST_METHOD_POST , false, false, false);

        // Check if the PPNs are from the same origin (either both should have an NLZ-prefix or both should not have it)
        $resultArray = array();
        $resultArray['response'] = array();
        $resultArray['response']['docs'] = array();
        foreach ($result['response']['docs'] as $resp) {
            if (($this->_isNLZ($resp['id']) && $this->_isNLZ($rid)) || (!$this->_isNLZ($resp['id']) && !$this->_isNLZ($rid))) {
                $resultArray['response']['docs'][] = $resp;
            }
        }

        //return ($result['response'] > 0) ? $result['response'] : false;
        return ($resultArray['response'] > 0) ? $resultArray['response'] : false;
    }

    /**
     * Check if at least one article for this item exists.
     * Method to keep performance lean in core.tpl.
     *
     * @return bool
     * @access protected
     */
    public function searchArticleVolume($rid, $fieldref)
    {
        $index = $this->getIndexEngine();

        $queryparts = array();
        $queryparts[] = 'ppnlink:'.$this->stripNLZ($rid);
        if ($fieldref['volume']) {
            $fieldsToSearch .= $fieldref['volume'].'.';
        }
        if ($fieldref['date']) {
            $fieldsToSearch .= $fieldref['date'];
        }
        if ($fieldsToSearch) {
            $queryparts[] = $fieldsToSearch;
        }
        $queryparts[] = '(format:Book OR format:"Serial Volume")';
        // Assemble the query parts and filter out current record:
        $query = implode(" AND ", $queryparts);
        $query = '('.$query.')';
        //$query = '(ppnlink:'.$rid.' AND '.$fieldref.')';

        // Perform the search and return either results or an error:
        $this->setHiddenFilters();

        $result = $index->search($query, null, $this->hiddenFilters, 0, 1000, null, '', null, null, '',  HTTP_REQUEST_METHOD_POST, false, false, false);

        return ($result['response'] > 0) ? $result['response'] : false;
    }

    /**
     * Check if at least one article for this item exists.
     * Method to keep performance lean in core.tpl.
     *
     * @return bool
     * @access protected
     */
    public function hasArticles()
    {
        $rid=$this->fields['id'];
        if(strlen($rid)<2) {
            return array();
        }
        $rid=str_replace(":","\:",$rid);
        $index = $this->getIndexEngine();

        // Assemble the query parts and filter out current record:
        $query = '(ppnlink:'.$this->stripNLZ($rid).' AND (format:Article OR format:"electronic Article")';
        //if ($this->isNLZ() === false) $query .= ' AND (NOT id:"NLZ*")';
        $query .= ')';

        // Perform the search and return either results or an error:
        $this->setHiddenFilters();

        $result = $index->search($query, null, $this->hiddenFilters, 0, 1000, null, '', null, null, 'id',  HTTP_REQUEST_METHOD_POST , false, false, false);

        $showRegister = false;
        foreach ($result['response']['docs'] as $resp) {
            // Walk through the results until there is a match, which is added to the result array
            if (($this->_isNLZ($resp['id']) && $this->_isNLZ($rid)) || (!$this->_isNLZ($resp['id']) && !$this->_isNLZ($rid))) {
                $showRegister = true;
                // After one hit is found, its clear that the register card needs to be shown, so leave the loop
                break;
            }
        }

        return $showRegister;
    }

    /**
     * Get multipart parent.
     *
     * @return array
     * @access protected
     */
    protected function getMultipartParent()
    {
        if (!(isset($this->fields['ppnlink'])) || $this->fields['ppnlink'] == null) {
            return array();
        }
        $mpid = $this->fields['ppnlink'];
        $query="";
        foreach($mpid as $mp) {
            if(strlen($mp)<2) continue;
            $mp=str_replace(":","\:",$mp);
            if(strlen($query)>0) $query.=" OR ";
            $query.= "id:".$this->addNLZ($mp);
        }

        // echo "<pre>".$query."</pre>";

        $index = $this->getIndexEngine();

        // Perform the search and return either results or an error:
        $this->setHiddenFilters();
        $result = $index->search($query, null, $this->hiddenFilters, 0, null, null, '', null, null, 'title, id',  HTTP_REQUEST_METHOD_POST , false, false, false);

        if (PEAR::isError($result)) {
            return $result;
        }

        if (isset($result['response']['docs'])
            && !empty($result['response']['docs'])
            ) {
            $cnt=0;
            foreach($result['response']['docs'] as $doc) {
                $retval[$cnt]['title']=$doc['title'];
                $retval[$cnt]['id']=$doc['id'];
                $cnt++;
            }
            // sort array for key 'part'
            return $retval = $this->_sortArray($retval,'title','asort');
        }
        return array();
    }

    /**
     * Get article children.
     *
     * @return array
     * @access protected
     */
    protected function getArticleChildren()
    {
        $cnt=0;
        $retval = array();
        $sort = array();
        $result = $this->getArticles();
        foreach($result as $doc) {
            $retval[$cnt]['title']=$doc['title_full'][0];
            $retval[$cnt]['id']=$doc['id'];
            $retval[$cnt]['date']=$doc['publishDate'][0];
            $retval[$cnt]['volume'] = $doc['volume'];
            $retval[$cnt]['issue'] = $doc['issue'];
            $retval[$cnt]['pages'] = $doc['pages'];
            $retval[$cnt]['sort'] = $doc['sort'];
            $cnt++;
        }
        foreach ($retval as $key => $row) {
            $part0[$key] = (isset($row['sort'])) ? $row['sort'] : 0;
            $part1[$key] = (isset($row['date'])) ? $row['date'] : 0;
            $part2[$key] = (isset($row['volume'])) ? $row['volume'] : 0;
            $part3[$key] = (isset($row['issue'])) ? $row['issue'] : 0;
            $part4[$key] = (isset($row['pages'])) ? $row['pages'] : 0;
            $part5[$key] = (isset($row['title'])) ? $row['title'] : 0;
        }
        array_multisort($part0, SORT_DESC, $part1, SORT_DESC, $part2, SORT_DESC, $part3, SORT_DESC, $part4, SORT_DESC, $part5, SORT_ASC, $retval );
        return $retval;
    }

    /**
     * Get the multipart resource record level of the current record.
     *
     * @return string
     */
    public function getMultipartLevel()
    {
        $leader = $this->getMarcRecord()->getLeader();
        $mpLevel = strtoupper($leader[19]);

        switch ($mpLevel) {
        case 'A': // multipart set parent
            return "parent";
        case 'B': // part with independent title
            return "independent";
        case 'C': // part with dependent title
            return "dependent";
        default:
            return "unknown";
        }
    }

    /**
     * Get the short (pre-subtitle) title of the record.
     *
     * @return string
     */
    public function getShortTitle()
    {
        $ts = parent::getShortTitle();
        if ($ts == '') {
            $ts = $this->getTitleAdvanced();
        }
        $r = $ts;
        if (is_array($ts) === true) {
            $r = $ts[0];
        }
        return $r;
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
     * Get an array of newer titles for the record.
     *
     * @return array
     */
    public function getNewerTitles()
    {
        $resultarr = null;
        $marcFields = $this->marcRecord->getFields('785');
        $nameField = '';
        $id = '';
        if (!empty($marcFields)) {
            foreach ($marcFields as $field) {
                if (!empty($field->getSubfields('t'))) {
                    $nameField = $field->getSubfields('t')[0]->getData();
                }
                if (!empty($field->getSubfields('w'))) {
                    $linkField = $field->getSubfields('w')[0];
                    $idArr = explode(')', $linkField->getData());
                    $id = '(DE-599)ZDB'.$idArr[1];
                }
                if ($nameField != '' && $id != '') {
                    $resultarr = [];
                    $resultarr[] = ['name' => $nameField, 'id' => $id];
                }
            }
        }
        return $resultarr;
    }

    /**
     * Get an array of newer titles for the record.
     *
     * @return array
     */
    public function getPreviousTitles()
    {
        $resultarr = [];
        $marcFields = $this->marcRecord->getFields('780');
        $nameField = '';
        $id = '';
        if (!empty($marcFields)) {
            foreach ($marcFields as $field) {
                if (!empty($field->getSubfields('t'))) {
                    $nameField = $field->getSubfields('t')[0]->getData();
                }
                if (!empty($field->getSubfields('w'))) {
                    $linkField = $field->getSubfields('w')[0];
                    $idArr = explode(')', $linkField->getData());
                    $id = '(DE-599)ZDB'.$idArr[1];
                }
                if ($nameField != '' && $id != '') {
                    $resultarr = [];
                    $resultarr[] = ['name' => $nameField, 'id' => $id];
                }
            }
        }
        return $resultarr;
    }

    /**
     * Obtains an array of remarks and comments in MARC 980$k and $g. The array is saved to $this->remarks
     *
     * @return void
     * @access protected
     */
    public function getRemarksFromMarc()
    {
        $copy = array();
        $iln = isset($this->recordConfig->Library->iln)
            ? $this->recordConfig->Library->iln : null;
        $vs = $this->marcRecord->getFields('980');
        if ($vs) {
            // Durchlaufe die Felder 980 (Bestandsangaben aller GBV-Bibliotheken)
            // Dies ist notwendig, um die Kommentare und Bemerkungen aus dem MARC-Code abzufischen
            foreach($vs as $v) {
                // is this ours? In Feld $2 steht die ILN der Bibliothek, zu der diese Bestandsangabe gehoert
                // Wenn der Titel zur konfigurierten Bibliothek gehoert, werte die Zeile aus
                $libArr = $v->getSubfields('2');
                $lib = $libArr[0]->getData();
//echo $lib;
                if ($lib === $iln) {
                    $v_signature = null;
                    $epnArr = $v->getSubfields('b');
                    $epn = $epnArr[0]->getData();
                    $copy[$epn] = array();
                    $v_names = $v->getSubfields('k');
                    $v_remarks = $v->getSubfields('g');
                    if (count($v_names) > 0) {
                        $copy[$epn]['summary'] = array();
                        foreach($v_names as $v_name) {
                            $copy[$epn]['summary'][] = $v_name->getData();
                        }
                    }
                    if (count($v_remarks) > 0) {
                        $copy[$epn]['marc_notes'] = array();
                        foreach($v_remarks as $v_remark) {
                            $copy[$epn]['marc_notes'][] = $v_remark->getData();
                        }
                    }
                }
            }
        }
        return $copy;
    }

    /**
     * Get a value from a MARC field matching a specific where condition
     *
     * @param string $field Field to get from MARC
     * @param int    $id    The context ID, taht this field should match
     *
     * @return null|string
     */
    public function getSpecificFieldValue($fields, $id)
    {
        $marcFields = $this->marcRecord->getFields($fields);
        if (!empty($marcFields)) {
            foreach ($marcFields as $field) {
                $linkFields = $field->getSubfields('w');
                foreach ($linkFields as $current) {
                    $idArr = explode(')', $current->getData());
                    if ($idArr[0] == '(DE-601') {
                        if ($idArr[1] == $id) {
                            if (isset($field->getSubfields('v')[0])) {
                                return $field->getSubfields('v')[0]->getData();
                            }
                            else {
                                return "n/a";
                            }
                        }
                    }
                }
            }
        }
        return null;
    }

    /**
     * Get an array of information about record holdings, obtained in real-time
     * from the ILS.
     *
     * @return array
     */
    public function getRealTimeHoldings($lang = null)
    {
        return $this->hasILS() ? $this->holdLogic->getHoldings(
            $this->getUniqueID(), $this->getConsortialIDs(), $lang
        ) : [];
    }

    /**
     * Return an array of all values extracted from the specified field/subfield
     * combination.  If multiple subfields are specified and $concat is true, they
     * will be concatenated together in the order listed -- each entry in the array
     * will correspond with a single MARC field.  If $concat is false, the return
     * array will contain separate entries for separate subfields.
     *
     * @param string $field     The MARC field number to read
     * @param array  $subfields The MARC subfield codes to read
     * @param bool   $concat    Should we concatenate subfields?
     * @param string $separator Separator string (used only when $concat === true)
     *
     * @return array
     */
    protected function getLegacyFieldArray($field, $subfields = null, $concat = true,
        $separator = ' '
    ) {
        // Default to subfield a if nothing is specified.
        if (!is_array($subfields)) {
            $subfields = ['a'];
        }

        // Initialize return array
        $matches = [];

        // Try to look up the specified field, return empty array if it doesn't
        // exist.
        $fields = $this->getMarcRecord()->getFields($field);
        if (!is_array($fields)) {
            return $matches;
        }
        
        // Extract all the requested subfields, if applicable.
        foreach ($fields as $currentField) {
            $next = $this
                ->getLegacySubfieldArray($currentField, $subfields, $concat, $separator);
            $matches = array_merge($matches, $next);
        }
        
        return $matches;
    }

    /**     
     * Return an array of non-empty subfield values found in the provided MARC
     * field.  If $concat is true, the array will contain either zero or one
     * entries (empty array if no subfields found, subfield values concatenated
     * together in specified order if found).  If concat is false, the array
     * will contain a separate entry for each subfield value found.
     * In contradiction to original method, this method will not omit empty subfields in the result array,
     * so that sorting can work if needed.
     *
     * @param object $currentField Result from File_MARC::getFields.
     * @param array  $subfields    The MARC subfield codes to read
     * @param bool   $concat       Should we concatenate subfields?
     * @param string $separator    Separator string (used only when $concat === true)
     *
     * @return array
     */
    protected function getLegacySubfieldArray($currentField, $subfields, $concat = true,
        $separator = ' '
    ) {
        // Start building a line of text for the current field
        $matches = [];

        // Loop through all subfields, collecting results that match the whitelist;
        // note that it is important to retain the original MARC order here!
        $allSubfields = $currentField->getSubfields();
        if (count($allSubfields) > 0) {
            foreach ($allSubfields as $currentSubfield) {
                if (in_array($currentSubfield->getCode(), $subfields)) {
                    // Grab the current subfield value and act on it if it is
                    // non-empty:
                    $data = trim($currentSubfield->getData());
                    if (!empty($data)) {
                        $matches[] = $data;
                    }
                }
            }
        }

        // Send back the data in a different format depending on $concat mode:
        return $concat ? [implode($separator, $matches)] : $matches;
    }

    public function supportsAjaxStatus() {
        return true;
    }

}
