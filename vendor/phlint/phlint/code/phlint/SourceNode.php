<?php

namespace phlint;

use \PhpParser\Node;
use \PhpParser\NodeAbstract;

class SourceNode extends NodeAbstract
{
    /** @var Node[] Statements */
    public $stmts;

    /**
     * Constructs a source node.
     *
     * @param array $stmts Statements
     */
    public function __construct ($stmts) {
        parent::__construct($stmts);
        $this->stmts = $stmts;
    }

    public function getSubNodeNames () {
        return ['stmts'];
    }
}
