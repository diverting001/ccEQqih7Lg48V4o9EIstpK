<?php
namespace App\Console\Commands;

use App\Api\Model\OpenPlatform\OpenPlatformConfig;
use App\Api\V1\Service\OpenPlatform\AdapterOpenPlatform;
use App\Api\V1\Service\OpenPlatform\OpenPlatform;
use Illuminate\Console\Command;

class OpenPlatformAutoRefreshToken extends Command
{
    protected $signature = 'refreshAccessToken';
    protected $description = '应用自动刷新access_token';

    const MAX_PAGE = 1000;
    private $_model;
    public function __construct()
    {
        parent::__construct();
        $this->_model = new OpenPlatformConfig();
    }

    public function handle()
    {
        $expires_time = time() - 20;
        $page = 1;
        /** @var OpenPlatform $service */
        $service = new AdapterOpenPlatform();
        while ($page < self::MAX_PAGE) {
            $list = $this->_model->getAdventList($expires_time, $page, 10);
            if (empty($list)) {
                echo "暂无\n";
                break;
            }

            foreach ($list as $item) {
                $params = [
                    'app_id'        => $item['app_id'],
                    'force_refresh' => true,
                ];
                $dataRes = $service->GetAccessToken($params);
                if (!$dataRes['status']) {
                    \Neigou\Logger::General('ThirdApplicationAutoRefreshToken', ['params' => $params, 'res' => $dataRes]);
                    continue;
                }
                echo $item['app_id']."-access_token刷新成功\n";
            }

            $page++;
        }
    }
}
