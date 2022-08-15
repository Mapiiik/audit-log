<?php
declare(strict_types=1);

namespace AuditLog\Shell;

use Cake\Console\ConsoleOptionParser;
use Cake\Console\Shell;
use Cake\Datasource\ConnectionManager;
use Cake\Utility\Inflector;
use Elastica\Mapping;
use Elastica\Request;

/**
 * Exposes a shell command to create the required Elastic Search mappings.
 */
class ElasticMappingShell extends Shell
{
    /**
     * @inheritDoc
     */
    public function getOptionParser(): ConsoleOptionParser
    {
        return parent::getOptionParser()
            ->setDescription(
                'Creates type mappings in elastic search for the tables you want tracked with audit logging'
            )
            ->addArgument('table', [
                'short' => 't',
                'help' => 'The name of the database table to inspect and create a mapping for',
                'required' => true,
            ])
            ->addOption('use-templates', [
                'short' => 'u',
                'help' => 'Creates mapping templates instead of creating the mapping directly',
                'boolean' => true,
            ])
            ->addOption('dry-run', [
                'short' => 'd',
                'help' => 'Do not create the mapping, just output it to the screen',
                'boolean' => true,
            ]);
    }

    /**
     * Creates the elastic search mapping for the provided table, or just prints it out
     * to the screen if the `dry-run` option is provided.
     *
     * @param string $table The table name to inspect and create a mapping for
     * @return bool
     */
    public function main($table)
    {
        $table = $this->fetchTable($table);
        $schema = $table->getSchema();
        $mapping = [
            '@timestamp' => [
                'type' => 'date',
                'format' => 'basic_t_time_no_millis||dateOptionalTime||basic_date_time||ordinal_date_time_no_millis'
                            . '||yyyy-MM-dd HH:mm:ss',
            ],
            'transaction' => ['type' => 'text', 'index' => false],
            'type' => ['type' => 'text', 'index' => false],
            'primary_key' => ['type' => 'text', 'index' => false],
            'source' => ['type' => 'text', 'index' => false],
            'parent_source' => ['type' => 'text', 'index' => false],
            'original' => [
                'properties' => [],
            ],
            'changed' => [
                'properties' => [],
            ],
            'meta' => [
                'properties' => [
                    'ip' => ['type' => 'text', 'index' => false],
                    'url' => ['type' => 'text', 'index' => false],
                    'user' => ['type' => 'text', 'index' => false],
                    'app_name' => ['type' => 'text', 'index' => false],
                ],
            ],
        ];

        $properties = [];
        foreach ($schema->columns() as $column) {
            $properties[$column] = $this->mapType($schema, $column);
        }

        $indexName = $table->getTable();
        $typeName = Inflector::singularize(str_replace('%s', '', $indexName));

        if ($table->hasBehavior('AuditLog')) {
            $whitelist = (array)$table->getBehavior('AuditLog')->getConfig('whitelist');
            $blacklist = (array)$table->getBehavior('AuditLog')->getConfig('blacklist');
            $properties = empty($whitelist) ? $properties : array_intersect_key($properties, array_flip($whitelist));
            $properties = array_diff_key($properties, array_flip($blacklist));
            $indexName = $table->getBehavior('AuditLog')->getConfig('index') ?: $indexName;
            $typeName = $table->getBehavior('AuditLog')->getConfig('type') ?: $typeName;
        }

        $mapping['original']['properties'] = $mapping['changed']['properties'] = $properties;
        /** @var \Elastica\Client $client */
        $client = ConnectionManager::get('auditlog_elastic')->getDriver();
        $index = $client->getIndex(sprintf($indexName, '-' . gmdate('Y.m.d')));
        //$type = $index->getType($typeName);
        $elasticMapping = new Mapping();
        // $elasticMapping->setType($type);
        $elasticMapping->setProperties($mapping);

        if ($this->params['dry-run']) {
            $this->out(json_encode($elasticMapping->toArray(), JSON_PRETTY_PRINT));

            return true;
        }

        if ($this->params['use-templates']) {
            $template = [
                'template' => sprintf($indexName, '*'),
                'mappings' => $elasticMapping->toArray(),
            ];
            $response = $client->request('_template/template_', Request::PUT, $template);
            $this->out('<success>Successfully created the mapping template</success>');

            return $response->isOk();
        }

        if (!$index->exists()) {
            $index->create();
        }

        $elasticMapping->send($index);
        $this->out('<success>Successfully created the mapping</success>');

        return true;
    }

    /**
     * Returns the correct mapping properties for a table column.
     *
     * @param \Cake\Database\Schema\TableSchemaInterface $schema The table schema
     * @param string $column The column name to instrospect
     * @return array
     */
    protected function mapType($schema, $column)
    {
        $baseType = $schema->baseColumnType($column);
        switch ($baseType) {
            case 'uuid':
                return ['type' => 'text', 'index' => false, 'null_value' => '_null_'];
            case 'integer':
                return ['type' => 'integer', 'null_value' => pow(-2, 31)];
            case 'date':
                return [
                    'type' => 'date',
                    'format' => 'dateOptionalTime||basic_date||yyy-MM-dd',
                    'null_value' => '0001-01-01',
                ];
            case 'datetime':
            case 'timestamp':
                return [
                    'type' => 'date',
                    'format' => 'basic_t_time_no_millis||dateOptionalTime||basic_date_time||ordinal_date_time_no_millis'
                                . '||yyyy-MM-dd HH:mm:ss||basic_date', 'null_value' => '0001-01-01 00:00:00',
                ];
            case 'float':
            case 'decimal':
                return ['type' => 'float', 'null_value' => pow(-2, 31)];
            case 'boolean':
                return ['type' => 'boolean'];
            default:
                return [
                'type' => 'text',
                'fields' => [
                    $column => ['type' => 'text'],
                    'raw' => ['type' => 'text', 'index' => false],
                ],
            ];
        }
    }
}
