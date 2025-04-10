<?php

namespace App\Api\V3\Service\Image;

use Obs\ObsClient;
use Neigou\Logger;

class Obs implements UploadInterface
{
    public function upload($name, $file, $path)
    {
        $config = [
            'key' => config('neigou.OBS_KEY'),
            'secret' => config('neigou.OBS_SECRET'),
            'endpoint' => config('neigou.OBS_ENDPOINT'),
        ];
        $obsClient = new ObsClient($config);
        $bucket = config('neigou.OBS_BUCKET');
        $orgPath = 'public/' . $path . '/' . $name;
        try {
            $resp = $obsClient->putObject([
                'Bucket' => $bucket,
                'Key' => $orgPath,
                'SourceFile' => $file
            ]);
            $obsClient->close();
            return config('neigou.CDN_WEB_NEIGOU_WWW') . '/' . $orgPath;
        } catch (Obs\Common\ObsException $obsException) {
            Logger::General('upload.obs.err', ['remark' => 'obs_up_err', 'config' => $config, 'bucket' => $bucket, 'res' => $resp, 'err' => $obsException->getMessage()]);
            $obsClient->close();
        }
    }
}
