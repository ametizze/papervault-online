<?php

declare(strict_types=1);

use SimpleVault\Controllers\EntryController;
use SimpleVault\Models\Entry;

test('normalizeFields reads the legacy "label" key as name', function () {
    $fields = Entry::normalizeFields([
        ['label' => 'mysql', 'value' => 'pw', 'secret' => true],
    ]);
    assert_equals('mysql', $fields[0]['name']);
    assert_true($fields[0]['secret']);
    assert_equals('', $fields[0]['observation']);
});

test('normalizeFields drops rows with no name, value or observation', function () {
    $fields = Entry::normalizeFields([
        ['name' => '', 'value' => '', 'observation' => ''],
        ['name' => 'keep', 'value' => ''],
    ]);
    assert_equals(1, count($fields));
    assert_equals('keep', $fields[0]['name']);
});

test('normalizeFields defaults unknown types to text and forces secret for password/totp', function () {
    $fields = Entry::normalizeFields([
        ['name' => 'a', 'value' => 'x', 'type' => 'bogus'],
        ['name' => 'b', 'value' => 'x', 'type' => 'password', 'secret' => false],
        ['name' => 'c', 'value' => 'x', 'type' => 'totp', 'secret' => false],
        ['name' => 'd', 'value' => 'x', 'type' => 'url'],
    ]);
    assert_equals('text', $fields[0]['type']);
    assert_true($fields[1]['secret'], 'password implies secret');
    assert_true($fields[2]['secret'], 'totp implies secret');
    assert_equals('url', $fields[3]['type']);
    assert_false($fields[3]['secret']);
});

test('parseFields keeps valid expiry dates and discards malformed ones', function () {
    $parse = (new ReflectionMethod(EntryController::class, 'parseFields'));
    $parse->setAccessible(true);
    $out = $parse->invoke(new EntryController(), [
        ['name' => 'cert', 'value' => 'x', 'expiresAt' => '2027-01-15'],
        ['name' => 'key', 'value' => 'x', 'expiresAt' => 'not-a-date'],
    ], []);
    assert_equals('2027-01-15', $out[0]['expiresAt']);
    assert_equals(null, $out[1]['expiresAt']);
});

test('parseFields trails prior values in history only when the value changes', function () {
    $parse = (new ReflectionMethod(EntryController::class, 'parseFields'));
    $parse->setAccessible(true);
    $controller = new EntryController();

    $first = $parse->invoke($controller, [['name' => 'db', 'value' => 'v1']], [])[0];
    assert_equals(0, count($first['history']), 'new fields start with no history');

    $second = $parse->invoke($controller, [
        ['id' => $first['id'], 'name' => 'db', 'value' => 'v2'],
    ], [$first['id'] => $first])[0];
    assert_equals(1, count($second['history']));
    assert_equals('v1', $second['history'][0]['value']);

    // Saving again without changing the value adds nothing.
    $third = $parse->invoke($controller, [
        ['id' => $second['id'], 'name' => 'db', 'value' => 'v2'],
    ], [$second['id'] => $second])[0];
    assert_equals(1, count($third['history']));
});

test('parseFields assigns an id and timestamps to new fields', function () {
    $parse = (new ReflectionMethod(EntryController::class, 'parseFields'));
    $parse->setAccessible(true);
    $out = $parse->invoke(new EntryController(), [
        ['name' => 'redis', 'value' => 'v', 'secret' => '1', 'observation' => 'note'],
    ], []);
    assert_true($out[0]['id'] !== '', 'id must be generated');
    assert_true($out[0]['createdAt'] !== '', 'createdAt must be set');
    assert_equals($out[0]['createdAt'], $out[0]['updatedAt']);
    assert_equals('note', $out[0]['observation']);
});

test('parseFields preserves updatedAt when a field is unchanged', function () {
    $parse = (new ReflectionMethod(EntryController::class, 'parseFields'));
    $parse->setAccessible(true);
    $controller = new EntryController();

    $first = $parse->invoke($controller, [
        ['name' => 'redis', 'value' => 'v', 'secret' => '1', 'observation' => ''],
    ], [])[0];
    $previous = [$first['id'] => $first];

    $again = $parse->invoke($controller, [
        ['id' => $first['id'], 'name' => 'redis', 'value' => 'v', 'secret' => '1', 'observation' => ''],
    ], $previous)[0];

    assert_equals($first['updatedAt'], $again['updatedAt']);
    assert_equals($first['id'], $again['id']);
});

test('parseFields bumps updatedAt but keeps createdAt and id when a field changes', function () {
    $parse = (new ReflectionMethod(EntryController::class, 'parseFields'));
    $parse->setAccessible(true);
    $controller = new EntryController();

    $first = $parse->invoke($controller, [
        ['name' => 'redis', 'value' => 'v', 'secret' => '1', 'observation' => ''],
    ], [])[0];
    // Force a different timestamp on the next stamp.
    $first['updatedAt'] = '2000-01-01T00:00:00+00:00';
    $first['createdAt'] = '2000-01-01T00:00:00+00:00';
    $previous = [$first['id'] => $first];

    $changed = $parse->invoke($controller, [
        ['id' => $first['id'], 'name' => 'redis', 'value' => 'CHANGED', 'secret' => '1', 'observation' => ''],
    ], $previous)[0];

    assert_true($changed['updatedAt'] !== $first['updatedAt'], 'updatedAt must move');
    assert_equals($first['createdAt'], $changed['createdAt']);
    assert_equals($first['id'], $changed['id']);
});
