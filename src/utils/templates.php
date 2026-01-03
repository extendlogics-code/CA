<?php
declare(strict_types=1);

function get_templates(): array
{
    $pdo = db();
    $stmt = $pdo->query('SELECT Template_ID AS id, Name AS name, Category AS category, File_Path AS file_path, Uploaded_By AS uploaded_by, Uploaded_At AS uploaded_at FROM templates ORDER BY Uploaded_At DESC');
    return array_map(static function (array $row) {
        return [
            'id' => (int) $row['id'],
            'name' => $row['name'],
            'category' => $row['category'],
            'file_path' => $row['file_path'],
            'uploaded_by' => $row['uploaded_by'],
            'uploaded_at' => $row['uploaded_at'],
        ];
    }, $stmt->fetchAll(PDO::FETCH_ASSOC));
}

function find_template(int $id): ?array
{
    if ($id <= 0) return null;
    $pdo = db();
    $stmt = $pdo->prepare('SELECT Template_ID AS id, Name AS name, Category AS category, File_Path AS file_path, Uploaded_By AS uploaded_by, Uploaded_At AS uploaded_at FROM templates WHERE Template_ID = :id');
    $stmt->execute(['id' => $id]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$row) return null;
    $row['id'] = (int) $row['id'];
    return $row;
}

function add_template_record(array $payload): void
{
    $pdo = db();
    $stmt = $pdo->prepare('INSERT INTO templates (Name, Category, File_Path, Uploaded_By, Uploaded_At)
                           VALUES (:name, :category, :path, :by, :uploaded_at)');
    $stmt->execute([
        'name' => $payload['name'],
        'category' => $payload['category'],
        'path' => $payload['file_path'],
        'by' => $payload['uploaded_by'],
        'uploaded_at' => $payload['uploaded_at'] ?? date('c'),
    ]);
}

function update_template_record(int $id, array $payload): bool
{
    if ($id <= 0) return false;
    $pdo = db();
    $fields = ['Name' => 'name', 'Category' => 'category'];
    $set = [];
    $params = ['id' => $id];
    foreach ($fields as $col => $key) {
        if (array_key_exists($key, $payload)) {
            $set[] = $col . ' = :' . $key;
            $params[$key] = $payload[$key];
        }
    }
    if (array_key_exists('file_path', $payload)) {
        $set[] = 'File_Path = :file_path';
        $params['file_path'] = $payload['file_path'];
    }
    if (empty($set)) return false;
    $sql = 'UPDATE templates SET ' . implode(', ', $set) . ' WHERE Template_ID = :id';
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    return $stmt->rowCount() > 0;
}

function delete_template_record(int $id): bool
{
    if ($id <= 0) return false;
    $tpl = find_template($id);
    $pdo = db();
    $stmt = $pdo->prepare('DELETE FROM templates WHERE Template_ID = :id');
    $stmt->execute(['id' => $id]);
    $deleted = $stmt->rowCount() > 0;
    if ($deleted && $tpl && !empty($tpl['file_path'])) {
        $fullPath = realpath(__DIR__ . '/../../' . $tpl['file_path']);
        if ($fullPath && is_file($fullPath)) { @unlink($fullPath); }
    }
    return $deleted;
}
function templates_bootstrap(): void
{
    // Database-backed; nothing to preload.
}
