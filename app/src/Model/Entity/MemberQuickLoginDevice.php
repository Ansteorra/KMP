<?php

declare(strict_types=1);

namespace App\Model\Entity;

/**
 * MemberQuickLoginDevice Entity
 *
 * Stores per-device quick-login PIN credentials for a member.
 *
 * @property int $id
 * @property int $member_id
 * @property string $device_id
 * @property string $pin_hash
 * @property string|null $configured_ip_address
 * @property string|null $configured_location_hint
 * @property string|null $configured_os
 * @property string|null $configured_browser
 * @property string|null $configured_user_agent
 * @property int $failed_attempts
 * @property \Cake\I18n\DateTime|null $last_failed_login
 * @property \Cake\I18n\DateTime|null $last_used
 * @property string|null $last_used_ip_address
 * @property string|null $last_used_location_hint
 * @property \Cake\I18n\DateTime $created
 * @property \Cake\I18n\DateTime $modified
 *
 * @property \App\Model\Entity\Member $member
 */
class MemberQuickLoginDevice extends BaseEntity
{
    /**
     * Fields accessible for mass assignment.
     *
     * @var array<string, bool>
     */
    protected array $_accessible = [
        'member_id' => true,
        'device_id' => true,
        'pin_hash' => true,
        'configured_ip_address' => true,
        'configured_location_hint' => true,
        'configured_os' => true,
        'configured_browser' => true,
        'configured_user_agent' => true,
        'failed_attempts' => true,
        'last_failed_login' => true,
        'last_used' => true,
        'last_used_ip_address' => true,
        'last_used_location_hint' => true,
        'member' => true,
    ];

    /** @var array<string> Fields hidden from serialization */
    protected array $_hidden = [
        'pin_hash',
    ];
}
