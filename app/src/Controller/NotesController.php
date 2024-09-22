<?php

declare(strict_types=1);


namespace App\Controller;

use Cake\ORM\TableRegistry;
use Cake\Datasource\Exception\NotFoundException;

/**
 * Notes Controller
 *
 * @property \App\Model\Table\NotesTable $Notes
 */
class NotesController extends AppController
{
    /**
     * Add method
     *
     * @return \Cake\Http\Response|null|void Redirects on successful add, renders view otherwise.
     */
    public function add()
    {
        $note = $this->Notes->newEmptyEntity();
        if ($this->request->is('post')) {
            $note = $this->Notes->patchEntity($note, $this->request->getData());
            $note->author_id = $this->Authentication
                ->getIdentity()
                ->getIdentifier();
            //get the table based on the name "Model"
            $modelTbl = TableRegistry::getTableLocator()->get($note->topic_model);
            $model = $modelTbl->get($note->topic_id);
            if (!$model) {
                throw new \Cake\Http\Exception\NotFoundException();
            }
            $this->Authorization->authorize($model, 'addNote');
            if ($this->Notes->save($note)) {
                $this->Flash->success(__('The note has been saved.'));
            } else {
                $this->Flash->error(__('The note could not be saved. Please, try again.'));
            }
        }
        return $this->redirect($this->referer());
    }


    /**
     * Delete method
     *
     * @param string|null $id Note id.
     * @return \Cake\Http\Response|null Redirects to index.
     * @throws \Cake\Datasource\Exception\RecordNotFoundException When record not found.
     */
    public function delete($id = null)
    {
        $this->request->allowMethod(['post', 'delete']);
        $note = $this->Notes->get($id);
        if ($this->Notes->delete($note)) {
            $this->Flash->success(__('The note has been deleted.'));
        } else {
            $this->Flash->error(__('The note could not be deleted. Please, try again.'));
        }
        return $this->redirect($this->referer());
    }
}