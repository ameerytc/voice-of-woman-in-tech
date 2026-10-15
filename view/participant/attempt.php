<div id="quiz-app">
  <div class="progress" aria-hidden="true"><div id="pfill" class="progress-fill"></div></div>
  <p id="qcount" class="qcount"></p>
  <div id="warn" class="alert a-warn" hidden role="alert"></div>
  <form id="qform" class="card" novalidate>
    <fieldset><legend id="qtext" class="qtext" tabindex="-1"></legend><p id="qmarks" class="fine"></p><div id="opts" class="opts"></div></fieldset>
    <p id="qmsg" class="field-error" role="alert"></p>
    <div class="actions"><button type="button" class="btn btn-outline" id="prev">Previous</button><button type="button" class="btn" id="next">Next</button></div>
  </form>
  <p class="center"><button type="button" class="btn btn-lg btn-block" id="submit">Submit Quiz</button></p>
  <p class="fine center">The timer is controlled by the server. Your answers are saved as you choose them.</p>
</div>
<div id="overlay" class="overlay" hidden role="alertdialog" aria-modal="true"><div class="overlay-box"><h2 id="ov-t"></h2><p id="ov-p"></p><div class="actions"><button class="btn btn-outline" id="ov-no" hidden>Keep working</button><button class="btn" id="ov-ok">OK</button></div></div></div>
<script type="application/json" id="quiz-data"><?= json_encode($data, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?></script>
<script src="<?= e(asset('js/quiz.js')) ?>"></script>
<noscript><p class="center">Please turn on JavaScript to take the quiz.</p></noscript>
