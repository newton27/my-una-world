<?php defined('BX_DOL') or die('hack attempt');

/** Self-contained public page; never exposes private backup paths or table names. */
class MyUnaWorldMaintenance
{
    public static function buildPage(array $settings, $startedAt, $duration = 600)
    {
        $escape = static function ($key, $default) use ($settings) {
            $value = trim((string)($settings[$key] ?? ''));
            if (strpos($value, 'Our site is temporarily unavailable while My UNA') === 0) {
                $value = 'Our site is temporarily unavailable while scheduled maintenance is in progress. The public site will return automatically when maintenance is complete.';
            }
            $value = str_replace(array('My UNA World', 'My UNA Doctor'), 'scheduled maintenance', $value);
            return htmlspecialchars($value !== '' ? $value : $default, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        };
        $startedAt = max(1, (int)$startedAt);
        $duration = max(60, min(86400, (int)$duration));
        return strtr(<<<'HTML'
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<meta http-equiv="refresh" content="15">
<meta name="robots" content="noindex,nofollow,noarchive">
<title>__HEADING__ | Scheduled maintenance</title>
<style>
:root{--navy:#081943;--ink:#14264e;--muted:#607193;--line:#dbe7f4;--soft:#f5f9fd;--teal:#0bb3ae;--cyan:#1597ed;--green:#22a864;--purple:#7051d8;--card:#fff;--shadow:0 18px 55px rgba(22,55,103,.10)}
*{box-sizing:border-box}html,body{margin:0;min-height:100%;font-family:-apple-system,BlinkMacSystemFont,"Segoe UI",Roboto,Arial,sans-serif;color:var(--navy);background:radial-gradient(circle at 78% 38%,rgba(29,151,237,.08),transparent 25%),linear-gradient(180deg,#fff 0,#f8fbfe 100%)}
body{min-height:100vh}.mud-page{min-height:100vh;display:flex;flex-direction:column}.mud-header{height:112px;border-bottom:1px solid var(--line);display:flex;align-items:center;justify-content:space-between;padding:0 4.5vw;background:rgba(255,255,255,.94)}
.mud-brand{display:flex;align-items:center;gap:15px}.mud-heart{width:58px;height:52px;border-radius:19px 19px 24px 24px;background:linear-gradient(145deg,#16b8b0,#078ba2);display:grid;place-items:center;box-shadow:0 8px 24px rgba(11,179,174,.18)}.mud-heart svg{width:42px;height:42px}.mud-brandtext strong{display:block;font-size:31px;line-height:1;font-weight:800;letter-spacing:-.8px}.mud-brandtext span{display:block;margin-top:4px;font-size:15px;color:var(--muted)}
.mud-header-left{display:flex;align-items:center;gap:30px}.mud-divider{width:1px;height:44px;background:var(--line)}.mud-pill{display:flex;align-items:center;gap:10px;padding:13px 23px;border-radius:999px;background:#e8f8f7;color:#078b92;font-weight:800;font-size:17px}.mud-trust{display:flex;align-items:center;gap:12px;text-align:left}.mud-trust svg{width:38px;height:38px}.mud-trust strong{display:block;font-size:15px}.mud-trust span{display:block;color:var(--muted);font-size:13px;margin-top:4px}
.mud-main{width:min(1500px,92vw);margin:30px auto 0;display:grid;grid-template-columns:minmax(0,1.38fr) minmax(420px,.9fr);gap:52px;align-items:start}.mud-hero{padding:20px 8px 0}.mud-hero h1{font-size:clamp(50px,5vw,80px);line-height:.98;margin:0;letter-spacing:-3px;font-weight:850}.mud-hero h1 .accent{color:var(--teal)}.mud-cross{display:inline-grid;place-items:center;width:.82em;height:.82em;border-radius:18px;background:linear-gradient(160deg,#12b5b1,#0b7fb0);color:#fff;font-size:.72em;vertical-align:.08em;margin-left:.12em;box-shadow:0 10px 26px rgba(15,148,182,.20)}
.mud-lead{font-size:20px;line-height:1.6;color:var(--muted);max-width:760px;margin:34px 0 52px}.mud-progress-title{display:flex;justify-content:space-between;max-width:760px;font-size:17px;font-weight:800;margin-bottom:18px}.mud-progress-title span:last-child{color:#079da5}.mud-track{height:28px;max-width:760px;border-radius:999px;background:#e7edf5;overflow:hidden;box-shadow:inset 0 1px 2px rgba(8,25,67,.06)}.mud-fill{height:100%;width:0;border-radius:inherit;background:linear-gradient(90deg,#1479ef,#0bb3ae);transition:width .55s ease}.mud-verified{display:flex;align-items:center;gap:12px;margin-top:22px;color:var(--muted);font-size:15px}.mud-check{width:25px;height:25px;border-radius:50%;display:grid;place-items:center;background:var(--teal);color:#fff;font-weight:900}
.mud-side{position:relative}.mud-timer{background:var(--card);border:1px solid var(--line);border-radius:26px;padding:24px 28px 20px;box-shadow:var(--shadow)}.mud-timer-title{display:flex;align-items:center;gap:13px;font-weight:800;font-size:19px}.mud-clock{width:39px;height:39px;border-radius:50%;display:grid;place-items:center;background:#edf6ff;color:#117be8}.mud-count{display:grid;grid-template-columns:1fr auto 1fr auto 1fr;align-items:center;text-align:center;margin:20px 0 13px}.mud-num{font-variant-numeric:tabular-nums;font-size:clamp(49px,4vw,68px);line-height:1;font-weight:780;letter-spacing:1px}.mud-colon{font-size:43px;line-height:1;color:#22345d;padding:0 6px 20px}.mud-unit{display:block;color:#0d76df;font-size:13px;font-weight:800;margin-top:11px;letter-spacing:.3px}.mud-timer-foot{border-top:1px solid var(--line);margin-top:18px;padding-top:16px;display:flex;align-items:center;gap:13px}.mud-timer-foot .icon{width:43px;height:43px;border-radius:50%;display:grid;place-items:center;background:#eef6ff;color:#157ee8;font-size:23px}.mud-timer-foot strong{display:block;font-size:15px}.mud-timer-foot span{display:block;color:var(--muted);font-size:14px;margin-top:5px}
.mud-visual{height:340px;position:relative;margin-top:18px;border-radius:26px;overflow:hidden;background:#061927;border:1px solid #cbdfea;box-shadow:var(--shadow);isolation:isolate}
.mud-server-video{position:absolute;inset:0;width:100%;height:100%;object-fit:cover;object-position:center 48%;display:block;background:#061927}
.mud-video-shade{position:absolute;inset:0;z-index:1;background:linear-gradient(180deg,rgba(2,18,30,.03) 30%,rgba(2,18,30,.78) 100%);pointer-events:none}
.mud-health-overlay{position:absolute;z-index:2;left:17px;right:17px;bottom:16px;padding:13px 15px 12px;border-radius:17px;border:1px solid rgba(255,255,255,.20);background:rgba(3,25,38,.74);box-shadow:0 10px 28px rgba(0,0,0,.19);color:#fff;backdrop-filter:blur(8px)}
.mud-health-head{display:flex;align-items:center;gap:10px}.mud-health-dot{width:11px;height:11px;border-radius:50%;background:#38e0a0;box-shadow:0 0 0 5px rgba(56,224,160,.13),0 0 18px rgba(56,224,160,.75);animation:mud-health-pulse 1.8s ease-in-out infinite}.mud-health-head strong{font-size:15px;letter-spacing:.1px}.mud-health-head span{margin-left:auto;font-size:12px;font-weight:800;color:#93f1cf;text-transform:uppercase;letter-spacing:.6px}
.mud-health-sub{display:block;margin:7px 0 10px;color:#c6d9e5;font-size:12px;line-height:1.35}.mud-health-metrics{display:grid;grid-template-columns:repeat(3,1fr);gap:7px}.mud-health-metric{padding:7px 8px;border-radius:10px;background:rgba(255,255,255,.09);border:1px solid rgba(255,255,255,.08);min-width:0}.mud-health-metric b{display:block;font-size:13px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}.mud-health-metric small{display:block;margin-top:3px;color:#9fb6c6;font-size:9px;font-weight:800;letter-spacing:.7px}
@keyframes mud-health-pulse{0%,100%{opacity:1;transform:scale(1)}50%{opacity:.62;transform:scale(.82)}}
.mud-health-overlay.is-waiting .mud-health-dot{background:#55b9ff;box-shadow:0 0 0 5px rgba(85,185,255,.13),0 0 18px rgba(85,185,255,.65)}
.mud-health-overlay.is-waiting .mud-health-head span:last-child{color:#b9ddff}
.mud-health-overlay.is-stale .mud-health-dot,.mud-health-overlay.is-error .mud-health-dot{background:#f5ba45;box-shadow:0 0 0 5px rgba(245,186,69,.13),0 0 18px rgba(245,186,69,.65)}
.mud-health-overlay.is-stale .mud-health-head span:last-child,.mud-health-overlay.is-error .mud-health-head span:last-child{color:#ffdb8f}
.mud-status{width:min(1540px,94vw);margin:18px auto 0;background:rgba(255,255,255,.95);border:1px solid var(--line);border-radius:24px;box-shadow:0 10px 35px rgba(22,55,103,.06);display:grid;grid-template-columns:repeat(4,1fr);padding:26px 28px}.mud-card{display:grid;grid-template-columns:70px 1fr;gap:17px;align-items:center;padding:0 23px;border-right:1px solid var(--line)}.mud-card:last-child{border-right:0}.mud-card-icon{width:66px;height:66px;border-radius:50%;display:grid;place-items:center;font-size:30px}.mud-card:nth-child(1) .mud-card-icon{background:#e5f8f7;color:#07999b}.mud-card:nth-child(2) .mud-card-icon{background:#eaf3ff;color:#1479ef}.mud-card:nth-child(3) .mud-card-icon{background:#f1edff;color:var(--purple)}.mud-card:nth-child(4) .mud-card-icon{background:#eaf8ef;color:var(--green)}.mud-card small{font-size:15px;font-weight:800}.mud-card:nth-child(1) small{color:#07999b}.mud-card:nth-child(2) small{color:#1479ef}.mud-card:nth-child(3) small{color:var(--purple)}.mud-card:nth-child(4) small{color:var(--green)}.mud-card strong{display:block;font-size:16px;margin:7px 0 7px}.mud-card p{margin:0;color:var(--muted);font-size:13px;line-height:1.55}
.mud-footer{width:min(1540px,94vw);margin:18px auto 28px;border:1px solid #e5eef8;border-radius:18px;padding:18px 28px;background:rgba(255,255,255,.78);display:flex;justify-content:center;align-items:center;gap:18px;color:var(--muted);font-size:14px}.mud-footer strong{color:#1279df}.mud-footer .sep{height:27px;width:1px;background:var(--line)}
@media(max-width:1050px){.mud-header{height:auto;min-height:96px;padding:20px 4vw}.mud-trust{display:none}.mud-main{grid-template-columns:1fr;gap:25px}.mud-hero{padding-top:0}.mud-lead{margin-bottom:35px}.mud-side{display:grid;grid-template-columns:1fr 1fr;gap:18px}.mud-visual{margin-top:0}.mud-status{grid-template-columns:1fr 1fr;gap:20px}.mud-card{border-right:0;border-bottom:1px solid var(--line);padding-bottom:18px}.mud-card:nth-child(3),.mud-card:nth-child(4){border-bottom:0}}
@media(max-width:720px){.mud-header-left{gap:14px}.mud-divider,.mud-pill{display:none}.mud-heart{width:48px;height:44px}.mud-brandtext strong{font-size:25px}.mud-main{width:90vw;margin-top:22px}.mud-hero h1{font-size:49px;letter-spacing:-2px}.mud-lead{font-size:17px;margin-top:25px}.mud-side{grid-template-columns:1fr}.mud-visual{height:230px}.mud-health-overlay{left:11px;right:11px;bottom:10px;padding:10px 11px}.mud-health-sub{display:none}.mud-health-metric{padding:6px}.mud-health-metric b{font-size:12px}.mud-status{grid-template-columns:1fr;padding:22px}.mud-card{border-bottom:1px solid var(--line)!important;padding:16px 4px}.mud-card:last-child{border-bottom:0!important}.mud-footer{flex-direction:column;text-align:center}.mud-footer .sep{display:none}}
@media(prefers-reduced-motion:reduce){*{scroll-behavior:auto!important;transition:none!important}.mud-health-dot{animation:none!important}}
[hidden]{display:none!important}
</style>
</head>
<body>
<div class="mud-page" id="mud-maintenance" data-start="__START__" data-end="__END__" data-patience="__PATIENCE__" data-return-message="__RETURN__">
<header class="mud-header"__HEADER_HIDDEN__>
  <div class="mud-header-left">
    <div class="mud-brand">
      <div class="mud-heart" aria-hidden="true"><svg viewBox="0 0 64 64" fill="none"><path d="M8 34h11l4-12 7 25 7-34 7 21h12" stroke="#fff" stroke-width="4" stroke-linecap="round" stroke-linejoin="round"/></svg></div>
      <div class="mud-brandtext"><strong>Scheduled maintenance</strong><span>We will be back shortly.</span></div>
    </div>
    <div class="mud-divider"></div>
    <div class="mud-pill"><span aria-hidden="true">🛠</span> Maintenance</div>
  </div>
  <div class="mud-trust"><svg viewBox="0 0 48 48" fill="none" aria-hidden="true"><path d="M24 4l16 6v12c0 10-6 17-16 22C14 39 8 32 8 22V10l16-6z" stroke="#6d82a5" stroke-width="3"/><path d="M17 23l5 5 10-11" stroke="#6d82a5" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"/></svg><div><strong>Secure. Verified. Trusted.</strong><span>Your community, our priority.</span></div></div>
</header>

<main class="mud-main">
  <section class="mud-hero">
    <h1>__HEADING__<span class="mud-cross" aria-hidden="true">+</span></h1>
    <p class="mud-lead">__MESSAGE__</p>
    <div class="mud-progress-title"><span>Estimated progress</span><span id="mud-progress-label">Starting…</span></div>
    <div class="mud-track" role="progressbar" aria-label="Estimated maintenance progress" aria-valuemin="0" aria-valuemax="100" aria-valuenow="0"><div class="mud-fill" id="mud-progress-fill"></div></div>
    <div class="mud-verified"><span class="mud-check">✓</span><span>Maintenance workflow verified and running.</span></div>
  </section>

  <aside class="mud-side">
    <section class="mud-timer" aria-label="Estimated time until return">
      <div class="mud-timer-title"><span class="mud-clock" aria-hidden="true">◷</span>Estimated time until return</div>
      <div class="mud-count" aria-live="polite">
        <div><div class="mud-num" id="mud-hours">00</div><span class="mud-unit">HOURS</span></div><div class="mud-colon">:</div>
        <div><div class="mud-num" id="mud-minutes">00</div><span class="mud-unit">MINUTES</span></div><div class="mud-colon">:</div>
        <div><div class="mud-num" id="mud-seconds">00</div><span class="mud-unit">SECONDS</span></div>
      </div>
      <div class="mud-timer-foot"><span class="icon" aria-hidden="true">▣</span><div><strong id="mud-timer-message">__PATIENCE__</strong><span id="mud-timer-sub">__RETURN__</span></div></div>
    </section>

      <section class="mud-visual" aria-label="Server activity and health">
        <video id="mud-server-video" class="mud-server-video" autoplay muted loop playsinline preload="metadata" poster="/modules/newton/my_una_world/template/media/my-una-doctor-server-poster.jpg">
          <source src="/modules/newton/my_una_world/template/media/my-una-doctor-server.mp4" type="video/mp4">
        </video>
        <div class="mud-video-shade" aria-hidden="true"></div>
        <div class="mud-health-overlay is-waiting">
          <div class="mud-health-head"><span class="mud-health-dot" aria-hidden="true"></span><strong>Server Health</strong><span id="mud-health-state">Monitoring</span></div>
          <span class="mud-health-sub" id="mud-health-message">Server capacity is monitored while maintenance is running.</span>
          <div class="mud-health-metrics">
            <div class="mud-health-metric"><b id="mud-health-load">—</b><small>SERVER LOAD</small></div>
            <div class="mud-health-metric"><b id="mud-health-memory">—</b><small>MEMORY</small></div>
            <div class="mud-health-metric"><b id="mud-health-batch">Adaptive</b><small>WORKFLOW</small></div>
          </div>
        </div>
      </section>
  </aside>
</main>

<section class="mud-status">
  <div class="mud-card"><div class="mud-card-icon">⚙</div><div><small>Status</small><strong>Maintenance mode active</strong><p>The site is in controlled soft maintenance.</p></div></div>
  <div class="mud-card"><div class="mud-card-icon">⬡</div><div><small>Module</small><strong>Scheduled maintenance</strong><p>The verified maintenance workflow is running.</p></div></div>
  <div class="mud-card"><div class="mud-card-icon">↻</div><div><small>Next step</small><strong>Restoring services</strong><p>Public access returns automatically after validation.</p></div></div>
  <div class="mud-card"><div class="mud-card-icon">✓</div><div><small>Data safety</small><strong>Rollback state preserved</strong><p>Original maintenance state is retained for recovery.</p></div></div>
</section>

<footer class="mud-footer"__FOOTER_HIDDEN__><strong>Scheduled maintenance</strong><span class="sep"></span><span>__FOOTER__</span></footer>
</div>
<script>
(function(){
  'use strict';
  var root=document.getElementById('mud-maintenance');
  if(!root)return;
  var start=Number(root.getAttribute('data-start'))*1000;
  var end=Number(root.getAttribute('data-end'))*1000;
  var duration=Math.max(60000,end-start);
  var hours=document.getElementById('mud-hours');
  var minutes=document.getElementById('mud-minutes');
  var seconds=document.getElementById('mud-seconds');
  var fill=document.getElementById('mud-progress-fill');
  var label=document.getElementById('mud-progress-label');
  var track=fill.parentNode;
  var msg=document.getElementById('mud-timer-message');
  var sub=document.getElementById('mud-timer-sub');
  var patience=root.getAttribute('data-patience')||'We appreciate your patience.';
  var returnMessage=root.getAttribute('data-return-message')||'Estimated return time is counting down live.';
  var video=document.getElementById('mud-server-video');
  var reduced=window.matchMedia?window.matchMedia('(prefers-reduced-motion: reduce)'):null;
  if(video&&reduced&&reduced.matches){video.pause();}
  var healthPanel=document.querySelector('.mud-health-overlay');
  var healthState=document.getElementById('mud-health-state');
  var healthMessage=document.getElementById('mud-health-message');
  var healthLoad=document.getElementById('mud-health-load');
  var healthMemory=document.getElementById('mud-health-memory');
  var healthMode=document.getElementById('mud-health-batch');
  var healthTimer=null;
  var lastHealth=null;
  var healthUrl='/mud-doctor-maintenance-health.json';
  function healthNumber(value){var n=Number(value);return isFinite(n)?n:null;}
  function healthText(value){if(typeof value!=='string'||!value)return '';return value.replace(/[_-]+/g,' ').replace(/\b\w/g,function(c){return c.toUpperCase();});}
  function setHealthClass(name){if(!healthPanel)return;healthPanel.classList.remove('is-waiting','is-stale','is-error');if(name)healthPanel.classList.add(name);}
  function renderHealth(data){
    var updated=healthNumber(data.updated_at);
    var load=healthNumber(data.normalized_load_percent);
    var memory=healthNumber(data.memory_used_percent);
    var available=healthNumber(data.memory_available_percent);
    var disk=healthNumber(data.disk_free_percent);
    var cores=healthNumber(data.cpu_cores);
    var age=updated===null?999999:Math.max(0,Math.floor((Date.now()/1000)-updated));
    if(healthLoad)healthLoad.textContent=load===null?'—':load.toFixed(1)+'%';
    if(healthMemory)healthMemory.textContent=memory===null?'—':memory.toFixed(1)+'%';
    if(healthMode)healthMode.textContent=healthText(data.doctor_mode)||'Monitoring';
    var detail=[];
    if(cores!==null)detail.push(Math.round(cores)+' CPU cores');
    if(available!==null)detail.push(available.toFixed(1)+'% memory available');
    if(disk!==null)detail.push(disk.toFixed(1)+'% disk free');
    if(typeof data.phase==='string'&&data.phase)detail.push(healthText(data.phase));
    if(age>20){
      setHealthClass('is-stale');
      if(healthState)healthState.textContent='Delayed';
      if(healthMessage)healthMessage.textContent='Latest Server telemetry is '+age+' seconds old. Last verified readings remain displayed.';
    }else{
      setHealthClass('');
      if(healthState)healthState.textContent='Live';
      if(healthMessage)healthMessage.textContent=detail.length?detail.join(' • '):'Live Server telemetry received.';
    }
  }
  function scheduleHealth(){
    if(healthTimer){window.clearTimeout(healthTimer);healthTimer=null;}
    if(!document.hidden)healthTimer=window.setTimeout(refreshHealth,5000);
  }
  function refreshHealth(){
    if(document.hidden)return;
    if(!window.fetch){
      setHealthClass('is-error');
      if(healthState)healthState.textContent='Unavailable';
      if(healthMessage)healthMessage.textContent='Live server telemetry is unavailable in this browser.';
      return;
    }
    fetch(healthUrl+'?t='+Date.now(),{cache:'no-store',credentials:'same-origin'})
      .then(function(response){if(!response.ok)throw new Error('HTTP '+response.status);return response.json();})
      .then(function(data){
        if(!data||data.schema!=='my_una_doctor_maintenance_health_v1')throw new Error('Invalid telemetry schema');
        lastHealth=data;
        renderHealth(data);
      })
      .catch(function(){
        setHealthClass('is-error');
        if(healthState)healthState.textContent='Delayed';
        if(lastHealth){
          if(healthMessage)healthMessage.textContent='Live telemetry request was delayed. Last verified readings remain displayed.';
        }else{
          if(healthMessage)healthMessage.textContent='Waiting for the next Server telemetry update.';
        }
      })
      .then(scheduleHealth);
  }
  document.addEventListener('visibilitychange',function(){
    if(document.hidden){
      if(healthTimer){window.clearTimeout(healthTimer);healthTimer=null;}
    }else{
      refreshHealth();
    }
  });
  function pad(n){return String(Math.max(0,n)).padStart(2,'0');}
  function render(){
    var now=Date.now();
    var remaining=Math.max(0,end-now);
    var totalSeconds=Math.floor(remaining/1000);
    var h=Math.floor(totalSeconds/3600);
    var m=Math.floor((totalSeconds%3600)/60);
    var s=totalSeconds%60;
    hours.textContent=pad(h);minutes.textContent=pad(m);seconds.textContent=pad(s);
    var elapsed=Math.max(0,Math.min(duration,now-start));
    var percent=Math.floor((elapsed/duration)*100);
    if(remaining>0){percent=Math.min(94,Math.max(1,percent));label.textContent=percent+'% Complete';msg.textContent=patience;sub.textContent=returnMessage;}
    else{percent=95;label.textContent='Final verification';msg.textContent='Final verification in progress.';sub.textContent='The estimate has elapsed; Server will restore the site only after validation succeeds.';}
    fill.style.width=percent+'%';track.setAttribute('aria-valuenow',String(percent));
  }
  render();
  window.setInterval(render,1000);
  refreshHealth();
})();
</script>
</body>
</html>
HTML
        , array(
            '__HEADING__' => $escape('maintenance_heading', 'Scheduled Maintenance in Progress'),
            '__MESSAGE__' => $escape('maintenance_message', 'Our site is temporarily unavailable while scheduled maintenance is in progress.'),
            '__PATIENCE__' => $escape('maintenance_patience', 'We appreciate your patience.'),
            '__RETURN__' => $escape('maintenance_return_message', 'Estimated time until public access returns.'),
            '__FOOTER__' => $escape('maintenance_footer_message', 'Thank you for your patience while the verified backup completes.'),
            '__HEADER_HIDDEN__' => !empty($settings['maintenance_show_header']) ? '' : ' hidden',
            '__FOOTER_HIDDEN__' => !empty($settings['maintenance_show_footer']) ? '' : ' hidden',
            '__START__' => (string)$startedAt,
            '__END__' => (string)($startedAt + $duration),
        ));
    }
}
