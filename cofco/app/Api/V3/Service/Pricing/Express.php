<?php

namespace App\Api\V3\Service\Pricing;

class Express
{
    private $__objlist = array();

    /**
     * 符号
     * @var array
     */
    protected $_symbols = array(
        '||' => 1,
        '&&' => 2,
        '==' => 3,
        '!=' => 3,
        '>=' => 4,
        '<=' => 4,
        '>'  => 4,
        '<'  => 4,
        '+'  => 5,
        '-'  => 5,
        '*'  => 6,
        '/'  => 6,
        '('  => 7,
        ')'  => 7
    );

    const LEFT_BRACKET  = '(';
    const RIGHT_BRACKET = ')';

    const AFTER_STACK  = 1;
    const EXPRES_STACK = 2;

    protected $_stacks = array();

    static function compute($expressStr)
    {
        try {
            $expressObj = new self();
            return $expressObj->startCalculate($expressStr);
        } catch (\Exception $e) {
            return false;
        }
    }


    /**
     * 开始计算
     * @param $expressStr
     * @return mixed
     */
    public function startCalculate($expressStr)
    {
        $this->initStack();
        if (!$this->isExpress($expressStr)) {
            if (floatval($expressStr) == $expressStr) {
                return $expressStr;
            } else {
                throw new Exception('express str is invalid');
            }
        }
        $expressStr    = $this->_filterCenterStr($expressStr);
        $centerArr     = $this->_toCenterArr($expressStr);
        $afterArr      = $this->_centerToAfterArr($centerArr);
        $result        = $this->_computeExpress($afterArr);
        $this->_stacks = array();
        return $result;
    }

    /**
     * 初始化栈
     */
    protected function initStack()
    {
        $this->__single('App\Api\V3\Service\Pricing\Stack')->getAllPopStack();
    }

    /**
     * 获取栈
     * @return array
     */
    protected function getStack($stack)
    {
        if (!isset($this->_stacks[$stack])) {
            return array();
        }
        return $this->_stacks[$stack]->getAllElem();
    }

    /**
     * 入栈
     * @param $value
     */
    protected function pushStack($stack, $value)
    {
        if (!isset($this->_stacks[$stack])) {
            $this->_stacks[$stack] = $this->__single('App\Api\V3\Service\Pricing\Stack');
        }
        $this->_stacks[$stack]->getPushStack($value);
    }

    /**
     * 出栈
     * @param $stack
     * @param $key
     */
    protected function popStack($stack)
    {
        $data = '';
        if (isset($this->_stacks[$stack])) {
            $this->_stacks[$stack]->getPopStack($data);
        }
        return $data;
    }

    /**
     * 过滤表达式
     * @param $centerStr
     * @return array
     */
    protected function _filterCenterStr($centerStr)
    {
        if (empty($centerStr)) {
            throw new Exception('express is empty');
        }
        $expres = str_replace(' ', '', $centerStr);
        $expres = str_ireplace('true', '1', $expres);
        $expres = str_ireplace('false', '0', $expres);
        return $expres;
    }

    //是否是表达式
    public function isExpress($str)
    {
        return (count($this->_toCenterArr($str, false)) > 1) ? true : false;
    }

    /**
     * 转换为表达式数组
     * @param $centerStr
     * @return array
     * @throws Exception
     */
    protected function _toCenterArr($centerStr, $isThrow = true)
    {
        $expresOneArr = str_split($centerStr);
        $expresArr    = array();
        $currentNum   = '';
        $isContinue   = false;
        foreach ($expresOneArr as $key => $value) {
            if ($isContinue) {
                $isContinue = false;
                continue;
            }
            $single = isset($this->_symbols[$value]);
            $double = isset($this->_symbols[$value . $this->getNextNode($expresOneArr, $key)]);
            if (is_numeric($value) || $value == '.') {
                $currentNum .= $value;
            } else {
                if ($single || $double) {
                    if ($currentNum != '') {
                        array_push($expresArr, $currentNum);
                        $currentNum = '';
                    }
                    if ($double) {
                        $isContinue = true;
                        array_push($expresArr, $value . $this->getNextNode($expresOneArr, $key));
                    } else {
                        array_push($expresArr, $value);
                    }
                } else {
                    if ($isThrow) {
                        throw new Exception($value . ' is invalid');
                    } else {
                        return false;
                    }
                }
            }
        }
        array_push($expresArr, $currentNum);
        return $expresArr;
    }

    public function getNextNode($arr, $key)
    {
        return isset($arr[$key + 1]) ? $arr[$key + 1] : false;
    }

    /**
     * 表达式转换
     * @param $expres
     */
    protected function _centerToAfterArr($expresArr)
    {
        $afterArr = array();
        //$i = 0;
        foreach ($expresArr as $value) {
            if (empty($value)) {
                if ($value !== '0') {
                    continue;
                }
            }
            if (isset($this->_symbols[$value])) { //符号
                $tempStack = $this->getStack(self::AFTER_STACK);
                if (empty($tempStack) || $value == self::LEFT_BRACKET) {
                    $this->pushStack(self::AFTER_STACK, $value);
                } else {
                    if ($value == self::RIGHT_BRACKET) {
                        while (true) {
                            $data = $this->popStack(self::AFTER_STACK);
                            if ($data == self::LEFT_BRACKET) {
                                break;
                            } else {
                                array_push($afterArr, $data);
                            }
                        }
                    } else {
                        $currentSymbol = $this->popStack(self::AFTER_STACK);
                        if (
                            $currentSymbol == self::LEFT_BRACKET ||
                            $this->_symbols[$value] > $this->_symbols[$currentSymbol]
                        ) {
                            $this->pushStack(self::AFTER_STACK, $currentSymbol);
                            $this->pushStack(self::AFTER_STACK, $value);
                        } else {
                            if (
                                $this->_symbols[$value] === $this->_symbols[$currentSymbol] ||
                                $this->_symbols[$value] < $this->_symbols[$currentSymbol]
                            ) {
                                array_push($afterArr, $currentSymbol);
                                $this->pushStack(self::AFTER_STACK, $value);
                            }
                        }
                    }
                }
            } else {                              //数字
                array_push($afterArr, $value);
            }
//            echo 'i:'.$i."\n";
//            echo 'stack:'.json_encode($this->getStack(self::AFTER_STACK))."\n";
//            echo "afterarr:".json_encode($afterArr)."\n";
//            echo "-----------------\n";
//            $i++;
        }
        foreach ($this->getStack(self::AFTER_STACK) as $key => $value) {
            $afterArr[] = $value;
        }
        return $afterArr;
    }

    /**
     * @param $expressStr
     */
    protected function _computeExpress($afterArr)
    {
        foreach ($afterArr as $value) {
            if (isset($this->_symbols[$value])) {
                $index  = 0;
                $oneNum = 0;
                foreach ($this->getStack(self::EXPRES_STACK) as $key => $numValue) {
                    if ($index == 0) {
                        $oneNum = $numValue;
                        $this->popStack(self::EXPRES_STACK);
                    } else {
                        if ($index == 1) {
                            $calulateNum = $this->_calculate($numValue, $oneNum, $value);
                            $this->popStack(self::EXPRES_STACK);
                            $this->pushStack(self::EXPRES_STACK, $calulateNum);
                        } else {
                            if ($index == 2) {
                                break;
                            }
                        }
                    }
                    $index++;
                }
            } else {
                $this->pushStack(self::EXPRES_STACK, $value);
            }
        }
        return $this->popStack(self::EXPRES_STACK);
    }

    /**
     *  计算
     * @param $one
     * @param $two
     * @param $express
     * @return float
     * @throws Exception
     */
    protected function _calculate($one, $two, $express)
    {
        switch ($express) {
            case '+':
                return $one + $two;
                break;
            case '-':
                return $one - $two;
                break;
            case '*':
                return $one * $two;
                break;
            case '/':
                if ($two == 0) {
                    throw new Exception('dividend not be zero');
                }
                return $one / $two;
                break;
            case '||':
                return $one || $two;
                break;
            case '&&':
                return $one && $two;
                break;
            case '>':
                return $one > $two;
                break;
            case '<':
                return $one < $two;
                break;
            case '>=':
                return $one >= $two;
                break;
            case '<=':
                return $one <= $two;
                break;
            case '==':
                return $one == $two;
                break;
            case '!=':
                return $one != $two;
                break;
            default:
                throw new Exception('express not found');
        }
    }

    /*
     * @todo 获取类列表
     */
    private function __single($class_name)
    {
        if (!isset($this->__objlist[$class_name])) {
            $this->__objlist[$class_name] = new $class_name();
        }
        return $this->__objlist[$class_name];
    }
}
