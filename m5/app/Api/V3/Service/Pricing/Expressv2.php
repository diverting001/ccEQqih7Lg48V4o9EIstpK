<?php
namespace App\Api\V3\Service\Pricing;
// 表达式计算
class Expressv2
{

   protected $cache_data = array() ;

   public static  function bc($expression)
   {
        $functions = 'ceil|floor';// 或者其他函数
        bcscale(3) ;
        $string = str_replace(' ', '', '('.$expression.')');
        while (preg_match('/(('.$functions.')?)\(([^\)\(]*)\)/i', $string, $match)) {
            while (
                preg_match('/([\-?0-9\.]+)(\^)([\-?0-9\.]+)/', $match[3], $m) ||
                preg_match('/([\-?0-9\.]+)([\*\/\%])([0-9\.]+)/', $match[3], $m) ||
                preg_match('/([\-?0-9\.]+)([\+\-])([0-9\.]+)/', $match[3], $m)
            ) {
                switch($m[2]) {
                    case '+': $result = bcadd($m[1], $m[3]); break;
                    case '-': $result = bcsub($m[1], $m[3]); break;
                    case '*': $result = bcmul($m[1], $m[3]); break;
                    case '/': $result = bcdiv($m[1], $m[3]); break;
                    case '%': $result = bcmod($m[1], $m[3]); break;
                    case '^': $result = bcpow($m[1], $m[3]); break;
                }
                $match[3] = str_replace($m[0], $result, $match[3]);
            }
            // 执行bc函数
            if (!empty($match[1]) && function_exists($func =  $match[1]))  {
                $match[3] = $func($match[3]);
                if(trim($func) == "floor" && bccomp($match[3] ,'0' ,2) == 0) {
                    $match[3] = 1 ;
                }
            }
            $string = str_replace($match[0], $match[3], $string);
        }
        return $string;
    }
    // 调用函数  直接传入表达式 返回计算多值
    public  function calculate($expresssStr)
    {
        // 替换掉 开头 带有 { }的情况
        $expresssStr = str_replace(array("{" ,"}") ,"" ,$expresssStr) ;
        if(!isset($this->cache_data[$expresssStr])) {
            $this->cache_data[$expresssStr] = $this->parseTernary($expresssStr);
        }
        $expresss =  $this->cache_data[$expresssStr] ;
        if(count($expresss) == 1) {
            return self::bc($expresss[0]) ;
        }
        if(count($expresss) == 3) {
              $result  =  $this->regexMatch($expresss[0]) ;
              if($result == '1') {
                  return self::bc($expresss[1]) ;
              }
              if($result == '0') {
                  return self::bc($expresss[2]) ;
              }
        }
        return false ;
    }

    // 规则引擎解析， 参数 布尔表达式
    // 简单多 规则严重 例如 a*12 > b*14
    public function regexMatch($expression)
    {
        $exp= self::bc($expression) ;
        $string  = str_replace(' ' ,'' ,$exp) ;
         while (
                preg_match('/([\-?0-9\.]+)(>|<|==|>=|<=|\|\||&&)([\-?0-9\.]+)/i', $string, $m)
            ) {
                switch($m[2]) {
                    case '>':  $result =  $m[1] > $m[3] ;break;
                    case '>=':  $result =  $m[1] > $m[3] ;break;
                    case '<':  $result = $m[1] < $m[3]; break;
                    case '<=':  $result = $m[1] <= $m[3]; break;
                    case '||':  $result = $m[1] || $m[3]; break;
                    case '&&':  $result = $m[1] && $m[3]; break;
                    case '==':  $result = $m[1] == $m[3]; break;
                }
                $resultStr = $result ? '1' :'0' ;
                $string = str_replace($m[0], $resultStr, $string);
            }
        return $string ;
    }
    // 多规则引擎解析， 参数 布尔表达式
    // 带 多 （）匹配 例如（1*1021 > 2*1021） && (21*2121 <2324)
    public function regexRule($expression)
    {
        $string  = str_replace(' ' ,'' ,$expression) ;
        $functions = '' ;
        while (preg_match('/(('.$functions.')?)\(([^\)\(]*)\)/i', $string, $match) ){
            $resultStr = $this->regexMatch($match[3]) ;
            $string = str_replace($match[0], $resultStr, $string);
        }
        return $this->regexMatch($string) ;
    }
    // 解析三木元算
    public static  function parseTernary($expressStr)
    {
        $expressArr = explode('?', $expressStr);
        $tag1 = false ;
        $data= array() ;
        if(count($expressArr) > 1){
            $data[0] = trim($expressArr[0]) ;
            $tag1 = true ;
        }
        $resultArr = explode(':', $expressArr[1]);
        $tag2 = false ;
        if(count($resultArr) > 1){
            $data[1] = trim($resultArr[0]) ;
            $data[2] = trim($resultArr[1]) ;
            $tag2 = true ;
        }
       return $tag1 && $tag2 ? $data : array($expressStr) ;
    }
}
