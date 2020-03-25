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

/**
 * Model for MARC records in Solr.
 *
 * @category VuFind2
 * @package  RecordDrivers
 * @author   Oliver Goldschmidt <o.goldschmidt@tuhh.de>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org/wiki/vufind2:record_drivers Wiki
 */
class SolrTubdok extends \VuFind\RecordDriver\SolrDefault
{

    /**
     * Get the URL to this record on TUBdok.
     *
     * @return string
     */
    public function getTubdokUrl()
    {
        return isset($this->fields['werkurl'])
            ? $this->fields['werkurl'] : '';
    }

    /**
     * Get the publication dates of the record.  See also getDateSpan().
     *
     * @return array
     */
    public function getPublicationDates()
    {
        $pubDates = [];
        if (isset($this->fields['created'])) {
            foreach ($this->fields['created'] as $pubDate) {
                $pubDates[] = date('d.m.Y', strtotime($pubDate));
            }
        }

        return $pubDates;
    }

    public function getAllAuthors() {
        $authors = [];
        if (isset($this->fields['author'])) {
            $authors[] = $this->fields['author'];
        }
        if (isset($this->fields['author2'])) {
            foreach ($this->fields['author2'] as $a2) {
                $authors[] = $a2;
            }
        }
        return $authors;
    }

    /**
     * Get the DOI of the item that contains this record
     *
     * @return string
     */
    public function getContainerDoi()
    {
        return isset($this->fields['doi_str'])
            ? $this->fields['doi_str'] : '';
    }

    /**
     * Get the Urn of the item
     *
     * @return string
     */
    public function getUrn()
    {
        return isset($this->fields['urn'])
            ? $this->fields['urn'] : '';
    }

    public function getTitleAdvanced()
    {
        return isset($this->fields['title'])
            ? $this->fields['title'] : '';
    }

    public function getSubseries()
    {
        return '';
    }

    public function getMoreContributors()
    {
        return [];
    }

    public function getVolumeStock()
    {
        return [];
    }

    public function getRemarksFromMarc()
    {
        return [];
    }

    public function checkInterlibraryLoan()
    {
        return false;
    }

    public function checkAcquisitionProposal()
    {
        return false;
    }

    public function getHss() {
        return '';
    }

    public function isMultipartChildren()
    {
        return false;
    }
}
