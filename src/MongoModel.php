<?php

namespace fairwic\MongoOrm;

class MongoModel extends MongoBasic
{
    //primary key id
    protected string $primaryKey = 'id';
    //database name
    protected string $database_name;
    //mongo document name
    protected string $document_name;
    //is soft delete
    protected bool $isSoftDelete = false;

    public function createIndex(array $index_name, $options = [])
    {
        return $this->getCollection()->createIndex($index_name, $options);
    }

    public function listIndexs()
    {
        return $this->getCollection()->listIndexes('im_group');
    }
}