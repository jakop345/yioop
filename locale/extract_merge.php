<?php
/* ***** BEGIN LICENSE BLOCK ***** 
 *
 * The contents of this file are copyright
 * Chris Pollett 2009.
 *
 * Use of this file is permitted provided:
 *
 * (a) this copyright notice is retained, 
 *     including in derived works.
 *
 * (b) any changes to this file are not
 *     publicly made available or used 
 *     for commercial purposes without 
 *     the express consent of Chris Pollett
 *     
 *  ***** END LICENSE BLOCK ***** */
 
/**
 * extract.php
 * 
 * Extracts strings of the
 * SeekQuarry project for localization
 * It then merges the data to each locale
 *
 * @author Chris Pollett
 *
 * @package seek_quarry
 */

require_once "../configs/config.php";

$extract_dirs = array("controllers", "views");
$extensions = array("php");
 
$strings = getTranslateStrings($extract_dirs, $extensions) ;
$general_ini = parse_ini_file(BASE_DIR."/locale/general.ini", true);
updateLocales($general_ini, $strings);

function updateLocales($general_ini, $strings)
{
  $path = BASE_DIR."/locale";
  if(!$dh = @opendir($path)) {
	die("Couldn't read locale directory!\n");
  }
  while (($obj = readdir($dh)) !== false) {
	 if($obj == '.' || $obj == '..') {
		 continue;
	 }
	 $cur_path = $path . '/' . $obj;
	 if (is_dir($cur_path)) {
	    updateLocale($general_ini, $strings, $path, $obj);
	 }	 
  } 
}

function updateLocale($general_ini, $strings, $dir, $locale)
{
   $old_configure = array();
   $cur_path = $dir . '/' . $locale;
   if(file_exists($cur_path.'/configure.ini')) {
      $old_configure = parse_ini_file($cur_path.'/configure.ini', true);
   }
   $n = array();
   $n[] = <<<EOT
;***** BEGIN LICENSE BLOCK ***** 
;
; The contents of this file are copyright
; Chris Pollett 2009.
;
; Use of this file is permitted provided:
;
; (a) this copyright notice is retained, 
;     including in derived works.
;
; (b) any changes to this file are not
;     publicly made available or used 
;     for commercial purposes without 
;     the express consent of Chris Pollett
;     
;  ***** END LICENSE BLOCK *****
;
; configure.ini 
;
; $locale configuration file
;
EOT;
   foreach($general_ini as $general_name => $general_value) {
     if(is_array($general_value)) {
	    $n[] = "[$general_name]";
		foreach($general_value as $name => $value) {
		   if(isset($old_configure[$general_name][$name])) {
		      $n[] = $name.' = "'.addslashes($old_configure[$general_name][$name]).'"';
		   } else {
		      $n[] = $name.' = "'.$value.'"';
		   }
		}
	 } else {
	   if(isset($old_configure[$general_name])) {
		  $n[] = $general_name.' = "'.addslashes($old_configure[$general_name]).'"';
	   } else {
		  $n[] = $name.' = "'.$value.'"';
	   }	 
	 }
   }
   
   $n[] = ";\n; Strings to translate on various pages\n;";
   $n[] = "[strings]";
   foreach($strings as $string) {
      if( isset($string[0]) && $string[0] == ";") {
	     $n[] = $string;
	  } else {
	     if(isset($old_configure['strings'][$string])) {
		    $n[] = $string.' = "'.addslashes($old_configure['strings'][$string]).'"';
		 } else {
		    $n[] = $string.' = ""';
		 }
	  }
   }
  
  $out = implode("\n", $n);
  file_put_contents($cur_path.'/configure.ini', $out);
}

function getTranslateStrings($extract_dirs, $extensions) 
{
   $strings = array();
   foreach($extract_dirs as $dir) {
     $path = BASE_DIR."/".$dir;
	 $dir_strings = traverseExtractRecursive($path, $extensions);
	 if(count($dir_strings) > 0) {	  
	    $strings[] = ";";
	    $strings[] = "; $path"; 	  
	    $strings = array_merge($strings, $dir_strings);
	 }	  	  
   }
   
   return $strings;

}

function traverseExtractRecursive($dir, $extensions) 
{
  $strings = array();
  
  if(!$dh = @opendir($dir)) {
	 return array();
  }
  
  while (($obj = readdir($dh)) !== false) {
	 if($obj == '.' || $obj == '..') {
		 continue;
	 }
	 
	 $cur_path = $dir . '/' . $obj;
	 if (is_dir($cur_path)) {
		 $dir_strings = traverseExtractRecursive($cur_path, $extensions);
		 if(count($dir_strings) > 0) {
	        $strings[] = ";";
	        $strings[] = "; $cur_path"; 		 	 
		    $strings = array_merge($strings, $dir_strings);
		 }
	 }
	 
	 if(is_file($cur_path)) {
	   $path_parts = pathinfo($cur_path);
	   $extension = (isset($path_parts['extension'])) ? $path_parts['extension'] : "";
	   if(in_array($extension, $extensions)) {
	      $lines = file($cur_path);
		  $num_lines = count($lines);
          for($i = 0; $i < $num_lines; $i++) {
		     $num_matches = preg_match_all('/tl\(([[:word:]]+?)[(\))|(\s+\,)]/', $lines[$i], $to_translates);
			 if($num_matches > 0) {
			    $strings[] = ";";
			    $strings[] = "; $obj line: $i";
				$strings = array_merge($strings, $to_translates[1]);
			 }
		  }
	   }
	 }
  }

 return $strings;
 closedir($dh);

 return;
}  
?>