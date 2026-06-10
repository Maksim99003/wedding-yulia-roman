<?php
session_start();
require_once __DIR__ . '/config.php';

if (isset($_GET['logout'])) { session_destroy(); header('Location: admin.php'); exit; }

$error = $success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['password'])) {
    if ($_POST['password'] === ADMIN_PASS) { $_SESSION['admin_auth'] = true; header('Location: admin.php'); exit; }
    $error = 'Неверный пароль';
}

$isAuth = !empty($_SESSION['admin_auth']);
$guests = [];
$stats  = ['total'=>0,'yes'=>0,'no'=>0,'wait'=>0,'zags'=>0];

if ($isAuth) {
    $guests = load_guests();

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $action = $_POST['action'] ?? '';
        if ($action === 'add') {
            $name = trim($_POST['name'] ?? '');
            if ($name) {
                $slug = slugify($name); $base = $slug; $i = 2;
                while (isset($guests[$slug])) $slug = $base . '_' . $i++;
                $guests[$slug] = ['name'=>$name,'slug'=>$slug,'created_at'=>date('Y-m-d\TH:i:s'),'rsvp'=>null];
                save_guests($guests);
                $success = SITE_URL . '/invite_' . $slug;
            }
        }
        if ($action === 'delete') {
            $slug = preg_replace('/[^a-z0-9_-]/', '', $_POST['slug'] ?? '');
            if ($slug && isset($guests[$slug])) { unset($guests[$slug]); save_guests($guests); }
            header('Location: admin.php'); exit;
        }
    }

    $n = 0;
    foreach ($guests as $g) {
        $n++;
        $st = $g['rsvp']['status'] ?? null;
        $stats['total']++;
        if ($st === 'attending')      $stats['yes']++;
        elseif ($st === 'not_attending') $stats['no']++;
        else                          $stats['wait']++;
        if (($g['rsvp']['zags'] ?? null) === 'yes') $stats['zags']++;
    }
}
?>
<!DOCTYPE html>
<html lang="ru">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Гости — Юлия &amp; Роман</title>
<style>
*{box-sizing:border-box;margin:0;padding:0}
body{font-family:system-ui,-apple-system,sans-serif;background:#f0ebe6;color:#1a0d08;min-height:100vh}

/* ── Header ── */
.hdr{background:linear-gradient(135deg,#2A0E1C 0%,#7D5A46 100%);color:#fff;padding:0 32px}
.hdr-inner{max-width:1200px;margin:0 auto;display:flex;align-items:center;justify-content:space-between;padding:20px 0}
.hdr-title h1{font-size:1.3rem;font-weight:700;letter-spacing:.02em}
.hdr-title p{font-size:.82rem;opacity:.65;margin-top:3px}
.hdr-actions{display:flex;align-items:center;gap:12px}
.hdr-btn{padding:9px 20px;border-radius:6px;font-size:.82rem;font-weight:600;letter-spacing:.06em;text-transform:uppercase;cursor:pointer;border:none;transition:all .2s}
.hdr-btn--add{background:#C07068;color:#fff}
.hdr-btn--add:hover{background:#a05560}
.hdr-btn--logout{background:rgba(255,255,255,.12);color:rgba(255,255,255,.8);text-decoration:none;display:inline-flex;align-items:center}
.hdr-btn--logout:hover{background:rgba(255,255,255,.2)}

/* ── Stats bar ── */
.stats-bar{background:#fff;border-bottom:1px solid #e8e0da;padding:0 32px}
.stats-inner{max-width:1200px;margin:0 auto;display:flex;align-items:center;gap:8px;padding:14px 0;flex-wrap:wrap}
.chip{display:inline-flex;align-items:center;gap:6px;padding:6px 14px;border-radius:20px;font-size:.8rem;font-weight:600;letter-spacing:.04em;border:1.5px solid transparent}
.chip b{font-size:.95rem}
.chip--all  {background:#f5f0ed;border-color:#e8ddd7;color:#7D5A46}
.chip--yes  {background:#eafaf1;border-color:#a8dfc2;color:#1a6640}
.chip--no   {background:#fdecea;border-color:#f5c6c2;color:#a93226}
.chip--wait {background:#fef9ec;border-color:#f5e4a0;color:#7a5f1a}
.chip--zags {background:#eef3fb;border-color:#b3c9ef;color:#1a3a6e}

/* ── Body ── */
.body-wrap{max-width:1200px;margin:0 auto;padding:24px 32px}

/* ── Add panel ── */
.add-panel{background:#fff;border-radius:12px;padding:1.2rem 1.5rem;margin-bottom:20px;box-shadow:0 2px 8px rgba(0,0,0,.06);display:none}
.add-panel.open{display:block}
.add-panel h3{font-size:.9rem;font-weight:700;text-transform:uppercase;letter-spacing:.08em;color:#7D5A46;margin-bottom:12px}
.add-row{display:flex;gap:10px;align-items:flex-end}
.add-row input{flex:1;padding:10px 14px;border:1.5px solid #ddd;border-radius:8px;font-size:.95rem;outline:none;transition:border-color .2s}
.add-row input:focus{border-color:#C07068}
.btn-add{padding:10px 22px;background:#C07068;color:#fff;border:none;border-radius:8px;font-size:.85rem;font-weight:600;text-transform:uppercase;letter-spacing:.06em;cursor:pointer;white-space:nowrap}
.btn-add:hover{background:#a05560}
.success-link{margin-top:10px;padding:10px 14px;background:#eafaf1;border-radius:8px;font-size:.88rem;color:#1a6640;word-break:break-all}
.success-link a{color:#1a6640;font-weight:600}

/* ── Table ── */
.tbl-wrap{background:#fff;border-radius:12px;overflow:hidden;box-shadow:0 2px 8px rgba(0,0,0,.06)}
.tbl-top{padding:14px 20px;border-bottom:1px solid #f0ebe8;display:flex;align-items:center;justify-content:space-between}
.tbl-top h2{font-size:1rem;font-weight:700;color:#2A0E1C}
.tbl-count{font-size:.82rem;color:#9e7d72}
table{width:100%;border-collapse:collapse}
thead tr{background:linear-gradient(135deg,#2A0E1C,#5a2535)}
th{padding:12px 16px;text-align:left;color:rgba(255,255,255,.75);font-size:.72rem;font-weight:600;text-transform:uppercase;letter-spacing:.09em;white-space:nowrap}
tbody tr{transition:background .15s}
tbody tr:nth-child(even){background:#faf7f4}
tbody tr:hover{background:#f5ede8}
td{padding:13px 16px;font-size:.88rem;border-bottom:1px solid #f0ebe8}
tbody tr:last-child td{border-bottom:none}
.td-num{color:#9e7d72;font-size:.8rem;font-weight:600}
.td-name{font-weight:600;color:#1a0d08}
.badge{display:inline-block;padding:4px 11px;border-radius:20px;font-size:.75rem;font-weight:700;letter-spacing:.04em}
.badge-wait{background:#fef9ec;color:#7a5f1a;border:1px solid #f5e4a0}
.badge-yes {background:#eafaf1;color:#1a6640;border:1px solid #a8dfc2}
.badge-no  {background:#fdecea;color:#a93226;border:1px solid #f5c6c2}
.badge-zags-yes{background:#eef3fb;color:#1a3a6e;border:1px solid #b3c9ef}
.badge-zags-no {background:#f5f0ed;color:#7D5A46;border:1px solid #e0d5cc}
.badge-zags-na {background:#f9f9f9;color:#aaa;border:1px solid #e8e8e8}
.link-wrap{display:flex;align-items:center;gap:6px}
.link-txt{font-size:.78rem;color:#9e7d72;max-width:180px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap}
.copy-btn{background:none;border:1px solid #ddd;border-radius:5px;padding:3px 8px;font-size:.72rem;cursor:pointer;color:#9e7d72;white-space:nowrap;transition:all .15s}
.copy-btn:hover{border-color:#C07068;color:#C07068}
.copy-btn.copied{border-color:#1a6640;color:#1a6640}
.del-btn{background:none;border:1px solid #e8c4c4;border-radius:5px;padding:4px 10px;font-size:.78rem;cursor:pointer;color:#c0392b;transition:all .15s}
.del-btn:hover{background:#c0392b;color:#fff;border-color:#c0392b}
.empty-row td{text-align:center;padding:3rem;color:#9e7d72;font-style:italic}

/* ── Login ── */
.login-wrap{display:flex;align-items:center;justify-content:center;min-height:80vh;padding:2rem}
.login-card{background:#fff;border-radius:16px;padding:2.5rem;width:100%;max-width:360px;box-shadow:0 4px 24px rgba(0,0,0,.08)}
.login-card h2{font-size:1.4rem;color:#2A0E1C;margin-bottom:.3rem}
.login-card p{color:#9e7d72;font-size:.88rem;margin-bottom:1.8rem}
.field label{display:block;font-size:.75rem;font-weight:700;text-transform:uppercase;letter-spacing:.08em;color:#9e7d72;margin-bottom:6px}
.field input{width:100%;padding:11px 14px;border:1.5px solid #ddd;border-radius:8px;font-size:1rem;outline:none;transition:border-color .2s}
.field input:focus{border-color:#C07068}
.login-btn{width:100%;padding:12px;background:#C07068;color:#fff;border:none;border-radius:8px;font-size:.9rem;font-weight:700;text-transform:uppercase;letter-spacing:.08em;cursor:pointer;margin-top:1.2rem}
.login-btn:hover{background:#a05560}
.login-err{color:#c0392b;font-size:.85rem;margin-bottom:1rem}

@media(max-width:700px){
  .hdr{padding:0 16px} .body-wrap{padding:16px}
  .stats-inner{padding:10px 0}
  th:nth-child(5),td:nth-child(5){display:none}
  .link-txt{max-width:100px}
}
</style>
</head>
<body>

<!-- Header -->
<div class="hdr">
  <div class="hdr-inner">
    <div class="hdr-title">
      <h1>Управление гостями</h1>
      <p>Юлия &amp; Роман — 25 июля 2026</p>
    </div>
    <?php if ($isAuth): ?>
    <div class="hdr-actions">
      <button class="hdr-btn hdr-btn--add" onclick="toggleAdd()">+ Добавить гостя</button>
      <a href="?logout" class="hdr-btn hdr-btn--logout">Выйти</a>
    </div>
    <?php endif; ?>
  </div>
</div>

<?php if (!$isAuth): ?>

<!-- Stats bar placeholder -->
<div class="stats-bar"><div class="stats-inner"></div></div>

<div class="login-wrap">
  <div class="login-card">
    <h2>Добро пожаловать</h2>
    <p>Панель управления приглашениями</p>
    <?php if ($error): ?><p class="login-err"><?= h($error) ?></p><?php endif; ?>
    <form method="post">
      <div class="field">
        <label>Пароль</label>
        <input type="password" name="password" autofocus autocomplete="current-password" required>
      </div>
      <button type="submit" class="login-btn">Войти</button>
    </form>
  </div>
</div>

<?php else: ?>

<!-- Stats bar -->
<div class="stats-bar">
  <div class="stats-inner">
    <span class="chip chip--all">Всего гостей&nbsp;<b><?= $stats['total'] ?></b></span>
    <span class="chip chip--yes">Придут&nbsp;<b><?= $stats['yes'] ?></b></span>
    <span class="chip chip--no">Не придут&nbsp;<b><?= $stats['no'] ?></b></span>
    <span class="chip chip--wait">Ожидают&nbsp;<b><?= $stats['wait'] ?></b></span>
    <span class="chip chip--zags">На ЗАГС&nbsp;<b><?= $stats['zags'] ?></b></span>
  </div>
</div>

<div class="body-wrap">

  <!-- Add guest panel -->
  <div class="add-panel" id="addPanel">
    <h3>Новое приглашение</h3>
    <form method="post">
      <input type="hidden" name="action" value="add">
      <div class="add-row">
        <input type="text" name="name" placeholder="Имя гостя или семьи (напр. Анна или Семья Ивановых)" required>
        <button type="submit" class="btn-add">Создать ссылку</button>
      </div>
    </form>
    <?php if ($success): ?>
    <div class="success-link">Ссылка создана: <a href="<?= h($success) ?>" target="_blank"><?= h($success) ?></a></div>
    <?php endif; ?>
  </div>

  <!-- Table -->
  <div class="tbl-wrap">
    <div class="tbl-top">
      <h2>Список гостей</h2>
      <span class="tbl-count">Всего: <?= $stats['total'] ?></span>
    </div>
    <table>
      <thead>
        <tr>
          <th>№</th>
          <th>Имя гостя</th>
          <th>Статус ответа</th>
          <th>Присутствие в ЗАГСе</th>
          <th>Ссылка приглашения</th>
          <th></th>
        </tr>
      </thead>
      <tbody>
      <?php if (empty($guests)): ?>
        <tr class="empty-row"><td colspan="6">Гостей пока нет. Нажмите «+ Добавить гостя».</td></tr>
      <?php else: $n = 0; foreach ($guests as $slug => $g):
        $n++;
        $st      = $g['rsvp']['status'] ?? null;
        $zags    = $g['rsvp']['zags']   ?? null;
        $cmt     = $g['rsvp']['comment'] ?? '';
        $url     = SITE_URL . '/invite_' . $slug;
        $badgeCls  = $st === 'attending' ? 'badge-yes' : ($st === 'not_attending' ? 'badge-no' : 'badge-wait');
        $badgeTxt  = $st === 'attending' ? 'Придёт'   : ($st === 'not_attending' ? 'Не придёт' : 'Ожидает');
        $zagsCls   = $zags === 'yes' ? 'badge-zags-yes' : ($zags === 'no' ? 'badge-zags-no' : 'badge-zags-na');
        $zagsTxt   = $zags === 'yes' ? 'Будет'         : ($zags === 'no' ? 'Не будет'       : '—');
        if ($st === 'not_attending') { $zagsCls = 'badge-zags-na'; $zagsTxt = '—'; }
      ?>
        <tr>
          <td class="td-num"><?= $n ?></td>
          <td class="td-name"><?= h($g['name']) ?><?php if ($cmt): ?><br><span style="font-weight:400;color:#9e7d72;font-size:.78rem;font-style:italic"><?= h($cmt) ?></span><?php endif; ?></td>
          <td><span class="badge <?= $badgeCls ?>"><?= $badgeTxt ?></span></td>
          <td><span class="badge <?= $zagsCls ?>"><?= $zagsTxt ?></span></td>
          <td>
            <div class="link-wrap">
              <span class="link-txt" title="<?= h($url) ?>"><?= h($url) ?></span>
              <button class="copy-btn" onclick="copyLink(this,'<?= h($url) ?>')">Копировать</button>
            </div>
          </td>
          <td>
            <form method="post" onsubmit="return confirm('Удалить гостя «<?= h($g['name']) ?>»?')">
              <input type="hidden" name="action" value="delete">
              <input type="hidden" name="slug"   value="<?= h($slug) ?>">
              <button type="submit" class="del-btn">Удалить</button>
            </form>
          </td>
        </tr>
      <?php endforeach; endif; ?>
      </tbody>
    </table>
  </div>

</div>
<?php endif; ?>

<script>
function toggleAdd() {
  var p = document.getElementById('addPanel');
  p.classList.toggle('open');
  if (p.classList.contains('open')) p.querySelector('input[name=name]').focus();
}
function copyLink(btn, url) {
  navigator.clipboard.writeText(url).then(function () {
    btn.textContent = 'Скопировано!';
    btn.classList.add('copied');
    setTimeout(function () { btn.textContent = 'Копировать'; btn.classList.remove('copied'); }, 2000);
  }).catch(function () { prompt('Скопируйте ссылку:', url); });
}
<?php if ($success): ?>
document.getElementById('addPanel').classList.add('open');
<?php endif; ?>
</script>
</body>
</html>
