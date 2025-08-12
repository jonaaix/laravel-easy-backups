<?php

it('ensures the create backup command is registered and does not throw an error', function () {
   $this->artisan('aaix:backup:db:create', ['--help'])
      ->assertExitCode(0);
});

it('ensures the restore backup command is registered and does not throw an error', function () {
   $this->artisan('aaix:backup:db:restore', ['--help'])
      ->assertExitCode(0);
});
