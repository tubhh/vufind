<?php
/**
 * Recommendation module plugin manager
 *
 * PHP version 7
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
 * Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301  USA
 *
 * @category VuFind
 * @package  Recommendations
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:plugins:recommendation_modules Wiki
 */
namespace TUBfind\Recommend;

use Laminas\ServiceManager\Factory\InvokableFactory;

/**
 * Recommendation module plugin manager
 *
 * @category VuFind
 * @package  Recommendations
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:plugins:recommendation_modules Wiki
 */
class PluginManager extends \VuFind\Recommend\PluginManager
{
    /**
     * Default plugin aliases.
     *
     * @var array
     */
    protected $aliases = [
        'alphabrowselink' => \VuFind\Recommend\AlphaBrowseLink::class,
        'authorfacets' => \VuFind\Recommend\AuthorFacets::class,
        'authorinfo' => \VuFind\Recommend\AuthorInfo::class,
        'authorityrecommend' => \VuFind\Recommend\AuthorityRecommend::class,
        'catalogresults' => \VuFind\Recommend\CatalogResults::class,
        'channels' => \VuFind\Recommend\Channels::class,
        'collectionsidefacets' => \VuFind\Recommend\CollectionSideFacets::class,
        'doi' => \VuFind\Recommend\DOI::class,
        'dplaterms' => \VuFind\Recommend\DPLATerms::class,
        'europeanaresults' => \VuFind\Recommend\EuropeanaResults::class,
        'europeanaresultsdeferred' => \VuFind\Recommend\EuropeanaResultsDeferred::class,
        'expandfacets' => \VuFind\Recommend\ExpandFacets::class,
        'externalsearch' => \VuFind\Recommend\ExternalSearch::class,
        'facetcloud' => \VuFind\Recommend\FacetCloud::class,
        'favoritefacets' => \VuFind\Recommend\FavoriteFacets::class,
        'libraryh3lp' => \VuFind\Recommend\Libraryh3lp::class,
        'mapselection' => \VuFind\Recommend\MapSelection::class,
        'sidefacets' => \VuFind\Recommend\SideFacets::class,
        'sidefacetsdeferred' => \VuFind\Recommend\SideFacetsDeferred::class,
        'openlibrarysubjects' => \VuFind\Recommend\OpenLibrarySubjects::class,
        'openlibrarysubjectsdeferred' => \VuFind\Recommend\OpenLibrarySubjectsDeferred::class,
        'pubdatevisajax' => \VuFind\Recommend\PubDateVisAjax::class,
        'randomrecommend' => \VuFind\Recommend\RandomRecommend::class,
        'recommendlinks' => \VuFind\Recommend\RecommendLinks::class,
        'removefilters' => \VuFind\Recommend\RemoveFilters::class,
        'resultgooglemapajax' => \VuFind\Recommend\Deprecated::class,
        'spellingsuggestions' => \VuFind\Recommend\SpellingSuggestions::class,
        'summonbestbets' => \VuFind\Recommend\SummonBestBets::class,
        'summonbestbetsdeferred' => \VuFind\Recommend\SummonBestBetsDeferred::class,
        'summondatabases' => \VuFind\Recommend\SummonDatabases::class,
        'summondatabasesdeferred' => \VuFind\Recommend\SummonDatabasesDeferred::class,
        'summonresults' => \VuFind\Recommend\SummonResults::class,
        'summonresultsdeferred' => \VuFind\Recommend\SummonResultsDeferred::class,
        'summontopics' => \VuFind\Recommend\SummonTopics::class,
        'switchquery' => \VuFind\Recommend\SwitchQuery::class,
        'switchtype' => \VuFind\Recommend\SwitchType::class,
        'topfacets' => \VuFind\Recommend\TopFacets::class,
        'visualfacets' => \VuFind\Recommend\VisualFacets::class,
        'webresults' => \VuFind\Recommend\WebResults::class,
        'worldcatidentities' => \VuFind\Recommend\WorldCatIdentities::class,
        'worldcatterms' => \VuFind\Recommend\Deprecated::class,
        'suggesttype' => \TUBfind\Recommend\SuggestType::class,
    ];

    /**
     * Default plugin factories.
     *
     * @var array
     */
    protected $factories = [
        \TUBfind\Recommend\SuggestType::class => \VuFind\Recommend\InjectResultsManagerFactory::class,
        \VuFind\Recommend\AlphaBrowseLink::class => \VuFind\Recommend\InvokableFactory::class,
        \VuFind\Recommend\AuthorFacets::class => \VuFind\Recommend\InjectResultsManagerFactory::class,
        \VuFind\Recommend\AuthorInfo::class => \VuFind\Recommend\AuthorInfoFactory::class,
        \VuFind\Recommend\AuthorityRecommend::class => \VuFind\Recommend\InjectResultsManagerFactory::class,
        \VuFind\Recommend\CatalogResults::class => \VuFind\Recommend\InjectSearchRunnerFactory::class,
        \VuFind\Recommend\Channels::class => \VuFind\Recommend\InvokableFactory::class,
        \VuFind\Recommend\CollectionSideFacets::class => \VuFind\Recommend\CollectionSideFacetsFactory::class,
        \VuFind\Recommend\Deprecated::class => \VuFind\Recommend\InvokableFactory::class,
        \VuFind\Recommend\DOI::class => \VuFind\Recommend\InvokableFactory::class,
        \VuFind\Recommend\DPLATerms::class => \VuFind\Recommend\DPLATermsFactory::class,
        \VuFind\Recommend\EuropeanaResults::class => \VuFind\Recommend\EuropeanaResultsFactory::class,
        \VuFind\Recommend\EuropeanaResultsDeferred::class => \VuFind\Recommend\InvokableFactory::class,
        \VuFind\Recommend\ExpandFacets::class => \VuFind\Recommend\ExpandFacetsFactory::class,
        \VuFind\Recommend\ExternalSearch::class => \VuFind\Recommend\InvokableFactory::class,
        \VuFind\Recommend\FacetCloud::class => \VuFind\Recommend\InvokableFactory::class,
        \VuFind\Recommend\FavoriteFacets::class => \VuFind\Recommend\FavoriteFacetsFactory::class,
        \VuFind\Recommend\Libraryh3lp::class => \VuFind\Recommend\InvokableFactory::class,
        \VuFind\Recommend\MapSelection::class => \VuFind\Recommend\MapSelectionFactory::class,
        \VuFind\Recommend\OpenLibrarySubjects::class => \VuFind\Recommend\InvokableFactory::class,
        \VuFind\Recommend\OpenLibrarySubjectsDeferred::class => \VuFind\Recommend\InvokableFactory::class,
        \VuFind\Recommend\PubDateVisAjax::class => \VuFind\Recommend\InvokableFactory::class,
        \VuFind\Recommend\RandomRecommend::class => \VuFind\Recommend\RandomRecommendFactory::class,
        \VuFind\Recommend\RecommendLinks::class => \VuFind\Recommend\InjectConfigManagerFactory::class,
        \VuFind\Recommend\RemoveFilters::class => \VuFind\Recommend\InvokableFactory::class,
        \VuFind\Recommend\SideFacets::class => \VuFind\Recommend\SideFacetsFactory::class,
        \VuFind\Recommend\SideFacetsDeferred::class => \VuFind\Recommend\InjectConfigManagerFactory::class,
        \VuFind\Recommend\SpellingSuggestions::class => \VuFind\Recommend\InvokableFactory::class,
        \VuFind\Recommend\SummonBestBets::class => \VuFind\Recommend\InjectResultsManagerFactory::class,
        \VuFind\Recommend\SummonBestBetsDeferred::class => \VuFind\Recommend\InvokableFactory::class,
        \VuFind\Recommend\SummonDatabases::class => \VuFind\Recommend\InjectResultsManagerFactory::class,
        \VuFind\Recommend\SummonDatabasesDeferred::class => \VuFind\Recommend\InvokableFactory::class,
        \VuFind\Recommend\SummonResults::class => \VuFind\Recommend\InjectSearchRunnerFactory::class,
        \VuFind\Recommend\SummonResultsDeferred::class => \VuFind\Recommend\InvokableFactory::class,
        \VuFind\Recommend\SummonTopics::class => \VuFind\Recommend\InjectResultsManagerFactory::class,
        \VuFind\Recommend\SwitchQuery::class => \VuFind\Recommend\SwitchQueryFactory::class,
        \VuFind\Recommend\SwitchType::class => \VuFind\Recommend\InvokableFactory::class,
        \VuFind\Recommend\TopFacets::class => \VuFind\Recommend\InjectConfigManagerFactory::class,
        \VuFind\Recommend\VisualFacets::class => \VuFind\Recommend\InjectConfigManagerFactory::class,
        \VuFind\Recommend\WebResults::class => \VuFind\Recommend\InjectSearchRunnerFactory::class,
        \VuFind\Recommend\WorldCatIdentities::class => \VuFind\Recommend\WorldCatIdentitiesFactory::class,
    ];

    /**
     * Constructor
     *
     * Make sure plugins are properly initialized.
     *
     * @param mixed $configOrContainerInstance Configuration or container instance
     * @param array $v3config                  If $configOrContainerInstance is a
     * container, this value will be passed to the parent constructor.
     */
    public function __construct($configOrContainerInstance = null,
        array $v3config = []
    ) {
        // These objects are not meant to be shared -- every time we retrieve one,
        // we are building a brand new object.
        $this->sharedByDefault = false;

        $this->addAbstractFactory(\VuFind\Recommend\PluginFactory::class);
        parent::__construct($configOrContainerInstance, $v3config);
    }

    /**
     * Return the name of the base class or interface that plug-ins must conform
     * to.
     *
     * @return string
     */
    protected function getExpectedInterface()
    {
        return \VuFind\Recommend\RecommendInterface::class;
    }
}
