<?php
declare(strict_types=1);

/**
 * Abstract schema for CakePHP tests.
 *
 * This format resembles the existing fixture schema
 * and is converted to SQL via the Schema generation
 * features of the Database package.
 */
return [
    [
        'table' => 'articles',
        'columns' => [
            'id' => [
                'type' => 'integer',
            ],
            'author_id' => [
                'type' => 'integer',
                'null' => true,
            ],
            'title' => [
                'type' => 'string',
                'null' => true,
            ],
            'body' => 'text',
            'published' => [
                'type' => 'string',
                'length' => 1,
                'default' => 'N',
            ],
        ],
        'constraints' => [
            'primary' => [
                'type' => 'primary',
                'columns' => [
                    'id',
                ],
            ],
        ],
    ],
    [
        'table' => 'comments',
        'columns' => [
            'id' => [
                'type' => 'integer',
            ],
            'article_id' => [
                'type' => 'integer',
                'null' => false,
            ],
            'user_id' => [
                'type' => 'integer',
                'null' => false,
            ],
            'comment' => [
                'type' => 'text',
            ],
            'published' => [
                'type' => 'string',
                'length' => 1,
                'default' => 'N',
            ],
            'created' => [
                'type' => 'datetime',
            ],
            'updated' => [
                'type' => 'datetime',
            ],
        ],
        'constraints' => [
            'primary' => [
                'type' => 'primary',
                'columns' => [
                    'id',
                ],
            ],
        ],
    ],
    [
        'table' => 'authors',
        'columns' => [
            'id' => [
                'type' => 'integer',
            ],
            'name' => [
                'type' => 'string',
                'default' => null,
            ],
        ],
        'constraints' => [
            'primary' => [
                'type' => 'primary',
                'columns' => [
                    'id',
                ],
            ],
        ],
    ],
    [
        'table' => 'tags',
        'columns' => [
            'id' => [
                'type' => 'integer',
                'null' => false,
            ],
            'name' => [
                'type' => 'string',
                'null' => false,
            ],
            'description' => [
                'type' => 'text',
                'length' => 16777215,
            ],
            'created' => [
                'type' => 'datetime',
                'null' => true,
                'default' => null,
            ],
        ],
        'constraints' => [
            'primary' => [
                'type' => 'primary',
                'columns' => [
                    'id',
                ],
            ],
        ],
    ],
    [
        'table' => 'articles_tags',
        'columns' => [
            'article_id' => [
                'type' => 'integer',
                'null' => false,
            ],
            'tag_id' => [
                'type' => 'integer',
                'null' => false,
            ],
        ],
        'constraints' => [
            'unique_tag' => [
                'type' => 'primary',
                'columns' => [
                    'article_id',
                    'tag_id',
                ],
            ],
            'tag_id_fk' => [
                'type' => 'foreign',
                'columns' => [
                    'tag_id',
                ],
                'references' => [
                    'tags',
                    'id',
                ],
                'update' => 'cascade',
                'delete' => 'cascade',
            ],
        ],
    ],
];
