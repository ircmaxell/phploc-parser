<?php

namespace PHPLOCParser;

use org\bovigo\vfs\vfsStream;
use SebastianBergmann\FinderFacade\FinderFacade;
use SebastianBergmann\PHPLOC\Analyser;
use GitTree;
use GitCommit;
use Git;
use RecursiveIteratorIterator;
use RecursiveDirectoryIterator;


class Parser {
    protected static $vfsRoot;
    protected $git;
    protected $root;
    protected $allowedExtensions = [
        "php",
        "inc",
        "module",
    ];
    
    public function __construct($dir) {
        if (!in_array("vfs", stream_get_wrappers())) {
            self::$vfsRoot = vfsStream::setup("root");
        }
        $this->setup($dir);
    }

    public function __clone() {
        // clone the filesystem
        $this->setup($this->root->url());
    }

    public function addAllowedExtension($ext) {
        $this->allowedExtensions[] = $ext;
    }

    public function getCommits($fromBranch = "master", callable $cb) {
        $commit = $this->git->getTip($fromBranch);
        $object = $this->git->getObject($commit);
        return $this->findParents($object, $cb);
    }

    public function parse($commit) {
        $root = $this->checkout($commit);
        $commitInfo = $this->findCommitInfo($commit);
        $files = $this->findFiles($root);
        $analyser = new Analyser;
        $ret = $analyser->countFiles($files, true);
        static::$vfsRoot->removeChild($root);
        return array_merge($commitInfo, $ret);
    }   

    private function findCommitInfo($commit) {
        $obj = $this->git->getObject(sha1_bin($commit));
        return [
            "summary" => $obj->summary,
            "detail" => $obj->detail,
            "author_name" => $obj->author->name,
            "author_email" => $obj->author->email,
            "time" => date("Y-m-d H:i:s", $obj->author->time),
            "parent" => count($obj->parents) === 1 ? "" : sha1_hex($obj->parents[0]),
        ];
    }

    private function checkout($commit) {
        $obj = $this->git->getObject(sha1_bin($commit));
        $root = $this->mkRandomDir();
        // checkout commit into root
        if ($obj instanceof GitCommit) {
            $tree = $this->git->getObject($obj->tree);
            $this->checkoutTree($tree, $root);
        } elseif ($obj instanceof GitTree) {
            $this->checkoutTree($obj, $root);
        }
        return $root;
    }
    
    private function checkoutTree(GitTree $tree, $root) {
        foreach ($tree->nodes as $name => $node) {
            if ($node->is_dir) {
                $dir  = vfsStream::newDirectory($name);
                $root->addChild($dir);
                $this->checkoutTree($this->git->getObject($node->object), $dir);
            } else {
                file_put_contents($root->url() . '/' . $name, $this->git->getObject($node->object)->data);
            }
        }
    }

    private function findFiles($root) {
        $regex = "(\.(" . implode("|", $this->allowedExtensions) . ')$)i';
        $files = [];
        foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root->url())) as $file) {
            if (strpos($file->getPathname(), "/vendor/") !== false) {
                continue;
            }
            if (!preg_match($regex, $file->getPathname())) {
                continue;
            }
            if ($file->isFile()) {
                $files[] = $file->getPathname();
            }
        }
        return $files;
    }

    private function findParents($object, callable $cb) {
        $cb(sha1_hex($object->getName()));
        $found = [$object->getName() => true];
        $queue = [$object];
        while ($obj = array_pop($queue)) {
            foreach ($obj->parents as $parent) {
                if (!isset($found[$parent])) {
                    $found[$parent] = true;
                    $queue[] = $this->git->getObject($parent);
                    $cb(sha1_hex($parent));
                }
            }
        }
    }

    private function mkRandomDir() {
        $tmpdir = "project";
        do {
            $tmpdir .= rand(0, 9);
        } while (file_exists(vfsStream::url("root/$tmpdir")));
        $root = vfsStream::newDirectory($tmpdir);
        self::$vfsRoot->addChild($root);
        return $root;
    }

    private function setup($dir) {
        // recursively copy directory into virtual streami
        $this->git = new Git($dir . '/.git');

    }
}
