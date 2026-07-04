<?php
declare(strict_types=1);

use Cake\I18n\DateTime;
use Migrations\BaseMigration;

class CreateRecommendationFeedbackRequests extends BaseMigration
{
    public bool $autoId = false;

    /**
     * Create feedback request tables and seed workflow metadata.
     */
    public function change(): void
    {
        $this->table('awards_recommendation_feedback_requests', ['id' => false])
            ->addColumn('id', 'integer', [
                'autoIncrement' => true,
                'limit' => 11,
                'null' => false,
            ])
            ->addColumn('requester_id', 'integer', [
                'limit' => 11,
                'null' => false,
            ])
            ->addColumn('status', 'string', [
                'default' => 'pending',
                'limit' => 32,
                'null' => false,
            ])
            ->addColumn('message', 'text', [
                'null' => true,
            ])
            ->addColumn('deadline', 'datetime', [
                'null' => true,
            ])
            ->addColumn('workflow_instance_id', 'integer', [
                'limit' => 11,
                'null' => true,
            ])
            ->addColumn('completed_at', 'datetime', [
                'null' => true,
            ])
            ->addColumn('retracted_at', 'datetime', [
                'null' => true,
            ])
            ->addColumn('expired_at', 'datetime', [
                'null' => true,
            ])
            ->addColumn('created', 'datetime', [
                'null' => false,
            ])
            ->addColumn('modified', 'datetime', [
                'null' => true,
            ])
            ->addColumn('created_by', 'integer', [
                'null' => true,
            ])
            ->addColumn('modified_by', 'integer', [
                'null' => true,
            ])
            ->addPrimaryKey(['id'])
            ->addIndex(['requester_id', 'status'], ['name' => 'idx_rec_feedback_requester_status'])
            ->addIndex(['workflow_instance_id'], ['name' => 'idx_rec_feedback_workflow_instance'])
            ->addForeignKey('requester_id', 'members', 'id', [
                'constraint' => 'fk_rec_fb_req_requester',
                'delete' => 'RESTRICT',
                'update' => 'CASCADE',
            ])
            ->addForeignKey('workflow_instance_id', 'workflow_instances', 'id', [
                'constraint' => 'fk_rec_fb_req_workflow',
                'delete' => 'SET_NULL',
                'update' => 'CASCADE',
            ])
            ->create();

        $this->table('awards_recommendation_feedback_request_items', ['id' => false])
            ->addColumn('id', 'integer', [
                'autoIncrement' => true,
                'limit' => 11,
                'null' => false,
            ])
            ->addColumn('feedback_request_id', 'integer', [
                'limit' => 11,
                'null' => false,
            ])
            ->addColumn('recommendation_id', 'integer', [
                'limit' => 11,
                'null' => false,
            ])
            ->addColumn('snapshot', 'json', [
                'null' => false,
            ])
            ->addColumn('created', 'datetime', [
                'null' => false,
            ])
            ->addColumn('modified', 'datetime', [
                'null' => true,
            ])
            ->addPrimaryKey(['id'])
            ->addIndex(['feedback_request_id'], ['name' => 'idx_rec_feedback_items_request'])
            ->addIndex(['recommendation_id'], ['name' => 'idx_rec_feedback_items_rec'])
            ->addForeignKey('feedback_request_id', 'awards_recommendation_feedback_requests', 'id', [
                'constraint' => 'fk_rec_fb_item_request',
                'delete' => 'CASCADE',
                'update' => 'CASCADE',
            ])
            ->addForeignKey('recommendation_id', 'awards_recommendations', 'id', [
                'constraint' => 'fk_rec_fb_item_rec',
                'delete' => 'RESTRICT',
                'update' => 'CASCADE',
            ])
            ->create();

        $this->table('awards_recommendation_feedback_request_recipients', ['id' => false])
            ->addColumn('id', 'integer', [
                'autoIncrement' => true,
                'limit' => 11,
                'null' => false,
            ])
            ->addColumn('feedback_request_id', 'integer', [
                'limit' => 11,
                'null' => false,
            ])
            ->addColumn('recipient_id', 'integer', [
                'limit' => 11,
                'null' => false,
            ])
            ->addColumn('workflow_approval_id', 'integer', [
                'limit' => 11,
                'null' => true,
            ])
            ->addColumn('workflow_approval_response_id', 'integer', [
                'limit' => 11,
                'null' => true,
            ])
            ->addColumn('status', 'string', [
                'default' => 'pending',
                'limit' => 32,
                'null' => false,
            ])
            ->addColumn('response_comment', 'text', [
                'null' => true,
            ])
            ->addColumn('responded_at', 'datetime', [
                'null' => true,
            ])
            ->addColumn('retracted_at', 'datetime', [
                'null' => true,
            ])
            ->addColumn('expired_at', 'datetime', [
                'null' => true,
            ])
            ->addColumn('created', 'datetime', [
                'null' => false,
            ])
            ->addColumn('modified', 'datetime', [
                'null' => true,
            ])
            ->addPrimaryKey(['id'])
            ->addIndex(['feedback_request_id', 'recipient_id'], [
                'unique' => true,
                'name' => 'idx_rec_feedback_recipient_unique',
            ])
            ->addIndex(['recipient_id', 'status'], ['name' => 'idx_rec_feedback_recipient_status'])
            ->addIndex(['workflow_approval_id'], ['name' => 'idx_rec_feedback_workflow_approval'])
            ->addForeignKey('feedback_request_id', 'awards_recommendation_feedback_requests', 'id', [
                'constraint' => 'fk_rec_fb_recipient_request',
                'delete' => 'CASCADE',
                'update' => 'CASCADE',
            ])
            ->addForeignKey('recipient_id', 'members', 'id', [
                'constraint' => 'fk_rec_fb_recipient_member',
                'delete' => 'RESTRICT',
                'update' => 'CASCADE',
            ])
            ->addForeignKey('workflow_approval_id', 'workflow_approvals', 'id', [
                'constraint' => 'fk_rec_fb_recipient_approval',
                'delete' => 'SET_NULL',
                'update' => 'CASCADE',
            ])
            ->addForeignKey('workflow_approval_response_id', 'workflow_approval_responses', 'id', [
                'constraint' => 'fk_rec_fb_recipient_response',
                'delete' => 'SET_NULL',
                'update' => 'CASCADE',
            ])
            ->create();

        $this->insertWorkflowDefinition();
        $this->insertFeedbackPermission();
    }

    /**
     * Insert the workflow definition used for Action Items delivery.
     */
    private function insertWorkflowDefinition(): void
    {
        $now = DateTime::now()->toDateTimeString();
        $definition = [
            'nodes' => [
                'trigger-feedback' => [
                    'type' => 'trigger',
                    'label' => 'Recommendation Feedback Requested',
                    'config' => [
                        'event' => 'Awards.RecommendationFeedbackRequested',
                        'entityIdField' => 'feedbackRequestId',
                    ],
                    'outputs' => [
                        ['port' => 'next', 'target' => 'create-feedback-approval'],
                    ],
                ],
                'create-feedback-approval' => [
                    'type' => 'action',
                    'label' => 'Create Feedback Approval',
                    'config' => [
                        'action' => 'Awards.CreateFeedbackApproval',
                        'nodeId' => 'create-feedback-approval',
                        'params' => [
                            'recipientId' => '$.trigger.recipientId',
                            'feedbackRequestRecipientId' => '$.trigger.feedbackRequestRecipientId',
                            'deadline' => '$.trigger.deadline',
                        ],
                    ],
                    'outputs' => [
                        ['port' => 'next', 'target' => 'end-feedback'],
                    ],
                ],
                'end-feedback' => [
                    'type' => 'end',
                    'label' => 'Feedback Approval Created',
                    'config' => [],
                    'outputs' => [],
                ],
            ],
            'startNode' => 'trigger-feedback',
        ];

        $definitions = $this->table('workflow_definitions');
        $versions = $this->table('workflow_versions');

        $existing = $this->fetchRow(
            "SELECT id FROM workflow_definitions WHERE slug = 'awards-recommendation-feedback-request' LIMIT 1",
        );
        if ($existing) {
            return;
        }

        $definitions->insert([
            'name' => 'Award Recommendation Feedback Request',
            'slug' => 'awards-recommendation-feedback-request',
            'description' => 'Routes recommendation feedback requests into the Action Items approval queue.',
            'trigger_type' => 'event',
            'trigger_config' => json_encode(['event' => 'Awards.RecommendationFeedbackRequested']),
            'entity_type' => 'Awards.RecommendationFeedbackRequests',
            'is_active' => true,
            'execution_mode' => 'durable',
            'current_version_id' => null,
            'created_by' => 1,
            'modified_by' => 1,
            'created' => $now,
            'modified' => $now,
        ])->save();

        $definitionRow = $this->fetchRow(
            "SELECT id FROM workflow_definitions WHERE slug = 'awards-recommendation-feedback-request' LIMIT 1",
        );
        $definitionId = (int)$definitionRow['id'];
        $versions->insert([
            'workflow_definition_id' => $definitionId,
            'version_number' => 1,
            'definition' => json_encode($definition),
            'canvas_layout' => '{}',
            'status' => 'published',
            'published_at' => $now,
            'published_by' => 1,
            'change_notes' => 'Initial recommendation feedback request workflow',
            'created_by' => 1,
            'created' => $now,
            'modified' => $now,
        ])->save();

        $versionRow = $this->fetchRow(
            "SELECT id FROM workflow_versions WHERE workflow_definition_id = {$definitionId} AND version_number = 1",
        );
        $this->execute(
            "UPDATE workflow_definitions SET current_version_id = {$versionRow['id']} WHERE id = {$definitionId}",
        );
    }

    /**
     * Insert the explicit policy permission for recommendation feedback requests.
     */
    private function insertFeedbackPermission(): void
    {
        $existing = $this->fetchRow(
            "SELECT id FROM permissions WHERE name = 'Can Request Recommendation Feedback' LIMIT 1",
        );
        if (!$existing) {
            $this->table('permissions')->insert([
                'name' => 'Can Request Recommendation Feedback',
                'require_active_membership' => true,
                'require_active_background_check' => false,
                'require_min_age' => 0,
                'is_system' => true,
                'is_super_user' => false,
                'requires_warrant' => true,
                'created' => DateTime::now(),
                'created_by' => 1,
            ])->save();
            $existing = $this->fetchRow(
                "SELECT id FROM permissions WHERE name = 'Can Request Recommendation Feedback' LIMIT 1",
            );
        }

        $permissionId = (int)$existing['id'];
        $this->insertPermissionPolicyIfMissing(
            $permissionId,
            'Awards\\Policy\\RecommendationPolicy',
            'canRequestFeedback',
        );
        $this->insertPermissionPolicyIfMissing(
            $permissionId,
            'Awards\\Policy\\RecommendationPolicy',
            'canRetractFeedback',
        );
        $this->grantFeedbackPermissionToRecommendationManagers($permissionId);
    }

    /**
     * Cross-engine insert-if-missing for permission_policies.
     */
    private function insertPermissionPolicyIfMissing(int $permissionId, string $policyClass, string $policyMethod): void
    {
        $class = str_replace("'", "''", $policyClass);
        $method = str_replace("'", "''", $policyMethod);
        $exists = $this->fetchRow(
            "SELECT 1 FROM permission_policies
             WHERE permission_id = {$permissionId}
               AND policy_class = '{$class}'
               AND policy_method = '{$method}'
             LIMIT 1",
        );
        if ($exists) {
            return;
        }

        $this->execute(
            "INSERT INTO permission_policies (permission_id, policy_class, policy_method)
             VALUES ({$permissionId}, '{$class}', '{$method}')",
        );
    }

    /**
     * Existing recommendation managers should be able to use the feedback workflow.
     */
    private function grantFeedbackPermissionToRecommendationManagers(int $permissionId): void
    {
        $managePermission = $this->fetchRow(
            "SELECT id FROM permissions WHERE name = 'Can Manage Recommendations' LIMIT 1",
        );
        if (!$managePermission) {
            return;
        }

        $roleRows = $this->fetchAll(
            'SELECT role_id FROM roles_permissions WHERE permission_id = ' . (int)$managePermission['id'],
        );
        foreach ($roleRows as $roleRow) {
            $roleId = (int)$roleRow['role_id'];
            $exists = $this->fetchRow(
                "SELECT 1 FROM roles_permissions
                 WHERE role_id = {$roleId}
                   AND permission_id = {$permissionId}
                 LIMIT 1",
            );
            if ($exists) {
                continue;
            }

            $this->execute(
                "INSERT INTO roles_permissions (role_id, permission_id, created, created_by)
                 VALUES ({$roleId}, {$permissionId}, CURRENT_TIMESTAMP, 1)",
            );
        }
    }
}
