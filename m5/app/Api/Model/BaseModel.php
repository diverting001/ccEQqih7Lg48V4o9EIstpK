<?php
namespace App\Api\Model;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Support\Facades\DB;

class BaseModel extends Model
{
    protected $perPage = 20;
    protected $connection = 'neigou_store' ;

    /**
     * 分页查询方法
     *
     * @param mixed|null $where 查询判断条件
     * @param string $select 查询字段
     * @param array $orders 查询排序
     * @param int $page 查询页码
     * @param int $perPage 自定义分页大小
     * @return mixed
     */
    public function getCommonList($where = null, $select = '*', array $orders = [], int $page, int $perPage = 0)
    {
        $table = $this->getBaseTable()->select($select);
        $this->setWhere($where) ;
        if ($orders) {
            foreach ($orders as $key => $value) {
                $table->orderBy($key, $value);
            }
        }
        $pageSize = $perPage ? $perPage : $this->perPage;
        return $table->paginate($pageSize, ['*'], 'page', $page)->toArray();
    }

    public function getRow($where = [], $select = '*')
    {
        $table = $this->getBaseTable();
        if (is_array($select)) {
            $table->select($select);
        } else {
            $table->selectRaw($select);
        }
        $table  = $this->setWhere($where ,$table) ;
        $data =  $table->first();
        if(is_object($data) && !empty($data)) {
            return get_object_vars($data) ;
        }
        return $data ;

    }

    /**
     * 插入数据
     *
     * @param $data
     * @return mixed
     */
    public function baseInsert($data)
    {
        return $this->getBaseTable()->insertGetId($data);
    }

    /**
     * 更新数据
     *
     * @param array $where
     * @param array $data
     * @return mixed
     */
    public function baseUpdate($where = [], array $data)
    {
        $table = $this->getBaseTable();
        if ($where) {
            $table  = $this->setWhere($where ,$table) ;
        }
        return $table->update($data);
    }

    protected function getBaseTable()
    {
        if ($this->connection) {
            return DB::connection($this->connection)->table($this->table);
        }

        return DB::table($this->table);
    }

    public function dealPaginate(QueryBuilder $query, $page)
    {
        $data = $query->paginate($this->perPage, ['*'], 'page', $page)->toArray();

        $newData['list'] = $data['data'];
        $newData['current_page'] = $data['current_page'];
        $newData['per_page'] = $data['per_page'];
        $newData['total'] = $data['total'];
        $newData['total_page'] = ceil($data['total'] / $data['per_page']);

        return $newData;
    }


    /**
     * 查询符合条件的总条数
     * @param array $where
     * @return array
     */
    public function getCommonNum(array $where = [], array $wheres = [])
    {
        $table = $this->getBaseTable();
        $table  = $this->setWhere($where ,$table) ;

        if ($wheres) {
            foreach ($wheres as $key => $item) {
                $table->whereIn($key, $item);
            }
        }
        $totalNum = $table->count();
        return $totalNum;
    }

    /**
     * 求和
     * @param array $where
     * @return array
     */
    public function getSum(array $where = [],$field = '', array $wheres = [])
    {
        $table = $this->getBaseTable();
        $table  = $this->setWhere($where ,$table) ;

        if ($wheres) {
            foreach ($wheres as $key => $item) {
                $table->whereIn($key, $item);
            }
        }
        return $table->pluck($field)->sum();
    }


    protected function setWhere($where,$table)
    {
        if (!empty($where)) {
            foreach ($where as $k=>$v) {
                if(is_string($v) || is_numeric($v)) {
                    $table->where($k ,$v);
                } elseif(is_array($v)) {
                    $table->whereIn($k ,$v);
                }
            }
        }
        return $table ;
    }

    /**
     * 根据条件查询
     * @param array $where 查询条件
     * @param string $select 查询字段
     * @param array $orders 排序规则 ['id' => 'asc', 'create_time' => 'desc']
     * @param array $likes 模糊查询 ['project_cate_name' => '2323']
     * @return mixed
     */
    public function getBaseInfo($where = [], $select = '*', array $orders = [], array $likes = [])
    {
        $table = $this->getBaseTable();
        $table->select($select);
        $table  = $this->setWhere($where ,$table) ;
        if (!empty($likes)) {
            foreach ($likes as $lkey => $item) {
                $table->where($lkey, 'like', '%' . $item . '%');
            }
        }

        if (!empty($orders)) {
            foreach ($orders as $key => $value) {
                $table->orderBy($key, $value);
            }
        }
        $data = $table->get()->toArray();
        return $this->Object2Arr($data) ;
    }

    public function Object2Arr($data) {
        if(!empty($data)) {
            foreach ($data as $k=>$val) {
                if(is_object($val)) {
                    $data[$k] = get_object_vars($val) ;
                }
            }
        }
        return $data ;
    }
    /**
     * 获取单条记录
     * @param array $where
     * @param string $select
     * @return Model|\Illuminate\Database\Query\Builder|object|null
     */
    public function getInfoRow(array $where = [], $select = '*', array $orders = [])
    {
        $table = $this->getBaseTable();
        $table->select($select);

        $table  = $this->setWhere($where ,$table) ;
        if ($orders) {
            foreach ($orders as $key => $value) {
                $table->orderBy($key, $value);
            }
        }
        $data =  $table->first();
        if(!empty($data) && is_object($data)) {
            return  get_object_vars($data) ;
        }
        return  $data ;
    }

    public function getCount($where) {
        $table = $this->getBaseTable();
        $table  = $this->setWhere($where ,$table) ;
        return  $table->count();
    }
    public function  selectrow($sql) {
        $result = $this->select($sql) ;
        return $result ? $result[0] : [] ;
    }

    // 批量插入数据
    public function mulitInsert($data) {
        return $this->getBaseTable()->insert($data) ;
    }
    /**
     * 查询某个字段的最大值
     * @param array $where
     * @param string $column
     * @return bool|mixed
     */
    public function getMaxValue(array $where = [], string $column = '')
    {
        if (empty($column)) {
            return false;
        }

        $table = $this->getBaseTable();

        if (!empty($where)) {
            $table->where($where);
        }

        return $table->max($column);
    }

    /**
     * whereIn 批量修改
     * @param array $where
     * @param array $update
     * @return int
     */
    public function baseUpdateBatch(array $where = [], $update = [])
    {
        $table = $this->getBaseTable();

        if ($where) {
            foreach ($where as $key => $item) {
                $table->whereIn($key, $item);
            }
        }

        return $table->update($update);
    }

    /**
     * 基本删除
     * @param array $where
     */
    public function baseDelete(array $where = [])
    {
        $table = $this->getBaseTable();
        return $table->where($where)->delete();
    }

    /**
     * 批量插入数据
     *
     * @param $data
     * @return mixed
     */
    public function patInsert($data)
    {
        return $this->getBaseTable()->insert($data);
    }

    /**
     * 改变数据库连接
     * 同一model中需要操作两个数据库中的表
     * @param string $dbName 数据库名称
     * @param string $table 表明
     * @return bool|QueryBuilder
     */
    protected function changeDbTable(string $dbName = '', string $table = '')
    {
        try {
            return DB::connection($dbName ? $dbName : $this->changeConnection)->table($table ? $table : $this->changeTable);
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * 批量获取信息
     * @param array $where
     * @param string $select
     * @param array $orders
     * @return array
     */
    public function getInfoBatch(array $where, $select = '*', array $orders = [], $page = 0, $pageSize = 0)
    {
        $table = $this->getBaseTable()->select($select);

        if ($where) {
            $table->where($where);
        }

        if ($orders) {
            foreach ($orders as $key => $value) {
                $table->orderBy($key, $value);
            }
        }
        if ($page) {
            $pageSize = $pageSize ? $pageSize : $this->perPage;
            return $table->paginate($pageSize, ['*'], 'page', $page)->toArray();
        }

        return $table->get()->toArray();
    }

    public function select($sql)
    {
        $list  = $this->getConnection()->select($sql);
        // $list =  DB::connection($this->connection)->select($sql) ;
        return $this->Object2Arr($list) ;
    }
    // 执行sql insert or update
    public function exec($sql) {
       $result =   $this->getConnection()->getPdo()->exec($sql) ;
       if(stripos($sql ,'insert') !== false && $result) {
           return $this->getConnection()->getPdo()->lastInsertId() ;
       }
       return $result ;
    }

    public function beginTransaction()
    {
        // DB::beginTransaction();
        $this->getConnection()->getPdo()->beginTransaction() ;
    }
    public function commit()
    {
       // DB::commit();
        $this->getConnection()->getPdo()->commit() ;
    }
    public function rollBack()
    {
        // DB::rollBack();
        $this->getConnection()->getPdo()->rollBack() ;
    }
}
