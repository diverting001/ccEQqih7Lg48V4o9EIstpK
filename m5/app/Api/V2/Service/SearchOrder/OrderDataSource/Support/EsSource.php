<?php

namespace App\Api\V2\Service\SearchOrder\OrderDataSource\Support;

class EsSource extends EsPredefined{
    public function ParseSource($source_data): array
    {
        $source = [];

        foreach ($source_data as $val){
            $source[] = $val;
        }

        if (empty($source)){
            return [];
        }

        $res_source['_source']['includes'] = $source;

        return $res_source;
    }
}
