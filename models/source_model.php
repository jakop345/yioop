<?php
/** 
 *  SeekQuarry/Yioop --
 *  Open Source Pure PHP Search Engine, Crawler, and Indexer
 *
 *  Copyright (C) 2009 - 2012  Chris Pollett chris@pollett.org
 *
 *  LICENSE:
 *
 *  This program is free software: you can redistribute it and/or modify
 *  it under the terms of the GNU General Public License as published by
 *  the Free Software Foundation, either version 3 of the License, or
 *  (at your option) any later version.
 *
 *  This program is distributed in the hope that it will be useful,
 *  but WITHOUT ANY WARRANTY; without even the implied warranty of
 *  MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 *  GNU General Public License for more details.
 *
 *  You should have received a copy of the GNU General Public License
 *  along with this program.  If not, see <http://www.gnu.org/licenses/>.
 *
 *  END LICENSE
 *
 * @author Chris Pollett chris@pollett.org
 * @package seek_quarry
 * @subpackage model
 * @license http://www.gnu.org/licenses/ GPL3
 * @link http://www.seekquarry.com/
 * @copyright 2009 - 2012
 * @filesource
 */

if(!defined('BASE_DIR')) {echo "BAD REQUEST"; exit();}

/** Loads the base class */
require_once BASE_DIR."/models/model.php";

/** Used for the crawlHash function */
require_once BASE_DIR."/lib/utility.php"; 

/**
 * This class is used 
 *
 * @author Chris Pollett
 * @package seek_quarry
 * @subpackage model
 */
class SourceModel extends Model 
{
    /**
     * Just calls the parent class constructor
     */
    function __construct() 
    {
        parent::__construct();
    }


    /**
     *  
     *  @param 
     */
    function getMediaSources($sourcetype = "")
    {
        $sources = array();
        $this->db->selectDB(DB_NAME);
        $sql = "SELECT * FROM MEDIA_SOURCE";
        if($sourcetype !="") {
            $sql .= " WHERE TYPE='$sourcetype'";
        }
        $i = 0;
        $result = $this->db->execute($sql);
        while($sources[$i] = $this->db->fetchArray($result)) {
            $i++;
        }
        unset($sources[$i]); //last one will be null

        return $sources;

    }

    /**
     *
     * @return
     */
    function addMediaSource($name, $source_type, $source_url, $thumb_url)
    {
        $this->db->selectDB(DB_NAME);

        $sql = "INSERT INTO MEDIA_SOURCE VALUES ('".time()."','".
            $this->db->escapeString($name)."','".
            $this->db->escapeString($source_type)."','".
            $this->db->escapeString($source_url)."','".
            $this->db->escapeString($thumb_url)."')";

        $this->db->execute($sql);
    }

    /**
     *
     *
     */
    function deleteMediaSource($timestamp)
    {
        $this->db->selectDB(DB_NAME);

        $sql = "DELETE FROM MEDIA_SOURCE WHERE TIMESTAMP='$timestamp'";

        $this->db->execute($sql);
    }
}

 ?>
