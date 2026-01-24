<?php

it('ensures the create backup command is registered and does not throw an error', function () {
   $this->artisan('easy-backups:db:create', ['--help'])
      ->assertExitCode(0);
});

it('ensures the restore backup command is registered and does not throw an error', function () {
   $this->artisan('easy-backups:db:restore', ['--help'])
      ->assertExitCode(0);
});

it('accepts notify-mail-success option in help', function () {
   $this->artisan('easy-backups:db:create', ['--help'])
      ->expectsOutputToContain('notify-mail-success')
      ->assertExitCode(0);
});

it('accepts notify-mail-failure option in help', function () {
   $this->artisan('easy-backups:db:create', ['--help'])
      ->expectsOutputToContain('notify-mail-failure')
      ->assertExitCode(0);
});
