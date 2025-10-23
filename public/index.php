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
  : 'img/profile/profile.jpg'; // crea public/img/avatar.jpg con tu foto

// Links opcionales
$linkedin = $profile['linkedin'] ?? '';
$github   = $profile['github']   ?? '';
$cv_url = $profile['resume_file'] ?? ''; // usamos la nueva columna
$cv_file = $profile['resume_file'] ?? '';

// ====== Resumen compacto del perfil para IA (guardado en sesión) ======
if (session_status() !== PHP_SESSION_ACTIVE) { session_start(); }

function str_trimmed(?string $s): string {
  $s = trim((string)$s);
  $s = preg_replace('/\s+/u', ' ', $s ?? '');
  return (string)$s;
}

// Top skills por categoría (1 línea)
$skills_lines = [];
foreach ($skills as $row) {
  $cat = str_trimmed($row['category'] ?? 'General');
  $it  = str_trimmed($row['items'] ?? '');
  if ($it !== '') $skills_lines[] = "$cat: $it";
}
$skills_summary = implode(' | ', $skills_lines);

// Proyectos: cantidad y hasta 3 títulos recientes
$project_titles = array_slice(array_map(
  fn($p) => str_trimmed($p['title'] ?? ''),
  $projects
), 0, 3);
$projects_summary = count($projects) . " proyectos";
if ($project_titles) $projects_summary .= " (ej.: " . implode(', ', $project_titles) . ")";

// Links útiles
$link_bits = [];
if (!empty($profile['email']))    $link_bits[] = "email: {$profile['email']}";
if (!empty($profile['linkedin'])) $link_bits[] = "linkedin: {$profile['linkedin']}";
if (!empty($profile['github']))   $link_bits[] = "github: {$profile['github']}";
if (!empty($profile['resume_file'])) $link_bits[] = "cv: {$profile['resume_file']}";
$links_summary = implode(' | ', $link_bits);

// Resumen final (máx ~600 chars)
$raw_summary = trim(implode(' — ', array_filter([
  str_trimmed(($profile['full_name'] ?? '') . ' · ' . ($profile['title'] ?? '')) ?: null,
  !empty($profile['location']) ? "Ubicación: " . str_trimmed($profile['location']) : null,
  $skills_summary ?: null,
  $projects_summary ?: null,
  $links_summary ?: null,
  !empty($profile['summary']) ? "Bio: " . str_trimmed($profile['summary']) : null,
])));
$summary = mb_substr($raw_summary, 0, 600);

// Guarda en sesión para que el JS lo consuma
$_SESSION['cv_profile_summary'] = $summary;


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
  <script>
  // Perfil resumido traído desde sesión (sanitizado con json_encode)
  const PROFILE_SUMMARY = <?= json_encode($_SESSION['cv_profile_summary'] ?? '', JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;
</script>

<nav class="navbar navbar-dark sticky-top">
  <div class="container py-2 d-flex align-items-center">
    <span class="fw-semibold d-flex align-items-center">
      <a href="admin.php" class="brand-dot me-2" aria-label="Ir al panel de administración"></a>
      <?=h($profile['full_name'] ?? 'Tu Nombre');?>
    </span>

    <!-- A la derecha -->
<span class="ms-auto d-flex align-items-center gap-2">
  <span class="text-secondary small">Powered by</span>

  <!-- Link al widget de IA -->
  <a href="#ai-chat" class="d-flex align-items-center gap-2"
     aria-label="Ir al asistente de IA" style="text-decoration:none">
    <img src="/img/resources/chat-gpt.png"
         alt="ChatGPT"
         style="width:25px;height:25px;object-fit:contain;display:block;">
    <span class="text-secondary small">OpenAI</span>
  </a>
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

<div style = "display: inline-flex; gap: 17px;">
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
<!-- (NUEVO) WIDGET CHATGPT (mini) -->
<section class="panel p-3 glass mt-3" style="display:block" id="ai-chat">
  <h2 class="section-title mb-2">Asistente de IA</h2>

<!-- Conversación -->
<div id="ai-chatlog" class="cardx mb-2" style="padding:12px; height:360px; overflow:auto">
  <!-- Burbuja inicial del asistente -->
  <div class="bubble assistant">
    <div class="role text-secondary">Asistente</div>
    <div class="mono">¡Hola! Soy tu asistente. Pregúntame lo que sea</div>
  </div>
</div>

 <textarea
  id="ai-input"
  class="form-control mb-2"
  rows="1"
  placeholder="Empieza a Chatear"
  style="
    border-color:rgba(139,255,203,.35);
    background:linear-gradient(180deg, rgba(20,40,35,.5), rgba(10,25,20,.35));
    color:#fff;
    caret-color:#fff;
    resize:none;           /* evita que el usuario estire manualmente */
    overflow-y:auto;       /* scroll cuando supera el máximo */
  "
></textarea>


    <div class="d-flex gap-2 align-items-center">
      <button id="ai-send" class="btn btn-warning">Enviar</button>
      <button id="ai-reset" class="btn btn-outline-light">Reset</button>
  <div class="form-check ms-auto" style="display:none">
    <input class="form-check-input" type="checkbox" id="ai-persist" checked>
    <label class="form-check-label small" for="ai-persist">Guardar chat local</label>
  </div>
      <div id="ai-status" class="small text-secondary ms-2" aria-live="polite"></div>
    </div>
  </div>
</section>
</div>

</div>
</body>
</html>

<style>

  #ai-input::placeholder { color: white; opacity: .2; }

  /* Burbujas tipo chat */
  .bubble { border:1px solid var(--border); border-radius:14px; padding:10px 12px; margin:8px 0; max-width:92%;
            background:linear-gradient(180deg, rgba(14,19,31,.75), rgba(10,15,25,.75)); }
  .bubble.user { margin-left:auto; border-color:rgba(139,255,203,.35);
                 background:linear-gradient(180deg, rgba(20,40,35,.5), rgba(10,25,20,.35)); }
  .bubble.assistant { margin-right:auto; }
  .role { font-size:.72rem; letter-spacing:.06em; text-transform:uppercase; opacity:.7; }
  .typing { display:inline-flex; gap:6px; align-items:center; opacity:.8 }
  .dot-typing { width:6px; height:6px; border-radius:50%; background:#b8c1cc; animation: blink 1.2s infinite; }
  .dot-typing:nth-child(2){ animation-delay:.2s } .dot-typing:nth-child(3){ animation-delay:.4s }
  @keyframes blink { 0%,80%,100%{opacity:.2} 40%{opacity:1} }
  .mono { font-family:"JetBrains Mono",monospace; white-space:pre-wrap }
  /* --- Anti-aplastamiento entre Contactos y Chat --- */

/* Ambos paneles comparten el espacio y pueden encogerse en flex */
#contact-links,
#ai-chat {
  flex: 1 1 360px;
  min-width: 0;        /* clave: permite que el flex item encoja con contenido largo */
}

/* El área de conversación queda fija y con scroll interno */
#ai-chatlog {
  height: 360px;       /* ya lo tienes, reafirmamos */
  overflow: auto;
  max-width: 100%;
}

/* Burbujas del chat: nunca exceden el ancho disponible */
#ai-chat .bubble { 
  max-width: 100%;     /* evita que una burbuja fuerce el ancho del panel */
}

/* Texto de las burbujas: cortar palabras/URLs largas */
#ai-chat .mono {
  white-space: pre-wrap;
  word-break: break-word;     /* corta palabras larguísimas */
  overflow-wrap: anywhere;    /* y también URLs/líneas sin espacios */
}

/* (Opcional) Limita el ancho total del widget de chat en desktop */
@media (min-width: 992px) {
  #ai-chat { max-width: 560px; }
}
</style>

<script>
  document.addEventListener('DOMContentLoaded', () => {
  const ta = document.getElementById('ai-input');
  if (!ta) return;

  const MAX_ROWS = 3;

  const autosize = () => {
    // Calcula alto de una línea
    const cs = window.getComputedStyle(ta);
    const lineHeight = parseFloat(cs.lineHeight || '20');
    const maxHeight  = lineHeight * MAX_ROWS +   // alto del contenido
                       parseFloat(cs.paddingTop) + parseFloat(cs.paddingBottom) +
                       parseFloat(cs.borderTopWidth) + parseFloat(cs.borderBottomWidth);

    ta.style.height = 'auto';                    // encoge primero
    const newHeight = Math.min(ta.scrollHeight, maxHeight);
    ta.style.height = newHeight + 'px';
    ta.style.overflowY = (ta.scrollHeight > maxHeight) ? 'auto' : 'hidden';
  };

  // Ajusta al cargar y en cada entrada
  autosize();
  ta.addEventListener('input', autosize);
});

document.addEventListener('DOMContentLoaded', () => {
  let ENDPOINT = "api/ai_proxy.php";

  const $log     = document.getElementById('ai-chatlog');
  const $in      = document.getElementById('ai-input');
  const $send    = document.getElementById('ai-send');
  const $reset   = document.getElementById('ai-reset');
  const $status  = document.getElementById('ai-status');
  const $persist = document.getElementById('ai-persist'); // oculto pero presente

  if(!$log || !$in || !$send || !$status || !$persist){
    console.warn('AI widget incompleto: verifica los IDs requeridos.');
    return;
  }

  // Persistencia siempre activa aunque esté oculto
  $persist.checked = true;
  const KEY = "miniChatGPT-history";
  let history = [];

  // ------ UI helpers ------
  function scrollToBottom(){ $log.scrollTop = $log.scrollHeight; }
  function setBusy(b){ $send.disabled = b; $in.disabled = b; $status.textContent = b ? "Escribiendo…" : ""; }
  function appendBubble(role, text){
    const wrap = document.createElement('div');
    wrap.className = `bubble ${role}`;
    wrap.innerHTML = `
      <div class="role text-secondary">${role === 'user' ? 'Tú' : 'Asistente'}</div>
      <div class="mono"></div>`;
    wrap.querySelector('.mono').textContent = text;
    $log.appendChild(wrap);
    scrollToBottom();
  }
  function appendTyping(){
    removeTyping();
    const wrap = document.createElement('div');
    wrap.className = 'bubble assistant';
    wrap.id = 'typing';
    wrap.innerHTML = `
      <div class="role text-secondary">Asistente</div>
      <div class="typing"><span class="dot-typing"></span><span class="dot-typing"></span><span class="dot-typing"></span></div>`;
    $log.appendChild(wrap);
    scrollToBottom();
  }
  function removeTyping(){ const t = document.getElementById('typing'); if(t) t.remove(); }

function ensureGreeting(){
  if (history.length === 0) {
    // evita duplicado si ya existe una burbuja de asistente
    const hasAnyBubble = $log.querySelector('.bubble.assistant');
    if (!hasAnyBubble) {
      appendBubble('assistant', '¡Hola! Soy tu asistente. Pregúntame lo que sea');
    }
  }
}
  // ------ Prompt compacto (para reducir latencia/timeouts) ------
  function buildPrompt(latestUserMessage){
  const system = "Eres un asistente breve, claro y útil para un portafolio. Si no sabes algo, dilo.";

  // Resumen compacto del perfil (inyectado desde PHP/SESION)
  // Lo recortamos por si acaso
  const profileCtx = (PROFILE_SUMMARY || "").toString();
  const profileShort = profileCtx.length > 600 ? profileCtx.slice(0,597) + "..." : profileCtx;

  // Últimos 4 turnos y recorte de 220 chars por mensaje
  const recent = history.slice(-4);
  const tail = recent.map(m => {
    let t = (m.content || "");
    if (t.length > 220) t = t.slice(0,217) + "...";
    return `${m.role.toUpperCase()}: ${t}`;
  }).join('\n');

  const userNow = `USER: ${latestUserMessage}`;

  // Policy + contexto
  const context = profileShort ? `\n\n[Contexto del perfil]\n${profileShort}\n` : "\n";

  const policy  = "Asistente: responde en español y no excedas 25 palabras.";

  // Ensamblar y limitar tamaño total
  let full = `${system}${context}\n${tail}\n${userNow}\n\n${policy}`;
  if (full.length > 1600) full = full.slice(0,1597) + "...";
  return full;
}


  // ------ Fetch helper ------
  async function apiPost(url, json, timeoutMs = 20000) {
    const ctrl = new AbortController();
    const tid = setTimeout(() => ctrl.abort(), timeoutMs);
    try {
      const res = await fetch(url, {
        method: "POST",
        headers: {"Content-Type":"application/json"},
        body: JSON.stringify(json),
        signal: ctrl.signal
      });
      const ct = res.headers.get("content-type") || "";
      const body = await res.text();
      if (!res.ok) throw new Error(`HTTP ${res.status}: ${body || "(sin cuerpo)"}`);
      return ct.includes("application/json") ? JSON.parse(body) : body;
    } finally { clearTimeout(tid); }
  }

  async function askAssistant(userText){
    const prompt = buildPrompt(userText);
    setBusy(true);
    appendBubble('user', userText);
    history.push({role:'user', content:userText});
    appendTyping();

    const tryRequest = async () => {
      try { return await apiPost(ENDPOINT, { prompt }); }
      catch (e) {
        const msg = String(e.message || e);
        if (msg.startsWith("HTTP 404") && !ENDPOINT.startsWith("/")) {
          ENDPOINT = "/" + ENDPOINT; // fallback a ruta absoluta
          return await apiPost(ENDPOINT, { prompt });
        }
        throw e;
      }
    };

    try{
      const data = await tryRequest();
      let answer = typeof data === 'string'
        ? data
        : (data.output ?? data.message ?? data.response ?? JSON.stringify(data, null, 2));

      removeTyping();
      appendBubble('assistant', answer || "(sin contenido)");
      history.push({role:'assistant', content: answer || "(sin contenido)"});
      try{ localStorage.setItem(KEY, JSON.stringify(history)); }catch{}
    }catch(err){
      console.error(err);
      removeTyping();
      const msg = "⚠️ No se pudo obtener respuesta.\n" + (err.message || err);
      appendBubble('assistant', msg);
      history.push({role:'assistant', content: msg});
      try{ localStorage.setItem(KEY, JSON.stringify(history)); }catch{}
    }finally{
      setBusy(false);
    }
  }

  // ------ Eventos ------
  $send.addEventListener('click', () => {
    const v = ($in.value || "").trim();
    if(!v){ $in.focus(); return; }
    $in.value = "";
    askAssistant(v);
  });

  $in.addEventListener('keydown', (e) => {
    if(e.key === 'Enter' && !e.shiftKey){
      e.preventDefault();
      $send.click();
    }
  });

  if ($reset) {
    $reset.addEventListener('click', () => {
      history = [];
      $log.innerHTML = "";
      try{ localStorage.removeItem(KEY); }catch{}
      ensureGreeting(); // vuelve a mostrar saludo inicial
      $status.textContent = "";
      $in.focus(); scrollToBottom();
    });
  }

  // ------ Cargar historial y mostrar saludo si no hay ------
  try{
    const raw = localStorage.getItem(KEY);
    if(raw){
      const parsed = JSON.parse(raw);
      if(Array.isArray(parsed)){
        history = parsed;
        $log.innerHTML = "";
        history.forEach(m => appendBubble(m.role, m.content));
      }
    }
  }catch{}
  ensureGreeting(); // siempre garantiza la primera burbuja

});
</script>
