# Queue test application fixtures

This directory contains minimal classes used only by the Queue plugin tests.

## Fixture namespaces

| Namespace/path | Test purpose |
| --- | --- |
| `TestApp\Dto\MyTaskDto` | Queued payload serialization |
| `TestApp\Mailer\TestMailer` | Mailer task behavior |
| `TestApp\Queue\Task\*` | Application task discovery, including nested tasks |
| `Foo\Queue\Task\*` | Plugin task discovery, including nested tasks |

The fixtures are autoloaded by `app/composer.json`:

```json
{
  "autoload-dev": {
    "psr-4": {
      "TestApp\\": "plugins/Queue/tests/test_app/src/",
      "Foo\\": "plugins/Queue/tests/test_app/plugins/Foo/src/"
    }
  }
}
```

After adding or moving a fixture, run `composer dump-autoload` from `app/`.
Run the owning tests with:

```bash
vendor/bin/phpunit plugins/Queue/tests/TestCase
```

These stubs intentionally omit production behavior and must not be copied into
application or plugin code.
