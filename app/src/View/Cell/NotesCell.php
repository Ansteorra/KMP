<?php

declare(strict_types=1);

namespace App\View\Cell;

use Cake\ORM\TableRegistry;
use Cake\View\Cell;

/**
 * Notes cell
 */
class NotesCell extends Cell
{
    /**
     * List of valid options that can be passed into this
     * cell's constructor.
     *
     * @var array<string, mixed>
     */
    protected array $_validCellOptions = [];

    /**
     * Initialization logic run at the end of object construction.
     *
     * @return void
     */
    public function initialize(): void
    {
    }

    /**
     * Default display method.
     *
     * @return void
     */
    public function display($topic_id, $topic_model, $viewPrivate = false)
    {
        $notesTable = TableRegistry::getTableLocator()->get('Notes');
        $newNote = $notesTable->newEmptyEntity();
        $notesQuery = $notesTable->find('all')
            ->contain(['Authors' => function ($q) {
                return $q->select(['id', 'sca_name']);
            }])
            ->where([
                'topic_id' => $topic_id,
                'topic_model' => $topic_model
            ]);
        if (!$viewPrivate) {
            $notesQuery->where(['private' => false]);
        }
        $notes = $notesQuery->orderBy(['Notes.created' => 'ASC'])->all();
        $this->set(compact('newNote', 'notes', 'topic_id', 'topic_model', 'viewPrivate'));
    }
}