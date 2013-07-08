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
 * @subpackage element
 * @license http://www.gnu.org/licenses/ GPL3
 * @link http://www.seekquarry.com/
 * @copyright 2009 - 2013
 * @filesource
 */

if(!defined('BASE_DIR')) {echo "BAD REQUEST"; exit();}

/**
 * Element responsible for drawing footer links on search view and static view
 * pages
 *
 * @author Chris Pollett
 * @package seek_quarry
 * @subpackage element
 */

class FooterElement extends Element
{

    /**
     *  Element used to render the login screen for the admin control panel
     *
     *  @param array $data many data from the controller for the footer
     *      (so far none)
     */
    function render($data)
    {
        if(isset($_SERVER["PATH_INFO"])) {
            $path_info = $_SERVER["PATH_INFO"];
        } else {
            $path_info = ".";
        }
    ?>
            <div>
            - <a href="<?php e($path_info); ?>/blog.php"><?php
            e(tl('footer_element_blog')); ?></a> -
            <a href="<?php e($path_info); ?>/privacy.php"><?php
            e(tl('footer_element_privacy')); ?></a> -
            <a href="<?php e($path_info); ?>/bot.php"><?php
            e(tl('footer_element_bot')); ?></a> - <?php if(MOBILE) {
                e('<br /> - ');
            }
            ?>

            <a href="http://www.seekquarry.com/"><?php
            e(tl('footer_element_developed_seek_quarry')); ?></a> -
            </div>
            <div>
            <?php e(tl('footer_element_copyright_yioop')); ?>
             - <a href="http://www.yioop.com/"><?php
            e(tl('footer_element_php_search_engine'));
            ?></a>
            </div>
    <?php
    }
}
?>
