<?php
/** 
 *  SeekQuarry/Yioop --
 *  Open Source Pure PHP Search Engine, Crawler, and Indexer
 *
 *  Copyright (C) 2009, 2010  Chris Pollett chris@pollett.org
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
 * @subpackage test
 * @license http://www.gnu.org/licenses/ GPL3
 * @link http://www.seekquarry.com/
 * @copyright 2009, 2010
 * @filesource
 */

if(!defined('BASE_DIR')) {echo "BAD REQUEST"; exit();}

/**
 *  Load the string_array library we'll be testing
 */
require_once BASE_DIR."/lib/bst_array.php"; 

/**
 *  Used to test that the BSTArray class properly stores/retrieves values,
 *
 *  @author Chris Pollett
 *  @package seek_quarry
 *  @subpackage test
 */
class BSTArrayTest extends UnitTest
{
    /**
     * We'll use two different tables one more representative of how the table 
     * is going to be used by the web_queue_bundle, the other small enough that 
     * we can manually figure out what the result should be
     */
    public function setUp()
    {
        $this->test_objects['BST'] = new BSTArray(1, 1, "strcmp");
    }

    /**
     */
    public function tearDown()
    {
        unset($this->test_objects['BST']);
    }

    /**
     * Check if can put objects into BST array and retrieve them
     */
    public function insertTestCase()
    {
        $this->test_objects['BST']->insertUpdate(chr(65), chr(66));
        $flag = $this->test_objects['BST']->contains(chr(65), $offset, $parent);
        $this->assertTrue($flag, "BST contains what was just inserted");
        $this->test_objects['BST']->insertUpdate(chr(67), chr(68));
        $flag = $this->test_objects['BST']->contains(chr(67), $offset, $parent);
        $this->assertTrue($flag, "BST contains second insert");
        $this->test_objects['BST']->insertUpdate(chr(66), chr(69));
        $flag = $this->test_objects['BST']->contains(chr(66), $offset, $parent);
        $this->assertTrue($flag, "BST contains third insert");
        $this->test_objects['BST']->insertUpdate(chr(69), chr(69));
        $flag = $this->test_objects['BST']->contains(chr(69), $offset, $parent);
        $this->assertTrue($flag, "BST contains fourth insert");
    }

    /**
     * Check if can modify objects in BST array
     */
    public function updateTestCase()
    {
        $this->test_objects['BST']->insertUpdate(chr(65), chr(66));
        $this->test_objects['BST']->insertUpdate(chr(67), chr(68));
        $this->test_objects['BST']->insertUpdate(chr(66), chr(69));
        $this->test_objects['BST']->insertUpdate(chr(69), chr(69));
        $this->test_objects['BST']->insertUpdate(chr(66), chr(66));
        $this->test_objects['BST']->contains(chr(66), $offset, $parent);
        list($key, $value, $left, $right) = $this->test_objects['BST']->
            readEntry($offset);
        $this->assertEqual($value, chr(66), "BST contains fourth insert");
    }

}
?>
