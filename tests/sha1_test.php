<?php
/**
 *  SeekQuarry/Yioop --
 *  Open Source Pure PHP Search Engine, Crawler, and Indexer
 *
 *  Copyright (C) 2009 - 2014  Chris Pollett chris@pollett.org
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
 * @copyright 2009 - 2014
 * @filesource
 */
require_once ('java_script_test.php');

/**
 *  Used to test the implementation of Sha1 function of javascript.
 *  @author Chris Pollett
 *  @package seek_quarry
 *  @subpackage test
 */
class Sha1Test extends JavaScriptTest
{
     /**
     * Define numner of test cases
     * @var int
     */
    const NUM_TEST_CASES = 5;
    
    function sha1TestCase()
    {
        $time = time();
        $input_value = array();
        $k=0;
        for($i=0;$i<self::NUM_TEST_CASES;$i++)
        {
            $random_string = md5($time.rand(1,1000));
            $sha1 = sha1($random_string);
            $input_value[$k++] = $sha1;
            $input_value[$k++] = $random_string;
        }   
        $js_array = json_encode($input_value);
        ?>
        <html>
        <body>
        <table>
        <div id="sha1Test">
        </div>
        <head>
        <script type="text/javascript" src="../scripts/hashcaptcha.js" ></script>
        <script type="text/javascript">
        var input_array = <?php echo $js_array; ?>;
        var total_test_cases = <?php echo self::NUM_TEST_CASES?>;
        var cell, row, table;
        var result,color;
        var i = 0,counter=0;
        while(i<input_array.length) {
            if(input_array[i++] == generateSha1(input_array[i++])) {
                counter++;
            }
        }
        result = counter+"/"+total_test_cases+" Test Passed";
        if(total_test_cases == counter){
            color='lightgreen';
        } else {
            color='red';
        }
        table = document.createElement('table');
        table.setAttribute('border','1');
        row = table.insertRow(0);
        cell = row.insertCell(0);
        cell.style.fontWeight = 'bold';
        cell.innerHTML = "generateSha1TestCase";
        cell = row.insertCell(1);
        cell.setAttribute("style","background-color: "+color+";");
        cell.innerHTML = result;
        document.getElementById("sha1Test").appendChild(table);
        </script>
        </head>
        </body>
        </html>
<?php
        }
    }
?>
