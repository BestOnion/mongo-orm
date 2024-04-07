Hyperf MongoDB
===============
[![Latest Stable Version](https://poser.pugx.org/hyperf/mongodb/v)](https://packagist.org/packages/hyperf/mongodb)
[![Total Downloads](https://poser.pugx.org/hyperf/mongodb/downloads)](https://packagist.org/packages/hyperf/mongodb)
[![GitHub license](https://img.shields.io/github/license/hyperf/mongodb)

基于reasno/fastmongo 实现的协程化 MongoDB客户端ORM

```php
<?php

declare(strict_types=1);

namespace App\Mongo;

use App\Constants\GroupConstans;
use App\Es\Group\GroupEs;
use App\Exception\TransactionsException;
use App\Model\Base\Group;
use App\Model\Customercare\CustomercareModel;
use App\Model\UserModel;
use App\Services\Group\GroupMemberService;
use App\Services\Group\GroupService;
use App\Utils\GlobalContext;
use Carbon\Carbon;
use DateTime;
use DateTimeZone;
use fairwic\MongoOrm\DocumentArr;
use fairwic\MongoOrm\Elasticsearch\EsInstanceInterface;
use fairwic\MongoOrm\MongoBasic;
use fairwic\MongoOrm\MongoCollection;
use fairwic\MongoOrm\MongoModel;
use Hyperf\Context\ApplicationContext;
use Hyperf\Database\Model\Builder;
use Hyperf\Database\Model\Collection;
use Hyperf\GoTask\MongoClient\Type\InsertManyResult;
use Hyperf\Stringable\Str;
use Hyperf\Utils\HigherOrderTapProxy;
use Psr\Container\ContainerExceptionInterface;
use Psr\Container\NotFoundExceptionInterface;


class GroupMongo extends MongoModel implements EsInstanceInterface
{
    protected string $database_name = "pxb7_im";
    /**
     * mongo document name
     * @var string
     */
    protected string $document_name = "im_group";
    /**
     * es index name
     * @var string
     */
    protected string $index_name = "group";

    protected bool $isSoftDelete = true;


```