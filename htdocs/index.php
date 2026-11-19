<?php
require __DIR__ . '/app.php';
$student = voter();
$category = ($_GET['categoria'] ?? '') === 'mister' ? 'mister' : 'miss';
$categories = ['miss' => 'Candidatas a Miss', 'mister' => 'Candidatos a Mister'];
if ($student) {
    $open = eventOpen();
    $registered = $student[$category . '_id'] !== null;
    $candidates = q('SELECT * FROM candidatos WHERE activo = 1 AND categoria = ? ORDER BY nombre, id', [$category])->fetchAll(PDO::FETCH_ASSOC);
    $photos = [];
    foreach (q('SELECT f.candidato_id, f.ruta FROM fotos_candidato f JOIN candidatos c ON c.id=f.candidato_id WHERE c.activo=1 AND c.categoria=? ORDER BY f.candidato_id, f.posicion', [$category]) as $row) {
        $photos[$row['candidato_id']][] = $row['ruta'];
    }
}
page('Votación');
?>
<div class="election">
<section class="masthead shell" aria-labelledby="event-title">
    <div class="mast-copy">
        <p class="event-kicker">Elección estudiantil</p>
        <h1 id="event-title">Miss <em>&amp;</em> Mister</h1>
        <p class="event-degree">Ingeniería de Software</p>
        <?php if ($student): ?><a class="explore" href="#gallery">Explorar candidaturas <span aria-hidden="true">↓</span></a><?php endif; ?>
    </div>
    <div class="crown-art" aria-hidden="true"><div class="halo"></div><div class="crown"><i></i><b></b><em></em></div></div>
</section>
<?php if (!$student): ?>
<section class="shell login-strip" aria-labelledby="login-title">
    <div><h2 id="login-title">Acceso de estudiantes</h2><p>Ingresa con tu cuenta institucional <strong>@senati.pe</strong> para ver las candidaturas y votar.</p></div>
    <a class="button primary sign-in" href="senati.php"><span class="ms-symbol" aria-hidden="true"></span>Ingresar con mi cuenta SENATI</a>
</section>
<?php else: ?>
<div class="shell session-strip"><div><span class="session-dot" aria-hidden="true"></span><span><?= e($student['correo']) ?></span></div>
<form method="post" action="login.php"><?= csrf() ?><button name="accion" value="salir_estudiante">Cerrar sesión</button></form></div>
<section class="shell ballot" aria-labelledby="gallery">
    <div class="ballot-heading"><div><h2 id="gallery">Candidaturas</h2><p>Elige a tu representante en cada categoría.</p></div>
        <span class="status <?= $open ? 'open' : 'closed' ?>"><?= $open ? 'Votación abierta' : 'Votaciones Cerradas' ?></span>
    </div>
    <nav class="category-switch" aria-label="Categorías de votación">
        <?php foreach ($categories as $key => $label): ?>
        <a href="?categoria=<?= $key ?>#gallery" <?= $category === $key ? 'aria-current="page"' : '' ?>>
            <span><?= $label ?></span><?php if ($student[$key . '_id'] !== null): ?><span class="voted-check" aria-label="Voto registrado">✓</span><?php endif; ?>
        </a>
        <?php endforeach; ?>
    </nav>
    <?php if ($registered): ?><p class="notice" role="status">Voto Registrado en esta categoría.</p><?php endif; ?>
    <?php if (!$candidates): ?>
        <div class="empty-ballot"><h3>Próximamente en <?= ucfirst($category) ?></h3><p>Las candidaturas aparecerán aquí cuando la organización las publique.</p></div>
    <?php else: ?>
    <div class="ballot-grid">
        <?php foreach ($candidates as $candidate):
            $album = array_merge([$candidate['foto']], $photos[$candidate['id']] ?? []);
            $name = $candidate['nombre']; ?>
        <article class="nominee">
            <div class="album-frame" data-portrait-album>
                <div class="album-slides" tabindex="0" role="region" aria-label="Fotografías de <?= e($name) ?>">
                    <?php foreach ($album as $i => $path): ?>
                    <a class="album-slide" href="<?= e($path) ?>" aria-label="Ampliar foto <?= $i+1 ?> de <?= e($name) ?>"><img src="<?= e($path) ?>" alt="<?= e($name) ?> · Foto <?= $i+1 ?>" width="480" height="540" loading="lazy"></a>
                    <?php endforeach; ?>
                </div>
                <span class="enlarge-mark" aria-hidden="true">⤢</span>
                <?php if (count($album) > 1): ?>
                <div class="album-tools" hidden><button type="button" data-slide="-1" aria-label="Foto anterior">‹</button><span aria-live="polite">1 / <?= count($album) ?></span><button type="button" data-slide="1" aria-label="Foto siguiente">›</button></div>
                <?php endif; ?>
            </div>
            <div class="nominee-info">
                <?php if ($category === 'miss' && !empty($candidate['video'])): ?>
                <details class="album-video"><summary class="button light">▶ Ver video del álbum</summary>
                <video controls playsinline preload="none" src="<?= e($candidate['video']) ?>" aria-label="Video de <?= e($name) ?>"></video></details>
                <?php endif; ?>
                <p class="category-label"><?= ucfirst($category) ?></p><h3><?= e($name) ?></h3>
                <?php if ($registered || !$open): ?>
                    <button class="button vote-action" disabled><?= $registered ? 'Voto Registrado' : 'Votaciones Cerradas' ?></button>
                <?php else: ?>
                    <form method="post" action="votar.php" data-confirm="¿Confirmas tu voto por <?= e($name) ?>?">
                        <?= csrf() ?><input type="hidden" name="id" value="<?= (int) $candidate['id'] ?>">
                        <button class="button primary vote-action" aria-label="Votar por <?= e($name) ?>">Votar <span aria-hidden="true">→</span></button>
                    </form>
                <?php endif; ?>
            </div>
        </article>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>
</section>
<dialog class="portrait-dialog" aria-labelledby="viewer-title">
    <div class="viewer-head"><h2 id="viewer-title">Fotografía</h2><button type="button" data-close>Cerrar <span aria-hidden="true">×</span></button></div>
    <img data-full-photo alt="">
    <div class="viewer-nav" hidden><button type="button" data-move="-1" aria-label="Fotografía anterior">‹</button><span aria-live="polite"></span><button type="button" data-move="1" aria-label="Fotografía siguiente">›</button></div>
</dialog>
<?php endif; ?>
</div>
<?php footer(); ?>
