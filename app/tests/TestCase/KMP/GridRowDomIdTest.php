<?php
declare(strict_types=1);

namespace App\Test\TestCase\KMP;

use App\KMP\GridRowDomId;
use Cake\TestSuite\TestCase;

class GridRowDomIdTest extends TestCase
{
    public function testFromTableFrameIdBuildsStableRowTarget(): void
    {
        $this->assertSame(
            'recommendations-grid-row-42',
            GridRowDomId::fromTableFrameId('recommendations-grid-table', 42),
        );
    }
}
