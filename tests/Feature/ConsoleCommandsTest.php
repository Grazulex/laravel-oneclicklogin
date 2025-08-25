<?php

declare(strict_types=1);

it('can register magic link commands with testbench', function (): void {
    // Test that all commands are registered by running them with --help
    $commands = [
        'oneclicklogin:generate',
        'oneclicklogin:list',
        'oneclicklogin:prune',
        'oneclicklogin:revoke',
        'oneclicklogin:test',
    ];

    foreach ($commands as $command) {
        // Test that the command exists by running it with --help (should not error)
        $this->artisan($command, ['--help' => true])
            ->assertExitCode(0);
    }
});

it('can run magic link test command via testbench', function (): void {
    // Run the test command
    $this->artisan('oneclicklogin:test', ['--email' => 'test@example.com'])
        ->expectsOutput('Testing OneClickLogin Magic Link functionality...')
        ->expectsOutput('🎉 All tests completed successfully!')
        ->assertExitCode(0);
});

it('can run magic link generate command via testbench', function (): void {
    // Run the generate command
    $this->artisan('oneclicklogin:generate', [
        'email' => 'test@example.com',
        '--url' => '/dashboard',
        '--ttl' => 30,
        '--force' => true,
    ])
        ->expectsOutput('Magic link generated for test@example.com')
        ->assertExitCode(0);
});
