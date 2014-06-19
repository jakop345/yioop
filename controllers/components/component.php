<?php
/**
 * SeekQuarry/Yioop --
 * Open Source Pure PHP Search Engine, Crawler, and Indexer
 *
 * Copyright (C) 2009 - 2014  Chris Pollett chris@pollett.org
 *
 * LICENSE:
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 *
 * END LICENSE
 *
 * @author Chris Pollett chris@pollett.org
 * @package seek_quarry
 * @subpackage component
 * @license http://www.gnu.org/licenses/ GPL3
 * @link http://www.seekquarry.com/
 * @copyright 2009 - 2014
 * @filesource
 */

if(!defined('BASE_DIR')) {echo "BAD REQUEST"; exit();}

/**
 * Base component class for all components on
 * the SeekQuarry site. A component consists of a collection of
 * activities and their auxiliary methods that can be used by a controller
 *
 * @author Chris Pollett
 * @package seek_quarry
 * @subpackage component
 */
class Component
{
    /**
     * Reference to the controller this component lives on
     *
     * @var object
     */
    var $parent = NULL;

    /**
     * Sets up this component by storing in its parent field a reference to
     *  controller this component lives on
     *
     * @param object $parent_controller reference to the controller this 
     *      component lives on
     */
    function __construct($parent_controller)
    {
        $this->parent = $parent_controller;
    }
}
