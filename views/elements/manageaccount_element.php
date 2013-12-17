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
 *  Element responsible for displaying the user account features
 *  that someone can modify for their own SeekQuarry/Yioop account.
 *  For now, you can only change your password
 *
 * @author Chris Pollett
 * @package seek_quarry
 * @subpackage element
 */

class ManageaccountElement extends Element
{

    /**
     *  Draws a change password form.
     *
     *  @param array $data   anti-CSRF token
     */
    function render($data)
    {?>
        <div class="current-activity">
            <h2><?php e(tl('manageaccount_element_change_email'))?></h2>
            <form id="changePasswordForm" method="post" action='#'>
            <input type="hidden" name="c" value="admin" />
            <input type="hidden" name="<?php e(CSRF_TOKEN); ?>" value="<?php
                e($data[CSRF_TOKEN]); ?>" />
            <input type="hidden" name="a" value="manageAccount" />
            <input type="hidden" name="arg" value="changeemail" />

            <table class="name-table">
            <tr><td><label for="old-email"><?php
                e(tl('manageaccount_element_old_email'))?></label></td>
                <td><input type="txt" id="old-email"
                    name="old_email"  maxlength="80" class="narrow-field"
                    value = "<?php e($data['OLD_EMAIL']);?>"/>
                </td></tr>
            <tr><td><label for="new-email"><?php
                e(tl('manageaccount_element_new_email'))?></label></td>
                <td><input type="text" id="new-email"
                    name="new_email"  maxlength="80" class="narrow-field"/>
                </td></tr>
            <tr><td><label for="retype-email"><?php
                e(tl('manageaccount_element_retype_email'))?></label></td>
                <td><input type="text" id="retype-email"
                    name="re_type_email"  maxlength="80"
                    class="narrow-field" />
                </td></tr>
            <tr><td></td>
                <td class="center"><button
                    class="button-box" type="submit"><?php
                    e(tl('manageaccount_element_save')); ?></button></td></tr>
            </table>
            </form>

            <h2><?php e(tl('manageaccount_element_change_password'))?></h2>
            <form id="changePasswordForm" method="post" action='#'>
            <input type="hidden" name="c" value="admin" />
            <input type="hidden" name="<?php e(CSRF_TOKEN); ?>" value="<?php
                e($data[CSRF_TOKEN]); ?>" />
            <input type="hidden" name="a" value="manageAccount" />
            <input type="hidden" name="arg" value="changepassword" />

            <table class="name-table">
            <tr><td><label for="old-password"><?php
                e(tl('manageaccount_element_old_password'))?></label></td>
                <td><input type="password" id="old-password"
                    name="old_password"  maxlength="80" class="narrow-field"/>
                </td></tr>
            <tr><td><label for="new-password"><?php
                e(tl('manageaccount_element_new_password'))?></label></td>
                <td><input type="password" id="new-password"
                    name="new_password"  maxlength="80" class="narrow-field"/>
                </td></tr>
            <tr><td><label for="retype-password"><?php
                e(tl('manageaccount_element_retype_password'))?></label></td>
                <td><input type="password" id="re-type-password"
                    name="re_type_password"  maxlength="80"
                    class="narrow-field" />
                </td></tr>
            <tr><td></td>
                <td class="center"><button
                    class="button-box" type="submit"><?php
                    e(tl('manageaccount_element_save')); ?></button></td></tr>
            </table>
            </form>
        </div>
        <?php
    }
}
?>
