<?php
/**
 * SeekQuarry/Yioop --
 * Open Source Pure PHP Search Engine, Crawler, and Indexer
 *
 * Copyright (C) 2009 - 2014 Chris Pollett chris@pollett.org
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
 * @author Sreenidhi Muralidharan
 * @package seek_quarry
 * @subpackage element
 * @license http://www.gnu.org/licenses/ GPL3
 * @link http://www.seekquarry.com/
 * @copyright 2009 - 2014
 * @filesource
 */
if(!defined('BASE_DIR')) {echo "BAD REQUEST"; exit();}
/**
 * Element used to handle configurations of Yioop related to authentication,
 * captchas, and recovery of missing passwords
 *
 * @author Sreenidhi Muralidharan/Chris Pollett
 * @package seek_quarry
 * @subpackage element
 */
class SecurityElement extends Element
{
    /**
     * Method that draws forms to to select either among a text or a
     * graphical captcha
     *
     * @param array $data holds data on the profile elements which have been
     *     filled in as well as data about which form fields to display
     */
    function render($data)
    {
        $localize_url = "?".CSRF_TOKEN."=".$data[CSRF_TOKEN].
            "&amp;c=admin&amp;a=manageLocales".
            "&amp;arg=editstrings&amp;selectlocale=".$data['LOCALE_TAG'];
        ?>
        <div class = "current-activity">
        <h2><?php e(tl('security_element_auth_captcha'));?></h2>
        <form class="top-margin" method="post" action="#">
            <input type="hidden" name="c" value="admin" />
            <input type="hidden" name="a" value="security"/>
            <input type="hidden" name="<?php e(CSRF_TOKEN); ?>" value="<?php
                e($data[CSRF_TOKEN]); ?>" />
            <input type="hidden" name="arg" value="updatetypes"/>
            <div class="top-margin">
            <fieldset>
                <legend><label
                for="authentication-mode"><b><?php
                e(tl('security_element_authentication_type'));
                ?>
                </label></b></legend>
                    <?php $this->view->helper("options")->render(
                        "authentication-mode", "AUTHENTICATION_MODE",
                         $data['AUTHENTICATION_MODES'],
                         $data['AUTHENTICATION_MODE']);
                    if(isset($data['ZKP_UNAVAILABLE']) && 
                        $data['ZKP_UNAVAILABLE']) {
                        e('<div class="red">'.
                            tl('security_element_zero_unavailable')."</div>");
                    }
                ?>
            </fieldset>
            </div>
            <div class="top-margin">
            <fieldset>
                <legend><label
                for="captcha-mode"><b><?php
                e(tl('security_element_captcha_type'));
                ?></b>
                </label></legend>
                <?php
                    $this->view->helper("options")->render("captcha-mode",
                        "CAPTCHA_MODE", $data['CAPTCHA_MODES'],
                        $data['CAPTCHA_MODE']);
                ?>
            </fieldset>
            </div>
            <div class="top-margin center"><button 
                class="button-box" type="submit"><?php
                e(tl('security_element_save')); ?></button>
            </div>
        </form>
        <h2><?php
            e(tl('security_element_captcha_recovery_questions'));
        ?></h2>
        <?php
        if($data['CAN_LOCALIZE']) { ?>
            <div class="top-margin">[<a href="<?php 
                e($localize_url.'&amp;filter=register_view_recovery');
                    ?>" ><?php e(tl('security_element_edit_recovery')); ?></a>]
            </div>
            <div class="top-margin">[<a href="<?php 
                e($localize_url.'&amp;filter=register_view_question');
                    ?>" ><?php e(tl('security_element_edit_captcha')); ?></a>]
            </div>
            <?php
        } else { ?>
            <div class="top-margin">[<b class="gray"><?php
                e(tl('security_element_edit_recovery')); ?></b>]
            </div>
            <div class="top-margin">[<b class="gray"><?php
                e(tl('security_element_edit_captcha')); ?></b>]
            </div>
            <?php
        }
        ?>
        </div>
        <?php
    }
}

?>
