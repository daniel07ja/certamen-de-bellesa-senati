<?php
require __DIR__ . '/app.php';
$admin = adminUser();
if (!$admin) {
    go('login.php');
}

ensureElectionSchema();
try {
    q('SELECT video FROM candidatos LIMIT 0');
} catch (PDOException $error) {
    if ((int) ($error->errorInfo[1] ?? 0) !== 1054) { throw $error; }
    try { q('ALTER TABLE candidatos ADD COLUMN video VARCHAR(100) DEFAULT NULL'); }
    catch (PDOException $race) { if ((int) ($race->errorInfo[1] ?? 0) !== 1060) { throw $race; } }
}

function readResetHistory(int $before): array
{
    $sql = 'SELECT * FROM historial_borrados WHERE id < ? ORDER BY id DESC LIMIT 21';
    try {
        $rows = q($sql, [$before]);
    } catch (PDOException $error) {
        if ((int) ($error->errorInfo[1] ?? 0) !== 1146) { throw $error; }
        q('CREATE TABLE IF NOT EXISTS historial_borrados (
 id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 administrador_id INT UNSIGNED NOT NULL,
 usuario VARCHAR(64) NOT NULL,
 responsable VARCHAR(120) NOT NULL,
 instante BIGINT UNSIGNED NOT NULL,
 estudiantes INT UNSIGNED NOT NULL,
 votos_miss INT UNSIGNED NOT NULL,
 votos_mister INT UNSIGNED NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4');
        $rows = q($sql, [$before]);
    }
    return $rows->fetchAll(PDO::FETCH_ASSOC);
}

function resetBallots(array $admin): void
{
    $name = trim(post('responsable'));
    if (!preg_match('/\A[\p{L}\p{M} .’\'-]{2,120}\z/u', $name) || !preg_match('/\p{L}.*\p{L}/u', $name)) {
        throw new DomainException('Escribe el nombre de la persona responsable del borrado.');
    }
    $version = post('ultimo_borrado');
    if (!ctype_digit($version) || strlen($version) > 10) {
        throw new DomainException('Abre el panel de nuevo para confirmar el borrado.');
    }
    if (($_SESSION['reintento_borrado'] ?? 0) > time()) {
        throw new DomainException('Espera unos segundos para volver a confirmar tus credenciales.');
    }
    $password = post('clave_borrado');
    if (!hash_equals($admin['usuario'], trim(post('usuario_borrado'))) || $password === '' || strlen($password) > 72
        || str_contains($password, "\0") || !password_verify($password, $admin['clave_hash'])) {
        $_SESSION['reintento_borrado'] = time() + 3;
        throw new DomainException('El usuario o la contraseña no coinciden con tu sesión.');
    }
    transaction(function () use ($admin, $name, $version) {
        eventOpen(true); // Serializa el borrado con los votos y los cambios de estado.
        $hash = q('SELECT clave_hash FROM administradores WHERE id=? FOR UPDATE', [$admin['id']])->fetchColumn();
        if (!is_string($hash) || !hash_equals($admin['clave_hash'], $hash)) {
            throw new DomainException('La credencial cambió. Vuelve a iniciar sesión.');
        }
        $latest = q('SELECT COALESCE(MAX(id),0) FROM historial_borrados')->fetchColumn();
        if ((string) $latest !== $version) {
            throw new DomainException('Ya se hizo otro borrado. Revisa el historial antes de continuar.');
        }
        $totals = q('SELECT COUNT(*) estudiantes, COUNT(miss_id) miss, COUNT(mister_id) mister
            FROM votantes WHERE miss_id IS NOT NULL OR mister_id IS NOT NULL')->fetch(PDO::FETCH_ASSOC);
        if (!(int) $totals['estudiantes']) { throw new DomainException('No hay votos que borrar.'); }
        q('INSERT INTO historial_borrados (administrador_id,usuario,responsable,instante,estudiantes,votos_miss,votos_mister)
           VALUES (?,?,?,UNIX_TIMESTAMP(),?,?,?)', [$admin['id'], $admin['usuario'], $name, $totals['estudiantes'], $totals['miss'], $totals['mister']]);
        q('UPDATE configuracion SET abierta=0 WHERE id=1');
        q('UPDATE votantes SET miss_id=NULL, mister_id=NULL WHERE miss_id IS NOT NULL OR mister_id IS NOT NULL');
    });
}

function candidateId()
{
    return filter_var(post('id'), FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
}

function removeMedia(string $path): void
{
    if (preg_match('~\Auploads/[a-f0-9]{32}\.(jpg|png|webp|mp4|webm)\z~', $path)) {
        $absolute = __DIR__ . '/' . $path;
        if (is_file($absolute) && !@unlink($absolute)) {
            error_log('No se pudo limpiar un archivo del certamen: ' . $path);
        }
    }
}

function receivePhoto(array $file): ?string
{
    if (!$file || $file['error'] === UPLOAD_ERR_NO_FILE) {
        return null;
    }
    if ($file['error'] !== UPLOAD_ERR_OK || $file['size'] > 4 * 1024 * 1024 || !is_uploaded_file($file['tmp_name'])) {
        throw new DomainException('La foto debe pesar hasta 4 MB y haberse subido correctamente.');
    }
    $info = @getimagesize($file['tmp_name']);
    $mime = (new finfo(FILEINFO_MIME_TYPE))->file($file['tmp_name']);
    $types = [IMAGETYPE_JPEG => 'jpg', IMAGETYPE_PNG => 'png', IMAGETYPE_WEBP => 'webp'];
    $extension = $info ? ($types[$info[2]] ?? null) : null;
    if (!$extension || $mime !== $info['mime'] || $info[0] < 1 || $info[1] < 1 || $info[0] * $info[1] > 16_000_000) {
        throw new DomainException('Usa una foto JPG, PNG o WebP de hasta 16 megapíxeles.');
    }
    $path = 'uploads/' . bin2hex(random_bytes(16)) . '.' . $extension;
    if (!move_uploaded_file($file['tmp_name'], __DIR__ . '/' . $path)) {
        throw new DomainException('No se pudo guardar la foto. Revisa la carpeta uploads.');
    }
    return $path;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    requirePost();
    $action = post('accion');
    try {
        if ($action === 'clave') {
            $new = post('nueva');
            if (strlen(post('actual')) > 72 || str_contains(post('actual'), "\0") || !password_verify(post('actual'), $admin['clave_hash']) || !preg_match('/\A.{8,}\z/us', $new) || strlen($new) > 72
                || str_contains($new, "\0") || $new !== post('confirmacion') || password_verify($new, $admin['clave_hash'])
                || $new === 'Senati-Temporal-2026!') {
                throw new DomainException('Verifica tu contraseña actual y elige una nueva de al menos 8 caracteres y hasta 72 bytes que coincida con la confirmación.');
            }
            $hash = password_hash($new, PASSWORD_BCRYPT);
            $update = q('UPDATE administradores SET clave_hash = ?, cambiar_clave = 0 WHERE id = ? AND clave_hash = ?',
                [$hash, $admin['id'], $admin['clave_hash']]);
            if ($update->rowCount() !== 1) {
                go('login.php', 'La credencial cambió. Vuelve a iniciar sesión.');
            }
            $_SESSION['admin_version'] = hash('sha256', $hash);
            session_regenerate_id(true);
            unset($_SESSION['csrf']);
            go('admin.php', 'Contraseña actualizada.');
        }
        if ((int) $admin['cambiar_clave']) {
            throw new DomainException('Cambia la contraseña inicial para continuar.');
        }
        if ($action === 'video') {
            $id = candidateId();
            $upload = $_FILES['clip'] ?? [];
            if (!$id || ($upload['error'] ?? -1) !== UPLOAD_ERR_OK || !is_uploaded_file($upload['tmp_name'])
                || !$upload['size'] || $upload['size'] > 4 * 1024 * 1024) {
                throw new DomainException('Selecciona un clip de hasta 4 MB.');
            }
            $mime = (new finfo(FILEINFO_MIME_TYPE))->file($upload['tmp_name']);
            $type = ['video/mp4'=>'mp4', 'video/webm'=>'webm'][$mime] ?? null;
            if (!$type) { throw new DomainException('El clip debe ser un video MP4 o WebM.'); }
            $path = 'uploads/' . bin2hex(random_bytes(16)) . '.' . $type;
            if (!move_uploaded_file($upload['tmp_name'], __DIR__ . '/' . $path)) {
                throw new DomainException('No se pudo guardar el video.');
            }
            try {
                $old = transaction(function () use ($id, $path) {
                    eventOpen(true);
                    $row = q("SELECT video FROM candidatos WHERE id=? AND categoria='miss' AND activo=1 FOR UPDATE", [$id])->fetch(PDO::FETCH_ASSOC);
                    if (!$row) { throw new DomainException('Selecciona una candidata a Miss publicada.'); }
                    q('UPDATE candidatos SET video=? WHERE id=?', [$path, $id]);
                    return $row['video'];
                });
            } catch (Throwable $error) { removeMedia($path); throw $error; }
            if ($old) { removeMedia($old); }
            go('admin.php?editar=' . $id . '#video-candidata', 'Video publicado en el álbum.');
        }
        if ($action === 'borrar_votos') {
            resetBallots($admin);
            go('admin.php#historial-borrados', 'Votos borrados. La votación está cerrada y el responsable quedó registrado.');
        }
        if ($action === 'programar') {
            $raw = post('cierre');
            $dt = DateTimeImmutable::createFromFormat('!Y-m-d\TH:i', $raw, new DateTimeZone('America/Lima'));
            if (!$dt || $dt->format('Y-m-d\TH:i') !== $raw || $dt->getTimestamp() <= time()) {
                throw new DomainException('Elige una fecha y hora futura de Lima.');
            }
            q('UPDATE configuracion SET abierta = IF(cierre IS NOT NULL AND cierre <= UNIX_TIMESTAMP(), 0, abierta), cierre = ? WHERE id = 1', [$dt->getTimestamp()]);
            go('admin.php', 'Cierre programado.');
        }
        if ($action === 'estado') {
            $state = post('abierta') === '1' ? 1 : 0;
            if (!q('UPDATE configuracion SET abierta = ? WHERE id = 1 AND (? = 0 OR cierre IS NULL OR cierre > UNIX_TIMESTAMP())', [$state,$state])->rowCount() && $state && !eventOpen()) {
                throw new DomainException('Programa un cierre futuro antes de abrir.');
            }
            go('admin.php', $state ? 'Votaciones abiertas.' : 'Votaciones Cerradas.');
        }
        if ($action === 'guardar' || $action === 'eliminar') {
            $id = candidateId();
            if ((post('id') !== '' || $action === 'eliminar') && !$id) {
                throw new DomainException('Selecciona un participante válido.');
            }
            $photo = null;
            $added = [];
            $name = $category = null;
            if ($action === 'guardar') {
                $name = trim(post('nombre'));
                $category = post('categoria');
                if (!preg_match('/\A[^\p{C}]{2,120}\z/u', $name) || !in_array($category, ['miss', 'mister'], true)) {
                    throw new DomainException('Verifica el nombre y la categoría.');
                }

            }
            try {
                $files = $_FILES['fotos'] ?? [];
                $errors = $files['error'] ?? [];
                if (!is_array($errors) || count($errors) > 5) { throw new DomainException('Selecciona hasta cinco fotos.'); }
                foreach ($errors as $i => $err) {
                    $path = receivePhoto(['error' => $err, 'size' => $files['size'][$i], 'tmp_name' => $files['tmp_name'][$i]]);
                    if ($path) { $added[] = $path; }
                }
                $photo = $added[0] ?? null;
                [$message, $oldPhoto] = transaction(function () use ($action, $id, $added, $photo, $name, $category): array {
                    eventOpen(true);
                    $candidate = $id ? q('SELECT * FROM candidatos WHERE id = ? AND activo = 1', [$id])->fetch(PDO::FETCH_ASSOC) : null;
                    if ($id && !$candidate) {
                        throw new DomainException('El participante ya no está disponible.');
                    }
                    $extra = $id ? q('SELECT ruta FROM fotos_candidato WHERE candidato_id = ? ORDER BY posicion', [$id])->fetchAll(PDO::FETCH_COLUMN) : [];
                    $replace = post('reemplazar') === '1' && $photo;
                    $existing = $candidate && !$replace ? array_merge([$candidate['foto']], $extra) : [];
                    $album = array_merge($existing, $added);
                    if (count($album) > 5) { throw new DomainException('El álbum admite cinco fotos. Marca reemplazar para cargar otro conjunto.'); }
                    $votes = $id && (bool) q('SELECT EXISTS(SELECT 1 FROM votantes WHERE miss_id = ? OR mister_id = ?)', [$id, $id])->fetchColumn();
                    if ($action === 'eliminar') {
                        if ($votes) {
                            q('UPDATE candidatos SET activo = 0 WHERE id = ?', [$id]);
                            return ['Participante retirado de la galería. Se conservaron sus votos.', null];
                        }
                        q('DELETE FROM candidatos WHERE id = ?', [$id]);
                        return ['Participante eliminado.', array_merge(array_filter([$candidate['foto'], $candidate['video']]), $extra)];
                    }
                    if (!$id) {
                        if (!$photo) {
                            throw new DomainException('Sube una fotografía para registrar al participante.');
                        }
                        q('INSERT INTO candidatos (nombre, ciclo, categoria, foto) VALUES (?, 0, ?, ?)', [$name, $category, $photo]);
                        $id = (int) db()->lastInsertId();
                    } elseif ($votes && $category !== $candidate['categoria']) {
                        throw new DomainException('La categoría queda fija después de recibir votos.');
                    }
                    q('UPDATE candidatos SET nombre = ?, categoria = ?, foto = ? WHERE id = ?',
                        [$name, $category, $album[0], $id]);
                    if ($added) {
                        q('DELETE FROM fotos_candidato WHERE candidato_id = ?', [$id]);
                        foreach (array_slice($album, 1) as $offset => $path) {
                            q('INSERT INTO fotos_candidato VALUES (?, ?, ?)', [$id, $offset + 2, $path]);
                        }
                    }
                    $retired = $replace && $candidate ? array_merge([$candidate['foto']], $extra) : [];
                    if ($candidate && $candidate['video'] && $category !== 'miss') {
                        q('UPDATE candidatos SET video=NULL WHERE id=?', [$id]);
                        $retired[] = $candidate['video'];
                    }
                    return ['Participante guardado.', $retired];
                });
            } catch (Throwable $error) {
                foreach ($added as $path) { removeMedia($path); }
                throw $error;
            }
            foreach ($oldPhoto ?? [] as $path) { removeMedia($path); }
            go('admin.php#participantes', $message);
        }

        throw new DomainException('Acción desconocida.');
    } catch (DomainException $error) {
        go('admin.php', $error->getMessage());
    }
}

$mustChange = (bool) $admin['cambiar_clave'];
page('Panel de organización');
?>
<div class="shell section">
<div class="heading-row"><div><p class="eyebrow">ORGANIZACIÓN</p><h1>Panel del certamen</h1></div>
<form method="post" action="login.php"><?= csrf() ?><button class="button light" name="accion" value="salir_admin">Cerrar sesión</button></form></div>
<?php if (!$mustChange):
$open = eventOpen();
$editingId = filter_var($_GET['editar'] ?? null, FILTER_VALIDATE_INT);
$editing = $editingId ? q('SELECT * FROM candidatos WHERE id = ? AND activo = 1', [$editingId])->fetch(PDO::FETCH_ASSOC) : [];
$candidates = q('SELECT * FROM candidatos WHERE activo = 1 ORDER BY categoria, nombre, id')->fetchAll(PDO::FETCH_ASSOC);
$issued = $_SESSION['new_code'] ?? null;
unset($_SESSION['new_code']);
?>
<div class="admin-grid grid gap-5 lg:grid-cols-2">
<section class="panel"><h2>Estado de la votación</h2>
<p><span class="status <?= $open ? 'open' : 'closed' ?>"><?= $open ? 'Votaciones abiertas' : 'Votaciones Cerradas' ?></span></p>
<form method="post" data-confirm="<?= $open ? '¿Cerrar las votaciones?' : '¿Abrir las votaciones?' ?>">
<?= csrf() ?><input type="hidden" name="abierta" value="<?= $open ? 0 : 1 ?>">
<button class="button primary" name="accion" value="estado"><?= $open ? 'Cerrar votaciones' : 'Abrir votaciones' ?></button>
</form>
<?php $deadline = q('SELECT cierre FROM configuracion WHERE id=1')->fetchColumn(); ?>
<form class="form" method="post"><?= csrf() ?><label>Fecha y hora de cierre (Lima)<input type="datetime-local" name="cierre" required value="<?= $deadline ? (new DateTimeImmutable('@'.$deadline))->setTimezone(new DateTimeZone('America/Lima'))->format('Y-m-d\TH:i') : '' ?>"></label><button class="button light" name="accion" value="programar">Guardar cierre</button></form></section>
<section class="panel"><h2>Acceso estudiantil</h2><p>Los alumnos se identifican con su cuenta institucional de Microsoft 365.</p></section>
</div>
<section class="section" aria-labelledby="resultados"><div class="heading-row"><h2 id="resultados">Resultados</h2></div>
<div id="results" data-results="resultados.php" aria-busy="true">Cargando resultados…</div>
<p id="results-state" class="hint" role="status"></p>
<noscript><p>Activa JavaScript para la actualización automática.</p><a href="resultados.php">Ver datos del conteo</a></noscript>
</section>
<?php
$before = filter_var($_GET['antes'] ?? null, FILTER_VALIDATE_INT, ['options'=>['min_range'=>1]]) ?: PHP_INT_MAX;
$history = readResetHistory($before);
$older = count($history) > 20;
if ($older) { array_pop($history); }
$lastReset = (int) q('SELECT COALESCE(MAX(id),0) FROM historial_borrados')->fetchColumn();
?>
<section class="panel section" id="historial-borrados" aria-labelledby="historial-titulo">
<h2 id="historial-titulo">Historial de borrados</h2>
<?php if (!$history): ?><p>Aún no hay borrados en esta página.</p><?php else: ?>
<ul class="candidate-list">
<?php foreach ($history as $row):
    $when = (new DateTimeImmutable('@'.$row['instante']))->setTimezone(new DateTimeZone('America/Lima')); ?>
<li><div><strong><?= e($row['responsable']) ?></strong><br>Usuario: <?= e($row['usuario']) ?></div>
<div><time datetime="<?= $when->format(DATE_ATOM) ?>"><?= $when->format('d/m/Y H:i:s') ?> (Lima)</time><br>
<?= (int) $row['estudiantes'] ?> estudiantes · Miss: <?= (int) $row['votos_miss'] ?> · Mister: <?= (int) $row['votos_mister'] ?> · Total: <?= (int) $row['votos_miss'] + (int) $row['votos_mister'] ?></div></li>
<?php endforeach; ?></ul>
<?php endif; ?>
<?php if ($older): ?><a class="button light" href="?antes=<?= (int) end($history)['id'] ?>#historial-borrados">Ver borrados anteriores</a><?php endif; ?>
<?php if ($before !== PHP_INT_MAX): ?><a class="button light" href="admin.php#historial-borrados">Ver los más recientes</a><?php endif; ?>
<details><summary class="button danger">Borrar votos de Miss y Mister</summary>
<p>Ambos conteos volverán a cero y se cerrará la votación. Si la abres de nuevo, los estudiantes podrán volver a votar. Las candidaturas y las fotos se conservan.</p>
<form class="form" method="post" action="admin.php" data-confirm="¿Borrar todos los votos de Miss y Mister y cerrar la votación? Esta acción es definitiva.">
<?= csrf() ?><input type="hidden" name="ultimo_borrado" value="<?= $lastReset ?>">
<label>Nombre completo de quien borra<input name="responsable" required minlength="2" maxlength="120" autocomplete="name"></label>
<label>Tu usuario de administrador<input name="usuario_borrado" required maxlength="64" autocomplete="username"></label>
<label>Tu contraseña actual<input type="password" name="clave_borrado" required maxlength="72" autocomplete="current-password"></label>
<button class="button danger" name="accion" value="borrar_votos">Confirmar borrado de todos los votos</button>
</form></details>
</section>
<section id="participantes" aria-labelledby="candidatos-title"><h2 id="candidatos-title">Participantes</h2>
<div class="admin-grid grid gap-5 lg:grid-cols-2">
<div class="panel"><h3><?= $editing ? 'Editar participante' : 'Registrar participante' ?></h3>
<form class="form" method="post" action="admin.php" enctype="multipart/form-data">
<?= csrf() ?><input type="hidden" name="id" value="<?= $editing ? (int) $editing['id'] : '' ?>">
<label>Nombre completo<input name="nombre" required minlength="2" maxlength="120" value="<?= e($editing['nombre'] ?? '') ?>"></label>
<label>Categoría<select name="categoria" required>
<option value="miss" <?= ($editing['categoria'] ?? '') === 'miss' ? 'selected' : '' ?>>Miss</option>
<option value="mister" <?= ($editing['categoria'] ?? '') === 'mister' ? 'selected' : '' ?>>Mister</option>
</select></label>
<label>Fotografías (1 a 5)<input type="file" name="fotos[]" multiple accept="image/jpeg,image/png,image/webp" <?= $editing ? '' : 'required' ?>></label>
<?php if ($editing): ?><label><span><input style="width:auto" type="checkbox" name="reemplazar" value="1"> Reemplazar el álbum actual</span></label><?php endif; ?>
<p class="hint">JPG, PNG o WebP, hasta 4 MB. <?= $editing ? 'Déjalo vacío para mantener la foto actual.' : '' ?></p>
<button class="button primary" name="accion" value="guardar">Guardar participante</button>
<?php if ($editing): ?><a class="button light" href="admin.php#participantes">Cancelar edición</a><?php endif; ?>
</form>
<?php if ($editing && $editing['categoria'] === 'miss'): ?>
<section class="clip-edit" id="video-candidata"><h3>Video de <?= e($editing['nombre']) ?></h3>
<?php if (!empty($editing['video'])): ?><video controls playsinline preload="none" src="<?= e($editing['video']) ?>" aria-label="Video publicado"></video><?php endif; ?>
<form class="form" data-clip-editor method="post" action="admin.php" enctype="multipart/form-data">
<?= csrf() ?><input type="hidden" name="accion" value="video"><input type="hidden" name="id" value="<?= (int) $editing['id'] ?>">
<label>Video del álbum<input type="file" data-original accept="video/*" required></label>
<p class="hint">Publica hasta 15 segundos. Puedes ajustar dónde empieza el fragmento antes de guardarlo.</p>
<video data-preview playsinline controls hidden preload="metadata"></video>
<label hidden data-trim>Inicio del fragmento <output>0 s</output><input type="range" min="0" max="0" step="0.1" value="0" data-start></label>
<button class="button light" data-play type="button" hidden>Probar fragmento</button>
<p role="status" class="hint" data-clip-status></p><button class="button primary" data-publish disabled>Guardar video en el álbum</button>
<noscript>Activa JavaScript para preparar el video.</noscript>
</form></section><?php endif; ?></div>
<div class="panel"><h3>Galería publicada</h3>
<?php if (!$candidates): ?><p>Aún no hay participantes registrados.</p><?php endif; ?>
<ul class="candidate-list">
<?php foreach ($candidates as $candidate): ?>
<li><img src="<?= e($candidate['foto']) ?>" alt="" width="56" height="56" loading="lazy">
<div><strong><?= e($candidate['nombre']) ?></strong><br><?= e(ucfirst($candidate['categoria'])) ?></div>
<div class="row-actions"><a class="button light" href="?editar=<?= (int) $candidate['id'] ?>#participantes">Editar</a>
<?php if ($candidate['categoria'] === 'miss'): ?><a class="button light" href="?editar=<?= (int) $candidate['id'] ?>#video-candidata">Video del álbum</a><?php endif; ?>
<form method="post" data-confirm="¿Retirar a esta persona de la galería? Los votos que haya recibido se conservarán.">
<?= csrf() ?><input type="hidden" name="id" value="<?= (int) $candidate['id'] ?>">
<button class="button danger" name="accion" value="eliminar">Eliminar</button></form></div>
</li>
<?php endforeach; ?>
</ul></div></div></section>
<?php endif; ?>
<section class="security-card" aria-labelledby="security-title">
<div class="security-cover">
<?= brand() ?>
<div><p class="security-label">CUENTA DE ORGANIZACIÓN</p>
<h2 id="security-title"><?= $mustChange ? 'Cambia tu contraseña inicial' : 'Cambiar contraseña' ?></h2>
<p><?= $mustChange ? 'Elige una contraseña personal para activar tu panel.' : 'Actualiza tu acceso al panel del certamen.' ?></p></div>
</div>
<form class="security-form" method="post" data-password-form><?= csrf() ?>
<?php foreach (['actual' => 'Contraseña actual', 'nueva' => 'Nueva contraseña', 'confirmacion' => 'Confirmar nueva contraseña'] as $key => $label): ?>
<div><label for="clave-<?= $key ?>"><?= $label ?></label>
<div class="secret-field"><input id="clave-<?= $key ?>" type="password" name="<?= $key ?>" maxlength="72" required
<?= $key === 'actual' ? 'autocomplete="current-password"' : 'minlength="8" autocomplete="new-password" aria-describedby="clave-feedback"' ?>>
<button type="button" class="reveal" hidden aria-controls="clave-<?= $key ?>" aria-label="Mostrar: <?= $label ?>">Mostrar</button></div></div>
<?php endforeach; ?>
<p id="clave-feedback" class="password-feedback" role="status">Usa al menos 8 caracteres.</p>
<button class="button primary save-password" name="accion" value="clave">Actualizar contraseña</button>
</form></section>
</div>
<?php footer(); ?>
