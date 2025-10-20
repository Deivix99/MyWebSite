<?php
require __DIR__.'/../config.mssql.php';
require __DIR__.'/_partials.php';

$photoUrl = null;

if (!empty($_FILES['photo']['name']) && $_FILES['photo']['error'] === UPLOAD_ERR_OK) {
    // Validaciones
    $allowed = ['image/jpeg','image/png','image/webp','image/gif'];
    $finfo   = finfo_open(FILEINFO_MIME_TYPE);
    $mime    = finfo_file($finfo, $_FILES['photo']['tmp_name']);
    finfo_close($finfo);

    if (!in_array($mime, $allowed, true)) {
        die('Tipo de archivo no permitido');
    }
    if ($_FILES['photo']['size'] > 2 * 1024 * 1024) { // 2 MB
        die('La imagen supera 2MB');
    }

    // Nombre único y carpeta destino
    $ext       = pathinfo($_FILES['photo']['name'], PATHINFO_EXTENSION);
    $slug      = bin2hex(random_bytes(8)); // nombre aleatorio
    $fileName  = "pf-{$slug}.".strtolower($ext);
    $publicDir = __DIR__.'/public/img/uploads';              // ruta física
    $publicUrl = 'img/uploads/'.$fileName;                   // ruta web

    if (!is_dir($publicDir)) {
        mkdir($publicDir, 0755, true);
    }

    $destPath = $publicDir.'/'.$fileName;

    if (!move_uploaded_file($_FILES['photo']['tmp_name'], $destPath)) {
        die('No se pudo guardar la imagen');
    }

    // Opcional: setear permisos de archivo
    @chmod($destPath, 0644);

    $photoUrl = $publicUrl; // lo que guardaremos en la BD
}

// Actualiza el perfil (asumo 1 solo perfil)
if ($photoUrl) {
    $stmt = $pdo->prepare("UPDATE dbo.profile SET photo_url = :url WHERE id = (
                             SELECT TOP 1 id FROM dbo.profile ORDER BY id ASC
                           )");
    $stmt->execute([':url' => $photoUrl]);
}

// Redirige de vuelta al panel
header('Location: admin.php?ok=1');
exit;
