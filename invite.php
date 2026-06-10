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

$html = file_get_contents(__DIR__ . '/index.html');

// Replace RSVP section (found by id="rsvp") with personalized version
$rsvpHtml = buildRsvpSection($guestName, $slug);
$html = preg_replace(
    '/<section[^>]+id="rsvp"[^>]*>.*?<\/section>/su',
    $rsvpHtml,
    $html
);

// Inject inline JS with guest state before </body>
$html = str_replace('</body>', buildRsvpJs($slug, $currentStatus, $currentComment) . "\n</body>", $html);

echo $html;


function buildRsvpSection(string $name, string $slug): string {
    $n = h($name);
    return <<<HTML
<section class="rsvp reveal" id="rsvp" aria-label="Подтверждение присутствия">
    <div class="rsvp__bg"></div>
    <div class="container narrow rsvp__inner">
      <p class="eyebrow">ваш ответ</p>
      <h2>Подтверждение</h2>
      <p class="rsvp__sub">Дорогой(-ая) <strong>{$n}</strong>, пожалуйста, сообщите о своём присутствии до <strong>20 июля 2026</strong>.</p>

      <div id="rsvpInvite">
        <div class="invite-btns" id="inviteBtns">
          <button class="btn btn--invite-yes" onclick="handleRsvp('attending')">&#129293;&nbsp;Буду присутствовать</button>
          <button class="btn btn--invite-no"  onclick="handleRsvp('not_attending')">Не смогу присутствовать</button>
        </div>

        <div id="inviteComment" class="invite-comment" style="display:none">
          <textarea id="rsvpCommentText" placeholder="Комментарий (по желанию)…" rows="3" maxlength="500"></textarea>
          <div class="invite-comment-actions">
            <button class="btn btn--primary" onclick="submitRsvp()">Сохранить</button>
            <button class="btn btn--outline"  onclick="cancelComment()">Отмена</button>
          </div>
        </div>

        <div id="rsvpDone" class="rsvp-success" style="display:none">
          <div class="success-icon" id="doneIcon">&#129293;</div>
          <h3 id="doneTitle"></h3>
          <p id="doneText"></p>
          <button class="btn btn--outline btn--sm" onclick="changeAnswer()" style="margin-top:1.2rem">Изменить ответ</button>
        </div>
      </div>
    </div>
  </section>
HTML;
}


function buildRsvpJs(string $slug, ?string $status, string $comment): string {
    $sj = json_encode($slug,   JSON_UNESCAPED_UNICODE);
    $stj = json_encode($status,  JSON_UNESCAPED_UNICODE);
    $cj = json_encode($comment, JSON_UNESCAPED_UNICODE);
    return <<<JS
<script>
(function () {
  var SLUG    = {$sj};
  var initSt  = {$stj};
  var initCmt = {$cj};

  function showDone(status, comment) {
    document.getElementById('inviteBtns').style.display    = 'none';
    document.getElementById('inviteComment').style.display = 'none';
    var box   = document.getElementById('rsvpDone');
    var icon  = document.getElementById('doneIcon');
    var title = document.getElementById('doneTitle');
    var text  = document.getElementById('doneText');
    box.style.display = 'flex';
    if (status === 'attending') {
      icon.textContent  = '🤍';
      title.textContent = 'Спасибо! Ждём вас!';
      text.textContent  = 'Вы подтвердили своё присутствие. Будем рады видеть вас!';
    } else {
      icon.textContent  = '🙏';
      title.textContent = 'Спасибо за ответ';
      text.textContent  = comment
        ? 'Мы получили ваш ответ. Комментарий: ' + comment
        : 'Жаль, что не получится. Спасибо, что сообщили заранее!';
    }
  }

  window.handleRsvp = function (status) {
    if (status === 'not_attending') {
      var box = document.getElementById('inviteComment');
      box.dataset.pending                                    = status;
      box.style.display                                      = 'block';
      document.getElementById('inviteBtns').style.display   = 'none';
    } else {
      save(status, '');
    }
  };

  window.submitRsvp = function () {
    var status  = document.getElementById('inviteComment').dataset.pending;
    var comment = document.getElementById('rsvpCommentText').value.trim();
    save(status, comment);
  };

  window.cancelComment = function () {
    document.getElementById('inviteComment').style.display = 'none';
    document.getElementById('inviteBtns').style.display    = 'flex';
  };

  window.changeAnswer = function () {
    document.getElementById('rsvpDone').style.display      = 'none';
    document.getElementById('inviteBtns').style.display    = 'flex';
    document.getElementById('inviteComment').style.display = 'none';
  };

  function save(status, comment) {
    fetch('/rsvp.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ guest: SLUG, status: status, comment: comment }),
    })
      .then(function (r) { return r.json(); })
      .then(function (d) { if (d.ok) showDone(status, comment); })
      .catch(function () {});
  }

  if (initSt) showDone(initSt, initCmt);
})();
</script>
JS;
}
