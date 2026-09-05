<?php

namespace Dynart\Disqus\Migration;

use Dynart\Micro\Entities\MigrationInterface;
use Dynart\Micro\Entities\QueryExecutor;
use Dynart\Disqus\Entity\DisqusThread;

/**
 * The version sorts after every core migration, which is all the interleaving needs
 */
class CreateDisqusThreadTable implements MigrationInterface {

    public function __construct(private QueryExecutor $queryExecutor) {}

    public function version(): string {
        return '2026_09_05_001_create_disqus_thread_table';
    }

    public function up(): void {
        $this->queryExecutor->createTable(DisqusThread::class, true);
    }
}
