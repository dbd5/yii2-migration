<?php

namespace bizley\migration;

use yii\base\Component;
use yii\base\InvalidConfigException;
use yii\db\Connection;

/**
 * Class Arranger
 * @package bizley\migration
 * @since 2.7.0
 */
class Arranger extends Component
{
    /**
     * @var Connection DB connection.
     */
    public $db;

    /**
     * @var array DB tables to be arranged.
     */
    public $inputTables = [];

    /**
     * Checks if DB connection is passed.
     * @throws InvalidConfigException
     */
    public function init()
    {
        parent::init();

        if (!($this->db instanceof Connection)) {
            throw new InvalidConfigException("Parameter 'db' must be an instance of yii\\db\\Connection!");
        }
    }

    /**
     * @return array
     * @throws InvalidConfigException
     */
    public function arrangeNewMigrations()
    {
        foreach ($this->inputTables as $inputTable) {
            $this->addDependency($inputTable);

            $generator = new Generator([
                'db' => $this->db,
                'tableName' => $inputTable,
            ]);

            $tableStructure = $generator->getTable();
            foreach ($tableStructure->foreignKeys as $foreignKey) {
                $this->addDependency($inputTable, $foreignKey->refTable);
            }
        }

        return $this->arrangeTables($this->_dependency);
    }

    private $_dependency = [];

    /**
     * @param string $table
     * @param string|null $dependensOnTable
     */
    protected function addDependency($table, $dependensOnTable = null)
    {
        if (!array_key_exists($table, $this->_dependency)) {
            $this->_dependency[$table] = [];
        }

        if ($dependensOnTable) {
            $this->_dependency[$table][] = $dependensOnTable;
        }
    }

    /**
     * @param array $input
     * @return array
     */
    public function arrangeTables($input)
    {
        $output = [];
        $checkList = [];
        $postLink = [];

        $inputCount = count($input);

        while ($inputCount > count($output)) {
            $done = false;
            $lastCheckedName = $lastCheckedDependency = null;

            foreach ($input as $name => $dependencies) {
                if (array_key_exists($name, $checkList)) {
                    continue;
                }

                $resolved = true;

                foreach ($dependencies as $dependency) {
                    if (!array_key_exists($dependency, $checkList)) {
                        $resolved = false;
                        $lastCheckedName = $name;
                        $lastCheckedDependency = $dependency;
                        break;
                    }
                }

                if ($resolved) {
                    $checkList[$name] = true;
                    $output[] = $name;

                    $done = true;
                }
            }

            if (!$done) {
                $input[$lastCheckedName] = array_diff($input[$lastCheckedName], [$lastCheckedDependency]);

                $redo = $this->arrangeTables($input);
                $output = $redo['order'];
                $postLinkMerged = array_merge_recursive(
                    [$lastCheckedName => [$lastCheckedDependency]],
                    $redo['suppressForeignKeys']
                );
                $filteredLink = [];
                foreach ($postLinkMerged as $name => $dependencies) {
                    $filteredLink[$name] = array_unique($dependencies);
                }
                $postLink = $filteredLink;
            }
        }

        return [
            'order' => $output,
            'suppressForeignKeys' => $postLink,
        ];
    }
}