<?php

namespace fairwic\MongoOrm\Elasticsearch;

trait EsSyncTrait
{
    /**
     * Sync data to Elasticsearch
     *
     * @param array $data
     * @return
     * @throws \Exception
     */
    public function getEs(array $data)
    {
        if ($this instanceof EsInstanceInterface) {
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
        throw new \Exception('EsInstanceInterface not implemented');
    }

    /**
     * Sync data to Elasticsearch
     *
     * @param string $id
     * @param array $data
     * @return bool
     * @throws \Exception
     */
    public function syncOneToEs(string $id, array $data): bool
    {
        if ($this instanceof EsInstanceInterface) {
            $es = $this->getEsInstance();
            $index_data = [
                'index' => $this->index_name,
                'id' => $id,
                'body' => $data,
            ];
            return $es->indexEs($index_data);
        }
        throw new \Exception('EsInstanceInterface not implemented');
    }

    /**
     * Sync data to Elasticsearch
     *
     * @param array $data
     * @return bool
     * @throws \Exception
     */
    public function syncBatch(array $data): bool
    {
        if ($this instanceof EsInstanceInterface) {
            $es = $this->getEsInstance();
            // 循环插入100条数据
            $params = [];
            foreach ($data as $item) {
                // bulk操作，先拼接一行index操作参数
                $params['body'][] = [
                    'index' => [
                        '_index' => $this->document_name, // 索引名
                        '_id' => $item['id'], // 设置文档Id, 可以忽略Id, Es也会自动生成
                    ]
                ];
                unset($item['id']);
                // 接着拼接index操作的内容，这里就是我们需要插入的文档内容
                $params['body'][] = $item;
            }
            if ($params) {
                return $es->updateMany($params);
            }
            return false;
        }
        throw new \Exception('EsInstanceInterface not implemented');
    }

}
