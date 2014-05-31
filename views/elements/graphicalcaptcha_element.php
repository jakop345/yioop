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
 * @subpackage model
 * @license http://www.gnu.org/licenses/ GPL3
 * @link http://www.seekquarry.com/
 * @copyright 2009 - 2014
 * @filesource
 */

if(!defined('BASE_DIR')) {echo "BAD REQUEST"; exit();}

/**
 * This is class is used to handle the 
 * graphical captcha settings for Yioop
 * 
 * @param string the captcha string as $data
 * @author Chris Pollett
 *
 * @package seek_quarry
 * @subpackage element
 */
 
class GraphicalcaptchaElement extends Element
{
    function render($data) 
    {
        if(isset($data['captcha_string'])) {
            $captcha_word = $data['captcha_string'];
            $image = @imagecreatetruecolor(195, 35);
            // defines background color, random lines color and text color
            $bg_color = imagecolorallocate($image, mt_rand(0,255), 255, 0);
            imagefill($image, 0, 0, $bg_color);
            $lines_color = imagecolorallocate($image, 0x99, 0xCC, 0x99);
            $text_color = imagecolorallocate($image, mt_rand(0,255), 0, 255);
            // draws random lines
            for($i = 0; $i < 4; $i++){
                imageline($image, 0, rand(0,35), 195, rand(0,35), 
                    $lines_color);
            }
            $captcha_letter_array = str_split($captcha_word);
            foreach($captcha_letter_array as $i => $captcha_letter) { 
                imagesetthickness($image, 1);
                imagestring($image, 5, 5 + ($i * 35), rand(2,14), 
                    $captcha_letter, $text_color); 
            }
            // creates image
            ob_start (); 
            imagepng ($image);
            $image_data = ob_get_contents ();
            ob_end_clean ();
            $image_data_base64 = base64_encode($image_data);
            e('<div class="graphical-captcha-container">');
            e('<img src="data:image/png;base64,'.$image_data_base64.'" 
                width="260" height="70" border="1" alt="CAPTCHA">');
            e('<input type="text" maxlength = "6"
                name="user_entered_graphical_captcha_string"/>');
            e('</div>');
            imagedestroy($image);
            
        }
    }
}
?>