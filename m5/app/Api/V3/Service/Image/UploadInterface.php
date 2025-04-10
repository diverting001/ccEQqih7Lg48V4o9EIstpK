<?php

namespace App\Api\V3\Service\Image;
interface UploadInterface
{
    public function upload($name, $file, $path);
}
