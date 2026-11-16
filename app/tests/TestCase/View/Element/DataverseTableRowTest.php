<?php
declare(strict_types=1);

namespace App\Test\TestCase\View\Element;

use App\Test\TestCase\BaseTestCase;
use Cake\Http\ServerRequest;
use Cake\I18n\DateTime;
use Cake\ORM\Entity;
use Cake\View\View;

class DataverseTableRowTest extends BaseTestCase
{
    public function testRenderFieldsDisplayStoredValuesForStringDateAndRelationColumns(): void
    {
        $columns = [
            'outcome' => ['label' => 'Outcome', 'type' => 'string', 'renderField' => 'status'],
            'closed' => ['label' => 'Closed', 'type' => 'date', 'renderField' => 'closed_at'],
            'owner' => ['label' => 'Owner', 'type' => 'relation', 'renderField' => 'member.name'],
            'custom' => [
                'label' => 'Custom',
                'renderField' => ['start', 'end'],
                'cellRenderer' => static fn($value): string => h($value),
            ],
        ];
        $data = [
            'id' => 'example', 'status' => 'Given <safely>', 'closed_at' => new DateTime('2026-11-05 18:00:00'),
            'member' => ['name' => 'A & B'], 'custom' => 'Custom range',
        ];
        $view = new View(new ServerRequest());
        $view->loadHelper('Timezone');
        foreach ([$data, new Entity($data)] as $row) {
            $html = $view->element('dataverse_table', [
                'columns' => $columns, 'visibleColumns' => array_keys($columns), 'data' => [$row],
                'enableColumnPicker' => false,
            ]);
            $this->assertStringContainsString('Given &lt;safely&gt;', $html);
            $this->assertStringNotContainsString('Given <safely>', $html);
            $this->assertStringContainsString('November 5, 2026', $html);
            $this->assertStringContainsString('A &amp; B', $html);
            $this->assertStringContainsString('Custom range', $html);
        }
    }
}
