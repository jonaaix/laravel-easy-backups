# Transfer progress for restore download and backup upload

## Goal
Downloading a backup in `RestoreJob` and uploading one in `UploadBackupAction` are no longer a black box.
In an interactive terminal a progress bar shows transferred / total bytes, percent and speed. Without a TTY
(queue worker, cron) a single summary line is printed at the end (`Downloaded 1.4 GB in 1m 18s (18 MB/s)`).
Done when both transfers report progress, existing restore/backup tests pass and the bar was seen in a real TTY.

## Milestones
- [x] `TransferProgress` + `ConsoleFeedback` progress bar / TTY detection + shared `ByteFormatter`
- [x] Chunked download with progress in `RestoreJob`
- [x] Upload progress via `ProgressReportingStream` in `UploadBackupAction`
- [x] Tests, TTY check, changelog

## State
Released in v1.4.0. The bar was checked in a pseudo-TTY (0 % → 100 %) with local disks only; mid-transfer
redraws and the upload behaviour against a real S3 bucket are not yet observed.

## Decisions
- Upload progress is the high-water mark of the read position, not the sum of bytes read. The S3 SDK reads the body
  once for checksums before sending and uploads multipart parts (5 MB) concurrently, so summed bytes double-count.
  The high-water mark leads the real network transfer by at most concurrency × part size.
- Small single-part S3 uploads (< 16 MB) jump to 100 % during checksumming; accepted, they finish in seconds.
- The download's existence check reads the size instead (`size()` instead of `exists()`), so the total costs no extra request.

## Open
- none
