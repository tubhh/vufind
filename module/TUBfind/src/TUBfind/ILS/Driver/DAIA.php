<?php
/**
 * TUBfind extension of the DAIA ILS Driver for VuFind.
 *
 * PHP version 5
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
 * @package  ILS_Drivers
 * @author   Oliver Goldschmidt <o.goldschmidt@tu-harburg.de>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org/wiki/vufind2:building_an_ils_driver Wiki
 */
namespace TUBfind\ILS\Driver;

/**
 * Extends VuFind DAIA ILS Driver for TUBfind
 *
 * @category VuFind2
 * @package  ILS_Drivers
 * @author   Oliver Goldschmidt <o.goldschmidt@tu-harburg.de>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org/wiki/vufind2:building_an_ils_driver Wiki
 */
class DAIA extends \VuFind\ILS\Driver\PAIA
{
    /**
     * Language for the DAIA server response
     *
     * @var string
     */
    protected $language;

    /**
     * Initialize the driver.
     *
     * Validate configuration and perform all resource-intensive tasks needed to
     * make the driver active.
     *
     * @throws \VuFind\Exception\ILS
     * @return void
     */
    public function init()
    {
        parent::init();
        $this->language = 'de';
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
     * Helper function to determine if the item is available as storage retrieval.
     *
     * @param $item
     * @return bool
     */
    protected function donotuse_checkIsStorageRetrievalRequest($item)
    {
        $services = ['available'=>[], 'unavailable'=>[]];
        if (isset($item['available'])) {
            // check if item is loanable or presentation
            foreach ($item['available'] as $available) {
                if (isset($available['service'])
                    && in_array($available['service'], ['loan', 'presentation'])
                    && isset($available['href'])
                ) {
                    $services['available'][] = $available['service'];
                }
            }
        }

        if (isset($item['unavailable'])) {
            foreach ($item['unavailable'] as $unavailable) {
                if (isset($unavailable['service'])
                    && in_array($unavailable['service'], ['loan', 'presentation'])
                    && isset($available['href'])
                ) {
                    $services['unavailable'][] = $unavailable['service'];
                }
            }
        }

        return in_array('loan', array_diff($services['available'], $services['unavailable']));
    }

    /**
     * Returns the value for "location" in VuFind getStatus/getHolding array
     *
     * @param array $item Array with DAIA item data
     *
     * @return string
     */
    protected function getItemDepartment($item)
    {
        return isset($item['storage']) && isset($item['storage']['content'])
        && !empty($item['storage']['content'])
            ? $item['storage']['content']
            : 'Unknown';
    }

    /**
     * Returns the value of item.department.href (e.g. to be used in VuFind
     * getStatus/getHolding array for linking the location)
     *
     * @param array $item Array with DAIA item data
     *
     * @return string
     */
    protected function getItemDepartmentLink($item)
    {
        return isset($item['storage']['href'])
            ? $item['storage']['href'] : false;
    }

    /**
     * Returns the value of item.storage.content (e.g. to be used in VuFind
     * getStatus/getHolding array as location)
     *
     * @param array $item Array with DAIA item data
     *
     * @return string
     */
    protected function getItemStorage($item)
    {
        return isset($item['storage']) && isset($item['storage']['content'])
        && !empty($item['storage']['content'])
            ? $item['storage']['content']
            : 'Unknown';
    }

    /**
     * Returns the value of item.storage.href (e.g. to be used in VuFind
     * getStatus/getHolding array for linking the location)
     *
     * @param array $item Array with DAIA item data
     *
     * @return string
     */
    protected function getItemStorageLink($item)
    {
        return isset($item['storage']) && isset($item['storage']['href'])
            ? $item['storage']['href'] : '';
    }

    /**
     * Returns an array with status information for provided item.
     *
     * @param array $item Array with DAIA item data
     *
     * @return array
     */
    protected function getItemStatus($item) {
        $return = parent::getItemStatus($item);

        $item_notes = $return['item_notes'];

        // add about to item_notes
        if (isset($item['about'])) {
            $item_notes[] = $item['about'];
        }

        $return['item_notes'] = $item_notes;
//        var_dump($item);
//        var_dump($return);
        return $return;
    }

    /**
     * Get Statuses
     *
     * This is responsible for retrieving the status information for a
     * collection of records.
     * As the DAIA Query API supports querying multiple ids simultaneously
     * (all ids divided by "|") getStatuses(ids) would call getStatus(id) only
     * once, id containing the list of ids to be retrieved. This would cause some
     * trouble as the list of ids does not necessarily correspond to the VuFind
     * Record-id. Therefore getStatuses(ids) has its own logic for multiQuery-support
     * and performs the HTTPRequest itself, retrieving one DAIA response for all ids
     * and uses helper functions to split this one response into documents
     * corresponding to the queried ids.
     *
     * @param array $ids The array of record ids to retrieve the status for
     *
     * @return array    An array of status information values on success.
     */
    public function getStatuses($ids)
    {
        $statuses = parent::getStatuses($ids);
/*
        $more = $this->getTUBItemStatus($statuses[0]);
        $return = [ [ $more ] ];
var_dump($return);
        return $return;
*/
        return $statuses;
    }

    /**
     * For compatibility reasons return 1 normally
     * If this item is not available at all, return an empty string
     *
     * @param array $item Array with DAIA item data
     *
     * @return string
     */
    protected function getItemBarcode($item)
    {
        if(empty($item['available']) && empty($item['storage'])) {
            return '';
        }
        return '1';
    }

}
