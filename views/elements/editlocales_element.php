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
 * Element responsible for displaying the form where users can input string
 * translations for a given locale
 *
 * @author Chris Pollett
 *
 * @package seek_quarry
 * @subpackage element
 */
class EditlocalesElement extends Element
{
    /**
     * Draws a form with strings to translate and a text field for the
     * translation into
     * the given locale. Strings with no translations yet appear in red
     *
     * @param array $data  contains msgid and already translated msg_string info
     */
    function render($data)
    {
    ?>
        <div class="current-activity">
        <div class="<?php e($data['leftorright']);?>">
        <a href="?c=admin&amp;a=manageLocales&amp;<?php
            e(CSRF_TOKEN."=".$data[CSRF_TOKEN]) ?>"
        ><?php e(tl('editlocales_element_back_to_manage'))?></a>
        </div>
        <h2><?php e(tl('editlocales_element_edit_locale',
            $data['CURRENT_LOCALE_NAME']))?></h2>
        <form id="editLocaleForm" method="post" action='?'>
        <input type="hidden" name="c" value="admin" />
        <input type="hidden" name="<?php e(CSRF_TOKEN); ?>" value="<?php
            e($data[CSRF_TOKEN]); ?>" />
        <input type="hidden" name="a" value="manageLocales" />
        <input type="hidden" name="arg" value="editstrings" />
        <input type="hidden" name="selectlocale" value="<?php
            e($data['CURRENT_LOCALE_TAG']); ?>" />
        <div class="slight-pad">
        <label for="show-strings"><b><?php e(tl('editlocales_element_show'));
        ?></b></label><?php $this->view->helper("options")->render(
            "show-strings","show",  $data['show_strings'],
            $data['show'], true); ?>
        <label for="string-filter"><b><?php e(tl('editlocales_element_filter'));
        ?></b></label><input type="text" id="string-filter" name="filter"
            value="<?php e($data['filter']); ?>" maxlength="20"
            onchange="this.form.submit()"
            class="narrow-field" /> <button class="button-box"
            type="submit"><?php
                e(tl('editlocales_element_go')); ?></button>
        </div>
        <?php
        if($data['STRINGS'] == array()) {
            e("<h3 class='red'>". tl('editlocales_element_no_matching').
                "</h3>");
        }
        ?>
        <table class="translate-table">
        <?php
        $mobile_tr = (MOBILE) ? "</tr><tr>" : "";
        foreach($data['STRINGS'] as $msg_id => $msg_string) {
            $out_id = $msg_id;
            if(MOBILE && strlen($out_id) > 33) {
                $out_id = wordwrap($out_id, 30, "<br />\n", true);
            }
            if(strlen($msg_string) > 0) {
                e("<tr><td><label for='$msg_id'>$out_id</label>".
                    "</td>$mobile_tr<td><input type='text' title='".
                    $data['DEFAULT_STRINGS'][$msg_id].
                    "' id='$msg_id' name='STRINGS[$msg_id]' ".
                    "value='$msg_string' /></td></tr>");
            } else {
                e("<tr><td><label for='$msg_id'>$out_id</label></td>".
                    "$mobile_tr<td><input class='highlight' type='text' ".
                    "title='".$data['DEFAULT_STRINGS'][$msg_id].
                    "' id='$msg_id' name='STRINGS[$msg_id]' ".
                    "value='$msg_string' /></td></tr>");
            }
        }
        ?>
        </table>
        <div class="center slight-pad"><button class="button-box"
            type="submit"><?php
                e(tl('editlocales_element_submit')); ?></button></div>
        </form>
        </div>
    <?php
    }
}
?>
