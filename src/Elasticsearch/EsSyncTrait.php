<?php

namespace fairwic\MongoOrm\Elasticsearch;

use App\Utils\Log;
use Psr\Container\ContainerExceptionInterface;
use Psr\Container\NotFoundExceptionInterface;

trait EsSyncTrait
{

    private function ensureEsInstanceInterface()
    {
        if (!($this instanceof EsInstanceInterface)) {
            throw new \InvalidArgumentException('The class must implement EsInstanceInterface');
        }
    }

    /**
     * 搜索es
     * @param array $data
     * @return array|mixed
     * @throws ContainerExceptionInterface
     * @throws NotFoundExceptionInterface
     */
    public function search(array $data)
    {
        $this->ensureEsInstanceInterface();

        $es = $this->getEsInstance();
        $index_data = [
            'index' => $this->index_name,
            'body' => $data,
        ];
        $data = $es->searchEs($index_data);
        $array = [];
        if ($data['hits']['total']['value'] > 0) {
            $array = $data['hits']['hits'];
        }
        return $array;

    }

    /**
     * Sync one data to Elasticsearch
     *
     * @param string $id
     * @param  $data
     * @return bool
     */
    public function searchable_one(string $id, mixed $data): bool
    {
        $this->ensureEsInstanceInterface();
        $es = $this->getEsInstance();
        $index_data = [
            'index' => $this->index_name,
            'id' => $id,
            'body' => $data,
        ];
        return $es->indexEs($index_data);
    }

    /**
     * Sync batch data to Elasticsearch
     * @param $data
     * @return bool
     */
    public function syncBatch($data): bool
    {
        $this->ensureEsInstanceInterface();

        $es = $this->getEsInstance();
        // 循环插入100条数据
        $params = [];
        foreach ($data as $item) {
            // bulk操作，先拼接一行index操作参数
            $params['body'][] = [
                'index' => [
                    '_index' => $this->index_name, // 索引名
                    '_id' => $item['id'], // 设置文档Id, 可以忽略Id, Es也会自动生成
                ]
            ];
            unset($item['id']);
            // 接着拼接index操作的内容，这里就是我们需要插入的文档内容
            $params['body'][] = $item;
        }
        if ($params) {
            return $es->bulk($params);
        }
        return false;
    }

    /**
     * delete batch data to Elasticsearch
     * @param array $ids
     * @return bool
     * @throws \Exception
     */
    public function deleteBatch(array $ids): bool
    {
        $this->ensureEsInstanceInterface();
        $es = $this->getEsInstance();
        // 循环插入100条数据
        $params = [];
        foreach ($ids as $item) {
            // bulk操作，先拼接一行index操作参数
            $params['body'][] = [
                'delete' => [
                    '_index' => $this->index_name, // 索引名
                    '_id' => $item, // 设置文档Id, 可以忽略Id, Es也会自动生成
                ]
            ];
        }
        if ($params) {
            return $es->bulk($params);
        }
        return false;
    }
}
