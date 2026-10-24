# PHP unit tests

Zero-dependency tests for release-critical server logic, run against the real
classes (composer autoloader) with a minimal in-memory WordPress stub layer
(`bootstrap.php`). No PHPUnit on purpose: the release lockfile stays frozen.

```bash
php tests/php/run.php
```

Covers release-critical server logic across the codebase: ConditionEvaluator
matching semantics (include-OR / exclude-veto / unknown-skip / mock
fail-closed), Migrator ordering + version-range + stamping + resumable
`BatchCursor` batches, the Sanitizer's key rules including the
object-injection guard, `Core\Container`/`ServiceProviderInterface`
composition, loop render identity/placement resolvers, and the multisite
`SiteBatchIterator`, among others. This is the fast, stub-backed suite —
real WordPress/database/Elementor behavior is `tests/integration/`'s.
