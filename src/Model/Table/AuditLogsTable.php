<?php
declare(strict_types=1);

namespace AuditLog\Model\Table;

use Cake\ORM\Table;

class AuditLogsTable extends Table
{
    /**
     * Initialize method
     *
     * @param array $config The configuration for the Table.
     * @return void
     */
    public function initialize(array $config): void
    {
        parent::initialize($config);

        $this->setTable('audit_logs');
        $this->setPrimaryKey('id');

        $this->setSchema([
            'id' => 'integer',
            'transaction' => 'string',
            'type' => 'string',
            'primary_key' => 'integer',
            'display_value' => 'string',
            'source' => 'string',
            'parent_source' => 'string',
            'original' => 'string',
            'changed' => 'string',
            'meta' => 'string',
            'created' => 'datetime',
        ]);
    }
}
