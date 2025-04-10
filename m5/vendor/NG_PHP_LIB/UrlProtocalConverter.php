<?php
namespace Neigou;
class UrlProtocalConverter{
    static function getURLWithoutSchema($string){
        $string = trim(substr($string,strpos($string,"//")));
        return $string;
    }
}