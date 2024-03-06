<?php

namespace BestOnion\MongoOrm\Elasticsearch;

interface EsInstanceInterface
{
    public function getEsInstance(): Es;
    public function getEsFields(): array;
}