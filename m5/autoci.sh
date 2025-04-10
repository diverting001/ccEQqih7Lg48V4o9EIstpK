#!/bin/bash

echo "创建$1 配置文件软连接"

if [ -f .env.$1 ];then
    ln -snf .env.$1 .env
else
    echo "配置文件 .env.$1 不存在" 
fi
