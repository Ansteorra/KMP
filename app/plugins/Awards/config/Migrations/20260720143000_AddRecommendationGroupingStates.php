<?php
declare(strict_types=1);

use Migrations\BaseMigration;

/**
 * Ensure recommendation grouping states exist in upgraded seed data.
 */
class AddRecommendationGroupingStates extends BaseMigration
{
    private const SETTING_NAME = 'Awards.RecommendationStatuses';

    private const REQUIRED_STATES = [
        'In Progress' => 'Linked',
        'Closed' => 'Linked - Closed',
    ];

    /**
     * Add linked recommendation states without duplicating existing values.
     *
     * @return void
     */
    public function up(): void
    {
        $this->updateRecommendationStatuses(true);
    }

    /**
     * Remove recommendation grouping states added by this migration.
     *
     * @return void
     */
    public function down(): void
    {
        $this->updateRecommendationStatuses(false);
    }

    /**
     * Add or remove grouping states while preserving valid YAML structure.
     *
     * @param bool $add Whether to add rather than remove the states.
     * @return void
     */
    private function updateRecommendationStatuses(bool $add): void
    {
        $row = $this->getSelectBuilder()
            ->select(['value'])
            ->from('app_settings')
            ->where(['name' => self::SETTING_NAME])
            ->execute()
            ->fetch('assoc');
        if ($row === false) {
            return;
        }

        $value = $row['value'] ?? null;
        if (!is_string($value) || trim($value) === '') {
            throw new RuntimeException(self::SETTING_NAME . ' must contain a YAML mapping.');
        }

        $statuses = yaml_parse($value);
        if (!is_array($statuses)) {
            throw new RuntimeException(self::SETTING_NAME . ' contains invalid YAML.');
        }

        $modified = false;
        foreach (self::REQUIRED_STATES as $status => $state) {
            if (!isset($statuses[$status])) {
                if (!$add) {
                    continue;
                }
                $statuses[$status] = [];
            }
            if (!is_array($statuses[$status])) {
                throw new RuntimeException(self::SETTING_NAME . " status '{$status}' must be a list.");
            }

            $stateIndex = array_search($state, $statuses[$status], true);
            if ($add && $stateIndex === false) {
                $statuses[$status][] = $state;
                $modified = true;
            } elseif (!$add && $stateIndex !== false) {
                unset($statuses[$status][$stateIndex]);
                $statuses[$status] = array_values($statuses[$status]);
                $modified = true;
            }
        }

        if (!$modified) {
            return;
        }

        $encodedStatuses = yaml_emit($statuses);
        if (!is_string($encodedStatuses)) {
            throw new RuntimeException('Unable to encode ' . self::SETTING_NAME . ' as YAML.');
        }

        $this->getUpdateBuilder()
            ->update('app_settings')
            ->set(['value' => $encodedStatuses])
            ->where(['name' => self::SETTING_NAME])
            ->execute();
    }
}
