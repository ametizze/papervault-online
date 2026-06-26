<?php

declare(strict_types=1);

/**
 * SimpleVault front controller.
 *
 * The web server document root must point at this /public directory so that
 * application code, storage, database, and configuration stay outside the web
 * root.
 */

use SimpleVault\Controllers\AuthController;
use SimpleVault\Controllers\EntryController;
use SimpleVault\Controllers\GeneratorController;
use SimpleVault\Controllers\ImportExportController;
use SimpleVault\Controllers\NoteController;
use SimpleVault\Controllers\SettingsController;
use SimpleVault\Controllers\VaultController;
use SimpleVault\Core\App;
use SimpleVault\Core\Router;

$basePath = dirname(__DIR__);

// Prefer Composer's autoloader; fall back to a tiny PSR-4 loader so the app
// runs even before "composer install" is executed.
$composerAutoload = $basePath . '/vendor/autoload.php';
if (is_file($composerAutoload)) {
    require $composerAutoload;
} else {
    require $basePath . '/app/helpers.php';
    spl_autoload_register(static function (string $class) use ($basePath): void {
        $prefix = 'SimpleVault\\';
        if (!str_starts_with($class, $prefix)) {
            return;
        }
        $relative = str_replace('\\', '/', substr($class, strlen($prefix)));
        $file = $basePath . '/app/' . $relative . '.php';
        if (is_file($file)) {
            require $file;
        }
    });
}

$app = new App($basePath);
$app->boot();

$router = new Router();

// --- Public / guest ----------------------------------------------------------
$router->get('/setup', [AuthController::class, 'showSetup'], ['guest']);
$router->post('/setup', [AuthController::class, 'setup'], ['guest']);
$router->get('/login', [AuthController::class, 'showLogin'], ['guest']);
$router->post('/login', [AuthController::class, 'login'], ['guest']);
$router->post('/logout', [AuthController::class, 'logout'], ['auth']);

// --- Dashboard ---------------------------------------------------------------
$router->get('/', [VaultController::class, 'dashboard'], ['auth']);

// --- Vault unlock / lock -----------------------------------------------------
$router->get('/vault/unlock', [VaultController::class, 'showUnlock'], ['auth']);
$router->post('/vault/unlock', [VaultController::class, 'unlock'], ['auth']);
$router->post('/vault/lock', [VaultController::class, 'lock'], ['auth']);

// --- Password entries (require unlocked vault) -------------------------------
$router->get('/entries', [EntryController::class, 'index'], ['auth', 'unlock']);
$router->get('/entries/create', [EntryController::class, 'create'], ['auth', 'unlock']);
$router->post('/entries', [EntryController::class, 'store'], ['auth', 'unlock']);
// Bulk action must be declared before the dynamic {id} routes.
$router->post('/entries/bulk', [EntryController::class, 'bulk'], ['auth', 'unlock']);
$router->get('/entries/{id}', [EntryController::class, 'show'], ['auth', 'unlock']);
$router->get('/entries/{id}/edit', [EntryController::class, 'edit'], ['auth', 'unlock']);
$router->post('/entries/{id}/update', [EntryController::class, 'update'], ['auth', 'unlock']);
$router->post('/entries/{id}/copy', [EntryController::class, 'copyPassword'], ['auth', 'unlock']);
$router->post('/entries/{id}/duplicate', [EntryController::class, 'duplicate'], ['auth', 'unlock']);
$router->post('/entries/{id}/archive', [EntryController::class, 'archive'], ['auth', 'unlock']);
$router->post('/entries/{id}/delete', [EntryController::class, 'destroy'], ['auth', 'unlock']);

// --- Password generator ------------------------------------------------------
$router->get('/generator', [GeneratorController::class, 'index'], ['auth']);

// --- Markdown notes (require unlocked vault) ---------------------------------
$router->get('/notes', [NoteController::class, 'index'], ['auth', 'unlock']);
$router->get('/notes/create', [NoteController::class, 'create'], ['auth', 'unlock']);
$router->post('/notes', [NoteController::class, 'store'], ['auth', 'unlock']);
// Static note sub-pages must be declared before the dynamic {id} route.
$router->post('/notes/bulk', [NoteController::class, 'bulk'], ['auth', 'unlock']);
$router->get('/notes/export', [ImportExportController::class, 'notesExportPage'], ['auth', 'unlock']);
$router->post('/notes/export', [ImportExportController::class, 'exportNotesMarkdown'], ['auth', 'unlock']);
$router->get('/notes/import', [ImportExportController::class, 'notesImportPage'], ['auth', 'unlock']);
$router->post('/notes/import', [ImportExportController::class, 'importNotesMarkdown'], ['auth', 'unlock']);
$router->get('/notes/{id}', [NoteController::class, 'show'], ['auth', 'unlock']);
$router->get('/notes/{id}/edit', [NoteController::class, 'edit'], ['auth', 'unlock']);
$router->post('/notes/{id}/update', [NoteController::class, 'update'], ['auth', 'unlock']);
$router->post('/notes/{id}/copy', [NoteController::class, 'copyMarkdown'], ['auth', 'unlock']);
$router->post('/notes/{id}/duplicate', [NoteController::class, 'duplicate'], ['auth', 'unlock']);
$router->post('/notes/{id}/archive', [NoteController::class, 'archive'], ['auth', 'unlock']);
$router->post('/notes/{id}/delete', [NoteController::class, 'destroy'], ['auth', 'unlock']);
$router->get('/notes/{id}/export-md', [NoteController::class, 'exportMarkdown'], ['auth', 'unlock']);

// --- Settings ----------------------------------------------------------------
$router->get('/settings', [SettingsController::class, 'index'], ['auth']);
$router->post('/settings/master-password', [SettingsController::class, 'changeMasterPassword'], ['auth']);
$router->post('/settings/account-password', [SettingsController::class, 'changeAccountPassword'], ['auth']);

// --- Import / export ---------------------------------------------------------
$router->get('/export', [ImportExportController::class, 'index'], ['auth']);
$router->post('/export', [ImportExportController::class, 'exportBackup'], ['auth']);
$router->post('/export/notes-md', [ImportExportController::class, 'exportNotesMarkdown'], ['auth', 'unlock']);
$router->get('/import', [ImportExportController::class, 'index'], ['auth']);
$router->post('/import', [ImportExportController::class, 'importBackup'], ['auth']);
$router->post('/import/notes-md', [ImportExportController::class, 'importNotesMarkdown'], ['auth', 'unlock']);

$app->run($router);
