<?php

declare(strict_types=1);

namespace TaskRunner\Sparql\Tests;

use PHPUnit\Framework\TestCase;
use Predis\Client;
use Symfony\Component\Yaml\Yaml;

/**
 * @coversDefaultClass \TaskRunner\Sparql\TaskRunner\Commands\SparqlCommands
 */
class SparqlCommandsTest extends TestCase
{
    /**
     * @var \EasyRdf\Sparql\Client
     */
    protected $client;

    /**
     * @var string
     */
    protected $sandboxDir;

    /**
     * {@inheritdoc}
     */
    protected function setUp(): void
    {
        parent::setUp();

        $this->client = new \EasyRdf\Sparql\Client($this->getSparqlEndpoint() . '/sparql');
        $this->sandboxDir = realpath(__DIR__ . '/../sandbox');

        // SPARQL commands need the connection as configs.
        $config = [
          'sparql' => parse_url($this->getSparqlEndpoint()),
        ];
        file_put_contents("{$this->sandboxDir}/runner.yml", Yaml::dump($config));
    }

    /**
     * @covers ::query
     */
    public function testQuery(): void
    {
        exec(__DIR__ . "/../../vendor/bin/run sparql:query" .
            " 'INSERT INTO <http://example.com/graph> { <http://example.com/subject> <http://example.com/predicate> \"test\" . }'" .
            " --working-dir={$this->sandboxDir}");

        $results = $this->client->query('SELECT ?g ?s ?p ?o WHERE { GRAPH ?g { ?s ?p ?o } }');
        $this->assertSame('http://example.com/subject', $results[0]->s->getUri());
        $this->assertSame('http://example.com/predicate', $results[0]->p->getUri());
        $this->assertSame('test', $results[0]->o->getValue());

        exec(__DIR__ . "/../../vendor/bin/run sparql:query" .
             " 'CLEAR GRAPH <http://example.com/graph>'" .
             " --working-dir={$this->sandboxDir}");
    }

    /**
     * @covers ::empty
     */
    public function testEmpty(): void
    {
        $this->client->query('INSERT INTO <http://example.com/graph1> { <http://example.com/subject1> <http://example.com/predicate> "test 1" . }');
        $this->client->query('INSERT INTO <http://example.com/graph2> { <http://example.com/subject2> <http://example.com/predicate> "test 2" . }');
        $results = $this->client->query('SELECT ?g ?s ?p ?o WHERE { GRAPH ?g { ?s ?p ?o } }');
        $this->assertSame('http://example.com/graph1', $results[0]->g->getUri());
        $this->assertSame('http://example.com/subject1', $results[0]->s->getUri());
        $this->assertSame('http://example.com/predicate', $results[0]->p->getUri());
        $this->assertSame('test 1', $results[0]->o->getValue());
        $this->assertSame('http://example.com/graph2', $results[1]->g->getUri());
        $this->assertSame('http://example.com/subject2', $results[1]->s->getUri());
        $this->assertSame('http://example.com/predicate', $results[1]->p->getUri());
        $this->assertSame('test 2', $results[1]->o->getValue());

        exec(__DIR__ . "/../../vendor/bin/run sparql:empty --working-dir={$this->sandboxDir}");

        $results = $this->client->query('SELECT ?g ?s ?p ?o WHERE { GRAPH ?g { ?s ?p ?o } }');
        $this->assertSame(0, $results->numRows());
    }

    /**
     * @return string
     */
    protected function getSparqlEndpoint(): string
    {
        return getenv('SPARQL_ENDPOINT') ?: 'http://dba:dba@localhost:8890';
    }
}
