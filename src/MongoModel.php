<?php

namespace BestOnion\MongoOrm;

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
}