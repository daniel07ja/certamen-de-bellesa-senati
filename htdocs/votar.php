<?php
require __DIR__ . '/app.php';
requirePost();
$student = voter();
$id = filter_var(post('id'), FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
if (!$student || !$id) {
    go('index.php', 'Inicia sesión y selecciona un participante válido.');
}

[$category, $message] = transaction(function () use ($student, $id): array {
    if (!eventOpen(true)) {
        return ['miss', 'Votaciones Cerradas.'];
    }
    $candidate = q('SELECT categoria FROM candidatos WHERE id = ? AND activo = 1', [$id])->fetchColumn();
    if (!$candidate) {
        return ['miss', 'El participante ya no está disponible.'];
    }
    $column = $candidate === 'miss' ? 'miss_id' : 'mister_id';
    $update = q("UPDATE votantes SET $column = ? WHERE id = ? AND codigo_hash = ? AND $column IS NULL",
        [$id, $student['id'], $student['codigo_hash']]);
    return [$candidate, $update->rowCount() === 1
        ? 'Voto Registrado.'
        : 'Ya existe un voto en esta categoría o tu código fue actualizado.'];
});
go('index.php?categoria=' . $category . '#gallery', $message);
