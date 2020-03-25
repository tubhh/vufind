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
class SolrWeblog extends \VuFind\RecordDriver\SolrDefault
{

    /**
     * Get the title of this record
     *
     * @return string
     */
    public function getTitle()
    {
        return isset($this->fields['title'])
            ? $this->fields['title'][0] : '';
    }

    /**
     * Get the german title of this record
     *
     * @return string
     */
    public function getTitleGerman()
    {
        return isset($this->fields['titleGer'])
            ? $this->fields['titleGer'][0] : null;
    }

    /**
     * Get the english title of this record
     *
     * @return string
     */
    public function getTitleEnglish()
    {
        return isset($this->fields['titleEng'])
            ? $this->fields['titleEng'][0] : null;
    }

    /**
     * Get the URL to the english fulltext
     *
     * @return string
     */
    public function getUrlEnglish()
    {
        return isset($this->fields['titleEng'])
            ? $this->fields['url'][1] : null;
    }

    /**
     * Get the URL to the german fulltext
     *
     * @return string
     */
    public function getUrlGerman()
    {
        return isset($this->fields['titleGer'])
            ? $this->fields['url'][0] : null;
    }

    /**
     * Get the publication dates of the record.  See also getDateSpan().
     *
     * @return array
     */
    public function getPublicationDates()
    {
        $pubDates = [];
        if (isset($this->fields['publishDate'])) {
            foreach ($this->fields['publishDate'] as $pubDate) {
                $pubDates[] = date('d.m.Y', strtotime($pubDate));
            }
        }

        return $pubDates;
    }
}
