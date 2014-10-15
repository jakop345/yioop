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
 * @subpackage element
 * @license http://www.gnu.org/licenses/ GPL3
 * @link http://www.seekquarry.com/
 * @copyright 2009 - 2014
 * @filesource
 */
if(!defined('BASE_DIR')) {echo "BAD REQUEST"; exit();}
/**
 * Base Element Class.
 * Elements are classes are used to render portions of
 * a web page which might be common to several views
 * like a view there is supposed to minimal php code
 * in an element
 *
 * @author Chris Pollett
 *
 * @package seek_quarry
 * @subpackage element
 */
abstract class Element
{
    /**
     * The View on which this Element is drawn
     * @var object
     */
    var $view;
    /**
     * constructor stores a reference to the view this element will reside on
     *
     * @param object $view   object this element will reside on
     */
    function __construct($view = NULL)
    {
        $this->view = $view;
    }
    /**
     * This method is responsible for actually drawing the view.
     * It should be implemented in subclasses.
     *
     * @param $data - contains all external data from the controller
     * that should be used in drawing the view
     */
    public abstract function render($data);

    /**
     * This method is used to render the help button,
     * given a help point  csrf token and target controller name.
     * 
     * @param  $help_point_id - used to set as help button id
     * @param  $csrf_token_value - csrf token to make api call/open edit link
     * @param  $target_c - target controller to remember the view.
     * @return String button html.
     */
    public function renderHelpButton($help_point_id, 
            $csrf_token_value, $target_c) {
        $is_mobile = MOBILE ? "true" : "false";
        return '<button type="button" 
                    href="" 
                    onclick="javascript:displayHelpForId(this,'
                . $is_mobile . ',\'' . $target_c . '\',\''. CSRF_TOKEN .'\',\'' 
                . $csrf_token_value . '\')" '
                . 'data-pagename="' . $help_point_id . '">?</button>';
    }

}

?>
