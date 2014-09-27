<?php
/*
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
 *  @author Eswara Rajesh Pinapala epinapala@live.com
 *  @package seek_quarry
 *  @subpackage element
 *  @license http://www.gnu.org/licenses/ GPL3
 *  @link http://www.seekquarry.com/
 *  @copyright 2009 - 2014
 *  @filesource
 */
if (!defined('BASE_DIR')) {
    echo "BAD REQUEST";
    exit();
}

/**
 * This element is used to display the list of available activities
 * in the AdminView
 *
 * @author Eswara Rajesh Pinapala
 *
 * @package seek_quarry
 * @subpackage element
 */
class HelpElement extends Element {

    /**
     * Displays a list of admin activities
     *
     * @param array $data  available activities and CSRF token
     */
    function render($data) {
        ?>
        <?php
        if (isset($data['ACTIVITIES'])) {
            if (MOBILE) { ?>
                
                <div id="mobile-help">
                    <!-- a href="javascript:;" onclick="toggleHelp('help-frame', true);">Help</a -->
                  <div id="help-frame" class="frame help-pane">
                    <h2>Help Content</h2>
                    <div id="help-frame-body" class="wordwrap">
                       
                    </div>
                  </div>
                </div>
            <?php } else {
                ?>
                
                <div id="help">
                    <!-- a href="javascript:;" onclick="toggleHelp('help-frame',false);">Help</a -->
                  <div id="help-frame" class="frame help-pane">
                    <h2>Help Content</h2>
                    <div id="help-frame-body" class="wordwrap">
                       
                    </div>
                  </div>
                </div>
                <?php
            }
        }
    }

}
?>

