<?php
declare(strict_types=1);

namespace App\Services;

use App\KMP\CaseInsensitiveQuery;
use App\Model\Entity\Member;
use Cake\ORM\Locator\LocatorAwareTrait;
use Cake\ORM\Query\SelectQuery;

/**
 * Handles member search and discovery queries.
 *
 * Covers SCA-name search with th/Þ character conversion, autocomplete
 * result building, and email availability checks.
 *
 * @property \App\Model\Table\MembersTable $Members
 */
class MemberSearchService
{
    use LocatorAwareTrait;

    /**
     * @var \App\Model\Table\MembersTable
     */
    private $Members;

    /**
     * Initialize the search service.
     */
    public function __construct()
    {
        /** @var \App\Model\Table\MembersTable $members */
        $members = $this->fetchTable('Members');
        $this->Members = $members;
    }

    /**
     * Convert a search query to include th/Þ (thorn) character variants.
     *
     * @param string|null $query Original search string.
     * @return array{q:string|null,nq:string|null,uq:string|null}
     */
    public function buildThornVariants(?string $query): array
    {
        $q = $query;
        $nq = $q;
        if ($q !== null && preg_match('/th/', $q)) {
            $nq = str_replace('th', 'Þ', $q);
        }
        $uq = $q;
        if ($q !== null && preg_match('/Þ/', $q)) {
            $uq = str_replace('Þ', 'th', $q);
        }

        return ['q' => $q, 'nq' => $nq, 'uq' => $uq];
    }

    /**
     * Build a member search query with thorn-variant matching.
     *
     * @param string|null $q Original search string.
     * @param string|null $nq Thorn-replaced variant.
     * @param string|null $uq Reverse thorn-replaced variant.
     * @param int $limit Maximum number of results.
     * @param array<string> $fields Columns to select.
     * @return \Cake\ORM\Query\SelectQuery
     */
    public function searchQuery(
        ?string $q,
        ?string $nq,
        ?string $uq,
        int $limit = 10,
        array $fields = ['id', 'sca_name'],
    ): SelectQuery {
        $terms = array_values(array_unique(array_map(
            static fn(?string $term): string => (string)$term,
            [$q, $nq, $uq],
        )));
        $conditions = array_map(
            static fn(string $term): array => CaseInsensitiveQuery::contains('sca_name', $term),
            $terms,
        );

        return $this->Members
            ->find('all')
            ->where([
                'status <>' => Member::STATUS_DEACTIVATED,
                'OR' => $conditions,
            ])
            ->select($fields)
            ->limit($limit);
    }

    /**
     * Check whether an email address is already in use.
     *
     * @param string|null $email Email address to check.
     * @return bool True when the email is already taken.
     */
    public function isEmailTaken(?string $email): bool
    {
        $count = $this->Members
            ->find('all')
            ->where(CaseInsensitiveQuery::equals('email_address', (string)$email))
            ->count();

        return $count > 0;
    }
}
