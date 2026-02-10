# Decision: BasePolicy array resource handling via before() reflection

**Date:** 2026-02-10
**Author:** Kaylee
**Status:** Implemented

## Context

`authorizeCurrentUrl()` passes URL params as an array to the Authorization component. For controller policies extending BasePolicy, this array reaches type-hinted `can*` methods (`canIndex(KmpIdentityInterface $user, BaseEntity|Table $entity)`) causing a TypeError for non-super users.

## Decision

Handle array resources in `BasePolicy::before()` using `ReflectionMethod::getDeclaringClass()` to distinguish inherited methods (can't accept arrays) from subclass overrides (may accept arrays).

- If method is declared in BasePolicy → intercept, use `_hasPolicyForUrl()`, return result
- If method is declared in a subclass → let it through, subclass handles its own types

## Why not other approaches

- **Change BasePolicy method signatures to `mixed`:** PHP parameter contravariance would break ALL subclass overrides that use `BaseEntity|Table`
- **Change to `BaseEntity|Table|array`:** Same variance problem — subclasses can't narrow to `BaseEntity|Table`
- **Blanket `before()` without reflection:** Would bypass subclass methods like `GatheringWaiversControllerPolicy::canNeedingWaivers` that have custom array handling (e.g., steward fallback checks)

## Impact

- Fixes controller policies that extend BasePolicy without overriding can* methods (e.g., HelloWorldControllerPolicy)
- No changes to any subclass or method signature
- Reflection cost is minimal (one call per authorization check)

## Files Changed

- `app/src/Policy/BasePolicy.php` — added array handling in `before()`
