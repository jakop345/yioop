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
 * @author Sreenidhi Muralidharan sreenidhimuralidharan@gmail.com
 * @package seek_quarry
 * @subpackage element
 * @license http://www.gnu.org/licenses/ GPL3
 * @link http://www.seekquarry.com/
 * @copyright 2009 - 2014
 * @filesource
 */


if(!defined('BASE_DIR')) {echo "BAD REQUEST"; exit();}

/**
 * Element used to draw forms to select either among a text or a 
 * graphical captcha
 *
 * @author Chris Pollett
 * @package seek_quarry
 * @subpackage element
 */
 require_once BASE_DIR."/models/model.php";
 require_once BASE_DIR."/models/textcaptchasettings_model.php";
  
 
class CaptchasettingsElement extends Element
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
    <div>
        <div id ="captcha-settings-container">
            <form class="top-margin" method="post" action="">
                <label for="captcha-settings">
                    <?php e(tl('captchasettings_element_captcha_setting'));?>
                </label>
                <?php 
                    $selected_value = $data["CAPTCHA_MODE"];
                    $this->view->helper("options")->render("captcha-settings", 
                        "CAPTCHA_MODE", $data['CAPTCHA_MODES'], 
                        $selected_value, true);
                ?>
                <input type="hidden" name="arg" value="setcaptchamode"/>
            </form>
            <?php
                if ($selected_value == TEXT_CAPTCHA) {
            ?>
                <div id="text-captcha-container">
                    <h4>Add Captcha/Recovery Question:</h4>
                    <form id="text-captcha-container-add" 
                        class="text-captcha-container-add" 
                        method="post" action="">
                        <ul><li><label id="captcha-type" 
                            for="captcha-type"><?php 
                            e(tl('captchasettings_element_captcha_type')); 
                            ?></label>
                            <?php $this->view->helper("options")->render(
                                "captcha-type", "CAPTCHA_TYPE", 
                                $data['CAPTCHA_TYPE'], 
                                $data['CAPTCHA_TYPE']);?>
                            </li>
                            <li><label id="captcha-possibilities" 
                            for="captcha-possibilities">
                            <?php 
                            e(tl(
                            'captchasettings_element_captcha_possibilities')); 
                            ?></label>
                            <?php $this->view->helper("options")->render(
                                "captcha-possibilities", "CAPTCHA_POSSIBILITIES", 
                                $data['CAPTCHA_POSSIBILITIES'], 
                                $data['CAPTCHA_POSSIBILITIES']);?>
                            </li>
                            <li><label id="locale-selection">
                            <?php 
                            e(tl('captchasettings_element_captcha_language')); 
                            ?></label>
                            <?php $this->view->helper("options")->render(
                                "captcha-locale", "CAPTCHA_LOCALE", 
                                $data['LANGUAGES'], "en-US");?>
                            </li>
                            <li><label id="captcha-question-input-label"><?php
                                e(tl(
                                'captchasettings_element_captcha_question'));
                                ?></label>
                                <input type="text" 
                                    id="captcha-question-input" 
                                    name="CAPTCHA_QUESTION_INPUT">
                            </li>
                            <li><label id="captcha-choices-input-label"><?php
                                e(tl(
                                'captchasettings_element_captcha_choices'));
                                ?></label>
                                <input type="text" 
                                    id="captcha-choices-input" 
                                    name="CAPTCHA_CHOICES_INPUT"></li>
                            <li><input type="hidden" name="arg" 
                                    value="addtextcaptcha" />
             				    <input type="submit" value= 
             				        'Add To Database' 
             				        id="text-captcha-add-to-database" 
             				        name="text_captcha_add_to_database">
             				</li>
                        </ul>
                        <input type="hidden" name="CAPTCHA_MODE" 
                                    value="text_captcha"/></form>
                        <form name="updateTextCaptchaForm" method="post" 
                                action =""> 
         		    	    <h4>Current Question:</h4>
                            <select multiple="multiple" 
                                class="text-captcha-input" 
                                name="text_captcha_delete_questions[]">    	    	     
         		     	        <?php 
         		     	        if($data['translation_locale']) {
                                    foreach($data['translation_locale'] as 
                                        $translation_locale) {
                                        e('<option value
                                            = "'.$translation_locale[
                                            'translation_locale_id'].'">'.
                                            $translation_locale[
                                                'questionChoices'].
                                        '</option>');  
                                    }
                                }               
                                ?>
                        </select>
                        <input type="hidden" name="arg" 
                            value="updatetextcaptcha" />
         				<input type="submit" value='Delete From Database' 
         				    name="actiondelete"/>
                        <input type="submit" value='Edit'  
                            name="actionedit"/>
                       </form> 
                    <?php if(isset($data['EDIT_CAPTCHA_MAPS'])) {
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
                            e('Question:<input name="updateField['.
                                $question_rowid.']" value="'.
                                $rowid_translation_map[$question_rowid].'" 
                                style="width:80%">');
                            e('</div>');
                            e('<div>');
                            e('Choices:<input name="updateField['.
                                $choices_rowid.']" value="'.
                                $rowid_translation_map[$choices_rowid].'" 
                                style="width:80%">');
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
            <?
                } // END TEXT CAPTCHA
            ?>
        </div><!-- End of div: captcha-settings-container-->
    </div>
</div><!-- End of div: current-activity -->

<?php
    }
}

?>
