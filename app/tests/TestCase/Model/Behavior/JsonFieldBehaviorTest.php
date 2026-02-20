<?php
declare(strict_types=1);

namespace App\Test\TestCase\Model\Behavior;

use App\Model\Behavior\JsonFieldBehavior;
use App\Test\TestCase\Support\SeedManager;
use Cake\ORM\Query\SelectQuery;
use Cake\ORM\Table;
use Cake\TestSuite\TestCase;
use InvalidArgumentException;

/**
 * @covers \App\Model\Behavior\JsonFieldBehavior
 */
class JsonFieldBehaviorTest extends TestCase
{
    /**
     * @return void
     */
    public function testAddJsonWhereUsesJsonExtractForNonPostgres(): void
    {
        if (SeedManager::isPostgres('test')) {
            $this->markTestSkipped('MySQL-specific JSON_EXTRACT syntax test');
        }
        $table = new Table(['table' => 'members']);
        $behavior = new JsonFieldBehavior($table);
        $query = $table->find()->select(['id']);

        $behavior->addJsonWhere($query, 'members.additional_info', '$.preferences.email', 'test@example.com');

        $sql = $query->sql();
        $this->assertStringContainsStringIgnoringCase('json_extract', $sql);
    }

    /**
     * @return void
     */
    public function testAddJsonWhereUsesPostgresExpression(): void
    {
        $table = new Table(['table' => 'members']);
        $behavior = new class($table) extends JsonFieldBehavior {
            protected function getDriverName(SelectQuery $query): string
            {
                return 'Postgres';
            }
        };
        $query = $table->find()->select(['id']);

        $behavior->addJsonWhere($query, 'members.additional_info', '$.preferences.email', 'test@example.com');

        $sql = $query->sql();
        $this->assertStringContainsStringIgnoringCase('jsonb_extract_path_text', $sql);
        $this->assertStringContainsString("'preferences'", $sql);
        $this->assertStringContainsString("'email'", $sql);
    }

    /**
     * @return void
     */
    public function testAddJsonWhereRejectsInvalidPostgresPath(): void
    {
        $table = new Table(['table' => 'members']);
        $behavior = new class($table) extends JsonFieldBehavior {
            protected function getDriverName(SelectQuery $query): string
            {
                return 'Postgres';
            }
        };
        $query = $table->find()->select(['id']);

        $this->expectException(InvalidArgumentException::class);
        $behavior->addJsonWhere($query, 'members.additional_info', 'preferences.email', 'test@example.com');
    }
}
