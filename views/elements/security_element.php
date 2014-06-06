<?php
/**
 *  SeekQuarry/Yioop --
 *  Open Source Pure PHP Search Engine, Crawler, and Indexer
 *
 *  Copyright (C) 2009 - 2014 Chris Pollett chris@pollett.org
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
     *      filled in as well as data about which form fields to display
     */
    function render($data)
    {
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
                <div id="text-captcha-container"
                    class="text-captcha-container" >
                    <form id="text-captcha-container-add"
                        class="text-captcha-container-add"
                        method="post" action="">
                        <ul><li><label for="captcha-type"><?php
                            e(tl('security_element_captcha_type'));
                            ?></label>
                            <?php $this->view->helper("options")->render(
                                "captcha-type", "CAPTCHA_TYPE",
                                $data['CAPTCHA_TYPE'],
                                $data['CAPTCHA_TYPE']);?>
                            </li>
                            <li><label for="captcha-possibilities">
                            <?php
                             e(tl('security_element_captcha_possible'));
                            ?>
                            </label>
                            <?php $this->view->helper("options")->render(
                                "captcha-possibilities",
                                "CAPTCHA_POSSIBILITIES",
                                $data['CAPTCHA_POSSIBILITIES'],
                                $data['CAPTCHA_POSSIBILITIES']);?>
                            </li>
                            <li><label for="captcha-question-input-label">
                            <?php
                             e(tl('security_element_captcha_question'));
                            ?>
                            </label>
                                <input type="text"
                                    id="captcha-question-input"
                                    name="CAPTCHA_QUESTION_INPUT">
                            </li>
                            <li><label for="captcha-choices-input-label">
                            <?php
                              e(tl('security_element_captcha_choices'));
                            ?>
                            </label>
                                <input type="text"
                                    id="captcha-choices-input"
                                    name="CAPTCHA_CHOICES_INPUT"></li>
                            <li><input type="hidden" name="arg"
                                    value="addtextcaptcha" />
                                 <input type="submit"
                                   id="text-captcha-add-to-database"
                                   name="text_captcha_add_to_database"
                                   value="<?php
                                  e(tl('security_element_captcha_add'));
                                  ?>"/>
                             </li>
                        </ul>
                        <input type="hidden" name="CAPTCHA_MODE"
                                    value="text_captcha"/></form>
                        <form name="updateTextCaptchaForm" method="post"
                                action ="">
                             <h2>
                             <?php
                            e(tl('security_element_current_question'));
                            ?></h2>
                            <select multiple="multiple"
                                class="text-captcha-input"
                                name="text_captcha_delete_questions[]">
                                  <?php
                                  if(isset($data['QUESTIONS'])) {
                                    foreach($data['QUESTIONS'] as
                                        $question) {
                                        e('<option value
                                            = "'.$question[ 'TRANSLATION_ID'].
                                            '">'. $question['QUESTION_TEXT'].
                                        '</option>');
                                    }
                                }
                                ?>
                            </select>
                            <input type="hidden" name="arg"
                                value="updatetextcaptcha" />
                             <input type="submit" value="<?php
                               e(tl('security_element_captcha_delete'));
                               ?>"
                                name="actiondelete"/>
                            <input type="submit" value="<?php
                                e(tl('security_element_captcha_edit'));
                                ?>"
                               name="actionedit"/>
                       </form>
                       <?php
                       if(isset($data['EDIT_CAPTCHA_MAPS'])) {
                        $question_choice_rowid_map =
                            $data['EDIT_CAPTCHA_MAPS']
                            ['question_choice_rowid_map'];
                        $rowid_translation_map = $data['EDIT_CAPTCHA_MAPS']
                            ['rowid_translation_map'];
                        e('<form method="post">');
                        foreach($question_choice_rowid_map as
                            $question_rowid => $choices_rowid) {
                            e('<div>');
                            e('<div>');
                            e('Question <input name="updateField['.
                                $question_rowid.']" value="'.
                                $rowid_translation_map[$question_rowid].'"
                                class="question-update-input">');
                            e('</div>');
                            e('<div>');
                            e('Choices <input name="updateField['.
                                $choices_rowid.']" value="'.
                                $rowid_translation_map[$choices_rowid].'"
                                class="choices-update-input">');
                            e('</div>');
                            e('</div>');
                        }
                        e('<input type="hidden" name="arg"
                            value="savetextcaptcha" />');
                        e('<input type="submit" value ="Save" />');
                        e('</form>');
                    }
                    ?>
                </div>
        </div><!-- End of div: captcha-settings-container-->
        </div><!-- End of div: current-activity -->
    <?php
    }
}

?>
