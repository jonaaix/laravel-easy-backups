<?php

return [
   'defaults' => [
      'temp_path' => 'easy-backups/tmp',

      'strategy' => [
         'prefix_env' => true,
      ],

      'database' => [
         'local_disk' => 'local',
         'local_path' => 'easy-backups/database',
         'remote_disk' => 's3-backup',
         'remote_path' => 'db-backups',
      ],

      'files' => [
         'local_disk' => 'local',
         'local_path' => 'easy-backups/files',
         'remote_disk' => 's3-backup',
         'remote_path' => 'file-backups',
      ],
   ],
];
