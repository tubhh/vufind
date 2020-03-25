<?php
/**
 * Hold Logic Class
 *
 * PHP version 5
 *
 * Copyright (C) Villanova University 2007.
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
 * @package  ILS_Logic
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @author   Luke O'Sullivan <l.osullivan@swansea.ac.uk>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development Wiki
 */
namespace TUBfind\ILS\Logic;
use VuFind\ILS\Connection as ILSConnection,
    VuFind\Exception\ILS as ILSException;

/**
 * Hold Logic Class
 *
 * @category VuFind
 * @package  ILS_Logic
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @author   Luke O'Sullivan <l.osullivan@swansea.ac.uk>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development Wiki
 */
class Holds extends \VuFind\ILS\Logic\Holds
{
    /**
     * Public method for getting item holdings from the catalog and selecting which
     * holding method to call
     *
     * @param string $id  A Bib ID
     * @param array  $ids A list of Source Records (if catalog is for a consortium)
     *
     * @return array A sorted results set
     */
    public function getHoldings($id, $ids = null, $language = 'en')
    {
        $holdings = [];

        // Get Holdings Data
        if ($this->catalog) {
            // Retrieve stored patron credentials; it is the responsibility of the
            // controller and view to inform the user that these credentials are
            // needed for hold data.
            try {
                $patron = $this->ilsAuth->storedCatalogLogin();

                // Does this ILS Driver allow language selection?
                $langcheck = $this->catalog->checkFunction(
                    'setLanguage'
                );
                if ($langcheck === true) {
                    $this->catalog->setLanguage($language);
                }

                // Does this ILS Driver handle consortial holdings?
                $config = $this->catalog->checkFunction(
                    'Holds', compact('id', 'patron')
                );
            } catch (ILSException $e) {
                $patron = false;
                $config = [];
            }

            if (isset($config['consortium']) && $config['consortium'] == true) {
                $result = $this->catalog->getConsortialHoldings(
                    $id, $patron ? $patron : null, $ids
                );
            } else {
                $result = $this->catalog->getHolding($id, $patron ? $patron : null);
            }

            $grb = 'getRequestBlocks'; // use variable to shorten line below:
            $blocks
                = $patron && $this->catalog->checkCapability($grb, compact($patron))
                ? $this->catalog->getRequestBlocks($patron) : false;

            $mode = $this->catalog->getHoldsMode();

            if ($mode == "disabled") {
                $holdings = $this->standardHoldings($result);
            } else if ($mode == "driver") {
                $holdings = $this->driverHoldings($result, $config, !empty($blocks));
            } else {
                $holdings = $this->generateHoldings($result, $mode, $config);
            }

            $holdings = $this->processStorageRetrievalRequests(
                $holdings, $id, $patron, !empty($blocks)
            );
            $holdings = $this->processILLRequests(
                $holdings, $id, $patron, !empty($blocks)
            );
        }
        return [
            'blocks' => $blocks,
            'holdings' => $this->formatHoldings($holdings)
        ];
    }
}
