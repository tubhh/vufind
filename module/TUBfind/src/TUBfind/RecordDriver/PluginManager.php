<?php
/**
 * Record driver plugin manager
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
use Zend\ServiceManager\ConfigInterface;

/**
 * Record driver plugin manager
 *
 * @category VuFind2
 * @package  RecordDrivers
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org/wiki/vufind2:record_drivers Wiki
 */
class PluginManager extends \VuFind\RecordDriver\PluginManager
{
    /**
     * Default plugin aliases.
     *
     * @var array
     */
    protected $aliases = [
        'browzine' => 'VuFind\RecordDriver\BrowZine',
        'eds' => 'VuFind\RecordDriver\EDS',
        'eit' => 'VuFind\RecordDriver\EIT',
        'libguides' => 'VuFind\RecordDriver\LibGuides',
        'missing' => 'VuFind\RecordDriver\Missing',
        'pazpar2' => 'VuFind\RecordDriver\Pazpar2',
        'primo' => 'TUBfind\RecordDriver\Primo',
        'solrauth' => 'VuFind\RecordDriver\SolrAuthMarc', // legacy name
        'solrauthdefault' => 'VuFind\RecordDriver\SolrAuthDefault',
        'solrauthmarc' => 'VuFind\RecordDriver\SolrAuthMarc',
        'solrdefault' => 'VuFind\RecordDriver\SolrDefault',
        'solrmarc' => 'VuFind\RecordDriver\SolrMarc',
        'solrgbv' => 'TUBfind\RecordDriver\SolrGBV',
        'solrlocal' => 'TUBfind\RecordDriver\SolrLocal',
        'solrwpsite' => 'TUBfind\RecordDriver\SolrWPsite',
        'solrweblog' => 'TUBfind\RecordDriver\SolrWeblog',
        'solrtubdok' => 'TUBfind\RecordDriver\SolrTubdok',
        'solrmarcremote' => 'VuFind\RecordDriver\SolrMarcRemote',
        'solrreserves' => 'VuFind\RecordDriver\SolrReserves',
        'solrweb' => 'VuFind\RecordDriver\SolrWeb',
        'summon' => 'VuFind\RecordDriver\Summon',
        'worldcat' => 'VuFind\RecordDriver\WorldCat',
    ];

    /**
     * Default plugin factories.
     *
     * @var array
     */
    protected $factories = [
        'VuFind\RecordDriver\BrowZine' =>
            'Zend\ServiceManager\Factory\InvokableFactory',
        'VuFind\RecordDriver\EDS' => 'VuFind\RecordDriver\NameBasedConfigFactory',
        'VuFind\RecordDriver\EIT' => 'VuFind\RecordDriver\NameBasedConfigFactory',
        'VuFind\RecordDriver\LibGuides' =>
            'Zend\ServiceManager\Factory\InvokableFactory',
        'VuFind\RecordDriver\Missing' => 'VuFind\RecordDriver\AbstractBaseFactory',
        'VuFind\RecordDriver\Pazpar2' =>
            'VuFind\RecordDriver\NameBasedConfigFactory',
        'TUBfind\RecordDriver\Primo' => 'TUBfind\RecordDriver\PrimoFactory',
        'TUBfind\RecordDriver\SolrGBV' => 'TUBfind\RecordDriver\SolrGBVFactory',
        'VuFind\RecordDriver\SolrAuthDefault' =>
            'VuFind\RecordDriver\SolrDefaultWithoutSearchServiceFactory',
        'VuFind\RecordDriver\SolrAuthMarc' =>
            'VuFind\RecordDriver\SolrDefaultWithoutSearchServiceFactory',
        'VuFind\RecordDriver\SolrDefault' =>
            'VuFind\RecordDriver\SolrDefaultFactory',
        'VuFind\RecordDriver\SolrMarc' => 'VuFind\RecordDriver\SolrDefaultFactory',
        'VuFind\RecordDriver\SolrMarcRemote' =>
            'VuFind\RecordDriver\SolrDefaultFactory',
        'VuFind\RecordDriver\SolrReserves' =>
            'VuFind\RecordDriver\SolrDefaultWithoutSearchServiceFactory',
        'VuFind\RecordDriver\SolrWeb' => 'VuFind\RecordDriver\SolrWebFactory',
        'VuFind\RecordDriver\Summon' => 'VuFind\RecordDriver\SummonFactory',
        'VuFind\RecordDriver\WorldCat' =>
            'VuFind\RecordDriver\NameBasedConfigFactory',
    ];

    /**
     * Convenience method to retrieve a populated Solr record driver.
     *
     * @param array $data Raw Solr data
     *
     * @return AbstractBase
     */
    public function getSolrRecord($data, $keyPrefix = 'Solr', $defaultKeySuffix = 'Default')
    {
        // Use GBV Record Driver if the record comes from GBV
        if ($data['recordtype'] === 'marc' && (array_search('GBV Zentral', $data['institution']) !== false || $data['institution'] === 'GBV Zentral' || 
                                               array_search('findex.gbv.de', $data['institution']) !== false || $data['institution'] === 'findex.gbv.de') &&
                                               array_search('Catalog', $data['collection']) === false) {
            $recordType = 'SolrGBV';
        }
        else if (array_search('Catalog', $data['collection']) !== false || $data['collection'] === 'Catalog') {
            $recordType = 'SolrLocal';
        }
        else if (isset($data['recordtype'])) {
            $key = 'Solr' . ucwords($data['recordtype']);
            $recordType = $this->has($key) ? $key : 'SolrDefault';
        } else {
            $recordType = 'SolrDefault';
        }

        // Build the object:
        $driver = $this->get($recordType);
        $driver->setRawData($data);
        return $driver;
    }
}