<?php
// ===================== Helpers UI & Seguridad =====================

function h($v){ return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }

// input básico con clase de estilo
function input($name,$type='text',$value='',$placeholder='',$attr=''){
  $v=h($value); $p=h($placeholder);
  echo "<input class='form-control fancy-input' type='$type' name='$name' value='$v' placeholder='$p' $attr>";
}

function banner($text,$type='success'){
  $map = [
    'success' => 'bg-success-subtle text-success-emphasis border-success',
    'danger'  => 'bg-danger-subtle text-danger-emphasis border-danger',
    'info'    => 'bg-info-subtle text-info-emphasis border-info',
    'warn'    => 'bg-warning-subtle text-warning-emphasis border-warning',
  ];
  $cls = $map[$type] ?? $map['info'];
  echo "<div class='alert $cls border glass my-3'>".h($text)."</div>";
}

// ===================== CSRF mínimo =====================
function csrf_boot(){
  if(session_status() !== PHP_SESSION_ACTIVE) session_start();
  if(empty($_SESSION['csrf'])) $_SESSION['csrf'] = bin2hex(random_bytes(16));
}
function csrf_token(){ return $_SESSION['csrf'] ?? ''; }
function csrf_input(){ echo '<input type="hidden" name="csrf" value="'.h(csrf_token()).'">'; }
function csrf_check(){
  $ok = isset($_POST['csrf']) && hash_equals($_SESSION['csrf'] ?? '', $_POST['csrf']);
  if(!$ok){ http_response_code(400); die('CSRF token inválido'); }
}
