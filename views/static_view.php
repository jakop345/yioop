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
 * @subpackage view
 * @license http://www.gnu.org/licenses/ GPL3
 * @link http://www.seekquarry.com/
 * @copyright 2009 - 2014
 * @filesource
 */
if(!defined('BASE_DIR')) {echo "BAD REQUEST"; exit();}
/**
 * This View is responsible for drawing forward-facing wiki pages in
 * a more static cleaned up way
 *
 * @author Chris Pollett
 * @package seek_quarry
 * @subpackage view
 */
class StaticView extends View
{
    /** This view is drawn on a web layout
     * @var string
     */
    var $layout = "web";
    /**
     * Draws wiki page in a more static fashion.
     *
     * @param array $data  contains the static page contents
     * the view
     */
    function renderView($data)
    {
        $logo = LOGO;
        $logged_in = (isset($data['ADMIN']) && $data['ADMIN']);
        $append_url = ($logged_in && isset($data[CSRF_TOKEN]))
                ? CSRF_TOKEN. "=".$data[CSRF_TOKEN] : "";
        if(MOBILE) {
            $logo = M_LOGO;
        }
        if(isset($_SERVER["PATH_INFO"])) {
            $path_info = $_SERVER["PATH_INFO"];
        } else {
            $path_info = ".";
        }
        ?>
        <div class="non-search center">
        <h1 class="logo"><a href="<?php e($path_info."/?".$append_url);?>"><img
            src="<?php e($path_info."/".$logo); ?>"
            alt="<?php e(tl('static_view_title')); ?>" /></a><span><?php
            e($data['subtitle']);?></span></h1>
        </div>
        <div class="content">
            <?php if(isset($data["value"])) {
                    $page = sprintf($this->page_objects[$data['page']],
                        $data["value"]);
                    e($page);
                } else {
                    e($this->page_objects[$data['page']]);
                }?>
        </div>
        <div class="landing-footer">
            <?php  $this->element("footer")->render($data);?>
        </div>
        <?php
    }
}
?>
