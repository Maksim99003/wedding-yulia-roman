<?php
session_start();
require_once __DIR__ . '/config.php';

if (isset($_GET['logout'])) {
    session_destroy();
    header('Location: admin.php');
    exit;
}

$error   = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['password'])) {
    if ($_POST['password'] === ADMIN_PASS) {
        $_SESSION['admin_auth'] = true;
        header('Location: admin.php');
        exit;
    }
    $error = 'Неверный пароль';
}

$isAuth = !empty($_SESSION['admin_auth']);
$guests = [];
$stats  = ['total' => 0, 'yes' => 0, 'no' => 0, 'wait' => 0];

if ($isAuth) {
    $guests = load_guests();

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $action = $_POST['action'] ?? '';

        if ($action === 'add') {
            $name = trim($_POST['name'] ?? '');
            if ($name) {
                $slug = slugify($name);
                $base = $slug; $i = 2;
                while (isset($guests[$slug])) $slug = $base . '_' . $i++;
                $guests[$slug] = ['name' => $name, 'slug' => $slug, 'created_at' => date('Y-m-d\TH:i:s'), 'rsvp' => null];
                save_guests($guests);
                $success = 'Ссылка создана: ' . SITE_URL . '/invite_' . $slug;
            }
        }

        if ($action === 'delete') {
            $slug = preg_replace('/[^a-z0-9_-]/', '', $_POST['slug'] ?? '');
            if ($slug && isset($guests[$slug])) {
                unset($guests[$slug]);
                save_guests($guests);
            }
            header('Location: admin.php');
            exit;
        }
    }

    foreach ($guests as $g) {
        $stats['total']++;
        $st = $g['rsvp']['status'] ?? null;
        if ($st === 'attending')     $stats['yes']++;
        elseif ($st === 'not_attending') $stats['no']++;
        else                         $stats['wait']++;
    }
}
?>
<!DOCTYPE html>
<html lang="ru">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Панель управления — Юлия &amp; Роман</title>
<style>
:root{--terra:#C07068;--brown:#7D5A46;--blush:#EDD9CF;--cream:#FAF7F4;--ink:#1a0d08;--mid:#7a5a50}
*{box-sizing:border-box;margin:0;padding:0}
body{font-family:system-ui,-apple-system,sans-serif;background:var(--cream);color:var(--ink);min-height:100vh}
a{color:var(--terra);text-decoration:none}

/* Header */
.hdr{background:var(--brown);color:#fff;padding:18px 32px;display:flex;align-items:center;justify-content:space-between}
.hdr h1{font-size:1.1rem;font-weight:600;letter-spacing:.05em}
.hdr a{color:rgba(255,255,255,.75);font-size:.82rem}

/* Login */
.login-wrap{display:flex;align-items:center;justify-content:center;min-height:80vh}
.login-card{background:#fff;border-radius:16px;padding:2.5rem;width:100%;max-width:380px;box-shadow:0 4px 24px rgba(0,0,0,.07)}
.login-card h2{font-size:1.4rem;margin-bottom:.4rem;color:var(--brown)}
.login-card p{color:var(--mid);font-size:.9rem;margin-bottom:1.8rem}
.field{margin-bottom:1.2rem}
.field label{display:block;font-size:.82rem;font-weight:600;letter-spacing:.06em;text-transform:uppercase;color:var(--mid);margin-bottom:6px}
.field input{width:100%;padding:11px 14px;border:1.5px solid #ddd;border-radius:8px;font-size:1rem;outline:none;transition:border-color .2s}
.field input:focus{border-color:var(--terra)}
.error{color:#c0392b;font-size:.88rem;margin-bottom:1rem}
.btn{display:inline-flex;align-items:center;gap:6px;padding:11px 22px;border-radius:6px;font-size:.85rem;font-weight:600;letter-spacing:.05em;text-transform:uppercase;cursor:pointer;border:2px solid transparent;transition:all .2s}
.btn-primary{background:var(--terra);color:#fff;border-color:var(--terra);width:100%;justify-content:center}
.btn-primary:hover{background:var(--brown);border-color:var(--brown)}
.btn-sm{padding:7px 14px;font-size:.78rem}
.btn-ghost{background:transparent;color:var(--mid);border-color:#ddd}
.btn-ghost:hover{border-color:var(--terra);color:var(--terra)}
.btn-danger{background:transparent;color:#c0392b;border-color:#e8c4c4}
.btn-danger:hover{background:#c0392b;color:#fff}

/* Main layout */
.wrap{max-width:900px;margin:0 auto;padding:2rem 1.5rem}

/* Stats */
.stats{display:grid;grid-template-columns:repeat(4,1fr);gap:12px;margin-bottom:2rem}
.stat{background:#fff;border-radius:12px;padding:1rem 1.2rem;text-align:center;box-shadow:0 2px 8px rgba(0,0,0,.05)}
.stat-num{font-size:2rem;font-weight:700;color:var(--terra)}
.stat-lbl{font-size:.78rem;color:var(--mid);text-transform:uppercase;letter-spacing:.06em;margin-top:2px}

/* Add form */
.add-card{background:#fff;border-radius:14px;padding:1.5rem;margin-bottom:2rem;box-shadow:0 2px 8px rgba(0,0,0,.05)}
.add-card h3{font-size:1rem;font-weight:700;margin-bottom:1rem;color:var(--brown)}
.add-row{display:flex;gap:10px;align-items:flex-end}
.add-row .field{flex:1;margin:0}
.add-row .btn{flex-shrink:0}

/* Success */
.success{background:#eafaf1;border:1.5px solid #a8dfc2;border-radius:8px;padding:.9rem 1.2rem;margin-bottom:1.5rem;font-size:.9rem;color:#1a6640}
.success a{color:#1a6640;text-decoration:underline}

/* Table */
.tbl-wrap{background:#fff;border-radius:14px;overflow:hidden;box-shadow:0 2px 8px rgba(0,0,0,.05)}
.tbl-head{padding:1rem 1.5rem;border-bottom:1px solid #f0ebe8;display:flex;align-items:center;justify-content:space-between}
.tbl-head h3{font-size:1rem;font-weight:700;color:var(--brown)}
table{width:100%;border-collapse:collapse}
th,td{padding:12px 16px;text-align:left;font-size:.88rem}
th{background:#faf5f3;font-weight:600;text-transform:uppercase;letter-spacing:.06em;color:var(--mid);font-size:.75rem;border-bottom:1px solid #f0ebe8}
tr:not(:last-child) td{border-bottom:1px solid #f7f3f1}
.badge{display:inline-block;padding:3px 10px;border-radius:20px;font-size:.75rem;font-weight:600;letter-spacing:.04em}
.badge-wait{background:#f5f0ed;color:#9e7d72}
.badge-yes{background:#eafaf1;color:#1a6640}
.badge-no{background:#fdecea;color:#a93226}
.link-cell{display:flex;align-items:center;gap:8px}
.link-text{font-size:.82rem;color:var(--mid);white-space:nowrap;overflow:hidden;text-overflow:ellipsis;max-width:200px}
.copy-btn{background:none;border:1px solid #ddd;border-radius:5px;padding:3px 8px;font-size:.75rem;cursor:pointer;color:var(--mid);transition:all .15s;white-space:nowrap}
.copy-btn:hover{border-color:var(--terra);color:var(--terra)}
.copy-btn.copied{border-color:#1a6640;color:#1a6640}
.comment-cell{color:var(--mid);font-style:italic;max-width:160px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap}
.empty{text-align:center;padding:3rem;color:var(--mid)}

@media(max-width:600px){
  .stats{grid-template-columns:repeat(2,1fr)}
  .add-row{flex-direction:column}
  .link-text{max-width:120px}
  th:nth-child(3),td:nth-child(3){display:none}
}
</style>
</head>
<body>

<div class="hdr">
  <h1>Юлия &amp; Роман — Панель управления</h1>
  <?php if ($isAuth): ?>
  <a href="?logout">Выйти</a>
  <?php endif; ?>
</div>

<?php if (!$isAuth): ?>
<div class="login-wrap">
  <div class="login-card">
    <h2>Добро пожаловать</h2>
    <p>Панель управления приглашениями</p>
    <?php if ($error): ?>
    <p class="error"><?= h($error) ?></p>
    <?php endif; ?>
    <form method="post">
      <div class="field">
        <label>Пароль</label>
        <input type="password" name="password" autofocus autocomplete="current-password" required>
      </div>
      <button type="submit" class="btn btn-primary">Войти</button>
    </form>
  </div>
</div>

<?php else: ?>
<div class="wrap">

  <!-- Stats -->
  <div class="stats">
    <div class="stat"><div class="stat-num"><?= $stats['total'] ?></div><div class="stat-lbl">Всего гостей</div></div>
    <div class="stat"><div class="stat-num" style="color:#1a6640"><?= $stats['yes'] ?></div><div class="stat-lbl">Придут</div></div>
    <div class="stat"><div class="stat-num" style="color:#a93226"><?= $stats['no'] ?></div><div class="stat-lbl">Не придут</div></div>
    <div class="stat"><div class="stat-num" style="color:#9e7d72"><?= $stats['wait'] ?></div><div class="stat-lbl">Ожидают</div></div>
  </div>

  <!-- Add guest -->
  <div class="add-card">
    <h3>Создать приглашение</h3>
    <form method="post">
      <input type="hidden" name="action" value="add">
      <div class="add-row">
        <div class="field">
          <label>Имя гостя</label>
          <input type="text" name="name" placeholder="Например: Анна или Семья Ивановых" required>
        </div>
        <button type="submit" class="btn btn-primary">Создать ссылку</button>
      </div>
    </form>
    <?php if ($success): ?>
    <div class="success" style="margin-top:1rem">
      Ссылка создана: <a href="<?= h($success) ?>" target="_blank"><?= h($success) ?></a>
    </div>
    <?php endif; ?>
  </div>

  <!-- Guests table -->
  <div class="tbl-wrap">
    <div class="tbl-head">
      <h3>Список гостей</h3>
    </div>
    <?php if (empty($guests)): ?>
    <p class="empty">Гостей пока нет. Создайте первое приглашение выше.</p>
    <?php else: ?>
    <table>
      <thead>
        <tr>
          <th>Имя</th>
          <th>Ссылка</th>
          <th>Ответ</th>
          <th>Комментарий</th>
          <th></th>
        </tr>
      </thead>
      <tbody>
      <?php foreach ($guests as $slug => $g):
        $st      = $g['rsvp']['status']  ?? null;
        $cmt     = $g['rsvp']['comment'] ?? '';
        $inviteUrl = SITE_URL . '/invite_' . $slug;
        $badgeClass = $st === 'attending' ? 'badge-yes' : ($st === 'not_attending' ? 'badge-no' : 'badge-wait');
        $badgeText  = $st === 'attending' ? 'Придёт' : ($st === 'not_attending' ? 'Не придёт' : 'Ожидает');
      ?>
      <tr>
        <td><strong><?= h($g['name']) ?></strong></td>
        <td>
          <div class="link-cell">
            <span class="link-text" title="<?= h($inviteUrl) ?>"><?= h($inviteUrl) ?></span>
            <button class="copy-btn" onclick="copyLink(this,'<?= h($inviteUrl) ?>')">Копировать</button>
          </div>
        </td>
        <td><span class="badge <?= $badgeClass ?>"><?= $badgeText ?></span></td>
        <td><span class="comment-cell" title="<?= h($cmt) ?>"><?= $cmt ? h($cmt) : '—' ?></span></td>
        <td>
          <form method="post" onsubmit="return confirm('Удалить гостя <?= h($g['name']) ?>?')">
            <input type="hidden" name="action" value="delete">
            <input type="hidden" name="slug"   value="<?= h($slug) ?>">
            <button type="submit" class="btn btn-sm btn-danger">Удалить</button>
          </form>
        </td>
      </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
    <?php endif; ?>
  </div>

</div>
<?php endif; ?>

<script>
function copyLink(btn, url) {
  navigator.clipboard.writeText(url).then(function () {
    btn.textContent = 'Скопировано!';
    btn.classList.add('copied');
    setTimeout(function () {
      btn.textContent = 'Копировать';
      btn.classList.remove('copied');
    }, 2000);
  }).catch(function () {
    prompt('Скопируйте ссылку:', url);
  });
}
</script>
</body>
</html>
