<?php

declare(strict_types=1);

namespace SimpleVault\Controllers;

use InvalidArgumentException;
use SimpleVault\Core\Request;
use SimpleVault\Core\Response;
use SimpleVault\Support\PasswordGenerator;

/**
 * Password generator page. Generation happens server-side with random_int().
 * Options are passed via query string so the page can regenerate on submit.
 */
final class GeneratorController extends Controller
{
    public function index(Request $request): Response
    {
        $hasQuery = $request->query !== [];

        $options = [
            'length' => (int) $request->input('length', 20),
            'upper' => $hasQuery ? $request->boolean('upper') : true,
            'lower' => $hasQuery ? $request->boolean('lower') : true,
            'digits' => $hasQuery ? $request->boolean('digits') : true,
            'symbols' => $hasQuery ? $request->boolean('symbols') : true,
            'avoid_ambiguous' => $request->boolean('avoid_ambiguous'),
        ];

        $password = '';
        $error = null;
        try {
            $password = (new PasswordGenerator())->generate($options);
        } catch (InvalidArgumentException $e) {
            $error = $e->getMessage();
        }

        return $this->view('vault/generator', [
            'password' => $password,
            'options' => $options,
            'error' => $error,
        ], 'Password Generator');
    }
}
