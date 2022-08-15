<?php
declare(strict_types=1);

/**
 * Abstract schema for CakePHP ElsticSearch tests.
 */
return [
    [
        // The name of the index and mapping.
        'name' => 'articles',
        // The schema for the mapping.
        'mapping' => [
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
        ],
    ],
    [
        // The name of the index and mapping.
        'name' => 'audits',
        // The schema for the mapping.
        'mapping' => [
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
                    'name' => ['type' => 'text'],
                ],
            ],
            'changed' => [
                'properties' => [
                    'id' => ['type' => 'integer'],
                    'name' => ['type' => 'text'],
                ],
            ],
        ],
    ],
    [
        // The name of the index and mapping.
        'name' => 'authors',
        // The schema for the mapping.
        'mapping' => [
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
                    'name' => ['type' => 'text'],
                ],
            ],
            'changed' => [
                'properties' => [
                    'id' => ['type' => 'integer'],
                    'name' => ['type' => 'text'],
                ],
            ],
        ],
    ],
    [
        // The name of the index and mapping.
        'name' => 'tags',
        // The schema for the mapping.
        'mapping' => [
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
                    'name' => ['type' => 'text'],
                ],
            ],
            'changed' => [
                'properties' => [
                    'id' => ['type' => 'integer'],
                    'name' => ['type' => 'text'],
                ],
            ],
        ],
    ],
];
