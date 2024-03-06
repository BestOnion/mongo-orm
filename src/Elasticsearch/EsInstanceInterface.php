<?php

namespace fairwic\MongoOrm\Elasticsearch;

interface EsInstanceInterface
{
    public function getEsInstance(): Es;
    public function getEsFields(): array;
}