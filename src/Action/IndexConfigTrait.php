<?php
declare(strict_types=1);

namespace AuditLog\Action;

use DateTime;

trait IndexConfigTrait
{
    /**
     * Configures the index to use in elastic search by completing the placeholders with the current date
     * if needed.
     *
     * @param \Cake\ElasticSearch\Index $repository A index in elastic search.
     * @param \Cake\Http\ServerRequest $request Server Request.
     * @return void
     */
    protected function _configIndex($repository, $request)
    {
        $client = $repository->getConnection()->getDriver();
        $indexTemplate = $repository->getName();
        $client->setConfig(['index' => sprintf($indexTemplate, '*')]);

        if ($request->getQuery('at')) {
            $client->setConfig([
                'index' => sprintf($indexTemplate, (new DateTime($request->getQuery('at')))->format('-Y.m.d')),
            ]);
        }
    }
}
