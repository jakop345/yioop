<?php
/**
 * SeekQuarry/Yioop --
 * Open Source Pure PHP Search Engine, Crawler, and Indexer
 * Copyright (C) 2009 - 2014  Chris Pollett chris@pollett.org
 * LICENSE:
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 * You should have received a copy of the GNU General Public License
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 * END LICENSE
 *
 * @author Eswara Rajesh Pinapala epinapala@live.com
 * @package seek_quarry
 * @subpackage test
 * @license http://www.gnu.org/licenses/ GPL3
 * @link http://www.seekquarry.com/
 * @copyright 2009 - 2014
 * @filesource
 */
/**
 * Used to run PhantomJs scripts from the command line.
 *
 * @author Eswara Rajesh Pinapala
 * @package seek_quarry
 * @subpackage test
 */
class YioopPhantomRunner
{
    private $phantomjs_bin_path = 'phantomjs';
    public function __construct($bin_path = null)
    {
        if($bin_path !== null) {
            $this->phantomjs_bin_path = $bin_path;
        }
        $version = $this->execute("-v");
        if(!$version){
            throw new Exception("PhantomJS binary not found.");
        }
    }
    public function execute($script, $decode_json = false)
    {
        $shell_result = shell_exec(
            escapeshellcmd("{$this->phantomjs_bin_path} " . implode(' ',
                    func_get_args())));
        if($shell_result === null) {
            return false;
        }
        if($decode_json) {
            if(substr($shell_result, 0, 1) !== '{') {
                //return if the result is not a JSON.
                return $shell_result;
            } else {
                //If the result is a JSON, decode JSON into a PHP array.
                $json = json_decode($shell_result, true);
                if($json === null) {
                    return false;
                }
                return $json;
            }
        } else {
            return $shell_result;
        }

    }
}