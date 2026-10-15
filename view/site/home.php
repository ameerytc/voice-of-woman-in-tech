<section class="hero"><div class="orb o1" aria-hidden="true"></div><div class="orb o2" aria-hidden="true"></div>
 <div class="wrap hero-grid">
  <div class="reveal">
    <p class="eyebrow">Voice of Women in Tech</p>
    <h1>Empowering Women. <em>Building a Safer Digital Future.</em></h1>
    <p class="lead">Join Voice of Women in Tech to develop digital skills, improve cybersecurity awareness, and participate in technology learning opportunities and competitions.</p>
    <div class="cta"><a class="btn btn-lg" href="<?= e(url('/quizzes')) ?>">Explore Available Quizzes</a><a class="btn btn-lg btn-outline" href="<?= e(url('/register')) ?>">Create Your Account</a></div>
  </div>
  <div class="hero-art reveal" aria-hidden="true">
   <svg viewBox="0 0 500 500" xmlns="http://www.w3.org/2000/svg" role="img" aria-label="Orbiting digital shield around the VW logo">
    <defs><radialGradient id="core" cx="50%" cy="50%" r="50%"><stop offset="0" stop-color="#7839D8" stop-opacity=".9"/><stop offset=".6" stop-color="#32105C" stop-opacity=".55"/><stop offset="1" stop-color="#160B2E" stop-opacity="0"/></radialGradient>
     <linearGradient id="ring" x1="0" y1="0" x2="1" y2="1"><stop offset="0" stop-color="#FF791F"/><stop offset="1" stop-color="#7839D8"/></linearGradient></defs>
    <circle cx="250" cy="250" r="240" fill="url(#core)"/>
    <g fill="none" stroke="url(#ring)" stroke-opacity=".55" stroke-width="1.4"><circle cx="250" cy="250" r="190"/><circle cx="250" cy="250" r="140" stroke-dasharray="4 8"/><circle cx="250" cy="250" r="95"/></g>
    <g class="orbit"><circle cx="440" cy="250" r="9" fill="#FF791F"/><circle cx="60" cy="250" r="6" fill="#EAE2FF"/>
      <g transform="translate(250 60)"><circle r="20" fill="#32105C" stroke="#FF791F" stroke-width="1.6"/><path d="M0-9l8 3v5c0 5-3.5 8-8 9-4.5-1-8-4-8-9v-5z" fill="none" stroke="#fff" stroke-width="1.6" stroke-linejoin="round"/></g></g>
    <g class="orbit r2"><circle cx="250" cy="110" r="7" fill="#7839D8"/><circle cx="250" cy="390" r="10" fill="#EAE2FF" fill-opacity=".85"/>
      <g transform="translate(390 250)"><circle r="19" fill="#32105C" stroke="#7839D8" stroke-width="1.6"/><path d="M-7 -1h14v10h-14zM-4 -1v-4a4 4 0 018 0v4" fill="none" stroke="#fff" stroke-width="1.6" stroke-linejoin="round"/></g></g>
    <g class="orbit r3"><circle cx="345" cy="250" r="5" fill="#FF791F"/><circle cx="155" cy="250" r="5" fill="#fff"/></g>
    <g class="shield"><circle cx="250" cy="250" r="78" fill="#1D0F42" stroke="url(#ring)" stroke-width="2.5"/><image href="<?= e(asset('images/logo.png')) ?>" x="190" y="190" width="120" height="120" preserveAspectRatio="xMidYMid meet"/></g>
    <g fill="#fff"><circle cx="70" cy="120" r="2"/><circle cx="430" cy="90" r="2.4"/><circle cx="460" cy="380" r="1.8"/><circle cx="90" cy="410" r="2.2"/><circle cx="330" cy="460" r="1.6"/></g>
    <g stroke="#EAE2FF" stroke-opacity=".3" stroke-width="1"><path d="M70 120L130 160M430 90L380 140M460 380L400 340M90 410L150 360"/></g>
   </svg>
  </div>
 </div>
</section>

<section class="section" id="about"><div class="wrap"><div class="section-head reveal"><p class="eyebrow">About us</p><h2>Who we are</h2><p><?= e($about) ?></p></div>
 <div class="grid3">
  <div class="glass pgm reveal"><h3><?= icon('spark', 22) ?> Digital skills</h3><p>Practical, beginner-friendly learning that helps women grow confidence with technology.</p></div>
  <div class="glass pgm reveal"><h3><?= icon('shield', 22) ?> Cybersecurity awareness</h3><p>Understand scams, phishing and safe online habits so you can protect yourself and others.</p></div>
  <div class="glass pgm reveal"><h3><?= icon('trophy', 22) ?> Learning competitions</h3><p>Fair, transparent quizzes with clear rules and verified results.</p></div>
 </div></div></section>

<section class="section" id="programs"><div class="wrap"><div class="section-head reveal"><p class="eyebrow">Our programs</p><h2>Programs and quizzes</h2><p>These are the programs and quizzes our team has published.</p>
  <?php if ($cats): ?><div class="chips"><?php foreach ($cats as $c): ?><span class="chip"><?= e($c) ?></span><?php endforeach; ?></div><?php endif; ?></div>
 <?php if (!$okP): ?><div class="glass empty-glass">Programs could not be loaded right now. Please refresh the page.</div>
 <?php elseif (!$programs): ?><div class="glass empty-glass"><?= icon('cal', 28) ?><h3>No programs have been published yet</h3><p>New quizzes and learning programs will appear here as soon as they are announced. Create an account to be notified.</p><a class="btn" href="<?= e(url('/register')) ?>">Create Your Account</a></div>
 <?php else: ?><div class="grid"><?php foreach ($programs as $q): ?>
  <article class="glass pgm reveal"><p class="eyebrow"><?= e($q['category']) ?></p><h3><?= e($q['title']) ?></h3><?php if ($q['description'] !== ''): ?><p><?= e($q['description']) ?></p><?php endif; ?>
   <ul class="meta"><li><?= (int)$q['qcount'] ?> questions · <?= (int)$q['duration_minutes'] ?> minutes</li><li>Opens <?= e(fmt_dt($q['opens_at'])) ?></li><li>Closes <?= e(fmt_dt($q['closes_at'])) ?></li></ul>
   <a class="btn btn-sm" href="<?= e(url('/quiz/' . $q['id'])) ?>"><?= ts($q['opens_at']) > time() ? 'View details' : 'Start' ?></a></article><?php endforeach; ?></div>
  <p class="center" style="margin-top:1rem"><a href="<?= e(url('/quizzes')) ?>">See all quizzes</a></p><?php endif; ?></div></section>

<section class="section"><div class="wrap"><div class="section-head reveal"><p class="eyebrow">Simple and fair</p><h2>How it works</h2></div>
 <ol class="steps"><?php foreach ([['Create your account', 'Register once and use it for every competition.'], ['Discover an available quiz', 'Browse published quizzes and their dates.'], ['Read and accept the rules', 'Every rule is shown before you start.'], ['Complete the quiz', 'Answer within the time limit; your answers save as you go.'], ['View your results when published', 'See your score and the official leaderboard.']] as [$t, $d]): ?>
  <li class="glass reveal"><b><?= e($t) ?></b><span><?= e($d) ?></span></li><?php endforeach; ?></ol></div></section>

<section class="section"><div class="wrap"><div class="section-head reveal"><p class="eyebrow">Verified numbers</p><h2>Our impact so far</h2></div>
 <?php if (!$okS): ?><div class="glass empty-glass">Statistics could not be loaded right now.</div>
 <?php elseif ($stats['participants'] === 0 && $stats['completed'] === 0 && $stats['competitions'] === 0): ?><div class="glass empty-glass">Our impact numbers will appear here once participants join and complete quizzes. Every figure on this page comes from our records.</div>
 <?php else: ?><div class="impact"><?php foreach ([['participants', 'Registered participants'], ['delivered', 'Quizzes delivered'], ['completed', 'Completed assessments'], ['competitions', 'Published competitions']] as [$k, $l]): ?><div class="glass reveal"><b><?= (int)$stats[$k] ?></b><span><?= e($l) ?></span></div><?php endforeach; ?></div><?php endif; ?></div></section>

<section class="section" id="contact"><div class="wrap"><div class="glass reveal" style="padding:1.6rem;text-align:center"><h2>Get in touch</h2>
 <?php $c = org_contact(); if ($c['email'] || $c['phone'] || $c['address']): ?><p><?php if ($c['email']): ?><a href="mailto:<?= e($c['email']) ?>"><?= e($c['email']) ?></a><?php endif; ?><?= $c['email'] && $c['phone'] ? ' · ' : '' ?><?= e($c['phone']) ?></p><?php if ($c['address']): ?><p><?= e($c['address']) ?></p><?php endif; ?>
 <?php else: ?><p>Contact details will be added soon.</p><?php endif; ?></div></div></section>
