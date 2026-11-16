# Operational Guide — File Management

SEO Academy manages private lesson materials, assignments, class videos, and course media.

## 1. File Browser Interface
- Accessible to administrators at `/admin/files`.
- Lists all active `Media` records with original filename, storage path, collection, size, disk, and upload date.
- Search by filename or MIME type.
- Filter by collection and storage disk.
- Sorting by newest, oldest, largest, and smallest.
- Secure deletion removes the database record and the underlying physical file.

## 2. Storage Security
- Files are stored on private disks (default: `storage/app/private/media`).
- Executable extensions (`.php`, `.phtml`, `.phar`, `.sh`, `.exe`, `.cgi`) are strictly prohibited and rejected during upload.
- Physical storage paths use generated UUIDs rather than trusting client filenames to prevent directory traversal attacks.
- Downloads are routed through `PrivateMediaController` and `PrivateMediaService` with server-side enrollment and ownership checks.
- Direct static access is blocked via web server configuration (`.htaccess`).

## 3. Storage Metrics
- The file management interface reports:
  - Total managed LMS file volume.
  - Total active file count.
  - Filesystem available free space (where supported by the host).
  - Total filesystem volume size.

