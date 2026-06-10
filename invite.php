<?php
require_once __DIR__ . '/config.php';

$slug = preg_replace('/[^a-z0-9_-]/', '', $_GET['guest'] ?? '');
if (!$slug) { header('Location: /'); exit; }

$guests = load_guests();
$guest  = $guests[$slug] ?? null;
if (!$guest) { header('Location: /'); exit; }

$guestName      = $guest['name'];
$currentStatus  = $guest['rsvp']['status']  ?? null;
$currentComment = $guest['rsvp']['comment'] ?? '';
$currentZags    = $guest['rsvp']['zags']    ?? null;

$html = file_get_contents(__DIR__ . '/index.html');

$rsvpHtml = buildRsvpSection($guestName);
$html = preg_replace('/<section[^>]+id="rsvp"[^>]*>.*?<\/section>/su', $rsvpHtml, $html);
$html = str_replace('</body>', buildRsvpJs($slug, $currentStatus, $currentComment, $currentZags) . "\n</body>", $html);

echo $html;


function buildRsvpSection(string $name): string {
    $n = h($name);
    return <<<HTML
<section class="rsvp reveal" id="rsvp" aria-label="Подтверждение присутствия">
    <div class="rsvp__bg"></div>
    <div class="container narrow rsvp__inner">
      <p class="eyebrow">ваш ответ</p>
      <h2>Подтверждение</h2>
      <p class="rsvp__sub rsvp__sub--invite">Дорогой(-ая) <strong>{$n}</strong>, пожалуйста, сообщите о своём присутствии до <strong>20 июля 2026</strong>.</p>

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
