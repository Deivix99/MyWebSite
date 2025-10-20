<?php
require __DIR__.'/../config.mssql.php';
require __DIR__.'/_partials.php';

// Datos
$profile  = $pdo->query("SELECT TOP 1 * FROM dbo.profile ORDER BY id ASC")->fetch();
$projects = $pdo->query("SELECT * FROM dbo.projects ORDER BY COALESCE(to_date, from_date) DESC, id DESC")->fetchAll();
$skills   = $pdo->query("
  SELECT category, STRING_AGG(CONCAT(name, COALESCE(' ('+CONVERT(varchar(10),level)+')','')), ', ')
         WITHIN GROUP (ORDER BY name) AS items
  FROM dbo.skills GROUP BY category
")->fetchAll();

function ym($d){ return $d ? date('M Y', strtotime($d)) : ''; }

// Foto: usa profile.photo_url si existe, si no un archivo local
$photo = isset($profile['photo_url']) && $profile['photo_url']
  ? $profile['photo_url']
  : 'img/profile.jpg'; // crea public/img/avatar.jpg con tu foto

// Links opcionales
$linkedin = $profile['linkedin'] ?? '';
$github   = $profile['github']   ?? '';
$cv_url = $profile['resume_file'] ?? ''; // usamos la nueva columna
$cv_file = $profile['resume_file'] ?? '';

?>
<!doctype html>
<html lang="es">
<head>
  <meta charset="utf-8">
  <title><?=h($profile['full_name'] ?? 'Mi CV');?> — <?=h($profile['title'] ?? '')?></title>
  <meta name="viewport" content="width=device-width,initial-scale=1" />
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;800&family=JetBrains+Mono:wght@400;700&display=swap" rel="stylesheet">
  <style>

    html { scroll-behavior: smooth; }

    :root{
      --bg:#0d0f14; --panel:#12151d; --panel-2:#121826; --card:#101523; --muted:#9aa7b3;
      --brand:#8bffcb; --brand-2:#62b2ff; --warn:#ffd166; --glass:rgba(255,255,255,.06);
      --border:rgba(255,255,255,.08);
    }
    *{box-sizing:border-box}
    html,body{height:100%}
    body{
      margin:0; color:#e9eef5; font-family:Inter,system-ui,Segoe UI,Roboto,Arial,sans-serif;
      background:
        radial-gradient(1200px 600px at 15% -10%, #1a2440 0%, var(--bg) 35%) fixed,
        linear-gradient(180deg, #0b1220, #0a0f17);
      overflow-x:hidden;
    }
    a{color:var(--warn); text-decoration:none}
    a:hover{opacity:.85}
    .glass{backdrop-filter: blur(10px); background:var(--glass)}
    .shell{border:1px solid var(--border); border-radius:20px; padding:22px; background:linear-gradient(180deg, rgba(18,24,36,.85), rgba(14,20,30,.85)); box-shadow:0 10px 30px rgba(0,0,0,.35), inset 0 0 0 1px rgba(255,255,255,.04);}
    .panel{border:1px solid var(--border); border-radius:18px; background:linear-gradient(180deg, rgba(14,19,31,.78), rgba(10,15,25,.78)); box-shadow:0 6px 22px rgba(0,0,0,.35), inset 0 0 0 1px rgba(255,255,255,.03);}
    .navbar{
      background: linear-gradient(180deg, rgba(16,24,40,.85), rgba(10,14,22,.85)) !important;
      border-bottom:1px solid var(--border);
    }
    .brand-dot{width:10px;height:10px;border-radius:999px;background: radial-gradient(circle at 30% 30%, var(--brand), #0aff9d 70%);
      box-shadow:0 0 18px rgba(139,255,203,.6), 0 0 28px rgba(98,178,255,.25); display:inline-block; animation:pulse 2.2s ease-in-out infinite;}
    @keyframes pulse{0%,100%{transform:scale(1)}50%{transform:scale(1.25)}}

    /* Sidebar */
    .avatar{
      width:120px;height:120px;border-radius:22px; overflow:hidden; position:relative;
      box-shadow:0 10px 30px rgba(0,0,0,.45), 0 0 0 1px rgba(255,255,255,.06) inset;
      transform: perspective(900px) rotateX(6deg);
      transition: transform .6s ease;
    }
    .avatar:hover{ transform: perspective(900px) rotateX(0deg) scale(1.03); }
    .chip{
      display:inline-flex; align-items:center; gap:.4rem; padding:.4rem .65rem; border-radius:999px; font-size:.82rem;
      background:linear-gradient(90deg, rgba(139,255,203,.18), rgba(98,178,255,.18)); border:1px solid rgba(139,255,203,.35);
    }
    .contact a{display:inline-flex; align-items:center; gap:.35rem; padding:.35rem .6rem; border-radius:10px; border:1px dashed rgba(255,255,255,.15); color:#e9eef5}
    .contact a:hover{background:rgba(255,255,255,.06)}

    /* Secciones principales */
    .section-title{font-size:1rem; letter-spacing:.08em; color:#b8c1cc; text-transform:uppercase}
    .divider{height:1px; background:linear-gradient(90deg, transparent, rgba(255,255,255,.1), transparent)}

    /* Timeline proyectos */
    .timeline{position:relative; padding-left:22px}
    .timeline::before{content:""; position:absolute; left:9px; top:0; bottom:0; width:2px; background:linear-gradient(180deg, rgba(255,255,255,.12), transparent)}
    .dot{
      position:absolute; left:3px; width:12px; height:12px; border-radius:999px;
      background: radial-gradient(circle at 30% 30%, var(--brand), #62b2ff 80%);
      box-shadow:0 0 18px rgba(139,255,203,.6), 0 0 28px rgba(98,178,255,.25);
      transform:translateY(6px);
    }
    .cardx{
      border-radius:16px; padding:16px; border:1px solid var(--border);
      background:linear-gradient(180deg, rgba(14,19,31,.75), rgba(10,15,25,.75));
      transition: transform .25s ease, border-color .25s ease, box-shadow .25s ease;
    }
    .cardx:hover{ transform: translateY(-3px); border-color: rgba(98,178,255,.35); box-shadow:0 12px 30px rgba(0,0,0,.45), 0 0 0 1px rgba(139,255,203,.18) inset; }
    .tag{font-family:"JetBrains Mono",monospace; font-size:.78rem; color:#b7c3cf}
    .project-row{position:relative; margin-left:14px; animation: rise .55s ease both}
    @keyframes rise{from{opacity:0; transform: translateY(8px)}to{opacity:1; transform: translateY(0)}}
  </style>
</head>
<body class="pb-5">
<nav class="navbar navbar-dark sticky-top">
  <div class="container py-2">
<span class="fw-semibold">
  <a href="admin.php" class="brand-dot me-2" aria-label="Ir al panel de administración"></a>
  <?=h($profile['full_name'] ?? 'Tu Nombre');?>
</span>
  </div>
</nav>

<div>
<div class="container my-4">
  <div class="row g-3">
    <!-- ========== SIDEBAR (arriba en móvil, izquierda en desktop) ========== -->
    <aside class="col-12 col-lg-4">
      <div class="panel p-3 glass ">
        <div class="d-flex align-items-center gap-3">
          <div class="avatar">
            <img src="<?=h($photo)?>" alt="Foto de perfil" style="width:100%;height:100%;object-fit:cover">
          </div>
          <div>
            <h1 class="h4 m-0"><?=h($profile['full_name'] ?? 'Tu Nombre');?></h1>
            <div class="text-warning small"><?=h($profile['title'] ?? '')?></div>
            <?php if(!empty($profile['location'])): ?><span class="text-warning small"><?=h($profile['location']);?></span><?php endif; ?><br>
            <div class="text-warning small">Universidad Nacional</div>

          </div>
        </div>

        <hr class="my-3 opacity-25">

       <div class="d-flex flex-wrap gap-2">
  <?php if(!empty($profile['email'])): ?>
    <a class="chip" href="#sec-email">Email</a>
  <?php endif; ?>

  <?php if($linkedin): ?>
    <a class="chip" href="#sec-linkedin">LinkedIn</a>
  <?php endif; ?>

  <?php if($github): ?>
    <a class="chip" href="#sec-github">GitHub</a>
  <?php endif; ?>

  <?php if($cv_file):  ?>
    <a class="chip" href="#sec-cv">CV</a>
  <?php endif; ?>

  <a class="chip" href="#sec-phone">Teléfono</a>
</div>

        <?php if(!empty($profile['summary'])): ?>
          <hr class="my-3 opacity-25">
          <div>
            <div class="section-title mb-2">Sobre mí</div>
            <p class="mb-0"><?=nl2br(h($profile['summary']))?></p>
          </div>
        <?php endif; ?>
      </div>

       <!-- PANEL HABILIDADES (nuevo, debajo del perfil) -->
      <section class="panel p-3 glass mt-3">
        <h2 class="section-title mb-2">Habilidades</h2>
        <?php if(!$skills): ?>
          <div class="text-secondary">Aún no hay habilidades.</div>
        <?php else: ?>
          <div class="row g-2">
            <?php foreach($skills as $s): ?>
              <div class="col-12">
                <div class="cardx h-100">
                  <div class="small text-secondary mb-1"><?=h($s['category'] ?? 'General')?></div>
                  <div><?=h($s['items'])?></div>
                </div>
              </div>
            <?php endforeach; ?>
          </div>
        <?php endif; ?>
      </section>
    </aside>

    

    <!-- ========== MAIN CONTENT ========== -->
    <main class="col-12 col-lg-8">
      <!-- Proyectos -->
      <section class="panel p-3 glass mb-3">
        <div class="d-flex align-items-center justify-content-between">
          <h2 class="section-title mb-2">Proyectos</h2>
          <?php if($projects): ?>
            <span class="small text-secondary"><?=count($projects)?> en total</span>
          <?php endif; ?>
        </div>
        <?php if(!$projects): ?>
          <div class="text-secondary">Aún no hay proyectos.</div>
        <?php else: ?>
          <div class="timeline mt-2">
            <?php foreach($projects as $i=>$p): ?>
              <article class="project-row mb-3">
                <div class="cardx">
                  <div class="d-flex flex-wrap justify-content-between align-items-start gap-2">
                    <div>
                      <div class="d-flex align-items-center gap-2">
                        <h3 class="h6 mb-0"><?=h($p['title'])?></h3>
                        <?php if($p['role']): ?><span class="tag">· <?=h($p['role'])?></span><?php endif; ?>
                      </div>
                      <?php if($p['description']): ?>
                        <p class="mb-2 mt-2"><?=nl2br(h($p['description']))?></p>
                      <?php endif; ?>
                      <div class="small text-secondary">
                        <?php
                          $period = trim((ym($p['from_date']) ?: '') . ' – ' . ($p['to_date'] ? ym($p['to_date']) : 'Actual'), ' –');
                          if($period) echo "<span class='me-2'>$period</span>";
                          if(!empty($p['tech_stack'])) echo "<span class='tag'>Stack: ".h($p['tech_stack'])."</span>";
                        ?>
                      </div>
                    </div>
                    <?php if($p['link']): ?>
                      <a class="btn btn-sm btn-outline-light" href="<?=h($p['link'])?>" target="_blank" rel="noopener">Ver</a>
                    <?php endif; ?>
                  </div>
                </div>
              </article>
            <?php endforeach; ?>
          </div>
        <?php endif; ?>
      </section>
      </div>
<div style = "display: flex">
<!-- ====== LINKS DE CONTACTO (destino del scroll) ====== -->
<section class="panel p-3 glass mt-3" style="display: flow-root" id="contact-links">
  <h2 class="section-title mb-2">Links de contacto</h2><br>

  <style>
    .contact-row{display:flex;align-items:center;gap:.6rem;margin-bottom:.5rem}
    .contact-row img{width:24px;height:24px;object-fit:contain}
    .contact-row a{word-break:break-all}
    .contact-empty{color:#6c757d}
  </style>

  <?php $resBase = 'img/resources'; ?>

  <!-- Email -->
  <div class="cardx mb-2" id="sec-email">
    <?php if(!empty($profile['email'])): ?>
      <div class="contact-row">
        <img src="<?=$resBase;?>/gmail.png" alt="Email">
        <a href="mailto:<?=h($profile['email']);?>"><?=h($profile['email']);?></a>
      </div>
    <?php else: ?>
      <div class="contact-row contact-empty">
        <img src="<?=$resBase;?>/gmail.png" alt="Email">
        <span>No configurado</span>
      </div>
    <?php endif; ?>
  </div>

  <!-- LinkedIn -->
  <div class="cardx mb-2" id="sec-linkedin">
    <?php if($linkedin): ?>
      <div class="contact-row">
        <img src="<?=$resBase;?>/linkedin.png" alt="LinkedIn">
        <a href="<?=h($linkedin);?>" target="_blank" rel="noopener"><?=h($linkedin);?></a>
      </div>
    <?php else: ?>
      <div class="contact-row contact-empty">
        <img src="<?=$resBase;?>/linkedin.png" alt="LinkedIn">
        <span>No configurado</span>
      </div>
    <?php endif; ?>
  </div>

  <!-- GitHub -->
  <div class="cardx mb-2" id="sec-github">
    <?php if($github): ?>
      <div class="contact-row">
        <img src="<?=$resBase;?>/github.png" alt="GitHub">
        <a href="<?=h($github);?>" target="_blank" rel="noopener"><?=h($github);?></a>
      </div>
    <?php else: ?>
      <div class="contact-row contact-empty">
        <img src="<?=$resBase;?>/github.png" alt="GitHub">
        <span>No configurado</span>
      </div>
    <?php endif; ?>
  </div>

  <!-- CV -->
  <div class="cardx mb-2" id="sec-cv">
    <?php if($cv_file): ?>
      <div class="contact-row">
        <img src="<?=$resBase;?>/resume.png" alt="CV">
        <a href="<?=h($cv_file);?>" target="_blank" rel="noopener">Ver CV</a>
      </div>
    <?php else: ?>
      <div class="contact-row contact-empty">
        <img src="<?=$resBase;?>/resume.png" alt="CV">
        <span>No configurado</span>
      </div>
    <?php endif; ?>
  </div>

  <!-- Teléfono / WhatsApp -->
  <div class="cardx" id="sec-phone">
    <?php $phone = $profile['phone'] ?? ''; ?>
    <?php if($phone): ?>
      <div class="contact-row">
        <img src="<?=$resBase;?>/whatsapp.png" alt="Teléfono">
        <a href="tel:<?=h($phone);?>"><?=h($phone);?></a>
      </div>
    <?php else: ?>
      <div class="contact-row contact-empty">
        <img src="<?=$resBase;?>/whatsapp.png" alt="Teléfono">
        <span>No configurado</span>
      </div>
    <?php endif; ?>
  </div>
</section>

</div>

</body>

</html>
