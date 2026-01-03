<?php
declare(strict_types=1);

function save_uploaded_file(array $file, string $bucket, array $allowedExtensions, int $maxSizeBytes = 5242880): array
{
    if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
        throw new RuntimeException('Upload failed.');
    }

    $extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    if ($allowedExtensions && !in_array($extension, $allowedExtensions, true)) {
        $mime = function_exists('finfo_open') ? (function($tmp){$f=finfo_open(FILEINFO_MIME_TYPE);$m=finfo_file($f,$tmp);finfo_close($f);return $m;})($file['tmp_name']) : (mime_content_type($file['tmp_name']) ?: '');
        $map = ['text/plain' => 'txt', 'text/markdown' => 'md', 'application/rtf' => 'rtf'];
        $candidate = $map[$mime] ?? '';
        if ($candidate !== '' && in_array($candidate, $allowedExtensions, true)) {
            $extension = $candidate;
        } else {
            throw new RuntimeException('Unsupported file type.');
        }
    }

    if (($file['size'] ?? 0) > $maxSizeBytes) {
        throw new RuntimeException('File too large.');
    }

    $safeName = bin2hex(random_bytes(8)) . ($extension ? ('.' . $extension) : '');
    $targetDir = __DIR__ . '/../../storage/uploads/' . trim($bucket, '/');

    if (!is_dir($targetDir) && !mkdir($targetDir, 0755, true) && !is_dir($targetDir)) {
        throw new RuntimeException('Failed to prepare upload folder.');
    }

    $targetPath = $targetDir . '/' . $safeName;

    if (!move_uploaded_file($file['tmp_name'], $targetPath)) {
        throw new RuntimeException('Unable to store uploaded file.');
    }

    $relativePath = 'storage/uploads/' . trim($bucket, '/') . '/' . $safeName;

    return [
        'path' => $relativePath,
        'name' => $file['name'],
        'uploaded_at' => date('c'),
        'size' => $file['size'] ?? 0,
    ];
}
