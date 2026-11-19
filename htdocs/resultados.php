<?php
require __DIR__ . '/app.php';
$admin = adminUser();
if (!$admin || $admin['cambiar_clave']) {
    http_response_code(403);
    exit('Acceso restringido.');
}
session_write_close();

$results = transaction(function (): array {
    $totals = q('SELECT COUNT(CASE WHEN miss_id IS NOT NULL OR mister_id IS NOT NULL THEN 1 END) estudiantes,
                        COUNT(miss_id) miss, COUNT(mister_id) mister FROM votantes')->fetch(PDO::FETCH_ASSOC);
    $rows = q("SELECT c.nombre, c.categoria, c.activo,
        CASE c.categoria WHEN 'miss' THEN COALESCE(m.votos, 0) ELSE COALESCE(r.votos, 0) END votos
        FROM candidatos c
        LEFT JOIN (SELECT miss_id, COUNT(*) votos FROM votantes WHERE miss_id IS NOT NULL GROUP BY miss_id) m ON c.id = m.miss_id
        LEFT JOIN (SELECT mister_id, COUNT(*) votos FROM votantes WHERE mister_id IS NOT NULL GROUP BY mister_id) r ON c.id = r.mister_id
        ORDER BY c.categoria, votos DESC, c.nombre, c.id")->fetchAll(PDO::FETCH_ASSOC);
    return ['totales' => $totals, 'participantes' => $rows];
});
header('Content-Type: application/json; charset=utf-8');
echo json_encode($results, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
