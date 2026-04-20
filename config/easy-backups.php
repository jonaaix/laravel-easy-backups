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
         'remote_disk' => 'backup',
         'remote_path' => 'db-backups',
         'mariadb' => [
            'use_parallel' => true,
         ],
         // Tables to exclude entirely from every database backup (no structure, no data).
         'exclude_tables' => [],
         // Tables where only the structure is exported (no row data). Useful for sensitive data.
         'exclude_table_data' => [],
      ],

      'files' => [
         'local_disk' => 'local',
         'local_path' => 'easy-backups/files',
         'remote_disk' => 'backup',
         'remote_path' => 'file-backups',
      ],
   ],
];
