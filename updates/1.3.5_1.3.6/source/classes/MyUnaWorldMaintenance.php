<?php defined('BX_DOL') or die('hack attempt');

/** Self-contained public page; never exposes private backup paths or table names. */
class MyUnaWorldMaintenance
{
    public static function buildPage(array $settings, $startedAt, $duration = 600)
    {
        $escape = static function ($key, $default) use ($settings) {
            $value = trim((string)($settings[$key] ?? ''));
            return htmlspecialchars($value !== '' ? $value : $default, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        };
        $startedAt = max(1, (int)$startedAt);
        $duration = max(60, min(86400, (int)$duration));
        return strtr(<<<'HTML'
<!doctype html>
<html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<meta name="robots" content="noindex,nofollow,noarchive"><meta http-equiv="refresh" content="15">
<title>__HEADING__ | My UNA World</title>
<style>
*{box-sizing:border-box}body{margin:0;background:#f5f9fd;color:#14264e;font-family:-apple-system,BlinkMacSystemFont,"Segoe UI",Arial,sans-serif}header{padding:25px 6vw;background:white;border-bottom:1px solid #dbe7f4;font-size:24px;font-weight:800}header span{color:#079b9e}main{width:min(920px,90vw);margin:9vh auto;background:white;border:1px solid #dbe7f4;border-radius:24px;padding:clamp(25px,5vw,60px);box-shadow:0 18px 55px #16376712}h1{font-size:clamp(32px,5vw,52px);line-height:1.12;margin:14px 0 24px}p{font-size:18px;line-height:1.6;color:#607193}.status{color:#078b92;font-size:14px;font-weight:800;letter-spacing:.08em;text-transform:uppercase}.timer{margin:30px 0;padding:24px;border-radius:16px;background:#e8f8f7;color:#14264e}.timer strong{display:block;font-size:36px;font-variant-numeric:tabular-nums;margin:10px 0}.timer small{color:#607193}.note{font-size:14px}footer{text-align:center;padding:20px 6vw;color:#607193;font-size:14px}[hidden]{display:none!important}
</style></head><body>
<header__HEADER_HIDDEN__>My UNA <span>World</span> · Maintenance</header>
<main id="muw-maintenance" data-start="__START__" data-end="__END__">
<div class="status">Backup in progress</div><h1>__HEADING__</h1><p>__MESSAGE__</p>
<section class="timer" aria-label="Estimated time remaining"><span id="muw-timer-label">Estimated time remaining</span><strong id="muw-countdown">Calculating…</strong><small id="muw-return">__RETURN__</small></section>
<p>__PATIENCE__</p><p class="note">Public access will resume automatically after this backup finishes. This page checks again every 15 seconds. The estimate does not control when access reopens.</p>
</main><footer__FOOTER_HIDDEN__>__FOOTER__</footer>
<script>(function(){var root=document.getElementById('muw-maintenance'),end=Number(root.getAttribute('data-end'))*1000;function tick(){var left=Math.max(0,Math.ceil((end-Date.now())/1000));document.getElementById('muw-countdown').textContent=left?String(Math.floor(left/3600)).padStart(2,'0')+':'+String(Math.floor(left%3600/60)).padStart(2,'0')+':'+String(left%60).padStart(2,'0'):'Still working';if(!left){document.getElementById('muw-timer-label').textContent='Backup is taking longer than estimated';document.getElementById('muw-return').textContent='Public access stays paused until the backup finishes.';}}tick();setInterval(tick,1000);}());</script>
</body></html>
HTML
        , array(
            '__HEADING__' => $escape('maintenance_heading', 'Scheduled Maintenance in Progress'),
            '__MESSAGE__' => $escape('maintenance_message', 'Our site is temporarily unavailable while My UNA World creates a verified backup.'),
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
