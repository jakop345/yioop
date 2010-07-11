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
 * Base class for all the SeekQuarry/Yioop engine Unit tests
 *
 * @author Chris Pollett
 * @package seek_quarry
 * @subpackage test
 */
abstract class UnitTest
{
    var $test_case_results;
    var $test_objects;

    const case_name = "TestCase";
    const case_name_len = 8;
    /**
     *
     */
    public function __construct()
    {
    }

    /**
     *
     */
    public function run()
    {

        $test_results = array();
        $methods = get_class_methods(get_class($this));
        foreach ($methods as $method) {
            $this->test_objects = NULL;
            $this->setUp();
            $len = strlen($method);
            
            if(substr_compare($method, self::case_name, $len - self::case_name_len) == 0) {
                $this->test_case_results = array();
                $this->$method();
                $test_results[$method] = $this->test_case_results;
            }
            $this->tearDown();
        }

        return $test_results;
    }

    /**
     *
     */
    public function assertTrue($x, $description = "")
    {
        $sub_case_num = count($this->test_case_results);
        $test = array();
        $test['NAME'] = "Case Test $sub_case_num assertTrue $description";
        if($x) {
            $test['PASS'] = true;
        } else {
            $test['PASS'] = false;
        }
        $this->test_case_results[] = $test;
    }

    /**
     *
     */
    public function assertFalse($x, $description = "")
    {
        $sub_case_num = count($this->test_case_results);
        $test = array();
        $test['NAME'] = "Case Test $sub_case_num assertFalse $description";
        if(!$x) {
            $test['PASS'] = true;
        } else {
            $test['PASS'] = false;
        }
        $this->test_case_results[] = $test;
    }

    /**
     *
     */
    public function assertEqual($x, $y, $description = "")
    {
        $sub_case_num = count($this->test_case_results);
        $test = array();
        $test['NAME'] = "Case Test $sub_case_num assertEqual $description";
        if($x == $y) {
            $test['PASS'] = true;
        } else {
            $test['PASS'] = false;
        }
        $this->test_case_results[] = $test;
    }

    /**
     *
     */
    public function assertNotEqual($x, $y, $description = "")
    {
        $sub_case_num = count($this->test_case_results);
        $test = array();
        $test['NAME'] = "Case Test $sub_case_num assertNotEqual $description";
        if($x != $y) {
            $test['PASS'] = true;
        } else {
            $test['PASS'] = false;
        }
        $this->test_case_results[] = $test;
    }

    /**
     *
     */
    abstract public function setUp();

    /**
     *
     */
    abstract public function tearDown();

}
?>
