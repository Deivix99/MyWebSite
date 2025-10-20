<?php
session_start();
require __DIR__.'/../config.mssql.php';
require __DIR__.'/_partials.php';


csrf_boot(); // CSRF listo

// ⚠️ cambia esta clave para tu admin
const ADMIN_PASS = 'root';





// === Config de la carpeta y archivo por defecto ===
const RESOURCES_DIR = __DIR__ . '/resources';       // carpeta física
const RESOURCES_URL = 'resources/';                 // ruta web
const RESOURCE_PHOTO_BASENAME = 'perfil.jpg';       // pon aquí tu archivo, o déjalo '' para autodetectar

function resolve_resources_photo(): ?string {
  // 1) Si definiste un nombre fijo
  if (RESOURCE_PHOTO_BASENAME !== '') {
    $abs = rtrim(RESOURCES_DIR, '/').'/'.RESOURCE_PHOTO_BASENAME;
    if (is_file($abs)) return rtrim(RESOURCES_URL,'/').'/'.RESOURCE_PHOTO_BASENAME;
  }

  // 2) Candidatos comunes
  $candidates = ['avatar.jpg','avatar.png','photo.jpg','photo.png','profile.jpg','profile.png','avatar.webp','photo.webp','profile.webp'];
  foreach ($candidates as $f) {
    $abs = rtrim(RESOURCES_DIR,'/').'/'.$f;
    if (is_file($abs)) return rtrim(RESOURCES_URL,'/').'/'.$f;
  }

  // 3) Autodetectar el primer archivo de imagen en resources/
  $patterns = ['*.jpg','*.jpeg','*.png','*.webp','*.gif'];
  foreach ($patterns as $p) {
    foreach (glob(rtrim(RESOURCES_DIR,'/').'/'.$p, GLOB_NOSORT) as $abs) {
      $base = basename($abs);
      return rtrim(RESOURCES_URL,'/').'/'.$base;
    }
  }

  // Nada encontrado
  return null;
}


// ================== PERFIL: crear/actualizar (SIN inputs de foto) ==================
if (isset($_POST['save_profile'])) {
  csrf_check();
  try {
    // 1) Detectar foto automáticamente desde /resources (puede quedar null)
    $autoPhotoUrl = resolve_resources_photo();

    // 2) Campos del perfil (trim para limpiar espacios)
    $full_name  = trim($_POST['full_name']  ?? '');
    $title      = trim($_POST['title']      ?? '');
    $email      = trim($_POST['email']      ?? '');
    $location   = trim($_POST['location']   ?? '');
    $linkedin   = trim($_POST['linkedin']   ?? '');
    $github     = trim($_POST['github']     ?? '');
    $summary    = trim($_POST['summary']    ?? '');

    // 3) ¿Existe perfil?
    $row = $pdo->query("SELECT TOP 1 id FROM dbo.profile ORDER BY id ASC")->fetch();

    if ($row) {
      // UPDATE (sin resume_url)
      $sql = "UPDATE dbo.profile SET
                full_name = :full_name,
                title     = :title,
                email     = :email,
                location  = :location,
                linkedin  = :linkedin,
                github    = :github,
                summary   = :summary,
                photo_url = COALESCE(:photo_url, photo_url)
              WHERE id = :id";
      $stmt = $pdo->prepare($sql);
      $stmt->execute([
        ':full_name'  => $full_name,
        ':title'      => $title,
        ':email'      => $email,
        ':location'   => $location,
        ':linkedin'   => $linkedin,
        ':github'     => $github,
        ':summary'    => $summary,
        ':photo_url'  => $autoPhotoUrl, // null => conserva la existente
        ':id'         => (int)$row['id'],
      ]);
    } else {
      // INSERT (sin resume_url)
      $sql = "INSERT INTO dbo.profile
                (full_name, title, email, location, linkedin, github, summary, photo_url)
              VALUES
                (:full_name, :title, :email, :location, :linkedin, :github, :summary, :photo_url)";
      $stmt = $pdo->prepare($sql);
      $stmt->execute([
        ':full_name'  => $full_name,
        ':title'      => $title,
        ':email'      => $email,
        ':location'   => $location,
        ':linkedin'   => $linkedin,
        ':github'     => $github,
        ':summary'    => $summary,
        ':photo_url'  => $autoPhotoUrl, // puede ser null
      ]);
    }

    header('Location: admin.php?ok=' . urlencode('Perfil guardado ✅')); exit;

  } catch (Throwable $e) {
    $err = "Error guardando perfil: " . $e->getMessage();
  }
}


// ----------------- Auth mínima -----------------
if(isset($_POST['doLogin'])){
  csrf_check();
  $_SESSION['is_admin'] = (($_POST['password'] ?? '') === ADMIN_PASS);
  if(!$_SESSION['is_admin']) $err = "Clave incorrecta";
}
if(isset($_GET['logout'])){
  session_destroy();
  header('Location: admin.php'); exit;
}
$auth = !empty($_SESSION['is_admin']);

if(!$auth){
  // Vista LOGIN
  ?>
  <!doctype html>
  <html lang="es">
  <head>
    <meta charset="utf-8">
    <title>Acceso — Admin CV</title>
    <meta name="viewport" content="width=device-width,initial-scale=1" />
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;800&display=swap" rel="stylesheet">
    <style>
      :root{ --bg:#0d0f14; --brand:#8bffcb; --brand-2:#62b2ff; --glass:rgba(255,255,255,.06); }
      body{background:radial-gradient(1100px 500px at 20% -10%, #1b2441 0%, var(--bg) 35%) fixed; color:#e9eef5; font-family:Inter,system-ui}
      .glass{backdrop-filter: blur(8px); background:var(--glass)}
      .shell{border:1px solid rgba(255,255,255,.08); border-radius:20px; padding:24px;
        background: linear-gradient(180deg, rgba(18,24,36,.85), rgba(14,20,30,.85));
        box-shadow: 0 10px 30px rgba(0,0,0,.35), inset 0 0 0 1px rgba(255,255,255,.04);}
      .fancy-input{background: rgba(255,255,255,.03)!important; color:#fff!important; border:1px solid rgba(255,255,255,.12)!important;}
      .fancy-input:focus{border-color: rgba(139,255,203,.65)!important; box-shadow: 0 0 0 .2rem rgba(139,255,203,.18)!important;}
      .btn-brand{color:#0b1220; font-weight:700; border:none; background:linear-gradient(90deg, var(--brand), var(--brand-2));
        box-shadow:0 8px 22px rgba(98,178,255,.25);}
    </style>
  </head>
  <body class="pb-5">
  <div class="container my-5" style="max-width:520px">
    <h1 class="h4 mb-3 text-center">Admin</h1>
    <?php if(!empty($err)) banner($err, 'danger'); ?>
    <div class="shell glass">
      <form method="post">
        <label class="form-label">Password</label>
        <?php input('password','password','','••••••••') ?>
        <?php csrf_input(); ?>
        <input type="hidden" name="doLogin" value="1">

        <div class="d-grid gap-2 mt-3">
          <button class="btn btn-brand">Enter</button>
          <a class="btn btn-outline-light" href="index.php">←Main</a>
        </div>
      </form>
    </div>
  </div>
  </body>
  </html>
  <?php
  exit;
}

// ----------------- Acciones CRUD (con CSRF) -----------------
if(isset($_POST['add_project'])){ csrf_check();
  $sql = "INSERT INTO dbo.projects(title, role, description, tech_stack, link, from_date, to_date)
          VALUES (?,?,?,?,?,?,?)";
  $stmt = $pdo->prepare($sql);
  $stmt->execute([
    $_POST['title'] ?? '',
    $_POST['role'] ?? '',
    $_POST['description'] ?? '',
    $_POST['tech_stack'] ?? '',
    $_POST['link'] ?? '',
    $_POST['from_date'] ?: null,
    $_POST['to_date']   ?: null
  ]);
  $ok = "Proyecto agregado ✅";
}

if(isset($_POST['update_project'])){ csrf_check();
  $id = (int)($_POST['id'] ?? 0);
  $sql = "UPDATE dbo.projects
          SET title=?, role=?, description=?, tech_stack=?, link=?, from_date=?, to_date=?
          WHERE id=?";
  $stmt = $pdo->prepare($sql);
  $stmt->execute([
    $_POST['title'] ?? '',
    $_POST['role'] ?? '',
    $_POST['description'] ?? '',
    $_POST['tech_stack'] ?? '',
    $_POST['link'] ?? '',
    $_POST['from_date'] ?: null,
    $_POST['to_date']   ?: null,
    $id
  ]);
  $ok = "Proyecto actualizado ✅";
}

if(isset($_POST['delete_project'])){ csrf_check();
  $id = (int)($_POST['id'] ?? 0);
  $pdo->prepare("DELETE FROM dbo.projects WHERE id=?")->execute([$id]);
  $ok = "Proyecto eliminado 🗑️";
}

if(isset($_POST['add_skill'])){ csrf_check();
  $lvl = strlen($_POST['level'] ?? '') ? (int)$_POST['level'] : null;
  $pdo->prepare("INSERT INTO dbo.skills(name, level, category) VALUES (?,?,?)")
      ->execute([ $_POST['name'] ?? '', $lvl, $_POST['category'] ?? null ]);
  $ok = "Habilidad agregada ✅";
}

if(isset($_POST['update_skill'])){ csrf_check();
  $id  = (int)($_POST['id'] ?? 0);
  $lvl = strlen($_POST['level'] ?? '') ? (int)$_POST['level'] : null;
  $pdo->prepare("UPDATE dbo.skills SET name=?, level=?, category=? WHERE id=?")
      ->execute([ $_POST['name'] ?? '', $lvl, $_POST['category'] ?? null, $id ]);
  $ok = "Habilidad actualizada ✅";
}

if(isset($_POST['delete_skill'])){ csrf_check();
  $id = (int)($_POST['id'] ?? 0);
  $pdo->prepare("DELETE FROM dbo.skills WHERE id=?")->execute([$id]);
  $ok = "Habilidad eliminada 🗑️";
}

// ----------------- Datos para mostrar -----------------
$profile  = $pdo->query("SELECT TOP 1 * FROM dbo.profile ORDER BY id ASC")->fetch();
$projects = $pdo->query("SELECT * FROM dbo.projects ORDER BY created_at DESC, id DESC")->fetchAll();
$skills   = $pdo->query("SELECT * FROM dbo.skills ORDER BY COALESCE(category,'~'), name")->fetchAll();

// Si viene ?edit_project=id o ?edit_skill=id, cargar registro
$editProject = null; $editSkill = null;
if(isset($_GET['edit_project'])){
  $id = (int)$_GET['edit_project'];
  $st = $pdo->prepare("SELECT * FROM dbo.projects WHERE id=?"); $st->execute([$id]);
  $editProject = $st->fetch();
}
if(isset($_GET['edit_skill'])){
  $id = (int)$_GET['edit_skill'];
  $st = $pdo->prepare("SELECT * FROM dbo.skills WHERE id=?"); $st->execute([$id]);
  $editSkill = $st->fetch();
}
?>
<!doctype html>
<html lang="es">
<head>
  <meta charset="utf-8">
  <title>Panel CV — Admin</title>
  <meta name="viewport" content="width=device-width,initial-scale=1" />
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;800&family=JetBrains+Mono:wght@400;700&display=swap" rel="stylesheet">
  <style>
    :root{
      --bg:#0d0f14; --panel:#12151d; --panel-2:#121826;
      --brand:#8bffcb; --brand-2:#62b2ff; --glass:rgba(255,255,255,.06);
    }
    body{background:radial-gradient(1100px 500px at 20% -10%, #1b2441 0%, var(--bg) 35%) fixed; color:#e9eef5; font-family:Inter,system-ui}
    .glass{backdrop-filter: blur(8px); background:var(--glass)}
    .shell{
      border:1px solid rgba(255,255,255,.08); border-radius:20px; padding:24px;
      background: linear-gradient(180deg, rgba(18,24,36,.85), rgba(14,20,30,.85));
      box-shadow: 0 10px 30px rgba(0,0,0,.35), inset 0 0 0 1px rgba(255,255,255,.04);
    }
    .fancy-input{
      background: rgba(255,255,255,.03)!important; color:#fff!important; border:1px solid rgba(255,255,255,.12)!important;
      transition: border-color .25s ease, box-shadow .25s ease, transform .08s ease;
    }
    .fancy-input:focus{
      border-color: rgba(139,255,203,.65)!important;
      box-shadow: 0 0 0 .2rem rgba(139,255,203,.18)!important;
      transform: translateY(-1px);
    }
    .btn-brand{
      color:#0b1220; font-weight:700; border:none;
      background:linear-gradient(90deg, var(--brand), var(--brand-2)); 
      box-shadow:0 8px 22px rgba(98,178,255,.25);
    }
    .btn-brand:hover{filter:brightness(.96)}
    .panel-tilt{
      transform: perspective(1000px) rotateX(6deg);
      transition: transform .6s ease, box-shadow .6s ease, border-color .6s ease;
      border:1px solid rgba(255,255,255,.1);
      background:linear-gradient(180deg, rgba(14,20,32,.7), rgba(12,16,26,.7));
      backdrop-filter: blur(10px);
    }
    .panel-tilt:hover{
      transform: perspective(1000px) rotateX(0) translateY(-2px);
      border-color: rgba(98,178,255,.35);
      box-shadow: 0 16px 36px rgba(0,0,0,.45), 0 0 0 1px rgba(139,255,203,.18) inset;
    }
    .table-dark{--bs-table-bg: rgba(12,16,26,.6); --bs-table-striped-bg: rgba(255,255,255,.02); --bs-table-hover-bg: rgba(255,255,255,.04)}
    .divider{height:1px; background:linear-gradient(90deg, transparent, rgba(255,255,255,.1), transparent)}
    .tag{font-family:"JetBrains Mono",monospace; font-size:.78rem; color:#b7c3cf}
  </style>
</head>
<body class="pb-5">
<div class="container my-4">
  <div class="d-flex align-items-center justify-content-between mb-3">
    <h1 class="h4 m-0">Panel del CV</h1>
    <div class="d-flex gap-2">
      <a class="btn btn-sm btn-outline-light" href="index.php">Ver CV</a>
      <a class="btn btn-sm btn-outline-danger" href="?logout=1">Salir</a>
    </div>
  </div>

  <?php if(!empty($ok)) banner($ok,'success'); ?>

  <div class="row g-3">

     <!-- ================== PERFIL ================== -->
<div class="col-12">
  <div class="shell panel-tilt">
    <h2 class="h6 mb-3">Perfil</h2>
    <form method="post" enctype="multipart/form-data" class="d-grid gap-2">
      <div class="row g-2">
        <div class="col-md-6">
          <label class="form-label">Nombre completo</label>
          <?php input('full_name','text',$profile['full_name'] ?? '','Tu Nombre') ?>
        </div>
        <div class="col-md-6">
          <label class="form-label">Título / Rol</label>
          <?php input('title','text',$profile['title'] ?? '','Desarrollador Backend') ?>
        </div>
      </div>

      <div class="row g-2">
        <div class="col-md-6">
          <label class="form-label">Email</label>
          <?php input('email','email',$profile['email'] ?? '','tu@correo.com') ?>
        </div>
        <div class="col-md-6">
          <label class="form-label">Ubicación</label>
          <?php input('location','text',$profile['location'] ?? '','Heredia, CR') ?>
        </div>
      </div>

      <div class="row g-2">
        <div class="col-md-4">
          <label class="form-label">LinkedIn</label>
          <?php input('linkedin','url',$profile['linkedin'] ?? '','https://www.linkedin.com/in/…') ?>
        </div>
        <div class="col-md-4">
          <label class="form-label">GitHub</label>
          <?php input('github','url',$profile['github'] ?? '','https://github.com/…') ?>
        </div>
        <div class="col-md-4">
          <label class="form-label">URL CV (PDF)</label>
          <?php input('resume_url','url',$profile['resume_url'] ?? '','https://…/cv.pdf') ?>
        </div>
      </div>

     <?php if (!empty($profile['photo_url'])): ?>
  <div class="mt-2">
    <img src="<?=h($profile['photo_url'])?>" alt="Foto actual" style="height:80px;border-radius:12px">
  </div>
<?php endif; ?>

  <?php csrf_input(); ?>
  <button class="btn btn-brand mt-1" name="save_profile" value="1">Guardar perfil</button>
</form>
  </div>
</div>


    <!-- ================== Proyectos: crear/editar ================== -->
    <div class="col-lg-7">
      <div class="shell panel-tilt">
        <div class="d-flex align-items-center justify-content-between">
          <h2 class="h6 mb-3"><?= $editProject ? 'Editar proyecto' : 'Agregar proyecto' ?></h2>
          <?php if($editProject): ?><a class="small link-light" href="admin.php">cancelar edición</a><?php endif; ?>
        </div>

        <form method="post" class="d-grid gap-2">
          <div><label class="form-label">Título</label><?php input('title','text',$editProject['title'] ?? '') ?></div>
          <div><label class="form-label">Rol</label><?php input('role','text',$editProject['role'] ?? '') ?></div>
          <div>
            <label class="form-label">Descripción</label>
            <textarea class="form-control fancy-input" name="description" rows="4" placeholder="Tu aporte, alcance y resultados."><?=h($editProject['description'] ?? '')?></textarea>
          </div>
          <div><label class="form-label">Stack (coma)</label><?php input('tech_stack','text',$editProject['tech_stack'] ?? '','PHP, SQL Server, Docker') ?></div>
          <div><label class="form-label">Link</label><?php input('link','url',$editProject['link'] ?? '','https://...') ?></div>
          <div class="row">
            <div class="col"><?php echo '<label class="form-label">Desde</label>'; input('from_date','date',$editProject['from_date'] ?? '') ?></div>
            <div class="col"><?php echo '<label class="form-label">Hasta</label>'; input('to_date','date',$editProject['to_date'] ?? '') ?></div>
          </div>
          <?php csrf_input(); ?>
          <?php if($editProject): ?>
            <input type="hidden" name="id" value="<?= (int)$editProject['id'] ?>">
            <button class="btn btn-brand mt-1" name="update_project" value="1">Actualizar proyecto</button>
          <?php else: ?>
            <button class="btn btn-brand mt-1" name="add_project" value="1">Guardar proyecto</button>
          <?php endif; ?>
        </form>
      </div>
    </div>

    <!-- ================== Habilidades: crear/editar ================== -->
    <div class="col-lg-5">
      <div class="shell panel-tilt">
        <div class="d-flex align-items-center justify-content-between">
          <h2 class="h6 mb-3"><?= $editSkill ? 'Editar habilidad' : 'Agregar habilidad' ?></h2>
          <?php if($editSkill): ?><a class="small link-light" href="admin.php">cancelar edición</a><?php endif; ?>
        </div>
        <form method="post" class="d-grid gap-2">
          <div><label class="form-label">Nombre</label><?php input('name','text',$editSkill['name'] ?? '','Spring Boot / SQL / Docker') ?></div>
          <div><label class="form-label">Nivel (1-5)</label><?php input('level','number',$editSkill['level'] ?? '','min="1" max="5"') ?></div>
          <div><label class="form-label">Categoría</label><?php input('category','text',$editSkill['category'] ?? '','Backend / DevOps / Data / Soft') ?></div>
          <?php csrf_input(); ?>
          <?php if($editSkill): ?>
            <input type="hidden" name="id" value="<?= (int)$editSkill['id'] ?>">
            <button class="btn btn-brand mt-1" name="update_skill" value="1">Actualizar habilidad</button>
          <?php else: ?>
            <button class="btn btn-brand mt-1" name="add_skill" value="1">Guardar habilidad</button>
          <?php endif; ?>
        </form>
      </div>
    </div>
  </div>

  <div class="my-4 divider"></div>

  <!-- ================== Listado de proyectos ================== -->
  <section class="shell panel-tilt mb-3">
    <h3 class="h6 mb-3">Proyectos</h3>
    <div class="table-responsive">
      <table class="table table-dark table-striped table-hover align-middle">
        <thead><tr><th>Título</th><th>Rol</th><th>Periodo</th><th>Stack</th><th>Acciones</th></tr></thead>
        <tbody>
          <?php if(!$projects): ?>
            <tr><td colspan="5" class="text-secondary">No hay proyectos aún.</td></tr>
          <?php else: foreach($projects as $p): ?>
            <tr>
              <td><?=h($p['title'])?></td>
              <td><span class="tag"><?=h($p['role'])?></span></td>
              <td class="small text-secondary">
                <?php
                  $from = $p['from_date'] ? date('Y-m', strtotime($p['from_date'])) : '';
                  $to   = $p['to_date']   ? date('Y-m', strtotime($p['to_date']))   : 'Actual';
                  echo h(trim("$from – $to", ' –'));
                ?>
              </td>
              <td class="small"><?=h($p['tech_stack'])?></td>
              <td class="text-nowrap">
                <a class="btn btn-sm btn-outline-light" href="?edit_project=<?= (int)$p['id'] ?>">Editar</a>
                <form method="post" class="d-inline" onsubmit="return confirm('¿Eliminar este proyecto?')">
                  <?php csrf_input(); ?>
                  <input type="hidden" name="id" value="<?= (int)$p['id'] ?>">
                  <button class="btn btn-sm btn-outline-danger" name="delete_project" value="1">Eliminar</button>
                </form>
              </td>
            </tr>
          <?php endforeach; endif; ?>
        </tbody>
      </table>
    </div>
  </section>

  <!-- ================== Listado de habilidades ================== -->
  <section class="shell panel-tilt">
    <h3 class="h6 mb-3">Habilidades</h3>
    <div class="table-responsive">
      <table class="table table-dark table-striped table-hover align-middle">
        <thead><tr><th>Nombre</th><th>Nivel</th><th>Categoría</th><th>Acciones</th></tr></thead>
        <tbody>
          <?php if(!$skills): ?>
            <tr><td colspan="4" class="text-secondary">No hay habilidades.</td></tr>
          <?php else: foreach($skills as $s): ?>
            <tr>
              <td><?=h($s['name'])?></td>
              <td><?=h($s['level'])?></td>
              <td><span class="tag"><?=h($s['category'])?></span></td>
              <td class="text-nowrap">
                <a class="btn btn-sm btn-outline-light" href="?edit_skill=<?= (int)$s['id'] ?>">Editar</a>
                <form method="post" class="d-inline" onsubmit="return confirm('¿Eliminar esta habilidad?')">
                  <?php csrf_input(); ?>
                  <input type="hidden" name="id" value="<?= (int)$s['id'] ?>">
                  <button class="btn btn-sm btn-outline-danger" name="delete_skill" value="1">Eliminar</button>
                </form>
              </td>
            </tr>
          <?php endforeach; endif; ?>
        </tbody>
      </table>
    </div>
  </section>

  <div class="my-4 divider"></div>
</div>
</body>
</html>
