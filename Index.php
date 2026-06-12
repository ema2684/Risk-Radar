<?php
// ============================================================
//  DAILY MOBILE TRADING RISK WORKSPACE
//  Drop your FMP API key below, then deploy to any PHP host.
// ============================================================
define('FMP_API_KEY', 'YOUR_API_KEY_HERE');   // <-- INSERT KEY

// ── Config ──────────────────────────────────────────────────
$TARGET_CURRENCIES = ['USD', 'EUR', 'GBP', 'AUD'];
$TARGET_IMPACTS    = ['High', 'Medium'];
$today             = date('Y-m-d');

// ── Fetch from FMP ──────────────────────────────────────────
function fetchCalendar(string $date): array {
    $url = sprintf(
        'https://financialmodelingprep.com/api/v3/economic_calendar?from=%s&to=%s&apikey=%s',
        $date, $date, FMP_API_KEY
    );
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 10,
        CURLOPT_HTTPHEADER     => ['Accept: application/json'],
        CURLOPT_SSL_VERIFYPEER => true,
    ]);
    $raw  = curl_exec($ch);
    $err  = curl_error($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($err || $code !== 200 || !$raw) {
        return ['error' => $err ?: "HTTP $code — check your API key or network."];
    }
    $data = json_decode($raw, true);
    return is_array($data) ? $data : ['error' => 'Unexpected API response format.'];
}

// ── Filter & sort ────────────────────────────────────────────
function processEvents(array $raw, array $currencies, array $impacts): array {
    if (isset($raw['error'])) return $raw;

    $events = array_filter($raw, function($e) use ($currencies, $impacts) {
        $cur = strtoupper($e['currency'] ?? '');
        $imp = $e['impact'] ?? '';
        return in_array($cur, $currencies, true) && in_array($imp, $impacts, true);
    });

    usort($events, fn($a, $b) => strcmp($a['date'] ?? '', $b['date'] ?? ''));
    return array_values($events);
}

$rawData = fetchCalendar($today);
$events  = processEvents($rawData, $TARGET_CURRENCIES, $TARGET_IMPACTS);
$hasError = isset($events['error']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
<meta name="theme-color" content="#020617">
<title>Risk Workspace · <?= htmlspecialchars($today) ?></title>
<script src="https://cdn.tailwindcss.com"></script>
<script>
tailwind.config = {
  theme: {
    extend: {
      colors: {
        surface: '#020617',
        card:    '#0f172a',
        border:  '#1e293b',
        muted:   '#334155',
      },
      fontFamily: {
        mono: ['"JetBrains Mono"', '"Fira Code"', 'ui-monospace', 'monospace'],
      }
    }
  }
}
</script>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=JetBrains+Mono:wght@400;600;700&display=swap" rel="stylesheet">
<style>
  * { -webkit-tap-highlight-color: transparent; }
  body { background-color: #020617; font-family: 'JetBrains Mono', monospace; }

  /* Sticky search bar safe-area aware */
  .search-bar { padding-top: env(safe-area-inset-top, 12px); }

  /* Pulse dot for live indicator */
  @keyframes pulse-dot {
    0%, 100% { opacity: 1; transform: scale(1); }
    50%       { opacity: .4; transform: scale(.7); }
  }
  .pulse-dot { animation: pulse-dot 2s ease-in-out infinite; }

  /* Card entrance */
  @keyframes slide-up {
    from { opacity: 0; transform: translateY(10px); }
    to   { opacity: 1; transform: translateY(0); }
  }
  .event-card { animation: slide-up .25s ease forwards; }
  .event-card:nth-child(n) { animation-delay: calc(var(--i, 0) * 40ms); }

  /* Scrollbar hidden on mobile */
  ::-webkit-scrollbar { display: none; }

  /* High impact border glow */
  .glow-red  { box-shadow: 0 0 0 1px rgba(239,68,68,.35), 0 2px 16px rgba(239,68,68,.1); }
  .glow-amber{ box-shadow: 0 0 0 1px rgba(245,158,11,.25), 0 2px 16px rgba(245,158,11,.06); }

  /* Search input clear */
  input[type="search"]::-webkit-search-cancel-button { display: none; }

  /* Hide cards that don't match search */
  .event-card.hidden-card { display: none; }
</style>
</head>
<body class="text-slate-100 min-h-screen antialiased">

<!-- ══════════════════════════════════════════
     STICKY HEADER + SEARCH
══════════════════════════════════════════ -->
<div class="sticky top-0 z-50 bg-surface/95 backdrop-blur-md border-b border-border search-bar">
  <div class="px-4 pt-3 pb-2">

    <!-- Brand row -->
    <div class="flex items-center justify-between mb-3">
      <div class="flex items-center gap-2">
        <div class="w-2 h-2 rounded-full bg-emerald-400 pulse-dot"></div>
        <span class="text-[10px] font-mono font-600 tracking-widest text-slate-400 uppercase">Risk Workspace</span>
      </div>
      <div class="text-right">
        <div class="text-[11px] font-mono text-slate-300 font-semibold"><?= date('D, d M Y') ?></div>
        <div id="clock" class="text-[10px] font-mono text-slate-500"></div>
      </div>
    </div>

    <!-- Search -->
    <div class="relative">
      <svg class="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-slate-500 pointer-events-none" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><circle cx="11" cy="11" r="8"/><path d="m21 21-4.35-4.35"/></svg>
      <input
        id="searchInput"
        type="search"
        inputmode="search"
        autocomplete="off"
        spellcheck="false"
        placeholder="Filter: USD · Fed · NFP · Gold…"
        class="w-full bg-muted/50 border border-border rounded-xl pl-10 pr-10 py-2.5 text-sm font-mono text-slate-200 placeholder-slate-600 focus:outline-none focus:border-slate-500 focus:bg-card transition-colors"
      >
      <button id="clearSearch" class="absolute right-3 top-1/2 -translate-y-1/2 text-slate-600 hover:text-slate-300 hidden">
        <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M18 6 6 18M6 6l12 12"/></svg>
      </button>
    </div>

  </div>

  <!-- Stats pill row -->
  <div class="flex gap-2 px-4 pb-3 overflow-x-auto">
    <?php
      $highCount   = $hasError ? 0 : count(array_filter($events, fn($e) => $e['impact'] === 'High'));
      $mediumCount = $hasError ? 0 : count(array_filter($events, fn($e) => $e['impact'] === 'Medium'));
      $totalCount  = $hasError ? 0 : count($events);
    ?>
    <div class="flex-shrink-0 flex items-center gap-1.5 bg-red-950/60 border border-red-900/50 rounded-lg px-2.5 py-1">
      <span class="w-1.5 h-1.5 rounded-full bg-red-500"></span>
      <span class="text-[11px] font-mono text-red-300 font-semibold"><?= $highCount ?> High</span>
    </div>
    <div class="flex-shrink-0 flex items-center gap-1.5 bg-amber-950/60 border border-amber-900/50 rounded-lg px-2.5 py-1">
      <span class="w-1.5 h-1.5 rounded-full bg-amber-400"></span>
      <span class="text-[11px] font-mono text-amber-300 font-semibold"><?= $mediumCount ?> Medium</span>
    </div>
    <div class="flex-shrink-0 flex items-center gap-1.5 bg-slate-800/60 border border-border rounded-lg px-2.5 py-1">
      <span class="text-[11px] font-mono text-slate-400"><?= implode(' · ', $TARGET_CURRENCIES) ?></span>
    </div>
    <div class="flex-shrink-0 flex items-center gap-1.5 bg-slate-800/60 border border-border rounded-lg px-2.5 py-1">
      <svg class="w-3 h-3 text-slate-500" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M4 4h16v4H4zM4 12h16M4 16h16"/></svg>
      <span class="text-[11px] font-mono text-slate-400"><?= $totalCount ?> events</span>
    </div>
  </div>
</div>

<!-- ══════════════════════════════════════════
     MAIN CONTENT
══════════════════════════════════════════ -->
<main class="px-3 py-4 pb-safe space-y-3 max-w-xl mx-auto" id="eventList">

<?php if ($hasError): ?>
<!-- Error state -->
<div class="bg-card border border-red-900/60 rounded-2xl p-5 text-center mt-8">
  <div class="text-3xl mb-3">⚠️</div>
  <div class="text-red-400 font-semibold text-sm mb-1">Data Fetch Failed</div>
  <div class="text-slate-400 text-xs font-mono leading-relaxed"><?= htmlspecialchars($events['error']) ?></div>
  <div class="mt-4 text-[10px] text-slate-600 font-mono">
    Verify your API key at line 6 of this file,<br>then reload the page.
  </div>
</div>

<?php elseif (empty($events)): ?>
<!-- Empty state -->
<div class="bg-card border border-border rounded-2xl p-8 text-center mt-8">
  <div class="text-3xl mb-3">✅</div>
  <div class="text-slate-300 font-semibold text-sm mb-1">Clear Schedule</div>
  <div class="text-slate-500 text-xs font-mono leading-relaxed">
    No High or Medium impact events found<br>for <?= htmlspecialchars(implode(', ', $TARGET_CURRENCIES)) ?> today.
  </div>
  <div class="mt-4 text-[10px] text-slate-700 font-mono">Engines may run with standard filters.</div>
</div>

<?php else: ?>
<!-- ── Section label ── -->
<div class="flex items-center gap-2 px-1 mb-1">
  <div class="h-px flex-1 bg-border"></div>
  <span class="text-[10px] font-mono text-slate-600 tracking-widest uppercase">Today's Timeline</span>
  <div class="h-px flex-1 bg-border"></div>
</div>

<?php
  $lastHour = null;
  foreach ($events as $i => $event):
    $impact    = $event['impact'] ?? 'Medium';
    $currency  = strtoupper($event['currency'] ?? '—');
    $name      = $event['event'] ?? 'Unknown Event';
    $actual    = $event['actual'] ?? null;
    $forecast  = $event['estimate'] ?? null;
    $previous  = $event['previous'] ?? null;
    $isHigh    = $impact === 'High';

    // Parse time (API returns full datetime string)
    $ts        = strtotime($event['date'] ?? '');
    $timeStr   = $ts ? gmdate('H:i', $ts) : '??:??';
    $hourLabel = $ts ? gmdate('H:00', $ts) : null;

    // Search data attributes
    $searchData = strtolower($currency . ' ' . $name . ' ' . $impact);
?>
  <?php if ($hourLabel && $hourLabel !== $lastHour): ?>
    <?php $lastHour = $hourLabel; ?>
    <div class="flex items-center gap-2 px-1 pt-1 pb-0.5">
      <span class="text-[10px] font-mono text-slate-700 font-semibold"><?= htmlspecialchars($hourLabel) ?> UTC</span>
      <div class="h-px flex-1 bg-border/60"></div>
    </div>
  <?php endif; ?>

  <!-- Event Card -->
  <div
    class="event-card <?= $isHigh ? 'glow-red' : 'glow-amber' ?> bg-card rounded-2xl overflow-hidden"
    style="--i: <?= $i ?>"
    data-search="<?= htmlspecialchars($searchData) ?>"
  >
    <!-- Top bar: time + currency + impact badge -->
    <div class="flex items-center justify-between px-4 pt-3.5 pb-2.5 border-b <?= $isHigh ? 'border-red-900/40' : 'border-amber-900/30' ?>">

      <!-- Time -->
      <div class="flex items-center gap-2.5">
        <div class="<?= $isHigh ? 'bg-red-950 border-red-800/60 text-red-300' : 'bg-amber-950 border-amber-800/50 text-amber-300' ?> border rounded-lg px-2.5 py-1 text-xs font-mono font-bold tracking-wider">
          <?= htmlspecialchars($timeStr) ?>
          <span class="text-[9px] font-normal opacity-60 ml-0.5">UTC</span>
        </div>
        <!-- Currency pill -->
        <span class="<?= $isHigh ? 'bg-red-500/15 text-red-400 ring-red-500/30' : 'bg-amber-500/15 text-amber-400 ring-amber-500/25' ?> ring-1 text-xs font-mono font-bold px-2 py-0.5 rounded-md">
          <?= htmlspecialchars($currency) ?>
        </span>
      </div>

      <!-- Impact badge -->
      <div class="flex items-center gap-1.5">
        <span class="<?= $isHigh ? 'bg-red-500 text-white' : 'bg-amber-500 text-slate-900' ?> text-[10px] font-mono font-bold px-2.5 py-1 rounded-lg tracking-wide uppercase">
          <?= htmlspecialchars($impact) ?>
        </span>
      </div>
    </div>

    <!-- Event name -->
    <div class="px-4 pt-3 pb-2">
      <p class="text-[14px] font-semibold text-slate-100 leading-snug"><?= htmlspecialchars($name) ?></p>
    </div>

    <!-- Forecast / Previous row (if available) -->
    <?php if ($forecast !== null || $previous !== null || $actual !== null): ?>
    <div class="flex gap-3 px-4 pb-2.5">
      <?php if ($actual !== null): ?>
      <div class="text-center">
        <div class="text-[9px] font-mono text-slate-600 uppercase tracking-widest">Actual</div>
        <div class="text-[12px] font-mono font-bold text-emerald-400"><?= htmlspecialchars($actual) ?></div>
      </div>
      <?php endif; ?>
      <?php if ($forecast !== null): ?>
      <div class="text-center">
        <div class="text-[9px] font-mono text-slate-600 uppercase tracking-widest">Forecast</div>
        <div class="text-[12px] font-mono font-semibold text-slate-300"><?= htmlspecialchars($forecast) ?></div>
      </div>
      <?php endif; ?>
      <?php if ($previous !== null): ?>
      <div class="text-center">
        <div class="text-[9px] font-mono text-slate-600 uppercase tracking-widest">Previous</div>
        <div class="text-[12px] font-mono text-slate-500"><?= htmlspecialchars($previous) ?></div>
      </div>
      <?php endif; ?>
    </div>
    <?php endif; ?>

    <!-- Action instruction box -->
    <div class="mx-3 mb-3 <?= $isHigh ? 'bg-red-950/70 border-red-800/50' : 'bg-amber-950/50 border-amber-800/40' ?> border rounded-xl px-3.5 py-2.5">
      <p class="text-[11px] font-mono font-bold leading-relaxed <?= $isHigh ? 'text-red-300' : 'text-amber-300' ?>">
        <?= $isHigh
          ? '🛑 RISK WINDOW ACTIVE: PAUSE AUTOMATED GRID &amp; SCALPING ENGINES'
          : '⚡ MONITOR LIQUIDITY: EXPAND SPREAD FILTERS'
        ?>
      </p>
    </div>

  </div><!-- /event-card -->

<?php endforeach; ?>
<!-- End of events -->

<?php endif; ?>

  <!-- No results placeholder (shown by JS) -->
  <div id="noResults" class="hidden text-center py-12">
    <div class="text-2xl mb-2">🔍</div>
    <div class="text-slate-500 text-sm font-mono">No events match your search.</div>
  </div>

</main>

<!-- ══════════════════════════════════════════
     FOOTER
══════════════════════════════════════════ -->
<footer class="text-center py-6 pb-safe">
  <div class="text-[10px] font-mono text-slate-700">
    Data · Financial Modeling Prep · <?= htmlspecialchars($today) ?><br>
    <?= $hasError ? '<span class="text-red-700">OFFLINE</span>' : '<span class="text-emerald-800">LIVE</span>' ?>
    · Refreshed <?= date('H:i:s') ?> UTC
  </div>
  <div class="mt-1 text-[9px] text-slate-800 font-mono">
    Not financial advice. For informational use only.
  </div>
</footer>

<!-- ══════════════════════════════════════════
     JAVASCRIPT
══════════════════════════════════════════ -->
<script>
// ── Live clock ────────────────────────────────────────────────
function updateClock() {
  const now = new Date();
  const hh = String(now.getUTCHours()).padStart(2,'0');
  const mm = String(now.getUTCMinutes()).padStart(2,'0');
  const ss = String(now.getUTCSeconds()).padStart(2,'0');
  document.getElementById('clock').textContent = `${hh}:${mm}:${ss} UTC`;
}
updateClock();
setInterval(updateClock, 1000);

// ── Search / Filter ───────────────────────────────────────────
const searchInput  = document.getElementById('searchInput');
const clearBtn     = document.getElementById('clearSearch');
const cards        = document.querySelectorAll('.event-card');
const noResults    = document.getElementById('noResults');

function filterCards(query) {
  const q = query.trim().toLowerCase();
  let visible = 0;
  cards.forEach(card => {
    const data = card.dataset.search || '';
    const show = !q || data.includes(q);
    card.classList.toggle('hidden-card', !show);
    if (show) visible++;
  });
  noResults.classList.toggle('hidden', visible > 0 || cards.length === 0);
  clearBtn.classList.toggle('hidden', !q);
}

searchInput.addEventListener('input', e => filterCards(e.target.value));
clearBtn.addEventListener('click', () => {
  searchInput.value = '';
  filterCards('');
  searchInput.focus();
});

// ── Pull-to-refresh hint (mobile) ─────────────────────────────
// Native browser handles this — no JS needed.
</script>

</body>
</html>
