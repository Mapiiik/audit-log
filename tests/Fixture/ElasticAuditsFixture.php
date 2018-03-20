<?php

namespace AuditStash\Test\Fixture;

use Cake\ElasticSearch\TestSuite\TestFixture;

class ElasticAuditsFixture extends TestFixture
{

    public $connection = 'test_elastic';

    /**
     * The table/index for this fixture.
     *
     * @var string
     */
    public $table = 'audits';

    /**
     * The mapping data.
     *
     * @var array
     */
    public $schema = [
        'id' => ['type' => 'integer'],
        '@timestamp' => ['type' => 'date'],
        'transaction' => ['type' => 'text', 'index' => 'not_analyzed'],
        'type' => ['type' => 'text', 'index' => 'not_analyzed'],
        'primary_key' => ['type' => 'integer'],
        'source' => ['type' => 'text', 'index' => 'not_analyzed'],
        'parent_source' => ['type' => 'text', 'index' => 'not_analyzed'],
        'original' => [
            'properties' => [
                'id' => ['type' => 'integer'],
                'name' => ['type' => 'text'],
            ]
        ],
        'changed' => [
            'properties' => [
                'id' => ['type' => 'integer'],
                'name' => ['type' => 'text'],
            ]
        ],
    ];
}
