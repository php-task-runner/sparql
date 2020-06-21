<?php

declare(strict_types=1);

namespace TaskRunner\Sparql\TaskRunner\Commands;

use Consolidation\AnnotatedCommand\CommandData;
use OpenEuropa\TaskRunner\Commands\AbstractCommands;
use Robo\Collection\CollectionBuilder;
use Robo\Exception\AbortTasksException;
use Robo\Sparql\Tasks\Sparql\loadTasks;

/**
 * Provides commands for SPARQL backend.
 */
class SparqlCommands extends AbstractCommands
{
    use loadTasks;

    /**
     * Runs a list of queries against the SPARQL backend.
     *
     * @param string[] $queries
     *   A space separated list of SPARQL queries to be executed against the
     *   SPARQL endpoint.
     *
     * @return \Robo\Collection\CollectionBuilder
     *   The Robo collection builder.
     *
     * @command sparql:query
     */
    public function query(array $queries): CollectionBuilder
    {
        $config = $this->getConfig();
        $endpointUrl = "http://{$config->get('sparql.user')}:{$config->get('sparql.password')}@{$config->get('sparql.host')}:{$config->get('sparql.port')}/sparql";
        $queryTask = $this->taskSparqlQuery()->setEndpointUrl($endpointUrl);

        foreach ($queries as $query) {
            $queryTask->addQuery($query);
        }

        return $this->collectionBuilder()->addTask($queryTask);
    }

    /**
     * Validates the sparql:query command.
     *
     * @param \Consolidation\AnnotatedCommand\CommandData $commandData
     *   The command data object.
     *
     * @throws \Robo\Exception\AbortTasksException
     *   If no query arguments were provided.
     *
     * @hook validate sparql:query
     */
    public function validateQuery(CommandData $commandData): void
    {
        if (!$commandData->arguments()['queries']) {
            throw new AbortTasksException(
              "No queries were provided as command arguments"
            );
        }
    }

    /**
     * Empties the SPARQL backend.
     *
     * @return \Robo\Collection\CollectionBuilder
     *   The Robo collection builder.
     *
     * @throws \Robo\Exception\AbortTasksException
     *   When an error occurred while getting the graph list.
     *
     * @command sparql:empty
     */
    public function empty(): CollectionBuilder
    {
        $config = $this->getConfig();
        $endpointUrl = "http://{$config->get('sparql.user')}:{$config->get('sparql.password')}@{$config->get('sparql.host')}:{$config->get('sparql.port')}/sparql";
        $queryTask = $this->taskSparqlQuery()->setEndpointUrl($endpointUrl);

        $query = 'SELECT DISTINCT(?g) WHERE { GRAPH ?g { ?s ?p ?o } } ORDER BY ?g';
        $result = $queryTask->addQuery($query)->run();

        if (!$result->wasSuccessful()) {
            throw new AbortTasksException(
              "Exit with: '{$result->getMessage()}'."
            );
        }

        $graphs = $result->getData()['results'][0];

        if (!$graphs->count()) {
            $this->say("The SPARQL backend is already empty.");

            return $this->collectionBuilder();
        }

        foreach ($graphs as $graph) {
            if ($graph_uri = $graph->g->getUri()) {
                $queryTask->addQuery("CLEAR GRAPH <{$graph_uri}>;");
            }
        }

        return $this->collectionBuilder()->addTask($queryTask);
    }
}
