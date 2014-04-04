<?php

namespace PHPLOCParser;

use Cilex\Command\Command;

use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputOption;
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
            ->setDescription("Run in slave mode")
            ->addOption('max-requests', null, InputOption::VALUE_REQUIRED, "Max requests before respawning", 100)
            ->addOption('task-id', null, InputOption::VALUE_REQUIRED, "The task id to process", 0);
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
        $requests = 0;
        $maxRequests = (int) $input->getOption("max-requests");
        $id = (int) $input->getOption("task-id");
        do {
            $task = [];
            $db->transactional(function($conn) use (&$task, $id) {
                if ($id) {
                    $conn->executeUpdate("UPDATE queue SET locked = ?, machine = ?, pid = ?, modified = NOW() WHERE id = ? LIMIT 1", [$this->id, gethostname(), getmypid(), $id]);
                    $task = $conn->fetchAssoc("SELECT * FROM queue WHERE id = ? LIMIT 1", [$id]);
                } else {
                    $conn->executeUpdate("UPDATE queue SET locked = ?, machine = ?, pid = ?, modified = NOW() WHERE locked = 0 LIMIT 1", [$this->id, gethostname(), getmypid()]);
                    $task = $conn->fetchAssoc("SELECT * FROM queue WHERE locked = ? LIMIT 1", [$this->id]);
                }
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
                unset($row['machine']);
                unset($row['modified']);
                unset($row['pid']);
                try {
                    $conn->insert("results", $row);
                } catch (\Doctrine\DBAL\Exception\UniqueConstraintViolationException $e) {
                    // Ignore and move on
                }
                $conn->delete("queue", ["id" => $task['id']]);
            });
            if ($id) {
                return;
            }
            if ($requests++ >= $maxRequests) {
                $cmd = 'hhvm ' . escapeshellarg($_SERVER['PHP_SELF']) . ' --max-requests="' . $maxRequests . '" slave >> /tmp/process.log 2>&1 &';
                exec($cmd);
                return;
            }
        } while (true);
    }

    protected function runTask(array $task) {
        if (!isset($this->parser[$task['path']])) {
            $this->parser[$task['path']] = new Parser($task['path']);
        }
        return $this->parser[$task['path']]->parse($task['commit']);
    }
    
}
