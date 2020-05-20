<?php
/**
 * Model for Multipart Lists.
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
 * @author   Oliver Goldschmidt <o.goldschmidt@tuhh.de>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org/wiki/vufind2:record_drivers Wiki
 */
namespace TUBfind\Content;

/**
 * Model for MultipartLists
 *
 * @category VuFind2
 * @author   Oliver Goldschmidt <o.goldschmidt@tuhh.de>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org/wiki/vufind2:record_drivers Wiki
 */
class MultipartList
{
    protected $multipartList = null;
    protected $id;

    /**
     * Constructor.
     *
     * @return array
     * @access protected
     */
    public function __construct($id)
    {
        $this->id = $id;
        $this->initList();
    }

    /**
     * Initializes MultipartList.
     *
     * @return array
     * @access protected
     */
    public function initList()
    {
        if (file_exists('/srv/www/vufind2/vufind/local/cache/objects/multipart-'.$this->id)) {
            $cacheF = file('/srv/www/vufind2/vufind/local/cache/objects/multipart-'.$this->id);
            $lastmod = $cacheF[0];
//            if ($lastmod > time()-1800) {
                $this->multipartList = unserialize($cacheF[1]);
//            }
        }
    }

    /**
     * Caches multipart children.
     *
     * @return array
     * @access protected
     */
    public function cacheList()
    {
        if (file_exists('/srv/www/vufind2/vufind/local/cache/objects/multipart-'.$this->id)) {
            $cacheF = file('/srv/www/vufind2/vufind/local/cache/objects/multipart-'.$this->id);
            $lastmod = $cacheF[0];
            if ($lastmod < time()-1800) {
//                $this->saveCache();
            }
        }
        else {
//            $this->saveCache();
        }
        return true;
    }

    /**
     * Caches multipart children.
     *
     * @return array
     * @access protected
     */
    protected function saveCache() {
        $cacheFile = fopen('/srv/www/vufind2/vufind/local/cache/objects/multipart-'.$this->id, 'w');
        fputs($cacheFile, time()."\n");
        fputs($cacheFile, serialize($this->multipartList));
        fclose($cacheFile);
    }

    /**
     * get multipart children from cache.
     *
     * @return array
     * @access protected
     */
    public function getCachedMultipartChildren()
    {
        return $this->multipartList;
    }

    /**
     * Set a multipart list.
     *
     * @return array
     * @access protected
     */
    public function setMultipartList($mpList)
    {
        $this->multipartList = $mpList;
    }

    /**
     * Is a multipart list set for this item?
     *
     * @return boolean
     * @access protected
     */
    public function hasList()
    {
        return isset($this->multipartList) ? true : false;
    }
}
