<?php
namespace Neigou;
class AutoCIUtils{

    public static function getCurrentVersion($domain = ''){
        if(!$domain) return 'SHELLCURRENTVERSION';
        if($domain == PSR_WEB_NEIGOU_LIFE_DOMAIN || $domain == PSR_WEB_DIANDI_CLUB_DOMAIN || $domain == PSR_WEB_NEIGOU_CLUB_DOMAIN )
            $domain = PSR_WEB_DIANDI_CLUB_DOMAIN;
        $domain = str_replace(array('-','.'),'_',$domain);
        $domain = $domain . '_AUTOCI_BRANCH';
        if(isset($_COOKIE[$domain])){
            return $_COOKIE[$domain];
        }
        return 'SHELLCURRENTVERSION';
    }

}
