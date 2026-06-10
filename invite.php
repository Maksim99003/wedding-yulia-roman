<?php
require_once __DIR__ . '/config.php';

$slug = preg_replace('/[^a-z0-9_-]/', '', $_GET['guest'] ?? '');
if (!$slug) { header('Location: /'); exit; }

$guests = load_guests();
$guest  = $guests[$slug] ?? null;
if (!$guest) { header('Location: /'); exit; }

$currentStatus  = $guest['rsvp']['status']  ?? null;
$currentComment = $guest['rsvp']['comment'] ?? '';
$currentZags    = $guest['rsvp']['zags']    ?? null;
$greeting       = guestGreeting($guest);
$displayNames   = guestDisplayNames($guest);

$html = file_get_contents(__DIR__ . '/index.html');

$rsvpHtml = buildRsvpSection($greeting, $displayNames);
$html = preg_replace('/<section[^>]+id="rsvp"[^>]*>.*?<\/section>/su', $rsvpHtml, $html);
$html = str_replace('</head>', buildInviteStyles() . "\n</head>", $html);
$html = str_replace('</body>', buildRsvpJs($slug, $currentStatus, $currentComment, $currentZags) . "\n</body>", $html);

echo $html;


function buildInviteStyles(): string {
    return <<<CSS
<style>
/* ── Invite page overrides ── */
.rsvp__sub--invite {
  font-size: 1.15rem !important;
  line-height: 1.75 !important;
  color: var(--mid);
  margin-bottom: 0 !important;
}
.invite-card {
  background: #ffffff;
  border-radius: 20px;
  padding: 2.2rem 2rem;
  margin: 1.8rem auto 0;
  max-width: 500px;
  box-shadow: 0 4px 12px rgba(90,40,30,.06), 0 16px 40px rgba(90,40,30,.10);
  border: 1px solid rgba(192,112,104,.18);
}
.invite-btns {
  display: flex !important;
  flex-direction: column !important;
  gap: 12px !important;
}
.btn.btn--invite-yes {
  background: #C07068 !important;
  color: #fff !important;
  border: none !important;
  border-radius: 10px !important;
  padding: 17px 28px !important;
  font-size: .95rem !important;
  letter-spacing: .1em;
  justify-content: center;
  box-shadow: 0 4px 14px rgba(192,112,104,.4) !important;
  transform: none;
  transition: background .2s, box-shadow .2s, transform .2s !important;
}
.btn.btn--invite-yes:hover {
  background: #a05560 !important;
  box-shadow: 0 6px 20px rgba(192,112,104,.45) !important;
  transform: translateY(-2px) !important;
}
.btn.btn--invite-no {
  background: transparent !important;
  color: #9e7d72 !important;
  border: 1.5px solid #ddd !important;
  border-radius: 10px !important;
  padding: 14px 28px !important;
  font-size: .85rem !important;
  letter-spacing: .08em;
  justify-content: center;
  box-shadow: none !important;
  transition: border-color .2s, color .2s !important;
}
.btn.btn--invite-no:hover {
  border-color: #9e7d72 !important;
  color: #7D5A46 !important;
  transform: none !important;
}
#rsvpDone { flex-direction: column; align-items: center; gap: .8rem; animation: fadeIn .5s ease; }
.invite-names-list { list-style: none; margin: .5rem 0 1.5rem; padding: 0; display: flex; flex-direction: column; gap: 4px; }
.invite-names-list li { font-family: var(--font-serif); font-size: 1.05rem; color: var(--ink); }
</style>
CSS;
}

function buildRsvpSection(string $greeting, array $displayNames): string {
    $greetH     = h($greeting);
    $namesHtml  = '';
    if (count($displayNames) > 1) {
        $items = array_map('h', $displayNames);
        $namesHtml = '<ul class="invite-names-list">'
            . implode('', array_map(fn($n) => "<li>{$n}</li>", $items))
            . '</ul>';
    }
    return <<<HTML
<section class="rsvp reveal" id="rsvp" aria-label="Подтверждение присутствия">
    <div class="rsvp__bg"></div>
    <div class="container narrow rsvp__inner">
      <p class="eyebrow">ваш ответ</p>
      <h2>Подтверждение</h2>
      <p class="rsvp__sub rsvp__sub--invite">{$greetH}, пожалуйста, сообщите о своём присутствии до <strong>20 июля 2026</strong>.</p>
      {$namesHtml}

      <div id="rsvpInvite">
        <div class="invite-card">

          <!-- Шаг 1: приду / не приду -->
          <div class="invite-btns" id="step1">
            <button class="btn btn--invite-yes" onclick="handleRsvp('attending')">&#129293;&nbsp;&nbsp;Буду присутствовать</button>
            <button class="btn btn--invite-no"  onclick="handleRsvp('not_attending')">Не смогу присутствовать</button>
          </div>

          <!-- Шаг 2: будет ли в ЗАГСе (только если attending) -->
          <div id="step2" style="display:none">
            <p style="font-size:1rem;color:var(--mid);margin-bottom:1.4rem;line-height:1.6">Планируете ли вы присутствовать на церемонии в ЗАГСе?</p>
            <div class="invite-btns">
              <button class="btn btn--invite-yes" onclick="handleZags('yes')">Да, буду в ЗАГСе</button>
              <button class="btn btn--invite-no"  onclick="handleZags('no')">Нет, только на банкете</button>
            </div>
          </div>

          <!-- Комментарий (если не придёт) -->
          <div id="inviteComment" class="invite-comment" style="display:none">
            <p style="font-size:.95rem;color:var(--mid);margin-bottom:.8rem">Хотите оставить комментарий?</p>
            <textarea id="rsvpCommentText" placeholder="Комментарий (по желанию)…" rows="3" maxlength="500"></textarea>
            <div class="invite-comment-actions">
              <button class="btn btn--primary" onclick="submitRsvp()">Сохранить</button>
              <button class="btn btn--outline"  onclick="cancelComment()">Отмена</button>
            </div>
          </div>

          <!-- Подтверждение -->
          <div id="rsvpDone" class="rsvp-success" style="display:none">
            <div class="success-icon" id="doneIcon">&#129293;</div>
            <h3 id="doneTitle"></h3>
            <p id="doneText"></p>
            <button class="btn btn--outline btn--sm" onclick="changeAnswer()" style="margin-top:1.2rem">Изменить ответ</button>
          </div>

        </div>
      </div>
    </div>
  </section>
HTML;
}


function buildRsvpJs(string $slug, ?string $status, string $comment, ?string $zags): string {
    $sj  = json_encode($slug,    JSON_UNESCAPED_UNICODE);
    $stj = json_encode($status,  JSON_UNESCAPED_UNICODE);
    $cj  = json_encode($comment, JSON_UNESCAPED_UNICODE);
    $zj  = json_encode($zags,    JSON_UNESCAPED_UNICODE);
    return <<<JS
<script>
(function () {
  var SLUG    = {$sj};
  var initSt  = {$stj};
  var initCmt = {$cj};
  var initZags = {$zj};
  var pendingStatus = null;

  function showDone(status, comment, zags) {
    document.getElementById('step1').style.display         = 'none';
    document.getElementById('step2').style.display         = 'none';
    document.getElementById('inviteComment').style.display = 'none';
    var box   = document.getElementById('rsvpDone');
    var icon  = document.getElementById('doneIcon');
    var title = document.getElementById('doneTitle');
    var text  = document.getElementById('doneText');
    box.style.display = 'flex';
    if (status === 'attending') {
      icon.textContent  = '🤍';
      title.textContent = 'Спасибо! Ждём вас!';
      var zagsNote = zags === 'yes' ? ' Вы также будете на церемонии в ЗАГСе.' : (zags === 'no' ? ' Ждём вас на банкете!' : '');
      text.textContent  = 'Вы подтвердили своё присутствие.' + zagsNote;
    } else {
      icon.textContent  = '🙏';
      title.textContent = 'Спасибо за ответ';
      text.textContent  = comment
        ? 'Мы получили ваш ответ. Комментарий: ' + comment
        : 'Жаль, что не получится. Спасибо, что сообщили заранее!';
    }
  }

  window.handleRsvp = function (status) {
    pendingStatus = status;
    if (status === 'attending') {
      document.getElementById('step1').style.display = 'none';
      document.getElementById('step2').style.display = 'block';
    } else {
      document.getElementById('step1').style.display         = 'none';
      document.getElementById('inviteComment').style.display = 'block';
    }
  };

  window.handleZags = function (zags) {
    save('attending', '', zags);
  };

  window.submitRsvp = function () {
    var comment = document.getElementById('rsvpCommentText').value.trim();
    save('not_attending', comment, null);
  };

  window.cancelComment = function () {
    document.getElementById('inviteComment').style.display = 'none';
    document.getElementById('step1').style.display         = 'flex';
    pendingStatus = null;
  };

  window.changeAnswer = function () {
    document.getElementById('rsvpDone').style.display  = 'none';
    document.getElementById('step1').style.display     = 'flex';
    document.getElementById('step2').style.display     = 'none';
    document.getElementById('inviteComment').style.display = 'none';
    pendingStatus = null;
  };

  function save(status, comment, zags) {
    fetch('/rsvp.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ guest: SLUG, status: status, comment: comment, zags: zags }),
    })
      .then(function (r) { return r.json(); })
      .then(function (d) { if (d.ok) showDone(status, comment, zags); })
      .catch(function () {});
  }

  if (initSt) showDone(initSt, initCmt, initZags);
})();
</script>
JS;
}
