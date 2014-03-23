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
 * @subpackage view
 * @license http://www.gnu.org/licenses/ GPL3
 * @link http://www.seekquarry.com/
 * @copyright 2009 - 2014
 * @filesource
 */

if(!defined('BASE_DIR')) {echo "BAD REQUEST"; exit();}

if(php_sapi_name() != 'cli') {
    $locale_version = tl('view_locale_version4');
}

//base class for Element's needed for this View
require_once BASE_DIR."/views/elements/element.php";

//base class for  Helper's needed for this View
require_once BASE_DIR."/views/helpers/helper.php";

//base class for Layout on which the View will be drawn
require_once BASE_DIR."/views/layouts/layout.php";
/**
 * Base View Class. A View is used to display
 * the output of controller activity
 *
 * @author Chris Pollett
 * @package seek_quarry
 * @subpackage view
 */

abstract class View
{
    /** The name of the type of layout object that the view is drawn on
     *  @var string
     */
    var $layout = "";

    /** The reference to the layout object that the view is drawn on
     *  @var object
     */
    var $layout_object;

    /**
     *  The constructor reads in any Element and Helper subclasses which are
     *  needed to draw the view. It also reads in the Layout subclass on which
     *  the View will be drawn.
     *
     */
    function __construct()
    {
        $layout_name = ucfirst($this->layout)."Layout";
        if($this->layout != "") {
            if(file_exists(
                APP_DIR."/views/layouts/".$this->layout."_layout.php")) {
                require_once
                    APP_DIR."/views/layouts/".$this->layout."_layout.php";
            } else {
                require_once
                    BASE_DIR."/views/layouts/".$this->layout."_layout.php";
            }
        }

        $this->layout_object = new $layout_name($this);
    }

    /**
     * Dynamic loader for Element objects which might live on the current
     * View
     *
     * @param string $element name of Element to return
     */
    function element($element)
    {
        if(!isset($this->element_instances[$element])) {
            if(file_exists(APP_DIR."/views/elements/".$element."_element.php")){
                require_once APP_DIR."/views/elements/".$element."_element.php";
            } else {
                require_once BASE_DIR.
                    "/views/elements/".$element."_element.php";
            }
            $element_name = ucfirst($element)."Element";
            $this->element_instances[$element] = new $element_name($this);
        }
        return $this->element_instances[$element];
    }

    /**
     * Dynamic loader for Helper objects which might live on the current
     * View
     *
     * @param $string element name of Helper to return
     */
    function helper($helper)
    {
        if(!isset($this->helper_instances[$helper])) {
            if(file_exists(APP_DIR."/views/helpers/".$helper."_helper.php")){
                require_once APP_DIR."/views/helpers/".$helper."_helper.php";
            } else {
                require_once BASE_DIR."/views/helpers/".$helper."_helper.php";
            }
            $helper_name = ucfirst($helper)."Helper";
            $this->helper_instances[$helper] = new $helper_name();
        }
        return $this->helper_instances[$helper];
    }

    /**
     * This method is responsible for drawing both the layout and the view. It
     * should not be modified to change the display of then view. Instead,
     * implement renderView.
     *
     * @param array $data  an array of values set up by a controller to be used
     *      in rendering the view
     */
    function render($data) {
        $this->layout_object->render($data);
    }

    /**
     * This abstract method is implemented in sub classes with code which
     * actually draws the view. The current layouts render method calls this
     * function.
     *
     *  @param array $data  an array of values set up by a controller to be used
     *      in rendering the view
     */
    abstract function renderView($data);
}

?>