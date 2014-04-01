<?php

namespace PHPLOCParser;

use Cilex\Command\Command;

use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputOption;

use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class Build extends Command {

    protected $id = null;
    protected $parser = [];

    protected $sock = null;
    protected $output;

    protected function configure() {
        $this
            ->setName("build")
            ->setDescription("Build a task queue")
            ->addArgument("path", InputArgument::REQUIRED, "The path to parse")
            ->addOption("branch", null, InputOption::VALUE_REQUIRED, "The branch to use", "master");
    }

    public function __destruct() {
    }

    protected function execute(InputInterface $input, OutputInterface $output) {
        $db = $this->getService("db");
        $path = $input->getArgument("path");
        $output->writeln("Parsing Repository $path");
        $parser = new Parser($path);
        $output->writeln("Finding Commits:");
        $parser->getCommits($input->getOption("branch"), function($commit) use ($db, $path, $output) {
            $output->write(".");
            try {
                $db->insert("queue", ["path" => $path, "commit" => $commit]);
            } catch (\Doctrine\DBAL\DBALException $e) {
                if ($e->getPrevious()->getCode() != "23000") {
                    throw $e;
                }
                // ignore duplicates
            }
        });
        $output->writeln("finished");
    }

}
