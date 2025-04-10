<?php

namespace App\Api\Model;
use Illuminate\Database\QueryException;

class Database{

    public function __call($name, $arguments){
        try{
            return call_user_func_array([app('db'),$name],$arguments);
        }catch (QueryException $e){
            return false;
        }
    }

}
