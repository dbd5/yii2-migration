<?php

namespace bizley\tests\pgsql;

use bizley\tests\cases\UpdaterColumnsTestCase;
use Yii;
use yii\base\ErrorException;
use yii\base\Exception as BaseException;
use yii\base\NotSupportedException;
use yii\db\Exception;

/**
 * @group pgsql
 */
class UpdaterColumnsTest extends UpdaterColumnsTestCase
{
    public static $schema = 'pgsql';

    /**
     * @runInSeparateProcess
     * @preserveGlobalState disabled
     * @throws ErrorException
     * @throws Exception
     * @throws NotSupportedException
     * @throws BaseException
     */
    public function testChangeDefaultArrayValue()
    {
        if (!method_exists($this, 'json')) {
            $this->markTestSkipped('Json is supported since Yii 2.0.14.');
        }

        $this->dbUp('test_addons');

        Yii::$app->db->createCommand()->alterColumn(
            'test_addons',
            'col_default_array',
            'SET DEFAULT \'["a", "b"]\''
        )->execute();

        $updater = $this->getUpdater('test_addons', false);
        $this->assertTrue($updater->isUpdateRequired());
        $this->assertCount(1, $updater->plan->alterColumn);
        $this->assertArrayHasKey('col_default_array', $updater->plan->alterColumn);
        $this->assertEquals(['a', 'b'], $updater->plan->alterColumn['col_default_array']->default);
    }
}
