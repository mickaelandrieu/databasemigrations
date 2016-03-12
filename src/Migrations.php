<?php

/*
 * This file is part of the Active Collab DatabaseMigrations project.
 *
 * (c) A51 doo <info@activecollab.com>. All rights reserved.
 */

namespace ActiveCollab\DatabaseMigrations;

use ActiveCollab\DatabaseConnection\ConnectionInterface;
use ActiveCollab\DatabaseMigrations\Finder\FinderInterface;
use ActiveCollab\DatabaseMigrations\Migration\MigrationInterface;
use Psr\Log\LoggerInterface;
use ReflectionClass;
use RuntimeException;

/**
 * @package ActiveCollab\DatabaseMigrations
 */
class Migrations implements MigrationsInterface
{
    /**
     * @var ConnectionInterface
     */
    private $connection;

    /**
     * @var FinderInterface
     */
    private $finder;

    /**
     * @var LoggerInterface
     */
    private $log;

    /**
     * @param ConnectionInterface $connection
     * @param FinderInterface     $finder
     * @param LoggerInterface     $log
     */
    public function __construct(ConnectionInterface &$connection, FinderInterface &$finder, LoggerInterface &$log)
    {
        $this->connection = $connection;
        $this->finder = $finder;
        $this->log = $log;
    }

    /**
     * @var bool
     */
    private $migrations_are_found = false;

    /**
     * @var MigrationInterface[]
     */
    private $migrations = [];

    /**
     * {@inheritdoc}
     */
    public function getMigrations()
    {
        if (!$this->migrations_are_found) {
            $migration_class_file_path_map = $this->finder->getMigrationClassFilePathMap();

            $migrations_by_class = $this->getMigrationInstances($migration_class_file_path_map);

            foreach ($migrations_by_class as $migration_class => $migration) {
                if (empty($this->migrations[$migration_class])) {
                    foreach ($migration->getExecuteAfter() as $execute_after_migration_file_path) {
                        $execute_after_migration_class = $this->getMigrationClassByMigrationPath($execute_after_migration_file_path, $migration_class_file_path_map);

                        if (empty($this->migrations[$execute_after_migration_class])) {
                            if (isset($migrations_by_class[$execute_after_migration_class])) {
                                $this->migrations[$execute_after_migration_class] = $migrations_by_class[$execute_after_migration_class];
                            } else {
                                throw new RuntimeException("Migration '$execute_after_migration_class' not found");
                            }
                        }
                    }

                    $this->migrations[$migration_class] = $migration;
                }
            }

            $this->migrations = array_values($this->migrations); // Reindex and remove class name as key. 0..n will work.
            $this->migrations_are_found = true;
        }

        return $this->migrations;
    }

    /**
     * Return an array of MigrationInterface instances indexed by class name.
     *
     * @param  array                $migration_class_file_path_map
     * @return MigrationInterface[]
     */
    private function getMigrationInstances(array $migration_class_file_path_map)
    {
        $result = [];

        foreach ($migration_class_file_path_map as $migration_class => $migration_file_path) {
            if (is_file($migration_file_path)) {
                require_once $migration_file_path;

                if (class_exists($migration_class, false)) {
                    $reflection = new ReflectionClass($migration_class);

                    if ($reflection->implementsInterface(MigrationInterface::class) && !$reflection->isAbstract()) {
                        $result[$migration_class] = new $migration_class($this->connection);
                    }
                } else {
                    throw new RuntimeException("Migration class '$migration_class' not found");
                }
            } else {
                throw new RuntimeException("File '$migration_file_path' not found");
            }
        }

        return $result;
    }

    private function getMigrationClassByMigrationPath($migration_file_path, array $migration_class_file_path_map)
    {
        if ($migration_class = array_search($migration_file_path, $migration_class_file_path_map)) {
            return $migration_class;
        } else {
            throw new RuntimeException("Migration from '$migration_file_path' not loaded");
        }
    }

    /**
     * {@inheritdoc}
     */
    public function up()
    {
    }
}
