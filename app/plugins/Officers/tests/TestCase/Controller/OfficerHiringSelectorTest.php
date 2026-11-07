<?php
declare(strict_types=1);

namespace Officers\Test\TestCase\Controller;

use App\Model\Entity\Member;
use App\Test\TestCase\Support\HttpIntegrationTestCase;
use DOMDocument;
use DOMXPath;

class OfficerHiringSelectorTest extends HttpIntegrationTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->authenticateAsSuperUser();
    }

    public function testSameNameAccountsHaveDistinctSafeLabelsAndKeepWarrantRestrictions(): void
    {
        $name = 'Hiring Twin <Oak> & Reed';
        $members = $this->getTableLocator()->get('Members');
        $members->updateAll([
            'sca_name' => $name, 'status' => Member::STATUS_VERIFIED_MEMBERSHIP, 'warrantable' => true,
        ], ['id' => self::TEST_MEMBER_AGATHA_ID]);
        $members->updateAll([
            'sca_name' => $name, 'status' => Member::STATUS_ACTIVE, 'warrantable' => false,
        ], ['id' => self::TEST_MEMBER_BRYCE_ID]);
        $office = $this->getTableLocator()->get('Officers.Offices')->find()->firstOrFail();
        $this->getTableLocator()->get('Officers.Offices')->updateAll(
            ['requires_warrant' => true],
            ['id' => $office->id],
        );
        $this->get('/officers/officers/auto-complete/' . $office->id . '?q=' . urlencode($name));
        $this->assertResponseOk();
        $this->assertResponseContains(h($name));
        $this->assertResponseNotContains($name);
        $document = new DOMDocument();
        $document->loadHTML((string)$this->_response->getBody());
        $options = (new DOMXPath($document))->query('//li[@role="option"]');
        $this->assertCount(2, $options);
        $labels = [];
        foreach ($options as $option) {
            $id = (int)$option->getAttribute('data-ac-value');
            $label = $option->getAttribute('data-ac-label');
            $labels[$id] = $label;
            $this->assertStringContainsString('Account #' . $id, $label);
            $this->assertStringContainsString($name, $label);
            $this->assertStringContainsString($label, $option->textContent);
            $this->assertSame($id === self::TEST_MEMBER_BRYCE_ID ? 'true' : 'false', $option->getAttribute('aria-disabled'));
        }
        $this->assertNotSame($labels[self::TEST_MEMBER_AGATHA_ID], $labels[self::TEST_MEMBER_BRYCE_ID]);
        $this->assertStringContainsString('Membership verified', $labels[self::TEST_MEMBER_AGATHA_ID]);
        $this->assertStringContainsString('Membership not verified', $labels[self::TEST_MEMBER_BRYCE_ID]);
    }

    public function testDeactivatedAccountsAreExcludedAndSearchIsLiteral(): void
    {
        $members = $this->getTableLocator()->get('Members');
        $members->updateAll(['sca_name' => 'Hiring Literal [Oak]', 'status' => Member::STATUS_ACTIVE], [
            'id' => self::TEST_MEMBER_AGATHA_ID,
        ]);
        $members->updateAll(['sca_name' => 'Hiring Literal [Oak]', 'status' => Member::STATUS_DEACTIVATED], [
            'id' => self::TEST_MEMBER_BRYCE_ID,
        ]);
        $office = $this->getTableLocator()->get('Officers.Offices')->find()->firstOrFail();
        $this->get('/officers/officers/auto-complete/' . $office->id . '?q=' . urlencode('Hiring Literal [Oak]'));
        $this->assertResponseOk();
        $this->assertResponseContains('data-ac-value="' . self::TEST_MEMBER_AGATHA_ID . '"');
        $this->assertResponseNotContains('data-ac-value="' . self::TEST_MEMBER_BRYCE_ID . '"');
    }
}
