<?php

namespace App\Api\V1\Service\Delivery;

class Utils
{
    //配送公式验算function
    public static function cal_fee($exp, $weight, $totalmoney, $first_price, $continue_price, $defPrice = 0)
    {
        if ($str = trim($exp)) {
            $dprice = 0;
            $weight = $weight + 0;
            $totalmoney = $totalmoney + 0;
            $first_price = $first_price + 0;
            $continue_price = $continue_price + 0;
            $str = str_replace("[", "self::_getceil(", $str);
            $str = str_replace("]", ")", $str);
            $str = str_replace("{", "self::_getval(", $str);
            $str = str_replace("}", ")", $str);
            $str = str_replace("<", "self::_getfloor(", $str);
            $str = str_replace(">", ")", $str);

            $str = str_replace("w", $weight, $str);
            $str = str_replace("W", $weight, $str);
            $str = str_replace("fp", $first_price, $str);
            $str = str_replace("cp", $continue_price, $str);
            $str = str_replace("p", $totalmoney, $str);
            $str = str_replace("P", $totalmoney, $str);
            eval("\$dprice = $str;");

            if ($dprice === 'failed') {
                return $defPrice;
            } else {
                return $dprice;
            }
        } else {
            return $defPrice;
        }
    }

    private static function _getval($expval)
    {
        $expval = trim($expval);
        if ($expval !== '') {
            eval("\$expval = $expval;");
            if ($expval > 0) {
                return 1;
            } else {
                if ($expval == 0) {
                    return 1 / 2;
                } else {
                    return 0;
                }
            }
        } else {
            return 0;
        }
    }

    private static function _getceil($expval)
    {
        if ($expval = trim($expval)) {
            eval("\$expval = $expval;");
            if ($expval > 0) {
                return ceil($expval);
            } else {
                return 0;
            }
        } else {
            return 0;
        }
    }

    private static function _getfloor($expval)
    {
        if ($expval = trim($expval)) {
            eval("\$expval = $expval;");
            if ($expval > 0) {
                return floor($expval);
            } else {
                return 0;
            }
        } else {
            return 0;
        }
    }
}
