<?php
declare(strict_types=1);

namespace App\Test\TestCase\View\Element;

use App\Test\TestCase\BaseTestCase;
use Cake\Routing\Route\DashedRoute;
use Cake\Routing\Router;
use Cake\View\View;
use DOMDocument;
use DOMXPath;

class NotesCellTest extends BaseTestCase
{
    public function testDifferentOwnersCanKeepTheirNotesOpenWithoutDuplicateIdsOrCrossedLabels(): void
    {
        Router::createRouteBuilder('/')->fallbacks(DashedRoute::class);
        $view = new View();
        $view->loadHelper('Form', ['className' => 'KmpForm']);
        $view->set([
            'notes' => [], 'canCreate' => true, 'viewPrivate' => true,
            'newNote' => $this->getTableLocator()->get('Notes')->newEmptyEntity(),
            'entity_type' => 'Members', 'entity_id' => self::ADMIN_MEMBER_ID,
        ]);
        $first = $view->render('cell/Notes/display', false);
        $view->set('entity_type', 'Awards.Bestowals');
        $second = $view->render('cell/Notes/display', false);
        $document = new DOMDocument();
        $document->loadHTML($first . $second, LIBXML_NOERROR | LIBXML_NOWARNING);
        $xpath = new DOMXPath($document);
        $ids = [];
        foreach ($xpath->query('//*[@id]') as $element) {
            $ids[] = $element->getAttribute('id');
        }
        $this->assertSame($ids, array_values(array_unique($ids)));
        $this->assertCount(2, $xpath->query('//form[@data-notes-new-form]'));
        foreach ($xpath->query('//label[@for]') as $label) {
            $field = $xpath->query('//*[@id="' . $label->getAttribute('for') . '"]');
            $this->assertCount(1, $field);
        }
        foreach ($xpath->query('//button[@aria-controls]') as $button) {
            $this->assertCount(1, $xpath->query('//*[@id="' . $button->getAttribute('aria-controls') . '"]'));
        }
    }
}
