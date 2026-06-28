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
