<?php

namespace App\Form;

use Cake\Form\Form;
use Cake\Form\Schema;
use Cake\Validation\Validator;

class ResetPasswordForm extends Form
{

    protected function _buildSchema(\Cake\Form\Schema $schema): \Cake\Form\Schema
    {
        return $schema
            ->addField('new_password', ['type' => 'password'])
            ->addField('confirm_password', ['type' => 'password']);
    }

    public function validationDefault(\Cake\Validation\Validator $validator): \Cake\Validation\Validator
    {
         return $validator->add('new_password', 'length', [
                'rule' => ['minLength', 6],
                'message' => 'Password is to short.'
            ])->add('confirm_password', 'compare', [
                'rule' => function ($value, $context) {
                        return
                                isset($context['data']['new_password']) &&
                                $context['data']['new_password'] === $value;
                },
                'message' => 'Password and confirmation do not match.'
            ]);
    }

    protected function _execute(array $data): bool
    {
        return true;
    }
}
