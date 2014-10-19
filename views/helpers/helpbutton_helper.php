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
 * @author Eswara Rajesh Pinapala epinapala@live.com
 * @package seek_quarry
 * @subpackage helper
 * @license http://www.gnu.org/licenses/ GPL3
 * @link http://www.seekquarry.com/
 * @copyright 2009 - 2014
 * @filesource
 */
if(!defined('BASE_DIR')) {echo "BAD REQUEST"; exit();}
/**
 * Load base helper class if needed
 */
require_once BASE_DIR."/views/helpers/helper.php";
/**
 * This is a helper class is used to
 * draw help button for context sensitive
 * help.
 *
 * @author Eswara Rajesh Pinapala
 * @package seek_quarry
 * @subpackage helper
 */
class HelpbuttonHelper extends Helper
{
    /**
     * The constructor at this point initializes the
     * all the required code for Wiki Help initialization.
     */
    function __construct()
    {
        $this->localizationdata = "{".
            'wiki_view_edit :"'.tl('wiki_view_edit').'",'.
            'wiki_view_read :"'.tl('wiki_view_read').'"'.
            "}";
        parent::__construct();
    }

    /**
     * This method is used to render the help button,
     * given a help point  csrf token and target controller name.
     *
     * @param  $help_point_id - used to set as help button id
     * @param  $csrf_token_value - csrf token to make api call/open edit link
     * @param  $target_c - target controller to remember the view.
     * @return String button html.
     */
    public function render($help_point_id,
                           $csrf_token_value, $target_c) {
        $is_mobile = MOBILE ? "true" : "false";
        $wiki_group_id = "7";
        $api_controller = "api";
        $api_wiki_action ="wiki";
        $api_wiki_mode = "read";

        return '<button type="button"
                    data-tl=\''.$this->localizationdata.'\'
                    onclick="javascript:displayHelpForId(this,'
        . $is_mobile . ',\'' . $target_c . '\',\''. CSRF_TOKEN .'\',\''
        . $csrf_token_value ."','$wiki_group_id','$api_controller',"
        . "'$api_wiki_action','$api_wiki_mode" . '\')" '
        . 'data-pagename="' . $help_point_id . '">?</button>';
    }
}
?>
