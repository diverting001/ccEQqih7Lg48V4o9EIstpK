<?php

namespace App\Api\V3\Service\Pricing;

use App\Api\V3\Service\Pricing\Lnode;

class Stack
{

    //头“指针”，指向栈顶元素
    public $mNext;
    public $mLength;

    /**
     *初始化栈
     *
     * @return void
     */
    public function __construct()
    {
        $this->mNext   = null;
        $this->mLength = 0;
    }

    /**
     *判断栈是否空栈
     *
     * @return boolean  如果为空栈返回true,否则返回false
     */
    public function getIsEmpty()
    {
        if ($this->mNext == null) {
            return true;
        } else {
            return false;
        }
    }

    /**
     *将所有元素出栈
     *
     * @return array 返回所有栈内元素
     */
    public function getAllPopStack()
    {
        $e = array();
        if ($this->getIsEmpty()) {
        } else {
            while ($this->mNext != null) {
                $e[]         = $this->mNext->mElem;
                $this->mNext = $this->mNext->mNext;
            }
        }
        $this->mLength = 0;
        return $e;
    }

    /**
     *返回栈内元素个数
     *
     * @return int
     */
    public function getLength()
    {
        return $this->mLength;
    }

    /**
     *元素进栈
     *
     * @param mixed $e 进栈元素值
     * @return void
     **/
    public function getPushStack($e)
    {
        $newLn        = new Lnode();
        $newLn->mElem = $e;
        $newLn->mNext = $this->mNext;
        $this->mNext  =& $newLn;
        $this->mLength++;
    }

    /**
     *元素出栈
     *
     * @param Lnode $e 保存出栈的元素的变量
     * @return boolean 出栈成功返回true,否则返回false
     **/
    public function getPopStack(&$e)
    {
        if ($this->getIsEmpty()) {
            return false;
        }
        $p           = $this->mNext;
        $e           = $p->mElem;
        $this->mNext = $p->mNext;
        $this->mLength--;
    }

    /**
     *仅返回栈内所有元素
     *
     * @return array 栈内所有元素组成的一个数组
     */
    public function getAllElem()
    {
        $sldata = array();
        if ($this->getIsEmpty()) {
        } else {
            $p = $this->mNext;
            while ($p != null) {
                $sldata[] = $p->mElem;
                $p        = $p->mNext;
            }
            return $sldata;
        }

    }

    /**
     * 返回栈内某个元素的个数
     *
     * @param mixed $e 待查找的元素的值
     * @return int
     * */
    public function getCountForElem($e)
    {
        $allelem = $this->getAllElem();
        $count   = 0;
        foreach ($allelem as $value) {
            if ($e === $value) {
                $count++;
            }
        }
        return $count;
    }
}
