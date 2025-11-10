# Queue Plugin Test Fixtures

This directory contains test fixtures (stub classes) used by the Queue plugin's unit tests.

## Structure

```
test_app/
├── plugins/
│   └── Foo/                          # Simulated plugin for testing plugin task discovery
│       └── src/
│           └── Queue/
│               └── Task/
│                   ├── FooTask.php          # Test task for plugin
│                   └── Sub/
│                       └── SubFooTask.php   # Test task for nested plugin discovery
└── src/
    ├── Dto/
    │   └── MyTaskDto.php            # Test DTO for data serialization tests
    ├── Mailer/
    │   └── TestMailer.php           # Test mailer for MailerTask tests
    └── Queue/
        └── Task/
            ├── FooTask.php          # Test task for app-level task discovery
            └── Sub/
                └── SubFooTask.php   # Test task for nested app task discovery
```

## Purpose

These test fixtures are referenced by various Queue plugin tests:

- **MyTaskDto**: Used in `QueuedJobsTableTest` to test DTO serialization
- **FooTask (App)**: Used in `TaskTest` and `TaskFinderTest` for task name resolution
- **SubFooTask (App)**: Used in `TaskFinderTest` for nested task discovery
- **TestMailer**: Used in `MailerTaskTest` to test email queue integration
- **FooTask (Plugin)**: Used in `TaskFinderTest` for plugin task discovery
- **SubFooTask (Plugin)**: Used in `TaskFinderTest` for nested plugin task discovery

## Autoloading

The test fixtures are autoloaded via the `autoload-dev` section in the Queue plugin's `composer.json`:

```json
"autoload-dev": {
    "psr-4": {
        "Foo\\": "tests/test_app/plugins/Foo/src/",
        "Queue\\Test\\TestCase\\": "tests/TestCase/",
        "TestApp\\": "tests/test_app/src/"
    }
}
```

After creating or modifying these fixtures, run `composer dump-autoload` from the Queue plugin directory to regenerate the autoloader.

## Note

These are minimal stub implementations meant only for testing. They do not represent production code patterns.
