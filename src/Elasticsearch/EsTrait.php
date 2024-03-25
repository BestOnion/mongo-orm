<?php

namespace fairwic\MongoOrm\Elasticsearch;


use Psr\Container\ContainerExceptionInterface;
use Psr\Container\NotFoundExceptionInterface;

trait EsTrait
{
    use EsSyncTrait;

    protected bool $isUsedEs = false;

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

    /**
     * @param array $data
     * @return bool
     * @throws ContainerExceptionInterface
     * @throws NotFoundExceptionInterface
     */
    function searchable_data(array $data = []): bool
    {
        //sync data to es
        return $this->syncBatch($data);
    }
}
