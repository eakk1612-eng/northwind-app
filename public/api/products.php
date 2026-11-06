<?php
require __DIR__ . '/../../config.php';
header('Content-Type: application/json; charset=utf-8');

function out(int $code, array $data): never {
    http_response_code($code);
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

try {
    $pdo = db();
    $S = schema($pdo);
} catch (Throwable $e) {
    out(500, ['ok' => false, 'message' => 'เชื่อมต่อฐานข้อมูลไม่ได้: ' . $e->getMessage()]);
}
$T = q($S['table']);
$M = $S['map'];
$method = $_SERVER['REQUEST_METHOD'];
$body = json_decode(file_get_contents('php://input'), true) ?: [];

// ---------- Validation ----------
function validate(array $d, array $M): array {
    $errors = [];
    $name = trim((string)($d['name'] ?? ''));
    if ($name === '') $errors['name'] = 'กรุณากรอกชื่อสินค้า';
    elseif (mb_strlen($name) > 40) $errors['name'] = 'ชื่อสินค้าต้องไม่เกิน 40 ตัวอักษร';
    $clean = ['name' => $name];
    if (isset($M['price'])) {
        $p = $d['price'] ?? '';
        if ($p === '' || !is_numeric($p)) $errors['price'] = 'ราคาต้องเป็นตัวเลข';
        elseif ((float)$p < 0) $errors['price'] = 'ราคาต้องไม่ติดลบ';
        else $clean['price'] = round((float)$p, 2);
    }
    if (isset($M['stock'])) {
        $s = $d['stock'] ?? '';
        if ($s === '' || filter_var($s, FILTER_VALIDATE_INT) === false) $errors['stock'] = 'จำนวนคงเหลือต้องเป็นจำนวนเต็ม';
        elseif ((int)$s < 0) $errors['stock'] = 'จำนวนคงเหลือต้องไม่ติดลบ';
        else $clean['stock'] = (int)$s;
    }
    if (isset($M['unit'])) {
        $u = trim((string)($d['unit'] ?? ''));
        if (mb_strlen($u) > 20) $errors['unit'] = 'หน่วยต้องไม่เกิน 20 ตัวอักษร';
        $clean['unit'] = $u;
    }
    return [$clean, $errors];
}

try {
    // ---------- READ / SEARCH ----------
    if ($method === 'GET') {
        $sel = [];
        foreach (['id', 'name', 'price', 'stock', 'unit'] as $k) {
            $sel[] = isset($M[$k]) ? q($M[$k]) . " AS $k" : "NULL AS $k";
        }
        $sql = "SELECT " . implode(', ', $sel) . " FROM $T";
        $params = [];
        $kw = trim((string)($_GET['q'] ?? ''));
        if ($kw !== '') {
            $sql .= " WHERE " . q($M['name']) . " LIKE :kw OR CAST(" . q($M['id']) . " AS CHAR) = :id";
            $params = [':kw' => "%$kw%", ':id' => $kw];
        }
        $sql .= " ORDER BY " . q($M['id']) . " LIMIT 500";
        $st = $pdo->prepare($sql);
        $st->execute($params);
        out(200, ['ok' => true, 'data' => $st->fetchAll()]);
    }

    // ---------- CREATE ----------
    if ($method === 'POST') {
        [$clean, $errors] = validate($body, $M);
        if ($errors) out(422, ['ok' => false, 'message' => 'ข้อมูลไม่ถูกต้อง', 'errors' => $errors]);
        $vals = [$M['name'] => $clean['name']];
        foreach (['price', 'stock', 'unit'] as $k) if (isset($M[$k])) $vals[$M[$k]] = $clean[$k];
        foreach ($S['cols'] as $c) {            // เติมค่าให้คอลัมน์ NOT NULL ที่ไม่มี default (กัน strict mode error)
            $n = $c['cname'];
            if (array_key_exists($n, $vals)) continue;
            $auto = stripos($c['extra'], 'auto_increment') !== false || stripos($c['extra'], 'GENERATED') !== false;
            if ($n === $M['id'] && !$auto) {
                $vals[$n] = (int)$pdo->query("SELECT COALESCE(MAX(" . q($n) . "),0)+1 FROM $T")->fetchColumn();
            } elseif ($c['nullable'] === 'NO' && $c['def'] === null && !$auto) {
                $numeric = ['int', 'tinyint', 'smallint', 'mediumint', 'bigint', 'decimal', 'float', 'double', 'bit'];
                $vals[$n] = in_array($c['dtype'], $numeric, true) ? 0 : (in_array($c['dtype'], ['date', 'datetime', 'timestamp'], true) ? '1970-01-01' : '');
            }
        }
        $cn = array_map('q', array_keys($vals));
        $ph = implode(', ', array_fill(0, count($vals), '?'));
        $pdo->prepare("INSERT INTO $T (" . implode(', ', $cn) . ") VALUES ($ph)")->execute(array_values($vals));
        out(201, ['ok' => true, 'message' => 'เพิ่มสินค้าสำเร็จ', 'id' => $pdo->lastInsertId()]);
    }

    // ---------- UPDATE ----------
    if ($method === 'PUT') {
        $id = $body['id'] ?? $_GET['id'] ?? null;
        if (!$id || !is_numeric($id)) out(422, ['ok' => false, 'message' => 'ไม่พบรหัสสินค้า']);
        [$clean, $errors] = validate($body, $M);
        if ($errors) out(422, ['ok' => false, 'message' => 'ข้อมูลไม่ถูกต้อง', 'errors' => $errors]);
        $sets = [q($M['name']) . '=?']; $vals = [$clean['name']];
        foreach (['price', 'stock', 'unit'] as $k) if (isset($M[$k])) { $sets[] = q($M[$k]) . '=?'; $vals[] = $clean[$k]; }
        $vals[] = $id;
        $st = $pdo->prepare("UPDATE $T SET " . implode(', ', $sets) . " WHERE " . q($M['id']) . "=?");
        $st->execute($vals);
        $chk = $pdo->prepare("SELECT COUNT(*) FROM $T WHERE " . q($M['id']) . "=?");
        $chk->execute([$id]);
        if (!$chk->fetchColumn()) out(404, ['ok' => false, 'message' => 'ไม่พบสินค้านี้']);
        out(200, ['ok' => true, 'message' => 'แก้ไขสินค้าสำเร็จ']);
    }

    // ---------- DELETE ----------
    if ($method === 'DELETE') {
        $id = $_GET['id'] ?? $body['id'] ?? null;
        if (!$id || !is_numeric($id)) out(422, ['ok' => false, 'message' => 'ไม่พบรหัสสินค้า']);
        $st = $pdo->prepare("DELETE FROM $T WHERE " . q($M['id']) . "=?");
        $st->execute([$id]);
        if ($st->rowCount() === 0) out(404, ['ok' => false, 'message' => 'ไม่พบสินค้านี้']);
        out(200, ['ok' => true, 'message' => 'ลบสินค้าสำเร็จ']);
    }

    out(405, ['ok' => false, 'message' => 'Method not allowed']);
} catch (PDOException $e) {
    if ($e->getCode() === '23000') {
        out(409, ['ok' => false, 'message' => 'ทำรายการไม่ได้ เพราะข้อมูลนี้ถูกอ้างอิงอยู่ (เช่น อยู่ในใบสั่งซื้อ) หรือข้อมูลซ้ำ']);
    }
    out(500, ['ok' => false, 'message' => 'เกิดข้อผิดพลาดของฐานข้อมูล: ' . $e->getMessage()]);
}
