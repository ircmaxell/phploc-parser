<?php

namespace PHPLOCParser;

use Cilex\Command\Command;

use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class Slave extends Command {

    protected $id = null;
    protected $parser = [];

    protected $sock = null;
    protected $output;

    protected function configure() {
        $this
            ->setName("slave")
            ->setDescription("Run in slave mode");
    }

    public function __destruct() {
        $this->unlock();
    }

    public function unlock() {
        if ($this->id) {
            $this->getService("db")->executeUpdate("UPDATE queue SET locked = 0 WHERE locked = ?", [$this->id]);
        }
    }

    protected function execute(InputInterface $input, OutputInterface $output) {
        $this->id = mt_rand(1, mt_getrandmax());
        register_shutdown_function([$this, "unlock"]);
        $db = $this->getService("db");
        do {
            $task = [];
            $db->transactional(function($conn) use (&$task) {
                $conn->executeUpdate("UPDATE queue SET locked = ? WHERE locked = 0 LIMIT 1", [$this->id]);
                $task = $conn->fetchAssoc("SELECT * FROM queue WHERE locked = ? LIMIT 1", [$this->id]);
            });
            if (!$task) {
                $output->writeln("No more tasks, exiting");
                return;
            }
            $output->writeln("Running task: {$task['commit']} for {$task['path']}");
            $result = $this->runTask($task);
            $db->transactional(function($conn) use ($task, $result) {
                $row = array_merge($task, $result);
                unset($row['locked']);
                $conn->insert("results", $row);
                $conn->delete("queue", ["id" => $task['id']]);
            });

        } while (true);
    }

    protected function runTask(array $task) {
        if (!isset($this->parser[$task['path']])) {
            $this->parser[$task['path']] = new Parser($task['path']);
        }
        return $this->parser[$task['path']]->parse($task['commit']);
    }
    
}
