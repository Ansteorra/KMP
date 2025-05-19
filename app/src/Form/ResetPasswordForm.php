<?php
declare(strict_types=1);

namespace App\Form;

use Cake\Form\Form;
use Cake\Form\Schema;
use Cake\Validation\Validator;

class ResetPasswordForm extends Form
{
    protected function _buildSchema(Schema $schema): Schema
    {
        return $schema
            ->addField('new_password', ['type' => 'password'])
            ->addField('confirm_password', ['type' => 'password']);
    }

    public function validationDefault(Validator $validator): Validator
    {
        return $validator
            ->maxLength('new_password', 125)
            ->minLength('new_password', 12, 'Password must be at least 12 characters long.')
            //->add("new_password", "requireCaps", [
            //    "rule" => ['custom', '/[A-Z]/'],
            //    "message" => "Password must contain at least one uppercase letter."
            //])
            //->add("new_password", "requireLowerCase", [
            //    "rule" => ['custom', '/[a-z]/'],
            //    "message" => "Password must contain at least one lowercase letter."
            //])
            //->add("new_password", "requireNumbers", [
            //    "rule" => ['custom', '/[0-9]/'],
            //    "message" => "Password must contain at least one number."
            //])
            //->add("new_password", "requireSpecial", [
            //    "rule" => ['custom', '/[\W]/'],
            //   "message" => "Password must contain at least one special character."
            //])
            ->add('confirm_password', 'compare', [
                'rule' => function ($value, $context) {
                    return isset($context['data']['new_password']) &&
                        $context['data']['new_password'] === $value;
                },
                'message' => 'Password and confirmation do not match.',
            ]);
    }

    protected function _execute(array $data): bool
    {
        return true;
    }
}
