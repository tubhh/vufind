<?php
/**
 * GBV cover content loader.
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
 * @package  Content
 * @author   Oliver Goldschmidt <o.goldschmidt@tuhh.de>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org/wiki/vufind2:developer_manual Wiki
 */
namespace TUBfind\Content\Covers;

/**
 * GBV cover content loader.
 *
 * @category VuFind2
 * @package  Content
 * @author   Oliver Goldschmidt <o.goldschmidt@tuhh.de>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org/wiki/vufind2:developer_manual Wiki
 */
class GBV extends \VuFind\Content\AbstractCover
{
    /**
     * Constructor
     *
     */
    public function __construct($url)
    {
        $this->supportsIsbn = $this->supportsPpn = $this->cacheAllowed = true;
        $this->url = $url;
    }

    /**
     * Get image URL for a particular API key and set of IDs (or false if invalid).
     *
     * @param string $key  API key
     * @param string $size Size of image to load (small/medium/large)
     * @param array  $ids  Associative array of identifiers (keys may include 'isbn'
     * pointing to an ISBN object and 'issn' pointing to a string)
     *
     * @return string|bool
     *
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function getUrl($key, $size, $ids)
    {
        if (!isset($ids['isbn']) && !isset($ids['ppn'])) {
            return false;
        }
        if (isset($ids['ppn'])) {
            $ppn = $ids['ppn'];
            return $this->url.'?format=img&id=gvk:ppn:' . $ppn;
        }
        $isbn = $ids['isbn']->get13();
        return $this->url.'?format=img&id=isbn:' . $isbn;
    }
}
