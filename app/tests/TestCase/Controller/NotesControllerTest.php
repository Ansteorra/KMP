<?php
declare(strict_types=1);

namespace App\Test\TestCase\Controller;

use App\Test\TestCase\Support\HttpIntegrationTestCase;

class NotesControllerTest extends HttpIntegrationTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->authenticateAsSuperUser();
        $this->enableCsrfToken();
        $this->enableSecurityToken();
        $this->configRequest(['headers' => ['Accept' => 'text/vnd.turbo-stream.html']]);
    }

    public function testSaveAppendsOnlyTheAuthorizedNewNote(): void
    {
        $this->post('/notes/add', [
            'entity_id' => self::ADMIN_MEMBER_ID,
            'entity_type' => 'Members',
            'subject' => 'Partial note subject',
            'body' => '<script>unsafe</script>',
            'private' => false,
        ]);
        $this->assertResponseOk();
        $this->assertContentType('text/vnd.turbo-stream.html');
        $this->assertResponseContains('action="append"');
        $this->assertResponseContains('target="notes-list-' . md5('Members:' . self::ADMIN_MEMBER_ID));
        $this->assertResponseContains('Partial note subject');
        $this->assertResponseNotContains('<script>unsafe</script>');
    }

    public function testValidationFailureDoesNotReplaceTheDraftOrNoteList(): void
    {
        $this->post('/notes/add', [
            'entity_id' => self::ADMIN_MEMBER_ID,
            'entity_type' => 'Members',
            'subject' => str_repeat('x', 256),
            'body' => 'Keep this draft',
            'private' => false,
        ]);
        $this->assertResponseCode(422);
        $this->assertResponseContains('The note could not be saved.');
        $this->assertResponseNotContains('action="append"');
    }
}
