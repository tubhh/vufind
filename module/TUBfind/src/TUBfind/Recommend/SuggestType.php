<?php
/**
 * SwitchType Recommendations Module
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
 * Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301  USA
 *
 * @category VuFind
 * @package  Recommendations
 * @author   Oliver Goldschmidt <o.goldschmidt@tuhh.de>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:plugins:recommendation_modules Wiki
 */
namespace TUBfind\Recommend;

/**
 * SwitchType Recommendations Module
 *
 * This class recommends switching to a different search type based on a search term analysis.
 *
 * @category VuFind
 * @package  Recommendations
 * @author   Oliver Goldschmidt <o.goldschmidt@tuhh.de>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:plugins:recommendation_modules Wiki
 */
class SuggestType implements \VuFind\Recommend\RecommendInterface
{
    /**
     * Search handler to try
     *
     * @var string
     */
    protected $newHandler;

    /**
     * On-screen description of handler
     *
     * @var string
     */
    protected $newHandlerName;

    /**
     * Search handler in configuration (if regex matches)
     *
     * @var string
     */
    protected $newHandlerMatch;

    /**
     * On-screen description of handler in configuration (if regex matches)
     *
     * @var string
     */
    protected $newHandlerNameMatch;

    /**
     * Regular expression for type detection
     *
     * @var string
     */
    protected $regex;

    /**
     * Search Term for this query
     *
     * @var string
     */
    protected $searchTerm;

    /**
     * Is this module active?
     *
     * @var bool
     */
    protected $active;

    /**
     * Results object
     *
     * @var \VuFind\Search\Base\Results
     */
    protected $results;

    /**
     * Results plugin manager
     *
     * @var \VuFind\Search\Results\PluginManager
     */
    protected $resultsManager;

    /**
     * Constructor
     *
     * @param \VuFind\Search\Results\PluginManager $results Results plugin manager
     */
    public function __construct(\VuFind\Search\Results\PluginManager $results)
    {
        $this->resultsManager = $results;
    }

    /**
     * Store the configuration of the recommendation module.
     *
     * @param string $settings Settings from searches.ini.
     *
     * @return void
     */
    public function setConfig($settings)
    {
        $params = explode(':', $settings);
        // In case that we find more than 2 colons, the regex probably contains one (or more)
        if (count($params) > 3) {
            $newparams = [];
            for ($n = 0; $n < count($params)-2; $n++) {
                $newparams[] = $params[$n];
            }
            $this->regex = implode(':', $newparams);
            $this->newHandlerMatch = $params[count($params)-2];
            $this->newHandlerNameMatch = $params[count($params)-1];
        }
        else if (count($params) == 3) {
            $this->regex = $params[0];
            $this->newHandlerMatch = $params[1];
            $this->newHandlerNameMatch = $params[2];
        }
        else {
            // less than two colons mean, that the configuration for SuggestType is invalid
            // do nothing!
        }
    }

    /**
     * Called at the end of the Search Params objects' initFromRequest() method.
     * This method is responsible for setting search parameters needed by the
     * recommendation module and for reading any existing search parameters that may
     * be needed.
     *
     * @param \VuFind\Search\Base\Params $params  Search parameter object
     * @param \Laminas\StdLib\Parameters    $request Parameter object representing user
     * request.
     *
     * @return void
     */
    public function init($params, $request)
    {
        $this->searchTerm = $params->getQuery()->getString();
    }

    /**
     * Called after the Search Results object has performed its main search.  This
     * may be used to extract necessary information from the Search Results object
     * or to perform completely unrelated processing.
     *
     * @param \VuFind\Search\Base\Results $results Search results object
     *
     * @return void
     */
    public function process($results)
    {
        $handler = $results->getParams()->getSearchHandler();
        $this->results = $results;
        $this->detectKnownItemType();

        // If the handler is null, we can't figure out a single handler, so this
        // is probably an advanced search.  In that case, we shouldn't try to change
        // anything!
        $this->active = !is_null($handler);
    }

    /**
     * Get results stored in the object.
     *
     * @return \VuFind\Search\Base\Results
     */
    public function getResults()
    {
        return $this->results;
    }

    /**
     * Get the new search handler, or false if it does not apply.
     *
     * @return string
     */
    public function getNewHandler()
    {
        return $this->active ? $this->newHandler : false;
    }

    /**
     * Get the description of the new search handler.
     *
     * @return string
     */
    public function getNewHandlerName()
    {
        return $this->newHandlerName;
    }

    /**
     * Analyse the query string for patterns, which might suggest using
     * a certain queryHandler.
     *
     * @param String $string QueryString
     *
     * @return void
     */
    protected function detectKnownItemType() {
        $match = preg_match($this->regex, $this->searchTerm);
        if ($match) {
            $this->active = true;
            $this->newHandler = $this->newHandlerMatch;
            $this->newHandlerName = $this->newHandlerNameMatch;
        }
    }

}
