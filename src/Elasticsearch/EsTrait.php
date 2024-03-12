<?php

namespace fairwic\MongoOrm\Elasticsearch;


trait EsTrait
{
    use EsSyncTrait;
    protected bool $isUsedEs = true;

    /**
     * @param array $ids
     * @return bool
     * @throws \Exception
     */
    function delete_es(array $ids): bool
    {
        return $this->deleteBatch($ids);
    }



    /**
     * @param $ids
     * @return bool
     * @throws \Exception
     */
    function searchableMany($ids): bool
    {
        //get data from mongo
        $data = $this->whereIn('id', $ids)->select($this->getEsFields())->get();
        //sync data to es
        return $this->syncBatch($data);
    }
}
