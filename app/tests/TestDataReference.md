# Stable test data reference

KMP's seeded dataset is intentionally rich, but only IDs exposed as constants in
`tests/TestCase/BaseTestCase.php` are a supported test contract. Record counts, migration IDs,
warrant dates, role assignments, and most plugin records may change whenever the seed is
regenerated.

## Supported constants

| Constant | ID | Seed record |
| --- | ---: | --- |
| `ADMIN_MEMBER_ID` | 1 | Admin von Admin (`admin@amp.ansteorra.org`), seeded super user |
| `KINGDOM_BRANCH_ID` | 2 | Ansteorra kingdom/root branch |
| `TEST_MEMBER_AGATHA_ID` | 2871 | Agatha Local MoAS Demoer |
| `TEST_MEMBER_BRYCE_ID` | 2872 | Bryce Local Seneschal Demoer |
| `TEST_MEMBER_DEVON_ID` | 2874 | Devon Regional Armored Demoer |
| `TEST_MEMBER_EIRIK_ID` | 2875 | Eirik Kingdom Seneschal Demoer |
| `TEST_BRANCH_LOCAL_ID` | 14 | Shire of Adlersruhe |
| `TEST_BRANCH_STARGATE_ID` | 39 | Barony of Stargate |
| `TEST_BRANCH_CENTRAL_REGION_ID` | 12 | Central Region |
| `TEST_BRANCH_SOUTHERN_REGION_ID` | 13 | Southern Region |
| `ADMIN_ROLE_ID` | 1 | Admin role |
| `SUPER_USER_PERMISSION_ID` | 1 | `Is Super User` permission |

Do not infer nearby IDs or substitute the old branch ID `1`; the supported kingdom branch ID
is `2`.

## Usage

Extend the project base class and use the named constant:

```php
use App\Test\TestCase\BaseTestCase;

final class ExampleTest extends BaseTestCase
{
    public function testAdminBelongsToSeed(): void
    {
        $admin = $this->getTableLocator()
            ->get('Members')
            ->get(self::ADMIN_MEMBER_ID);

        $this->assertSame('admin@amp.ansteorra.org', $admin->email_address);
    }
}
```

For anything not listed above, query by a unique semantic attribute or create the record in
the test:

```php
$permission = $this->getTableLocator()
    ->get('Permissions')
    ->find()
    ->where(['name' => 'Can Publish Gatherings to Kingdom Calendar'])
    ->firstOrFail();
```

Prefer an assertion about the specific records you create over an assertion about a whole
seeded-table count. Test-created names, emails, public IDs, and tokens should be unique to the
test so they cannot collide with parallel or future seed data.

## Isolation and reset behavior

`BaseTestCase` starts a transaction on the `test` connection and rolls it back in teardown.
Call `parent::setUp()` first and `parent::tearDown()` last when overriding those methods. Use
`reseedDatabase()` only for a scenario whose behavior cannot be isolated transactionally; it
reloads shared state and is much more expensive.

The PHPUnit bootstrap migrates core and loaded plugin schemas, loads the seed through
`SeedManager`, and aliases `test` to `default`. HTTP tenant resolution is disabled for ordinary
PHP tests. Therefore:

- these constants identify records in the test tenant dataset, not platform-registry rows;
- using the constants does not prove cross-tenant isolation;
- tenancy changes need explicit platform/tenant connection tests or the two-host Playwright
  harness in addition to ordinary seeded tests.

The local Docker reset normalizes demo-account passwords to `TestPassword` for interactive
development. That password is local test data, not a production default and not a substitute
for authentication helpers in PHPUnit.

## Maintaining the contract

When a numeric ID truly needs to become stable:

1. make it deterministic in the owning seed pipeline;
2. add a descriptive constant to `BaseTestCase`;
3. add a seed snapshot/contract test;
4. update this table; and
5. replace raw IDs in tests with the constant.

Do not add volatile totals or time-sensitive authorization claims here. The relevant sources
are `tests/TestCase/BaseTestCase.php`, `tests/TestCase/Support/SeedManager.php`,
`tests/bootstrap.php`, and the repository seed/reset scripts. See
[`../../docs/7.3-testing-infrastructure.md`](../../docs/7.3-testing-infrastructure.md) for the
complete test architecture.
