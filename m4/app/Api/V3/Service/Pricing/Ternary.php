<?php

namespace App\Api\V3\Service\Pricing;


class Ternary
{

    protected $_symbol = null;

    protected $_stacks = null;

    private $__objlist = array();

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
    protected function getStack()
    {
        if (!isset($this->_stacks)) {
            return array();
        }
        return $this->_stacks->getAllElem();
    }

    /**
     * 入栈
     * @param $value
     */
    protected function pushStack($value)
    {
        if (!isset($this->_stacks)) {
            $this->_stacks = $this->__single('App\Api\V3\Service\Pricing\Stack');
        }
        $this->_stacks->getPushStack($value);
    }

    /**
     * 出栈
     * @param $stack
     * @param $key
     */
    protected function popStack()
    {
        $data = '';
        if (isset($this->_stacks)) {
            $this->_stacks->getPopStack($data);
        }
        return $data;
    }

    /**
     * 计算
     * @param $expressStr
     * @return mixed
     * @throws Exception
     */
    public function compute($expressStr)
    {
        try {
            $this->initStack();
            $expressStr = $this->_filterCenterStr($expressStr);
            $this->isTernary($expressStr, true);
            $result = $this->_ternaryToArr($expressStr);
            return $result;
        } catch (Exception $e) {
            throw new Exception($e->getMessage());
        }
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
        return $expres;
    }


    /**
     * 判断是否是三元运算符
     * @param $expressStr
     * @return bool
     */
    public function isTernary($expressStr, $isThrow = false)
    {
        $expressArr = explode('?', $expressStr);
        if (count($expressArr) > 1) {
        } else {
            if ($isThrow) {
                throw new Exception('express ? is not ternary');
            } else {
                return false;
            }
        }

        $resultArr = explode(':', $expressStr);
        if (count($resultArr) > 1) {
        } else {
            if ($isThrow) {
                throw new Exception('express : is not ternary');
            } else {
                return false;
            }
        }
        return true;
    }


    /**
     * @param $formatArr
     * @return mixed
     * @throws Exception开始计算
     */
    protected function _startCalculate($ternaryStr)
    {
        $this->isTernary($ternaryStr, true);
        $expArr = explode('?', $ternaryStr);

        try {
            $expressnewObj = $this->__single('App\Api\V3\Service\Pricing\Express');
            $result        = $expressnewObj->startCalculate($expArr[0]);
            $resultArr     = explode(':', $expArr[1]);
            if ($result) {
                return $expressnewObj->startCalculate($resultArr[0]);
            } else {
                return $expressnewObj->startCalculate($resultArr[1]);
            }
        } catch (Exception $e) {
            throw new Exception($e->getMessage());
        }
    }

    /**
     * 表达式字符串转换为数组
     * @param $expressStr
     * @return array
     */
    protected function _ternaryToArr($expressStr)
    {
        $expresOneArr = str_split($expressStr);
        foreach ($expresOneArr as $key => $value) {
            if ($value != '}') {
                $this->pushStack($value);
            } else {
                $expressArr = array();
                $stacks     = $this->getStack();
                foreach ($stacks as $value) {
                    $this->popStack();
                    if ($value == '{') {
                        $expressStr = implode('', array_reverse($expressArr));
                        $result     = $this->_startCalculate($expressStr);
                        $this->pushStack($result);
                        break;
                    }
                    $expressArr[] = $value;
                }
            }
        }
        $expressStr = implode('', array_reverse($this->getStack()));
        $isExpress  = $this->__single('App\Api\V3\Service\Pricing\Express')->isExpress($expressStr);
        if ($isExpress) {
            $expressStr = $this->__single('App\Api\V3\Service\Pricing\Express')->startCalculate($expressStr);
        }
        if (floatval($expressStr) == $expressStr) {
            return $expressStr;
        } else {
            throw new Exception('express is invalid');
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
