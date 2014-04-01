<?php

namespace PHPLOCParser;

use Cilex\Command\Command;

use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class Setup extends Command {

    protected $version = 2;

    protected function configure() {
        $this
            ->setName("setup")
            ->setDescription("Run setup");
    }

    protected function execute(InputInterface $input, OutputInterface $output) {
        try {
            $version = $this->getService("db")->query("SELECT * FROM schema_version WHERE id = 1");
            if ($version && ($row = $version->fetch())) {
                $output->writeln("Found schema version {$row['version']}");
                for ($i = $row['version']; $i < $this->version; $i++) {
                    $output->writeln("Upgrading to version $i");
                    $method = "migrateFrom$i";
                    $this->$method();
                }
            }
        } catch (\Exception $e) {
            $output->writeln("Table not found, creating schema");
            $this->createSchema();
        }
    }

    protected function createSchema() {
        $schema = new \Doctrine\DBAL\Schema\Schema;
        $version = $schema->createTable("schema_version");
        $version->addColumn("id", "integer");
        $version->addColumn("version", "integer");
        $version->setPrimaryKey(["id"]);
        
        $queue = $schema->createTable("queue");
        $queue->addColumn("id", "integer", ["unsigned" => true, "autoincrement" => true]);
        $queue->addColumn("path", "string", ["length" => "255"]);
        $queue->addColumn("commit", "string", ["length" => "255"]);
        $queue->addColumn("locked", "integer", ["default" => 0]);
        $queue->setPrimaryKey(["id"]);
        $queue->addUniqueIndex(["path", "commit"]);

        $db = $this->getService("db");
        $sql = $schema->toSql($db->getDatabasePlatform());
        foreach ($sql as $q) {
            $db->query($q);
        }
        $db->query("INSERT INTO schema_version (id, version) VALUES (1, 1)");
        $this->migrateFrom1();
    }

    protected function migrateFrom1() {
        $schema = new \Doctrine\DBAL\Schema\Schema;

        $results = $schema->createTable("results");
        $results->addColumn("id", "integer", ["unsigned" => true, "autoincrement" => true]);
        $results->addColumn("path", "string", ["length" => "255"]);
        $results->addColumn("commit", "string", ["length" => "255"]);
        $results->addColumn("summary", "string", ["length" => "255"]);
        $results->addColumn("detail", "text");
        $results->addColumn("author_name", "string", ["length" => "255"]);
        $results->addColumn("author_email", "string", ["length" => "255"]);
        $results->addColumn("time", "datetime");
        $results->addColumn("parent", "string", ["length" => "255"]);
        $results->addColumn("files", "integer");
        $results->addColumn("loc", "integer");
        $results->addColumn("lloc", "integer");
        $results->addColumn("llocClasses", "integer");
        $results->addColumn("llocFunctions", "integer");
        $results->addColumn("llocGlobal", "integer");
        $results->addColumn("cloc", "integer");
        $results->addColumn("ccn", "integer");
        $results->addColumn("ccnMethods", "integer");
        $results->addColumn("interfaces", "integer");
        $results->addColumn("traits", "integer");
        $results->addColumn("classes", "integer");
        $results->addColumn("abstractClasses", "integer");
        $results->addColumn("concreteClasses", "integer");
        $results->addColumn("functions", "integer");
        $results->addColumn("namedFunctions", "integer");
        $results->addColumn("anonymousFunctions", "integer");
        $results->addColumn("methods", "integer");
        $results->addColumn("publicMethods", "integer");
        $results->addColumn("nonPublicMethods", "integer");
        $results->addColumn("nonStaticMethods", "integer");
        $results->addColumn("staticMethods", "integer");
        $results->addColumn("constants", "integer");
        $results->addColumn("classConstants", "integer");
        $results->addColumn("globalConstants", "integer");
        $results->addColumn("testClasses", "integer");
        $results->addColumn("testMethods", "integer");
        $results->addColumn("ccnByLloc", "float");
        $results->addColumn("ccnByNom", "float");
        $results->addColumn("llocByNoc", "float");
        $results->addColumn("llocByNom", "float");
        $results->addColumn("llocByNof", "integer");
        $results->addColumn("methodCalls", "integer");
        $results->addColumn("staticMethodCalls", "integer");
        $results->addColumn("instanceMethodCalls", "integer");
        $results->addColumn("attributeAccesses", "integer");
        $results->addColumn("staticAttributeAccesses", "integer");
        $results->addColumn("instanceAttributeAccesses", "integer");
        $results->addColumn("globalAccesses", "integer");
        $results->addColumn("globalVariableAccesses", "integer");
        $results->addColumn("superGlobalVariableAccesses", "integer");
        $results->addColumn("globalConstantAccesses", "integer");
        $results->addColumn("directories", "integer");
        $results->addColumn("namespaces", "integer");
        $results->addColumn("ncloc", "integer");

        $results->setPrimaryKey(["id"]);
        $results->addUniqueIndex(["path", "commit"]);
        $db = $this->getService("db");
        $sql = $schema->toSql($db->getDatabasePlatform());
        foreach ($sql as $q) {
            $db->query($q);
        }
        $db->update("schema_version", ["version" => 2], ["id" => 1]);      
    }

}
