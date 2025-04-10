#!/bin/bash

cd "$(dirname "$0")"
php artisan queue:restart
