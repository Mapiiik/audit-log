<?php

namespace AuditLog\Test\Fixture;

use Cake\ElasticSearch\TestSuite\TestFixture;

class ElasticArticlesFixture extends TestFixture
{
    public string $connection = 'test_elastic';

    /**
     * The table/index for this fixture.
     *
     * @var string
     */
    public string $table = 'articles';

    /**
     * The mapping data.
     *
     * @var array
     */
    public array $schema = [
        'id' => ['type' => 'integer'],
        '@timestamp' => ['type' => 'date'],
        'transaction' => ['type' => 'text', 'index' => false],
        'type' => ['type' => 'text', 'index' => false],
        'primary_key' => ['type' => 'integer'],
        'source' => ['type' => 'text', 'index' => false],
        'parent_source' => ['type' => 'text', 'index' => false],
        'original' => [
            'properties' => [
                'id' => ['type' => 'integer'],
                'author_id' => ['type' => 'integer'],
                'title' => ['type' => 'text'],
                'body' => ['type' => 'text'],
                'published' => ['type' => 'text', 'index' => false],
                'published_date' => ['type' => 'date'],
            ],
        ],
        'changed' => [
            'properties' => [
                'id' => ['type' => 'integer'],
                'author_id' => ['type' => 'integer'],
                'title' => ['type' => 'text'],
                'body' => ['type' => 'text'],
                'published' => ['type' => 'text', 'index' => false],
                'published_date' => ['type' => 'date'],
            ],
        ],
    ];
}
