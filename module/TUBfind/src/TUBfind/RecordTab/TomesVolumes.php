<?php
/**
 * User comments tab
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
 * @package  RecordTabs
 * @author   Oliver Goldschmidt <o.goldschmidt@tuhh.de>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org/wiki/vufind2:record_tabs Wiki
 */
namespace TUBfind\RecordTab;
use TUBfind\Content\MultipartList;

/**
 * User comments tab
 *
 * @category VuFind2
 * @package  RecordTabs
 * @author   Oliver Goldschmidt <o.goldschmidt@tuhh.de>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org/wiki/vufind2:record_tabs Wiki
 */
class TomesVolumes extends \VuFind\RecordTab\AbstractBase
{
    /**
     * Is this tab active?
     *
     * @return bool
     */
    public function isActive()
    {
        $multipart = $this->getRecordDriver()->tryMethod('isMultipartChildren');
        if (empty($multipart)) {
            return false;
        }
        $mp = $this->getRecordDriver()->isMultipartChildren();
        return $mp;
    }

    /**
     * Get the on-screen description for this tab.
     *
     * @return string
     */
    public function getDescription()
    {
        return 'Tomes/Volumes';
    }

    /**
     * Get the content of this tab.
     *
     * @return array
     */
    public function getContent()
    {
        $returnObjects = array();
        $vols = array();
        $mpList = new MultipartList($this->getRecordDriver()->getUniqueId());
        if ($mpList->hasList()) {
            $retval = $mpList->getCachedMultipartChildren();
            // $retval has now the correct order, now set the objects into the same order
            $returnObjects = array();
            $recordLoader = $this->sm;
            foreach ($retval as $object) {
                $returnObjects[] = $recordLoader->load($object['id']);
//                $returnObjects[] = $object['id'];
            }
            $vols['vols'] = $returnObjects;
            $vols['volscount'] = count($returnObjects);
        }
        else {
            $multipart = $this->getRecordDriver()->tryMethod('getMultipartChildren');
            if (empty($multipart)) {
                return null;
            }
            $vols['vols'] = $this->getRecordDriver()->getMultipartChildren();
            $vols['volscount'] = $this->getRecordDriver()->getVolsCount();
        }
        return $vols;

    }

    /**
     * Get the content of this tab page by page.
     *
     * @return array
     */
    public function getPagedContent($start = 0, $count = 5)
    {
        $returnObjects = array();
        $vols = array();
        $mpList = new MultipartList($this->getRecordDriver()->getUniqueId());
        if ($mpList->hasList()) {
            $retval = $mpList->getCachedMultipartChildren();
            // $retval has now the correct order, now set the objects into the same order
            $returnObjects = array();
            $recordLoader = $this->sm;
            for ($c = $start; $c < ($start+$count); $c++) {
                $object = $retval[$c];
                $returnObjects[] = $recordLoader->load($object['id']);
//                $returnObjects[] = $object['id'];
            }
            $vols['vols'] = $returnObjects;
            $vols['volscount'] = count($retval);
        }
        else {
            $multipart = $this->getRecordDriver()->tryMethod('getMultipartChildren');
            if (empty($multipart)) {
                return null;
            }
            $vols['vols'] = $this->getRecordDriver()->getMultipartChildren();
            $vols['volscount'] = $this->getRecordDriver()->getVolsCount();
        }
        return $vols;

    }

    /**
     * Get the content of this tab page by page.
     *
     * @return array
     */
    public function getMultipartList()
    {
        $mpList = new MultipartList($this->getRecordDriver()->getUniqueId());
        if ($mpList->hasList()) {
            $retval = $mpList->getCachedMultipartChildren();
            // $retval has now the correct order, now set the objects into the same order
/*            $returnObjects = array();
            $recordLoader = $this->getRecordLoader();
            for ($c = $_REQUEST['start']; $c < ($_REQUEST['start']+$_REQUEST['length']); $c++) {
                $object = $retval[$c];
                $returnObjects[] = $recordLoader->load($object['id']);
//                $returnObjects[] = $object['id'];
            }
*/        }
        return (isset($retval)) ? $retval : false;
    }

}
