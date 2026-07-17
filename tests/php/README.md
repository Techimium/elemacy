# PHP unit tests

Zero-dependency tests for release-critical server logic, run against the real
classes (composer autoloader) with a minimal in-memory WordPress stub layer
(`bootstrap.php`). No PHPUnit on purpose: the release lockfile stays frozen.

```bash
php tests/php/run.php
```

Covers: ConditionEvaluator matching semantics (include-OR / exclude-veto /
unknown-skip / mock fail-closed), Migrator ordering + version-range + stamping,
and the Sanitizer's key rules including the object-injection guard.
