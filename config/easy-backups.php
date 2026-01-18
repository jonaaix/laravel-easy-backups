<?php

return [
   'defaults' => [
      // Defines the temporary directory for processing archives (relative to storage/app)
      'temp_path' => 'easy-backups/tmp',

      'database' => [
         // The disk name where local backups are stored (defined in filesystems.php)
         'local_disk' => 'local',

         // Relative path to the disk root (e.g. storage/app)
         'local_storage_path' => 'easy-backups/database',

         // Remote disk is configured at execution. However, you can set the base path.
         'remote_storage_path' => 'db_backups/',
      ],
   ],
];
