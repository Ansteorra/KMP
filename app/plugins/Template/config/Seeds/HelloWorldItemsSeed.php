<?php

declare(strict_types=1);

use Migrations\AbstractSeed;

/**
 * HelloWorldItems seed.
 * 
 * This seed file provides sample data for development and testing.
 * 
 * To run this seed:
 * bin/cake migrations seed -p Template
 */
class HelloWorldItemsSeed extends AbstractSeed
{
    /**
     * Run Method.
     *
     * @return void
     */
    public function run(): void
    {
        $data = [
            [
                'title' => 'Welcome to KMP',
                'description' => 'This is a sample hello world item demonstrating the Template plugin.',
                'content' => 'The Kingdom Management Portal (KMP) provides a comprehensive plugin system for extending functionality. This template plugin shows you how to create your own plugins with all the standard components.',
                'active' => true,
                'created' => date('Y-m-d H:i:s'),
                'modified' => date('Y-m-d H:i:s'),
            ],
            [
                'title' => 'Plugin Features',
                'description' => 'The Template plugin demonstrates all major plugin features.',
                'content' => 'This includes controllers, models, policies, navigation integration, view cells, frontend assets, and more. Use it as a starting point for your own plugins.',
                'active' => true,
                'created' => date('Y-m-d H:i:s'),
                'modified' => date('Y-m-d H:i:s'),
            ],
            [
                'title' => 'Getting Started',
                'description' => 'Copy this template to create your own plugin.',
                'content' => 'Follow the instructions in the README.md file to customize this template for your needs. Update the namespace, class names, and functionality to match your requirements.',
                'active' => true,
                'created' => date('Y-m-d H:i:s'),
                'modified' => date('Y-m-d H:i:s'),
            ],
            [
                'title' => 'Best Practices',
                'description' => 'Follow KMP coding standards and conventions.',
                'content' => 'Use the patterns demonstrated in this plugin: PSR-12 coding standard, type declarations, docblocks, proper security, and authorization policies.',
                'active' => true,
                'created' => date('Y-m-d H:i:s'),
                'modified' => date('Y-m-d H:i:s'),
            ],
            [
                'title' => 'Inactive Item Example',
                'description' => 'This item is marked as inactive.',
                'content' => 'Use the active flag to soft-disable items without deleting them. This is useful for archiving content while maintaining data integrity.',
                'active' => false,
                'created' => date('Y-m-d H:i:s'),
                'modified' => date('Y-m-d H:i:s'),
            ],
        ];

        $table = $this->table('hello_world_items');
        $table->insert($data)->save();
    }
}
