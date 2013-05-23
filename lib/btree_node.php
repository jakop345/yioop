<?php

/**
 *  SeekQuarry/Yioop --
 *  Open Source Pure PHP Search Engine, Crawler, and Indexer
 *
 *  Copyright (C) 2009 - 2013  Chris Pollett chris@pollett.org
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
 * @subpackage bin
 * @license http://www.gnu.org/licenses/ GPL3
 * @link http://www.seekquarry.com/
 * @copyright 2009 - 2013
 * @filesource
 */

/**
 * This class implements B-Tree nodes used in the B-Tree implementation in 
 * btree.php
 * 
 * @author Akshat Kukreti
 * @package seek_quarry
 */

class Node
{
    /**
     * Storage for id of a B-Tree node
     * @var int
     */
    var $id;

    /**
     * Flag for checking if node is a leaf node or internal node
     * @var boolean
     */
    var $is_leaf;

    /**
     * Storage for keeping track of node ids
     * @var int
     */
    var $count;

    /**
     * Storage for key-value pairs in a B-Tree node
     * @var array
     */
    var $keys;

    /**
     * Storage for links to child nodes in a B-Tree node
     * @var array
     */
    var $links;

    /**
     * Creates and initializes an empty leaf node with id -1
     * @var int
     */
    function __construct()
    {
        $this->id = -1;
        $this->is_leaf = true;
        $this->count = 0;
        $this->keys = null;
        $this->links = null;
    }
}