<?php
/** 
 *  SeekQuarry/Yioop --
 *  Open Source Pure PHP Search Engine, Crawler, and Indexer
 *
 *  Copyright (C) 2011  Priya Gangaraju priya.gangaraju@gmail.com
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
 * @author Priya Gangaraju priya.gangaraju@gmail.com
 * @package seek_quarry
  * @license http://www.gnu.org/licenses/ GPL3
 * @link http://www.seekquarry.com/
 * @copyright 2011
 * @filesource
 */

if(!defined('BASE_DIR')) {echo "BAD REQUEST"; exit();}

/**
 * Base Component Class.
 
 * @author Priya Gangaraju
 * @package seek_quarry
 * @subpackage component
 */
 

require_once BASE_DIR."/lib/phrase_parser.php";

/** Get the crawlHash function */
require_once BASE_DIR."/lib/utility.php";
/** Loads common constants for web crawling */
require_once BASE_DIR."/lib/crawl_constants.php";
require_once BASE_DIR."/models/datasources/".DBMS."_manager.php";
 
class Component
{
    /**
    * list of models 
    **/
    var $models = array();
    var $index_archive;
    var $db;
    
    function __construct() 
    {
        $db_class = ucfirst(DBMS)."Manager";
        $this->db = new $db_class();
        
        require_once BASE_DIR."/models/model.php";

        foreach($this->models as $model) {
            require_once BASE_DIR."/models/".$model."_model.php";
             
            $model_name = ucfirst($model)."Model";
            $model_instance_name = lcfirst($model_name);

            $this->$model_instance_name = new $model_name();
        }
        
    }
}
?>