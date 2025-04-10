<?php

namespace App\Api\Model\ProjectMall;

class ProjectMall
{

    public function __construct()
    {
        $this->_db = app('api_db');
    }

    public function create($data)
    {
        return $this->_db->table('server_project_mall')->insertGetId($data);
    }

    public function findByProject($project_code)
    {
        if (empty($project_code)) {
            return array();
        }
        $info = $this->_db->table('server_project_mall')->where('project_code', $project_code)->first();
        return get_object_vars($info);
    }

    public function update($id, $data)
    {
        return $this->_db->table('server_project_mall')->where('id', $id)->update($data);
    }
}
