<?php
/** 
 *  SeekQuarry/Yioop --
 *  Open Source Pure PHP Search Engine, Crawler, and Indexer
 *
 *  Copyright (C) 2009, 2010  Chris Pollett chris@pollett.org
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
 * @subpackage library
 * @license http://www.gnu.org/licenses/ GPL3
 * @link http://www.seekquarry.com/
 * @copyright 2009, 2010
 * @filesource
 */
 
if(!defined('BASE_DIR')) {echo "BAD REQUEST"; exit();}

/**
 * Library of functions used to manipulate and to extract components from urls 
 *
 *
 * @author Chris Pollett
 *
 * @package seek_quarry
 * @subpackage library
 */
  
class UrlParser 
{

    /**
     * Checks if the url scheme is either http or https.
     *
     * @param string $url  the url to check
     * @return bool returns true if it is either http or https and false 
     *      otherwise
     */
    static function isSchemeHttpOrHttps($url) 
    {
        $url_parts = @parse_url($url);

        if(isset($url_parts['scheme']) && $url_parts['scheme'] != "http" && 
            $url_parts['scheme'] != "https") {
            return false;
        }

        return true; 

    }

    /**
     * Checks if the url has a host part.
     *
     * @param string $url  the url to check
     * @return bool true if it does; false otherwise
     */
    static function hasHostUrl($url) 
    {
       $url_parts = @parse_url($url);

       return isset($url_parts['host']);
    }

    /**
     * Get the host name portion of a url if present; if not return false
     *
     * @param string $url the url to parse
     * @return the host portion of the url if present; false otherwise
     */
    static function getHost($url) 
    {
        $url_parts = @parse_url($url);

        if(!isset($url_parts['scheme']) ) {return false;}
        $host_url = $url_parts['scheme'].'://';

        if(isset($url_parts['user']) && isset($url_parts['pass'])) {
            $host_url .= $url_parts['user'].":".$url_parts['pass']."@";
        }

        if(strlen($url_parts['host']) <= 0) { return false; }

        $host_url .= $url_parts['host'];

        if(isset($url_parts['port'])) {
            $host_url .= ":".$url_parts['port'];
        }

        return $host_url;
          
    }

    /**
     *  Get the path portion of a url if present; if not return NULL
     *
     *  @param string $url the url to parse
     *  @return the host portion of the url if present; NULL otherwise
     */
    public static function getPath($url) 
    {
        $url_parts = @parse_url($url);
        if(!isset($url_parts['path'])) {
            return NULL;
        }

        return $url_parts['path'];
    }

    /**
     * Gets an array of prefix urls from a given url. Each prefix contains at 
     * least the the hostname of the the start url
     *
     * http://host.com/b/c/ would yield http://host.com/ , http://host.com/b, 
     * http://host.com/b/, http://host.com/b/c, http://host.com/b/c/
     *
     * @param string $url the url to extract prefixes from
     * @return array the array of url prefixes
     */
    public static function getHostPaths($url) 
    {
        $host_paths = array($url);

        $host = self::getHost($url);
        if(!$host) {return $host_paths;}

        $host_paths[] = $host;

        $path = self::getPath($url);

        $path_parts = explode("/", $path);

        $url = $host;
        foreach($path_parts as $part) {
         if($part != "") {
            $url .="/$part";
            $host_paths[] = $url;
            }
            $host_paths[] = $url."/";
        }

        $host_paths = array_unique($host_paths);

        return $host_paths;

    }

    /**
     * Given a url, makes a guess at the file type of the file it points to
     *
     * @param string $url a url to figure out the file type for
     *
     * @return string the guessed file type.
     *
     */
    static function getDocumentType($url) 
    {

        $url_parts = @parse_url($url); 

        if(!isset($url_parts['path'])) {
            return "html"; //we default to html
        } else {
            $path_parts = pathinfo($url_parts['path']);

            if(!isset($path_parts["extension"]) ) {
             return "html"; //we default to html
            }

            return $path_parts["extension"];
        }

    }

    /**
     * Gets the filename portion of a url if present; 
     * otherwise returns "Some File"
     *
     * @param string $url a url to parse
     * @return string the filename portion of this url
     */
    static function getDocumentFilename($url)
    {

        $url_parts = @parse_url($url); 

        if(!isset($url_parts['path'])) {
            return "html"; //we default to html
        } else {
            $path_parts = pathinfo($url_parts['path']);

            if(!isset($path_parts["filename"]) ) {
                return "Some File";
            }

            return $path_parts["filename"];
        }

    }

    /**
     * Get the query string component of a url
     *
     * @param string $url  a url to get the query string out of
     * @return string the query string if present; NULL otherwise
     */
    static function getQuery($url) 
    {
        $url_parts = @parse_url($url);
        if(isset($url_parts['query'])) {
            $out = $url_parts['query'];
        } else {
            $out = NULL;
        }

        return NULL;
    }

    
    /**
     * Given a $link that was obtained from a website $site, returns 
     * a complete URL for that link.
     * For example, the $link
     * some_dir/test.html
     * on the $site
     * http://www.somewhere.com/bob
     * would yield the complete url
     * http://www.somewhere.com/bob/some_dir/test.html
     * 
     * @param string $link  a relative or complete url
     * @param string $site  a base url
     * 
     * @return string a complete url based on these two pieces of information
     * 
     */
    public static function canonicalLink($link, $site) 
    {

        if(!self::isSchemeHttpOrHttps($link)) {return NULL;}

        if(self::hasHostUrl($link)) {
            $host = self::getHost($link);
            $path = self::getPath($link);
            $query = self::getQuery($link);
        } else {

            $host = self::getHost($site);

            if($link !=NULL && $link[0] =="/") {
                $path = $link;

            } else {

                $site_path = self::getPath($site);
                $site_path_parts = pathinfo($site_path);

                if(isset($site_path_parts['dirname'])) {
                    $pre_path = $site_path_parts['dirname'];
                } else {
                    $pre_path = "";
                }
                if(isset($site_path_parts['basename']) && 
                    !isset($site_path_parts['extension'])) {
                    $pre_path .="/".$site_path_parts['basename'];
                }

                if(strlen($link) > 0 ) {$pre_path .="/".$link;}
                $path = self::getPath($pre_path);
                $query = self::getQuery($host.$pre_path);

            }
        }


        // take a stab at paths containing ..
        $path = preg_replace('/(\/\w+\/\.\.\/)+/', "/", $path);

            
        // if still has .. give up
        if(stristr($path, "../"))
        {
            return NULL;
        }

        // handle paths with dot in it 
        $path = preg_replace('/(\.\/)+/', "", $path);
        $path = str_replace(" ", "%20", $path);


        $link_path_parts = pathinfo($path);

        $path2 = $path;
        do {
            $path = $path2;
            $path2 = str_replace("//","/", $path);
        } while($path != $path2);

        $path = str_replace("/./","/", $path);   

        $url = $host.$path;

        if(isset($query) && $query !== "") {
            $url .= "?".$query;
        }

        return $url;
    }

    /**
     * Checks if a url has a repeated set of subdirectories, and if the number 
     * of repeats occurs more than some threshold number of times
     *
     *  A pattern like bob/.../bob counts as own reptition. 
     * bob/.../alice/.../bob/.../alice would count as two (... should be read 
     * as ellipsis, not a directory name).If the threshold is three and there 
     * are at least three repeated mathes this function return true; it returns
     * false otherwise.
     *
     * @param string $url the url to check
     * @param int $repeat_threshold the number of repeats of a subdir name to 
     *      trigger a true response
     * @return bool whether a repeated subdirectory name with more matches than
     *      the threshold was found
     *
     */
    static function checkRecursiveUrl($url, $repeat_threshold = 3) 
    {
        $url_parts = mb_split("/", $url);

        $count= count($url_parts);
        $flag = 0;
        for($i = 0; $i < $count; $i++) {
            for($j = 0; $j < $i; $j++) {
                if($url_parts[$j] == $url_parts[$i]) {
                    $flag++;
                }
            }
        }

        if($flag > $repeat_threshold) {
            return true;
        }

        return false;

    }


}

?>
