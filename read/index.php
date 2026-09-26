<?php
// Standalone reading view. File paths are relative to the content directory.
$boot = null;
if (isset($_GET['file'])) {
    $filename = $_GET['file'];
    if (!is_string($filename) || !preg_match('/\.md$/i', $filename)
        || strpos($filename, '..') !== false || strpos($filename, '\\') !== false
        || strpos($filename, "\0") !== false) {
        http_response_code(400);
        exit('Invalid file parameter. Must be a .md file within content.');
    }

    $contentDir = realpath(__DIR__ . '/../content');
    $filePath = $contentDir === false ? false : realpath($contentDir . '/' . $filename);
    if ($filePath === false || !is_file($filePath)
        || strpos($filePath, $contentDir . DIRECTORY_SEPARATOR) !== 0) {
        http_response_code(404);
        exit('File not found.');
    }

    $content = file_get_contents($filePath);
    if ($content === false) {
        http_response_code(500);
        exit('Unable to read file.');
    }
    $boot = ['content' => $content, 'name' => $filename];
}
?>
<!DOCTYPE html>
<html lang="sv" data-theme="light">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<title>Läsfokus – Kunskapsbasen</title>
<link rel="icon" href="data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 32 32'%3E%3Crect width='32' height='32' rx='4' fill='%230077bc'/%3E%3Crect x='6' y='9' width='20' height='1.6' fill='%23fff' opacity='.6'/%3E%3Crect x='6' y='21.4' width='20' height='1.6' fill='%23fff' opacity='.6'/%3E%3Crect x='12.2' y='9' width='1.6' height='4' fill='%23ffd666'/%3E%3Crect x='12.2' y='19' width='1.6' height='4' fill='%23ffd666'/%3E%3C/svg%3E">
<style>
:root{
  --gs-blue:#0077bc;
  --radius:4px;
  --bg:#FFFFFE; --panel:#F4F9FC; --panel2:#F2F9F9; --hover:#EEF3F6;
  --text:#333333; --strong:#000000; --muted:#6E6E6E; --faint:#a3a3a3;
  --border:#979797; --line:#e3e8ea; --link:#005799; --rail:#c9d0d3;
  --orp:#d24723; --guide:#d24723;
  --success:#5a8b3b; --warning:#f2a900;
  --sec:#0077bc;
  --fs:64px; --fw:600; --fit:1; --shift:0px; --fx:42; --ls:0em;
  --gh:.42; --gg:.2; --gl:.35; --gt:2px; --gs:solid; --gop:.7; --ow:.6em;
  --reader-font:Arial, Helvetica, sans-serif;
  color-scheme:light;
}
[data-theme="dark"]{
  --bg:#1F1F1F; --panel:#141414; --panel2:#282828; --hover:#2a2a2a;
  --text:#FFFFFF; --strong:#FFFFFE; --muted:#E3E8E9; --faint:#7e8588;
  --border:#666666; --line:#303335; --link:#479EF5; --rail:#464c4f;
  --orp:#ff7b5c; --guide:#ff7b5c;
  color-scheme:dark;
}
[data-theme="dim"]{
  --bg:#E3E8EA; --panel:#D7DEE1; --panel2:#DCE3E6; --hover:#d6dde0;
  --text:#2A2A2A; --strong:#1A1A1A; --muted:#545B5F; --faint:#858d91;
  --border:#8a9296; --line:#cfd6d9; --link:#005799; --rail:#aab3b7;
  --orp:#b83a1a; --guide:#b83a1a;
}
[data-orp="blue"]{ --orp:var(--link); --guide:var(--link); }

*{box-sizing:border-box}
[hidden]{display:none !important}
html,body{height:100%}
body{
  margin:0; background:var(--bg); color:var(--text);
  font-family:'Goteborg', Arial, Helvetica, sans-serif; font-size:16px; line-height:1.5;
  display:grid; grid-template-rows:auto 1fr auto; grid-template-columns:minmax(0,1fr); height:100dvh; overflow:hidden;
  padding-top:env(safe-area-inset-top,0px); padding-bottom:env(safe-area-inset-bottom,0px);
}
button,select,input,textarea{font:inherit;color:inherit}
:focus-visible{outline:2px solid var(--link);outline-offset:2px}

/* ---------- Top bar ---------- */
.topbar{
  display:grid; grid-template-columns:1fr auto 1fr; align-items:center; gap:12px;
  padding:8px 14px; transition:opacity .5s;
}
.tb-left,.tb-right{display:flex;align-items:center;gap:4px;min-width:0}
.tb-right{justify-content:flex-end}
.brand{display:flex;align-items:center;gap:8px;font-weight:700;white-space:nowrap;margin-right:6px}
.docname{color:var(--muted);font-size:14px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;min-width:0}
.status{
  border:0;background:none;border-radius:var(--radius);padding:4px 8px;font-size:13px;color:var(--muted);
  cursor:pointer;white-space:nowrap;display:inline-flex;align-items:center;gap:6px;margin-left:6px
}
.status::before{content:"";width:7px;height:7px;border-radius:50%;background:var(--faint)}
.status[data-state="reading"]::before{background:var(--gs-blue)}
.status[data-state="paused"]::before{background:var(--warning)}
.status[data-state="done"]::before{background:var(--success)}
.status:hover{background:var(--hover);color:var(--text)}
.center-info{font-size:13px;color:var(--muted);white-space:nowrap;font-variant-numeric:tabular-nums}
.center-info b{color:var(--text);font-weight:600}
.icon-btn{
  width:34px;height:34px;display:inline-grid;place-items:center;flex:none;
  border:0;background:none;border-radius:var(--radius);cursor:pointer;padding:0;color:var(--muted)
}
.icon-btn:hover{background:var(--hover);color:var(--text)}
.icon-btn svg{width:18px;height:18px}

/* ---------- Structure line ---------- */
main{position:relative;display:grid;grid-template-rows:auto 1fr auto;grid-template-columns:minmax(0,1fr);min-height:0;overflow:hidden}
.struct{
  display:flex;align-items:center;justify-content:space-between;gap:16px;
  margin:0 14px;padding:4px 10px;border-left:3px solid var(--sec);
  font-size:13px;color:var(--muted);transition:opacity .5s, border-color .4s;min-width:0;
}
.st-path{flex:1;display:flex;align-items:center;gap:6px;min-width:0;overflow:hidden;white-space:nowrap}
.st-path span{overflow:hidden;text-overflow:ellipsis;flex-shrink:1;min-width:2ch}
.st-path span:last-child{color:var(--text);font-weight:600;flex-shrink:0;max-width:60%}
.st-path i{font-style:normal;color:var(--faint);flex:none}
.st-meta{display:flex;align-items:center;gap:12px;white-space:nowrap;font-variant-numeric:tabular-nums}
.st-dots{display:inline-flex;gap:4px;align-items:center}
.st-dots b{width:6px;height:6px;border-radius:50%;background:var(--line);display:block}
.st-dots b.read{background:var(--sec);opacity:.4}
.st-dots b.cur{background:var(--sec)}

/* ---------- Reader ---------- */
.struct{grid-row:1}.reader{grid-row:2}.context{grid-row:3}
body.no-doc .bottom,body.no-doc .status,body.no-doc #tocBtn{visibility:hidden}
.reader{
  position:relative; display:grid; place-items:center; min-height:0; min-width:0; cursor:pointer;
  user-select:none; -webkit-user-select:none; outline:none; overflow:hidden; touch-action:pan-y;
  grid-template-columns:minmax(0,1fr);
}
.anchor{position:absolute; inset:0; --fsz:min(var(--fs), 14vw); transform:translateX(var(--shift)); transition:transform .6s ease}
.word{
  position:absolute; left:0; right:0; top:50%;
  height:calc(var(--fsz) * 1.3); margin-top:calc(var(--fsz) * -0.65);
  font-family:var(--reader-font); font-weight:var(--fw); color:var(--strong);
  font-size:calc(var(--fsz) * var(--fit)); line-height:calc(var(--fsz) * 1.3);
  font-kerning:normal; text-rendering:optimizeLegibility; -webkit-font-smoothing:antialiased; font-variant-ligatures:none;
}
.wi{position:absolute; top:calc(var(--fsz) * -0.035); left:0; white-space:pre; letter-spacing:var(--ls)}
.word .o{color:var(--orp)}
body.no-orp .word .o{color:inherit}
body.orp-bold .word .o{font-weight:800}
.outline .word:not(.is-heading) .wi{color:transparent;-webkit-text-stroke:max(1.5px, .03em) var(--strong)}
.outline .word:not(.is-heading) .o{-webkit-text-stroke-color:var(--orp)}
body.no-orp.outline .word .o{-webkit-text-stroke-color:var(--strong)}
.word.is-hw .wi{color:var(--link)}
.word.is-heading{
  height:auto; margin:0; top:50%; transform:translateY(-50%); left:6%; right:6%;
  white-space:normal; text-align:center; line-height:1.2; color:var(--strong);
  font-size:calc(min(var(--fs) * .56, 8vw) * var(--fit));
}
.word .ht{text-wrap:balance;max-width:24ch;margin:0 auto}
.word .ht::before{content:"";display:block;width:32px;height:3px;border-radius:2px;background:var(--sec);margin:0 auto .55em}
.word .hm{font-family:'Goteborg', Arial, sans-serif;font-size:13px;font-weight:400;color:var(--muted);margin-top:16px;line-height:1.4}

/* guides */
.guides{position:absolute;inset:0;pointer-events:none;opacity:var(--gop);transition:opacity .25s}
.anchor.is-h .guides,.anchor.breathing .guides{opacity:0}
.guides i{position:absolute;display:none}
.tick{left:calc(var(--fx) * 1%);width:0;height:calc(var(--fsz) * var(--gl));border-left:var(--gt) var(--gs) var(--guide);transform:translateX(calc(var(--gt) / -2))}
.tick.top{bottom:calc(50% + var(--fsz) * (var(--gh) + var(--gg)))}
.tick.bot{top:calc(50% + var(--fsz) * (var(--gh) + var(--gg)))}
.rail{left:max(3%, calc(var(--fx) * 1% - var(--fsz) * 4.2));right:max(3%, calc(100% - var(--fx) * 1% - var(--fsz) * 6.8));height:0;border-top:var(--gt) var(--gs) var(--rail)}
.rail.top{bottom:calc(50% + var(--fsz) * (var(--gh) + var(--gg) + var(--gl)))}
.rail.bot{top:calc(50% + var(--fsz) * (var(--gh) + var(--gg) + var(--gl)))}
.dot{left:calc(var(--fx) * 1%);width:calc(var(--gt) * 3);height:calc(var(--gt) * 3);border-radius:50%;background:var(--guide);transform:translateX(-50%)}
.dot.top{bottom:calc(50% + var(--fsz) * (var(--gh) + var(--gg)))}
.dot.bot{top:calc(50% + var(--fsz) * (var(--gh) + var(--gg)))}
.obox{
  left:calc(var(--fx) * 1%);top:50%;width:calc(var(--ow) + var(--fsz) * .12);height:calc(var(--fsz) * 1.0);
  border:var(--gt) var(--gs) var(--guide);border-radius:3px;transform:translate(-50%, calc(-50% - var(--fsz) * .095))
}
.anchor.phrase .obox{display:none !important}
.guides[data-mode="ticks"] .tick,
.guides[data-mode="rails"] .tick, .guides[data-mode="rails"] .rail,
.guides[data-mode="railsplain"] .rail,
.guides[data-mode="dots"] .dot,
.guides[data-mode="box"] .obox,
.guides[data-mode="concept"] .obox, .guides[data-mode="concept"] .tick{display:block}
.guides[data-mode="concept"] .tick{border-left-style:dashed}

.kind{
  position:absolute;left:calc(var(--fx) * 1%);transform:translateX(-50%);white-space:nowrap;
  top:calc(50% - var(--fsz) * (var(--gh) + var(--gg) + var(--gl)) - 30px);
  font-size:12px;color:var(--muted);
}
.anchor.is-h .kind{display:none}
.tri{
  position:absolute;left:0;right:0;top:calc(50% + var(--fsz) * (var(--gh) + var(--gg) + var(--gl)) + 18px);
  display:grid;grid-template-columns:calc(var(--fx) * 1%) 1fr;
  font-family:var(--reader-font);font-size:calc(var(--fsz) * .3);color:var(--faint);white-space:nowrap;overflow:hidden;
}
.tri .prev{text-align:right;overflow:hidden;text-overflow:ellipsis;direction:rtl;padding-right:.9em}
.tri .prev span{direction:ltr;unicode-bidi:isolate}
.tri .next{text-align:left;opacity:.7;overflow:hidden;text-overflow:ellipsis;padding-left:.9em}
.anchor.is-h .tri{display:none}

/* rest break */
.breath{position:absolute;inset:0;display:grid;place-items:center;text-align:center;pointer-events:none;padding:20px}
.breath .bt{font-size:clamp(20px, calc(var(--fs) * .4), 34px);font-weight:600;color:var(--strong)}
.breath .bs{color:var(--muted);margin-top:4px;font-size:15px}
.breath .bbar{width:min(200px,50vw);height:3px;border-radius:2px;background:var(--line);margin:18px auto 10px;overflow:hidden}
.breath .bbar i{display:block;height:100%;background:var(--sec);transform-origin:left;animation:breathbar var(--bl, 3s) linear forwards}
.breath .bp{font-size:13px;color:var(--muted)}
@keyframes breathbar{from{transform:scaleX(1)}to{transform:scaleX(0)}}
.anchor.breathing .word,.anchor.breathing .kind,.anchor.breathing .tri{visibility:hidden}

/* guided */
.guided{position:absolute;inset:0;overflow:auto;padding:14vh 24px 30vh;cursor:default;scrollbar-width:thin}
.gcol{max-width:62ch;margin:0 auto;font-family:var(--reader-font);font-size:clamp(18px, calc(var(--fs) * .34), 30px);line-height:1.7;color:var(--text);letter-spacing:var(--ls)}
.gb{margin:0 0 1em;opacity:.32;transition:opacity .3s}
.gb.cur{opacity:1}
.gb.gh{font-weight:700;color:var(--strong);line-height:1.25;margin-top:1.3em}
.gb.gh1{font-size:1.4em}.gb.gh2{font-size:1.22em}.gb.gh3,.gb.gh4,.gb.gh5,.gb.gh6{font-size:1.06em}
.gb.gh::before{content:"";display:block;width:28px;height:3px;border-radius:2px;background:var(--sec);margin-bottom:.45em}
.gb.gli{padding-left:1.4em;position:relative;margin-bottom:.4em}
.gb.gli .gm{position:absolute;left:0;color:var(--sec);font-weight:700}
.gb.gq{border-left:3px solid var(--sec);padding-left:.9em;font-style:italic}
.gb span[data-u]{border-radius:3px;cursor:pointer;transition:background-color .09s, box-shadow .09s}
.gb span.on{background:color-mix(in srgb, var(--orp) 18%, transparent);box-shadow:0 0 0 .12em color-mix(in srgb, var(--orp) 18%, transparent);color:var(--strong)}
.gb span.done{color:var(--muted)}

/* empty state */
.empty{width:min(460px, calc(100% - 40px));text-align:left;cursor:default}
.empty h1{font-size:26px;line-height:1.25;margin:0 0 8px;color:var(--strong);font-weight:700}
.empty p{margin:0 0 22px;color:var(--muted)}
.empty .row{display:flex;gap:10px;flex-wrap:wrap}
.recent{margin-top:30px}
.recent h2{font-size:13px;font-weight:600;color:var(--muted);margin:0 0 6px}
.recent ul{list-style:none;margin:0;padding:0}
.recent li{display:flex;align-items:center;gap:4px}
.recent li button.open{flex:1;display:flex;justify-content:space-between;gap:12px;text-align:left;border:0;background:none;padding:8px 8px;border-radius:var(--radius);cursor:pointer}
.recent li button.open:hover{background:var(--hover)}
.recent li button.open span{overflow:hidden;text-overflow:ellipsis;white-space:nowrap}
.recent li small{color:var(--muted);white-space:nowrap;font-variant-numeric:tabular-nums}
.recent .rm{opacity:0}
.recent li:hover .rm,.recent .rm:focus-visible{opacity:1}

.context{
  max-width:70ch;margin:0 auto 12px;padding:10px 16px;max-height:28vh;overflow:auto;
  border-left:3px solid var(--sec);background:var(--panel2);border-radius:var(--radius);
  font-size:16px;line-height:1.6;color:var(--faint);width:calc(100% - 32px);
}
.context .cs{color:var(--text)}
.context .cu{cursor:pointer;border-radius:2px}
.context .cu:hover{background:var(--line)}
.context .cur{color:var(--strong);font-weight:700;text-decoration:underline;text-decoration-color:var(--orp);text-decoration-thickness:2px;text-underline-offset:4px}
.context .ch{font-weight:700;color:var(--strong)}

/* ---------- Bottom ---------- */
.bottom{padding:6px 16px 12px;display:grid;gap:8px;transition:opacity .5s}
.progress{position:relative;height:14px;cursor:pointer;touch-action:none;display:flex;align-items:center}
.progress .track{position:absolute;left:0;right:0;height:4px;border-radius:2px;background:var(--line);transition:height .15s}
.progress:hover .track,.progress:focus-visible .track{height:8px}
.progress .segs{position:absolute;inset:0;border-radius:inherit;overflow:hidden}
.progress .segs i{position:absolute;top:0;bottom:0;border-right:2px solid var(--bg);opacity:.25}
.progress .segs i.cur{opacity:.5}
.progress .fill{position:absolute;inset:0 auto 0 0;width:0;border-radius:inherit;background:var(--gs-blue);pointer-events:none}
.ptip{
  position:absolute;bottom:18px;transform:translateX(-50%);background:var(--strong);color:var(--bg);
  font-size:12px;padding:3px 8px;border-radius:var(--radius);white-space:nowrap;pointer-events:none;max-width:60vw;overflow:hidden;text-overflow:ellipsis
}
.controls{display:grid;grid-template-columns:1fr auto 1fr;align-items:center;gap:12px}
.ctl-left{display:flex;align-items:center;gap:10px;font-size:13px;color:var(--muted);font-variant-numeric:tabular-nums;min-width:0;white-space:nowrap}
.ctl-left b{color:var(--text);font-weight:600}
.ctl-mid{display:flex;align-items:center;gap:4px}
.ctl-right{display:flex;align-items:center;justify-content:flex-end;gap:10px}
.play{width:48px;height:48px;border:0;border-radius:50%;background:var(--gs-blue);color:#fff;display:inline-grid;place-items:center;cursor:pointer;margin:0 6px}
.play:hover{background:#006aa8}
.play svg{width:20px;height:20px}
.speed{display:inline-flex;align-items:center;border-radius:var(--radius)}
.speed button{border:0;background:none;width:30px;height:30px;cursor:pointer;font-size:17px;border-radius:var(--radius);color:var(--muted)}
.speed button:hover{background:var(--hover);color:var(--text)}
.speed output{min-width:70px;text-align:center;font-weight:600;font-variant-numeric:tabular-nums;font-size:14px}
.modes{display:inline-flex;background:var(--hover);border-radius:6px;padding:2px}
.modes button{border:0;background:none;height:28px;padding:0 11px;cursor:pointer;font-size:13px;border-radius:4px;color:var(--muted)}
.modes button[aria-pressed="true"]{background:var(--bg);color:var(--strong);font-weight:600;box-shadow:0 1px 2px rgba(0,0,0,.12)}
.actual{color:var(--muted)}

/* zen: controls fade away while reading */
body.zen .topbar, body.zen .bottom .controls, body.zen .struct{opacity:0}
body.zen .progress{opacity:.35}
body.zen{cursor:none}
body.zen .reader{cursor:none}

/* ---------- Buttons ---------- */
.btn{border:1px solid var(--line);background:var(--bg);border-radius:var(--radius);padding:8px 16px;cursor:pointer;font-weight:600}
.btn:hover{border-color:var(--border)}
.btn-primary{background:var(--gs-blue);border-color:var(--gs-blue);color:#fff}
.btn-primary:hover{background:#006aa8;border-color:#006aa8}
.btn-sm{padding:4px 10px;font-size:13px;font-weight:500}

/* ---------- Dialogs ---------- */
dialog{
  border:1px solid var(--line);border-radius:6px;background:var(--bg);color:var(--text);
  padding:0;width:min(560px, calc(100vw - 24px));max-height:calc(100dvh - 40px);box-shadow:0 12px 40px rgba(0,0,0,.18)
}
dialog[open]{display:flex;flex-direction:column}
dialog::backdrop{background:rgba(0,0,0,.35)}
dialog.drawer{margin:0 0 0 auto;height:100dvh;max-height:100dvh;width:min(400px, 100vw);border-width:0 0 0 1px;border-radius:0}
dialog.drawer::backdrop{background:rgba(0,0,0,.08)}
.dlg-head{display:flex;align-items:center;justify-content:space-between;padding:14px 18px 10px;flex:none}
.dlg-head h2{margin:0;font-size:18px}
.dlg-body{padding:4px 18px 18px;overflow:auto;flex:1}
.dlg-foot{display:flex;gap:10px;justify-content:flex-end;padding:12px 18px;border-top:1px solid var(--line);flex:none}
details.group{border-top:1px solid var(--line)}
details.group:first-of-type{border-top:0}
details.group summary{list-style:none;cursor:pointer;padding:12px 0;font-weight:700;color:var(--strong);display:flex;justify-content:space-between;align-items:center}
details.group summary::-webkit-details-marker{display:none}
details.group summary::after{content:"";width:8px;height:8px;border-right:2px solid var(--muted);border-bottom:2px solid var(--muted);transform:rotate(45deg);margin-right:4px;transition:transform .2s}
details.group[open] summary::after{transform:rotate(-135deg)}
details.group > div{padding-bottom:10px}
.set{display:grid;grid-template-columns:1fr auto;gap:2px 12px;align-items:center;padding:7px 0}
.set label{font-size:15px}
.set .help{grid-column:1/-1;font-size:13px;color:var(--muted)}
.set input[type=range]{grid-column:1/-1;width:100%;accent-color:var(--gs-blue)}
.set output{font-variant-numeric:tabular-nums;color:var(--muted);font-size:14px}
.set select{border:1px solid var(--line);border-radius:var(--radius);background:var(--bg);padding:4px 8px;max-width:200px}
.set input[type=checkbox]{width:18px;height:18px;accent-color:var(--gs-blue)}
.presets{display:flex;flex-wrap:wrap;gap:6px;margin:0 0 6px}
.presets button[aria-pressed="true"]{border-color:var(--gs-blue);color:var(--link);box-shadow:inset 0 0 0 1px var(--gs-blue)}
.note{font-size:13px;color:var(--muted);margin:4px 0 6px}
.drop{border:1.5px dashed var(--border);border-radius:6px;padding:22px;text-align:center;margin-bottom:16px}
.drop.over{border-color:var(--gs-blue);background:var(--panel)}
textarea{width:100%;min-height:140px;border:1px solid var(--line);border-radius:var(--radius);background:var(--bg);padding:10px;resize:vertical;font-family:ui-monospace,Consolas,monospace;font-size:13px}
.toc a{display:flex;gap:8px;align-items:baseline;padding:6px 8px;border-radius:var(--radius);color:var(--text);text-decoration:none;cursor:pointer;border-left:3px solid transparent}
.toc a:hover{background:var(--hover)}
.toc a.cur{border-left-color:var(--sec);background:var(--panel2);font-weight:600}
.toc a.read{color:var(--muted)}
.toc a span{flex:1}
.toc a small{color:var(--muted);font-variant-numeric:tabular-nums;white-space:nowrap;font-weight:400}
.keys{display:grid;grid-template-columns:auto 1fr;gap:6px 16px;font-size:14px;margin:0}
.keys dt{font-weight:600;white-space:nowrap}
.keys dd{margin:0;color:var(--muted)}
kbd{font-family:inherit;font-size:12px;border:1px solid var(--line);border-bottom-width:2px;border-radius:3px;padding:0 5px;background:var(--panel)}
.sum-grid{display:grid;grid-template-columns:repeat(3,1fr);gap:1px;background:var(--line);border:1px solid var(--line);border-radius:6px;overflow:hidden;margin-bottom:18px}
.sum-grid div{padding:10px 12px;background:var(--bg)}
.sum-grid b{display:block;font-size:22px;color:var(--strong);font-variant-numeric:tabular-nums}
.sum-grid span{font-size:12px;color:var(--muted)}
.rating{display:flex;gap:6px;margin:8px 0 18px;flex-wrap:wrap;align-items:center}
.rating button{min-width:40px}
.rating button[aria-pressed="true"]{background:var(--gs-blue);color:#fff;border-color:var(--gs-blue)}
table.hist{width:100%;border-collapse:collapse;font-size:13px;font-variant-numeric:tabular-nums}
table.hist th,table.hist td{text-align:left;padding:5px 6px;border-bottom:1px solid var(--line)}
table.hist th{color:var(--muted);font-weight:600}

.toast{
  position:fixed;left:50%;top:calc(58px + env(safe-area-inset-top,0px));transform:translateX(-50%);
  background:var(--strong);color:var(--bg);padding:8px 14px;border-radius:6px;
  font-size:13px;max-width:min(520px,calc(100vw - 32px));z-index:50;display:none
}
.toast.show{display:block}
.toast button{margin-left:10px;background:none;border:1px solid currentColor;color:inherit;border-radius:var(--radius);padding:1px 8px;cursor:pointer}
body.dragging::after{
  content:"Släpp en Markdown-fil för att läsa den";position:fixed;inset:12px;border:2px dashed var(--gs-blue);
  border-radius:8px;display:grid;place-items:center;font-size:20px;font-weight:600;color:var(--link);
  background:color-mix(in srgb, var(--bg) 90%, transparent);z-index:60;pointer-events:none
}

@media (max-width:720px){
  .topbar{grid-template-columns:1fr auto;padding:6px 8px}
  .center-info,.docname,.brand-name{display:none}
  .struct{margin:0 8px}
  .st-path span:not(:last-child),.st-path i,.st-dots{display:none}
  .st-path span:last-child{max-width:100%}
  .controls{grid-template-columns:1fr;justify-items:center;gap:8px}
  .ctl-left{order:3}
  .ctl-right{order:2}
  .sum-grid{grid-template-columns:1fr 1fr}
}
@media (prefers-reduced-motion: reduce){
  *{transition:none !important;animation:none !important}
}
.reduce-motion *{transition:none !important;animation:none !important}
@media (max-width:720px){
  .topbar{grid-template-columns:minmax(0,1fr);gap:6px}
  .tb-left .brand{display:none}
  .tb-right{justify-content:space-between}
  .icon-btn,.speed button,.modes button{min-width:44px;min-height:44px}
  .ctl-right{flex-wrap:wrap;justify-content:center}
}
</style>
</head>
<body class="no-doc">

<header class="topbar">
  <div class="tb-left">
    <span class="brand"><svg width="20" height="20" viewBox="0 0 32 32" aria-hidden="true"><rect width="32" height="32" rx="4" fill="#0077bc"/><rect x="6" y="9" width="20" height="1.6" fill="#fff" opacity=".6"/><rect x="6" y="21.4" width="20" height="1.6" fill="#fff" opacity=".6"/><rect x="12.2" y="9" width="1.6" height="4" fill="#ffd666"/><rect x="12.2" y="19" width="1.6" height="4" fill="#ffd666"/></svg><span class="brand-name">Läsfokus</span></span>
    <span id="docName" class="docname"></span>
    <button id="statusBtn" class="status" data-state="empty" title="Avsluta och visa resultat">Ingen text</button>
  </div>
  <div class="center-info" id="centerInfo"></div>
  <div class="tb-right">
    <button class="icon-btn" id="openBtn" title="Öppna (O)" aria-label="Öppna">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M3 7a2 2 0 0 1 2-2h4l2 2h8a2 2 0 0 1 2 2v8a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/></svg></button>
    <button class="icon-btn" id="tocBtn" title="Innehåll (C)" aria-label="Innehåll">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"><path d="M4 6h16M8 12h12M8 18h12"/></svg></button>
    <button class="icon-btn" id="setBtn" title="Inställningar (S)" aria-label="Inställningar">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"><path d="M4 6h10M18 6h2M4 12h4M12 12h8M4 18h12"/><circle cx="16" cy="6" r="2"/><circle cx="10" cy="12" r="2"/><circle cx="18" cy="18" r="2"/></svg></button>
    <button class="icon-btn" id="themeBtn" title="Ljust eller mörkt (T)" aria-label="Växla mörkt läge">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linejoin="round"><path d="M21 12.8A9 9 0 1 1 11.2 3a7 7 0 0 0 9.8 9.8z"/></svg></button>
    <button class="icon-btn" id="fsBtn" title="Helskärm (F)" aria-label="Helskärm">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M4 9V4h5M20 9V4h-5M4 15v5h5M20 15v5h-5"/></svg></button>
    <button class="icon-btn" id="helpBtn" title="Kortkommandon (?)" aria-label="Kortkommandon">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"><circle cx="12" cy="12" r="9"/><path d="M9.5 9.3a2.6 2.6 0 0 1 5 .9c0 1.7-2.5 2.2-2.5 3.8"/><circle cx="12" cy="17" r=".6" fill="currentColor"/></svg></button>
  </div>
</header>

<main>
  <div id="struct" class="struct" hidden>
    <div class="st-path" id="stPath"></div>
    <div class="st-meta"><span class="st-dots" id="stDots"></span><span id="stSec"></span></div>
  </div>
  <div id="reader" class="reader" tabindex="0" aria-label="Läsyta. Tryck eller använd mellanslag för att starta och pausa.">
    <div id="empty" class="empty">
      <h1>Läs Markdown i din egen takt</h1>
      <p>Läs ett ord eller en kort fras i taget, eller följ en markering genom texten. Kod, tabeller och diagram hoppas över och tempot anpassas efter textens svårighetsgrad.</p>
      <div class="row">
        <button class="btn btn-primary" id="emptyOpen">Öppna en .md-fil</button>
        <button class="btn" id="emptyDemo">Prova exempeltexten</button>
      </div>
      <div class="recent" id="recentWrap" hidden><h2>Senaste texter</h2><ul id="recentList"></ul></div>
    </div>
    <div id="anchor" class="anchor" hidden>
      <div class="guides" id="guides" data-mode="rails">
        <i class="rail top"></i><i class="rail bot"></i>
        <i class="tick top"></i><i class="tick bot"></i>
        <i class="dot top"></i><i class="dot bot"></i>
        <i class="obox"></i>
      </div>
      <div id="kind" class="kind"></div>
      <div id="word" class="word"></div>
      <div id="tri" class="tri" hidden><div class="prev"></div><div class="next"></div></div>
      <div id="breath" class="breath" hidden>
        <div><div class="bt">Kort paus</div><div class="bs">Blinka några gånger och låt ögonen vila.</div><div class="bbar"><i></i></div><div class="bp" id="breathPos"></div></div>
      </div>
    </div>
    <div id="guided" class="guided" hidden></div>
  </div>
  <div id="context" class="context" hidden></div>
</main>

<footer class="bottom">
  <div id="progress" class="progress" role="slider" tabindex="0" aria-label="Läsposition" aria-valuemin="0" aria-valuemax="100" aria-valuenow="0">
    <div class="track"><div class="segs" id="segs"></div><div class="fill" id="fill"></div></div>
    <div class="ptip" id="ptip" hidden></div>
  </div>
  <div class="controls">
    <div class="ctl-left" id="stats"><span><b id="stIdx">0</b> / <span id="stTotal">0</span> ord</span><span class="actual" id="stActual"></span></div>
    <div class="ctl-mid">
      <button class="icon-btn" id="prevS" title="Föregående mening (Shift+←)" aria-label="Föregående mening">
        <svg viewBox="0 0 24 24" fill="currentColor"><path d="M6 5h2v14H6zM20 5v14l-10-7z"/></svg></button>
      <button class="icon-btn" id="prevU" title="Bakåt (←)" aria-label="Bakåt">
        <svg viewBox="0 0 24 24" fill="currentColor"><path d="M17 5v14L6 12z"/></svg></button>
      <button class="play" id="playBtn" title="Starta eller pausa (mellanslag)" aria-label="Starta"></button>
      <button class="icon-btn" id="nextU" title="Framåt (→)" aria-label="Framåt">
        <svg viewBox="0 0 24 24" fill="currentColor"><path d="M7 5v14l11-7z"/></svg></button>
      <button class="icon-btn" id="nextS" title="Nästa mening (Shift+→)" aria-label="Nästa mening">
        <svg viewBox="0 0 24 24" fill="currentColor"><path d="M16 5h2v14h-2zM4 5v14l10-7z"/></svg></button>
    </div>
    <div class="ctl-right">
      <span class="speed" role="group" aria-label="Hastighet">
        <button id="slower" title="Långsammare (↓)" aria-label="Långsammare">−</button>
        <output id="speedOut">300 ord/min</output>
        <button id="faster" title="Snabbare (↑)" aria-label="Snabbare">+</button>
      </span>
      <span class="modes" id="modes" role="group" aria-label="Läsläge">
        <button data-mode="word" title="Ett ord i taget">Ord</button>
        <button data-mode="phrase" title="Korta fraser">Fras</button>
        <button data-mode="guided" title="Markering som följer texten">Följ texten</button>
      </span>
    </div>
  </div>
</footer>

<!-- Open -->
<dialog id="openDlg" aria-labelledby="openTitle">
  <div class="dlg-head"><h2 id="openTitle">Öppna text</h2><button class="icon-btn" data-close aria-label="Stäng">✕</button></div>
  <div class="dlg-body">
    <div class="drop" id="drop">
      <p style="margin:0 0 12px;color:var(--muted)">Släpp en .md-fil här, eller</p>
      <button class="btn btn-primary" id="pickFile">Välj fil</button>
      <input type="file" id="fileInput" accept=".md,.markdown,.mdown,.txt,text/markdown,text/plain" hidden>
    </div>
    <label for="pasteArea" style="font-weight:600;display:block;margin-bottom:6px">Eller klistra in Markdown</label>
    <textarea id="pasteArea" placeholder="# Rubrik&#10;&#10;Klistra in text här..."></textarea>
  </div>
  <div class="dlg-foot">
    <button class="btn" id="demoBtn">Exempeltext</button>
    <button class="btn btn-primary" id="pasteBtn">Läs inklistrad text</button>
  </div>
</dialog>

<!-- Contents -->
<dialog id="tocDlg" aria-labelledby="tocTitle">
  <div class="dlg-head"><h2 id="tocTitle">Innehåll</h2><button class="icon-btn" data-close aria-label="Stäng">✕</button></div>
  <div class="dlg-body"><nav class="toc" id="toc"></nav></div>
</dialog>

<!-- Shortcuts -->
<dialog id="helpDlg" aria-labelledby="helpTitle">
  <div class="dlg-head"><h2 id="helpTitle">Kortkommandon</h2><button class="icon-btn" data-close aria-label="Stäng">✕</button></div>
  <div class="dlg-body">
    <dl class="keys">
      <dt><kbd>Space</kbd></dt><dd>Starta eller pausa</dd>
      <dt><kbd>←</kbd> <kbd>→</kbd></dt><dd>Stega bakåt eller framåt</dd>
      <dt><kbd>Shift</kbd> + <kbd>←</kbd> <kbd>→</kbd></dt><dd>Föregående eller nästa mening</dd>
      <dt><kbd>PgUp</kbd> <kbd>PgDn</kbd></dt><dd>Föregående eller nästa avsnitt</dd>
      <dt><kbd>Home</kbd> <kbd>End</kbd></dt><dd>Textens början eller slut</dd>
      <dt><kbd>↑</kbd> <kbd>↓</kbd></dt><dd>Snabbare eller långsammare</dd>
      <dt><kbd>M</kbd></dt><dd>Byt läsläge</dd>
      <dt><kbd>G</kbd></dt><dd>Byt fokusmarkering</dd>
      <dt><kbd>K</kbd></dt><dd>Byt visning av sammanhang</dd>
      <dt><kbd>C</kbd></dt><dd>Innehåll</dd>
      <dt><kbd>O</kbd> <kbd>S</kbd></dt><dd>Öppna, inställningar</dd>
      <dt><kbd>T</kbd> <kbd>F</kbd></dt><dd>Tema, helskärm</dd>
      <dt><kbd>Esc</kbd></dt><dd>Stäng en panel</dd>
    </dl>
    <p class="note" style="margin-top:14px">On touch screens, tap to play or pause and swipe sideways to step. When paused, the mouse wheel steps through the text.</p>
  </div>
</dialog>

<!-- Settings -->
<dialog id="setDlg" class="drawer" aria-labelledby="setTitle">
  <div class="dlg-head"><h2 id="setTitle">Inställningar</h2><button class="icon-btn" data-close aria-label="Stäng">✕</button></div>
  <div class="dlg-body">
    <details class="group" open>
      <summary>Hastighet</summary>
      <div>
        <div class="set"><label for="s-wpm">Grundhastighet</label><output data-out="wpm"></output>
          <input type="range" id="s-wpm" data-set="wpm" min="100" max="1000" step="10"></div>
        <p class="note" id="effNote"></p>
        <div class="set"><label for="s-ramp">Öka hastigheten gradvis</label><output data-out="speedUp"></output>
          <input type="range" id="s-ramp" data-set="speedUp" min="0" max="30" step="5">
          <span class="help">Adds a few words per minute for every minute you read, up to 50 % above the base speed. Resets each session.</span></div>
        <div class="set"><label for="s-wpmStep">Steg med piltangenterna</label><output data-out="wpmStep"></output>
          <input type="range" id="s-wpmStep" data-set="wpmStep" min="5" max="100" step="5"></div>
      </div>
    </details>
    <details class="group" open>
      <summary>Läsläge</summary>
      <div>
        <div class="set"><label for="s-mode">Läge</label>
          <select id="s-mode" data-set="mode"><option value="word">Ord</option><option value="phrase">Fras</option><option value="guided">Följ texten</option></select>
          <span class="help">Word gives the most focus, phrase keeps more of the sentence's meaning, and guided keeps the page and its context.</span></div>
        <div class="set"><label for="s-phrase">Ord per fras</label><output data-out="phraseSize"></output>
          <input type="range" id="s-phrase" data-set="phraseSize" min="2" max="5" step="1"></div>
        <div class="set"><label for="s-gphrase">Markera hela fraser</label><input type="checkbox" id="s-gphrase" data-set="guidedPhrase"></div>
        <div class="set"><label for="s-resume">Fortsätt från meningens början</label><input type="checkbox" id="s-resume" data-set="resumeSentence">
          <span class="help">After a pause of a few seconds, reading picks up at the start of the sentence.</span></div>
      </div>
    </details>
    <details class="group" open>
      <summary>Fokuspunkt</summary>
      <div>
        <div class="presets" id="guidePresets" role="group" aria-label="Guides">
          <button class="btn btn-sm" data-g="rails">Rails</button>
          <button class="btn btn-sm" data-g="ticks">Ticks</button>
          <button class="btn btn-sm" data-g="concept">Box and ticks</button>
          <button class="btn btn-sm" data-g="railsplain">Plain rails</button>
          <button class="btn btn-sm" data-g="dots">Dots</button>
          <button class="btn btn-sm" data-g="box">Box</button>
          <button class="btn btn-sm" data-g="none">None</button>
        </div>
        <div class="set"><label for="s-fx">Position</label><output data-out="fx"></output>
          <input type="range" id="s-fx" data-set="fx" min="30" max="55" step="1">
          <span class="help">The highlighted letter of every word lands exactly here. Slightly left of centre balances long words best.</span></div>
        <div class="set"><label for="s-gg">Gap to text</label><output data-out="guideGap"></output>
          <input type="range" id="s-gg" data-set="guideGap" min="0" max="80" step="2"></div>
        <div class="set"><label for="s-gl">Tick length</label><output data-out="guideLen"></output>
          <input type="range" id="s-gl" data-set="guideLen" min="5" max="100" step="5"></div>
        <div class="set"><label for="s-gt">Thickness</label><output data-out="guideThick"></output>
          <input type="range" id="s-gt" data-set="guideThick" min="1" max="5" step="0.5"></div>
        <div class="set"><label for="s-gop">Strength</label><output data-out="guideOpacity"></output>
          <input type="range" id="s-gop" data-set="guideOpacity" min="15" max="100" step="5"></div>
        <div class="set"><label for="s-gs">Line style</label>
          <select id="s-gs" data-set="guideStyle"><option value="solid">Solid</option><option value="dashed">Dashed</option><option value="dotted">Dotted</option></select></div>
        <div class="set"><label for="s-orp">Colour the focus letter</label><input type="checkbox" id="s-orp" data-set="orp"></div>
        <div class="set"><label for="s-orpb">Bold focus letter</label><input type="checkbox" id="s-orpb" data-set="orpBold"></div>
        <div class="set"><label for="s-orpc">Highlight colour</label>
          <select id="s-orpc" data-set="orpColor"><option value="red">Red</option><option value="blue">Blue</option></select></div>
      </div>
    </details>
    <details class="group">
      <summary>Text</summary>
      <div>
        <div class="set"><label for="s-theme">Tema</label>
          <select id="s-theme" data-set="theme"><option value="system">Följ systemet</option><option value="light">Ljust</option><option value="dark">Mörkt</option><option value="dim">Dämpat</option></select></div>
        <div class="set"><label for="s-font">Typsnitt</label><select id="s-font" data-set="font"></select>
          <span class="help">No typeface is best for everyone. Try a few and keep the one that feels easiest.</span></div>
        <div class="set"><label for="s-size">Storlek</label><output data-out="fontSize"></output>
          <input type="range" id="s-size" data-set="fontSize" min="28" max="120" step="2"></div>
        <div class="set"><label for="s-weight">Teckenvikt</label><output data-out="fontWeight"></output>
          <input type="range" id="s-weight" data-set="fontWeight" min="300" max="800" step="100"></div>
        <div class="set"><label for="s-ls">Teckenavstånd</label><output data-out="letterSpacing"></output>
          <input type="range" id="s-ls" data-set="letterSpacing" min="-2" max="10" step="1"></div>
        <div class="set"><label for="s-style">Stil</label>
          <select id="s-style" data-set="textStyle"><option value="fill">Filled</option><option value="outline">Outline</option></select></div>
      </div>
    </details>
    <details class="group">
      <summary>Anpassat tempo</summary>
      <div>
        <div class="set"><label for="s-adaptive">Adapt to word difficulty</label><output data-out="adaptive"></output>
          <input type="range" id="s-adaptive" data-set="adaptive" min="0" max="100" step="5">
          <span class="help">Long compounds, numbers, abbreviations, names and rare words get more time. Short common words and repeated words go faster. Dense paragraphs (high LIX) slow down.</span></div>
        <div class="set"><label for="s-punct">Pauses at punctuation</label><output data-out="punctPause"></output>
          <input type="range" id="s-punct" data-set="punctPause" min="0" max="200" step="10"></div>
        <div class="set"><label for="s-para">Slow start in paragraphs</label><input type="checkbox" id="s-para" data-set="paraRamp"></div>
        <div class="set"><label for="s-rampWords">Words of slow start</label><output data-out="paraRampWords"></output>
          <input type="range" id="s-rampWords" data-set="paraRampWords" min="2" max="15" step="1"></div>
        <div class="set"><label for="s-rampStr">Slow start strength</label><output data-out="paraRampStrength"></output>
          <input type="range" id="s-rampStr" data-set="paraRampStrength" min="10" max="150" step="10"></div>
        <div class="set"><label for="s-warm">Warm up after pauses and jumps</label><input type="checkbox" id="s-warm" data-set="warmup"></div>
      </div>
    </details>
    <details class="group">
      <summary>Structure</summary>
      <div>
        <div class="set"><label for="s-hwhole">Show headings whole</label><input type="checkbox" id="s-hwhole" data-set="headingWhole"></div>
        <div class="set"><label for="s-htime">Heading display time</label><output data-out="headingTime"></output>
          <input type="range" id="s-htime" data-set="headingTime" min="50" max="250" step="10"></div>
        <div class="set"><label for="s-hcard">Section card under headings</label><input type="checkbox" id="s-hcard" data-set="sectionCard"></div>
        <div class="set"><label for="s-hstop">Pause at every heading</label><input type="checkbox" id="s-hstop" data-set="pauseAtHeading"></div>
        <div class="set"><label for="s-struct">Show where you are</label><input type="checkbox" id="s-struct" data-set="structBar">
          <span class="help">Heading path, section number and a dot for each paragraph in the section.</span></div>
        <div class="set"><label for="s-seccol">Colour-code sections</label><input type="checkbox" id="s-seccol" data-set="sectionColors"></div>
        <div class="set"><label for="s-kind">Label list items and quotes</label><input type="checkbox" id="s-kind" data-set="kindLabels"></div>
      </div>
    </details>
    <details class="group">
      <summary>Variety</summary>
      <div>
        <div class="set"><label for="s-breath">Kort paus</label><output data-out="breathEvery"></output>
          <input type="range" id="s-breath" data-set="breathEvery" min="0" max="10" step="1">
          <span class="help">Pauses for a few seconds at the next paragraph break so you can blink and rest your eyes.</span></div>
        <div class="set"><label for="s-breathLen">Break length</label><output data-out="breathLen"></output>
          <input type="range" id="s-breathLen" data-set="breathLen" min="2" max="8" step="1"></div>
        <div class="set"><label for="s-tvar">Rhythm variation</label><output data-out="tempoVar"></output>
          <input type="range" id="s-tvar" data-set="tempoVar" min="0" max="12" step="1">
          <span class="help">Each paragraph gets a slightly different pace so the rhythm never becomes flat.</span></div>
        <div class="set"><label for="s-auto">Switch mode automatically</label>
          <select id="s-auto" data-set="autoSwitch"><option value="0">Off</option><option value="60">Every minute</option><option value="90">Every 90 seconds</option><option value="120">Every 2 minutes</option><option value="180">Every 3 minutes</option></select>
          <span class="help">Alternates between your mode and guided highlight at paragraph breaks.</span></div>
        <div class="set"><label for="s-shift">Nudge the focus point between sentences</label><input type="checkbox" id="s-shift" data-set="anchorShift"></div>
      </div>
    </details>
    <details class="group">
      <summary>Sammanhang och fokus</summary>
      <div>
        <div class="set"><label for="s-ctx">Visa sammanhang</label>
          <select id="s-ctx" data-set="context"><option value="off">Aldrig</option><option value="pause">Vid paus</option><option value="always">Aktuell mening</option><option value="tri">Föregående och nästa fras</option></select>
          <span class="help">When paused, the whole paragraph is shown. Click a word to jump to it.</span></div>
        <div class="set"><label for="s-zen">Dölj kontroller under läsning</label><input type="checkbox" id="s-zen" data-set="zen">
          <span class="help">Move the mouse to bring them back.</span></div>
        <div class="set"><label for="s-rm">Minska rörelser</label><input type="checkbox" id="s-rm" data-set="reduceMotion"></div>
      </div>
    </details>
  </div>
  <div class="dlg-foot">
    <button class="btn" id="resetSet">Återställ inställningar</button>
    <button class="btn btn-primary" data-close>Klart</button>
  </div>
</dialog>

<!-- Results -->
<dialog id="sumDlg" aria-labelledby="sumTitle">
  <div class="dlg-head"><h2 id="sumTitle">Läspasset är klart</h2><button class="icon-btn" data-close aria-label="Stäng">✕</button></div>
  <div class="dlg-body">
    <div class="sum-grid" id="sumGrid"></div>
    <div style="font-weight:600">Hur väl förstod du texten?</div>
    <div class="rating" id="rating" role="group" aria-label="Comprehension from 1 to 5">
      <button class="btn" data-r="1">1</button><button class="btn" data-r="2">2</button><button class="btn" data-r="3">3</button><button class="btn" data-r="4">4</button><button class="btn" data-r="5">5</button>
      <span style="color:var(--muted);font-size:13px;margin-left:4px">1 = nästan ingenting, 5 = alla viktiga delar</span>
    </div>
    <div id="histWrap"></div>
  </div>
  <div class="dlg-foot">
    <button class="btn" id="restartBtn">Läs igen</button>
    <button class="btn btn-primary" data-close>Stäng</button>
  </div>
</dialog>

<div id="toast" class="toast" role="status" aria-live="polite"></div>

<script type="text/markdown" id="demoText">
---
title: Sample
---
# Welcome to Readpace

Readpace shows text one piece at a time, in the same place on the screen. Your eyes no longer travel across lines, so your attention can stay on the meaning.

## How the pace works

Every word gets its own display time. Long words such as responsibilities or internationalisation get a little more time, while short common words like and, the and of go faster. Numbers like 2026 or 3.5 percent slow down too, as do abbreviations such as e.g. and NASA.

A comma gives a short pause. The end of a sentence gives a longer one. The longest pause comes between paragraphs, so you have time to take in what you just read.

## Three ways to read

Word mode shows one word at a time, with the focus letter always in exactly the same place. Phrase mode shows short groups of words. Guided mode shows the running text and moves a highlight through it.

- Space plays and pauses.
- The left and right arrows step through the text, and with Shift a whole sentence.
- The up and down arrows change the speed, and M switches mode.

> The thin line at the top shows the heading path, which section you are in and a dot for each paragraph.

| This | is not shown |
|---|---|
| Tables | are skipped |

```mermaid
flowchart LR
  A[Code and diagrams] --> B[are skipped too]
```

## Read for understanding

The goal is not the highest possible speed. Find your fastest pace that still keeps comprehension, and slow down when the text gets hard. When you finish, you can rate how well you understood the text, so over time you can see how speed and understanding relate.
</script>

<script>
(() => {
'use strict';

/* =========================================================
   Helpers
   ========================================================= */
const $ = (s, r = document) => r.querySelector(s);
const $$ = (s, r = document) => [...r.querySelectorAll(s)];
const clamp = (v, a, b) => Math.min(b, Math.max(a, v));
const esc = s => String(s).replace(/[&<>"]/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;' }[c]));
const nf = n => Math.round(n).toLocaleString('sv-SE');
const store = {
  get(k, d) { try { const v = localStorage.getItem(k); return v == null ? d : JSON.parse(v); } catch { return d; } },
  set(k, v) { try { localStorage.setItem(k, JSON.stringify(v)); return true; } catch { return false; /* storage unavailable */ } },
  del(k) { try { localStorage.removeItem(k); } catch { /* ignore */ } }
};
function fmtTime(ms) {
  const s = Math.max(0, Math.round(ms / 1000));
  const h = Math.floor(s / 3600), m = Math.floor((s % 3600) / 60), r = s % 60;
  return h ? `${h}:${String(m).padStart(2, '0')}:${String(r).padStart(2, '0')}` : `${m}:${String(r).padStart(2, '0')}`;
}
function hash(str) { let h = 5381; for (let i = 0; i < str.length; i++) h = ((h << 5) + h + str.charCodeAt(i)) | 0; return (h >>> 0).toString(36); }

/* =========================================================
   Markdown → text blocks
   Keeps only headings, paragraphs, list items and quotes.
   ========================================================= */
const ENT = { amp: '&', lt: '<', gt: '>', quot: '"', apos: "'", nbsp: ' ', ndash: '–', mdash: '—', hellip: '…', laquo: '«', raquo: '»', rsquo: '’', lsquo: '‘', rdquo: '”', ldquo: '“' };
function decodeEntities(s) {
  return s.replace(/&(#x?[0-9a-f]+|[a-z]+);/gi, (m, e) => {
    if (e[0] === '#') { const n = e[1].toLowerCase() === 'x' ? parseInt(e.slice(2), 16) : parseInt(e.slice(1), 10); return isNaN(n) ? m : String.fromCodePoint(n); }
    return ENT[e.toLowerCase()] ?? m;
  });
}
function cleanInline(s) {
  s = s
    .replace(/!\[[^\]]*\]\([^)]*\)/g, ' ')            // images
    .replace(/!\[[^\]]*\]\[[^\]]*\]/g, ' ')
    .replace(/\[\^[^\]]+\]/g, '')                      // footnote refs
    .replace(/\[([^\]]+)\]\([^)]*\)/g, '$1')           // links → link text
    .replace(/\[([^\]]+)\]\[[^\]]*\]/g, '$1')
    .replace(/\[\[([^\]|]+)\|([^\]]+)\]\]/g, '$2')     // wiki links
    .replace(/\[\[([^\]]+)\]\]/g, '$1')
    .replace(/<(https?:\/\/[^>\s]+)>/g, '$1')
    .replace(/<[^>]+>/g, ' ')                          // html tags
    .replace(/`+([^`]*)`+/g, '$1')                     // inline code → text
    .replace(/\\\((.+?)\\\)/g, ' ')                    // inline math
    .replace(/\$\$[^$]*\$\$/g, ' ')
    .replace(/(^|[\s(])\$[^$\s][^$]*?\$(?=[\s.,;:!?)]|$)/g, '$1')
    .replace(/~~|==/g, '')
    .replace(/(\*\*|__)(?=\S)([\s\S]*?\S)\1/g, '$2')
    .replace(/(^|[\s(“"'«])[*_]+(?=\S)/g, '$1')
    .replace(/(\S)[*_]+(?=[\s).,;:!?”"'»]|$)/g, '$1')
    .replace(/\\([\\`*_{}\[\]()#+\-.!|>~$])/g, '$1');
  return decodeEntities(s).replace(/\s+/g, ' ').trim();
}
function parseMarkdown(src) {
  const skipped = { code: 0, table: 0, math: 0, image: 0 };
  src = src.replace(/^\uFEFF/, '').replace(/\r\n?/g, '\n');
  src = src.replace(/^\s*---\n[\s\S]*?\n(?:---|\.\.\.)[ \t]*(?:\n|$)/, '');
  src = src.replace(/<!--[\s\S]*?-->/g, '');
  skipped.image = (src.match(/!\[[^\]]*\]\(/g) || []).length;
  const lines = src.split('\n');
  const blocks = [];
  let para = [], paraType = 'p', fence = null, math = null, inTable = false;
  const sepRe = /^\s*\|?\s*:?-{2,}:?\s*(\|\s*:?-{2,}:?\s*)*\|?\s*$/;
  const flush = () => {
    if (para.length) { const t = cleanInline(para.join(' ')); if (/[\p{L}\p{N}]/u.test(t)) blocks.push({ type: paraType, text: t }); }
    para = []; paraType = 'p';
  };
  for (let i = 0; i < lines.length; i++) {
    const raw = lines[i];
    let t = raw.trim();
    if (fence) { if (t.startsWith(fence)) fence = null; continue; }
    if (math) { if (t.includes(math)) math = null; continue; }
    const fm = t.match(/^(`{3,}|~{3,})/);
    if (fm) { flush(); fence = fm[1]; skipped.code++; continue; }
    if (t.startsWith('$$')) { flush(); skipped.math++; if (!(t.length > 2 && t.endsWith('$$'))) math = '$$'; continue; }
    if (t.startsWith('\\[')) { flush(); skipped.math++; if (!t.endsWith('\\]')) math = '\\]'; continue; }
    if (!t) { flush(); inTable = false; continue; }
    // tables
    if (inTable && t.includes('|')) continue;
    if (sepRe.test(t) && t.includes('-') && (t.includes('|') || para.length === 0)) { if (t.includes('|')) { flush(); inTable = true; continue; } }
    if ((t.startsWith('|') || t.includes('|')) && i + 1 < lines.length && sepRe.test(lines[i + 1]) && lines[i + 1].includes('|')) {
      flush(); inTable = true; skipped.table++; i++; continue;
    }
    if (t.startsWith('|') && t.endsWith('|')) { flush(); continue; }
    // setext headings
    if (/^=+$/.test(t) && para.length && paraType === 'p') { blocks.push({ type: 'h', level: 1, text: cleanInline(para.join(' ')) }); para = []; continue; }
    if (/^-+$/.test(t) && para.length && paraType === 'p') { blocks.push({ type: 'h', level: 2, text: cleanInline(para.join(' ')) }); para = []; continue; }
    // horizontal rule
    if (/^([-*_])(\s*\1){2,}$/.test(t)) { flush(); continue; }
    // indented code (four spaces after a blank line, outside lists)
    if (/^( {4}|\t)/.test(raw) && para.length === 0 && (i === 0 || !lines[i - 1].trim()) && !/^\s*([-*+]|\d+[.)])\s/.test(raw)) {
      const prevList = blocks.length && /^(li|ol)$/.test(blocks[blocks.length - 1].type);
      if (!prevList) { skipped.code++; while (i + 1 < lines.length && (/^( {4}|\t)/.test(lines[i + 1]) || !lines[i + 1].trim())) i++; continue; }
    }
    // heading
    const hm = t.match(/^(#{1,6})\s+(.*?)\s*#*\s*$/);
    if (hm) { flush(); const tx = cleanInline(hm[2]); if (tx) blocks.push({ type: 'h', level: hm[1].length, text: tx }); continue; }
    // link definitions, footnotes, directives
    if (/^\[[^\]]+\]:\s*\S+/.test(t)) continue;
    if (/^(:::|!!!|\{%|%\})/.test(t)) continue;
    if (/^<\/?[a-zA-Z][^>]*>\s*$/.test(t)) { flush(); continue; }
    // blockquote
    let quote = false;
    if (t.startsWith('>')) {
      quote = true;
      t = t.replace(/^(>\s?)+/, '').trim();
      if (!t) { flush(); continue; }
      if (/^\[!\w+\][+-]?/.test(t)) { t = t.replace(/^\[!\w+\][+-]?\s*/, ''); if (!t) continue; flush(); }
      const qh = t.match(/^(#{1,6})\s+(.*)$/);
      if (qh) { flush(); blocks.push({ type: 'h', level: qh[1].length, text: cleanInline(qh[2]) }); continue; }
    }
    // list item
    const lm = t.match(/^([-*+•]|\d{1,9}[.)])\s+(\[[ xX]\]\s+)?(.*)$/);
    if (lm) { flush(); para = [lm[3]]; paraType = /\d/.test(lm[1]) ? 'ol' : 'li'; continue; }
    if (quote && para.length === 0) paraType = 'q';
    para.push(t);
  }
  flush();
  return { blocks, skipped };
}

/* =========================================================
   Words, sentences and difficulty
   ========================================================= */
const ABBR = new Set(['t.ex.', 'bl.a.', 'dvs.', 'd.v.s.', 's.k.', 'm.fl.', 'm.m.', 'mm.', 'osv.', 'o.s.v.', 'ca.', 'c:a', 'nr.', 'kl.', 'jfr.', 'resp.', 'fr.o.m.', 't.o.m.', 'p.g.a.', 'pga.', 'e.g.', 'i.e.', 'etc.', 'vs.', 'dr.', 'st.', 'ex.', 'kap.', 'sid.', 'f.d.', 'inkl.', 'exkl.', 'prof.', 'ang.', 'bil.', 'tel.', 'o.d.', 'm.a.o.', 'ev.', 'bl.', 'eng.', 'sv.', 'jan.', 'feb.', 'aug.', 'sep.', 'sept.', 'okt.', 'nov.', 'dec.', 'mr.', 'mrs.', 'ms.', 'jr.', 'sr.', 'inc.', 'ltd.', 'co.', 'corp.', 'fig.', 'vol.', 'approx.', 'dept.', 'u.s.', 'u.k.', 'a.m.', 'p.m.', 'cf.', 'al.', 'et al.', 'jan.', 'mar.', 'apr.', 'jun.', 'jul.']);
const COMMON = new Set(('och i att det som en på är av för med till den har de inte om ett han men var jag sig från vi så kan man när år hon under också efter eller nu sin där vid mot ska skulle kommer ut får finns vara hade alla andra mycket än här då sedan över bara in blir upp även vad få två vill ha många hur mer går detta nya hans utan sina något dessa dem denna vilket vilka varför bli kan inom samt eftersom mellan genom hela både man min mitt mina din ditt dina oss er deras dess ju väl nog redan alltid aldrig själv varje sådan sådana olika the and of to a in is it that for on as with be are this was by or an at from not you your can will i he she we they his her its our their them us me my has have had do does did so if but then than there here what which who when where how all any some no more most very just also only into over about out up one two new would could should may might must been being were these those such each other both').split(' '));
const VOWELS = /[aeiouyåäöéèüáà]+/gi;
function isAbbr(raw) {
  const r = raw.toLowerCase().replace(/[,;:)»”"']+$/, '');
  return ABBR.has(r) || /^(\p{L}\.){2,}$/u.test(r);
}
function mkWord(raw, next, sentStart) {
  const core = raw.replace(/^[(\["'«»“”‘’]+/, '').replace(/[)\]"'«»“”‘’.,;:!?…–—\-\s]+$/, '') || raw;
  const lower = core.toLowerCase();
  const letters = (core.match(/[\p{L}\p{N}]/gu) || []).length;
  const abbr = isAbbr(raw);
  let special = null;
  if (/^(https?:\/\/|www\.)/i.test(core)) special = 'url';
  else if (/^\S+@\S+\.\S+$/.test(core)) special = 'mail';
  else if (/\d/.test(core)) special = 'num';
  else if (abbr || (/^[\p{Lu}\d]{2,}$/u.test(core) && letters <= 6)) special = 'abbr';
  const pm = raw.match(/([.,;:!?…–—]+)[)\]"'»”’]*$/);
  let punct = pm ? pm[1].slice(-1) : '';
  if (abbr && punct === '.') punct = '';
  const endS = /[.!?…]/.test(punct) && (!next || /^[("'«“‘\[]*[\p{Lu}\d]/u.test(next));
  return {
    raw, lower, len: letters, special, punct, endS, sentStart,
    cap: /^[\p{Lu}]/u.test(core) && !sentStart && special !== 'abbr',
    hyphen: /\p{L}-\p{L}/u.test(core),
    syl: (lower.match(VOWELS) || []).length,
    common: COMMON.has(lower)
  };
}
function buildDoc(blocks) {
  const occ = new Map();
  const out = [];
  let g = 0;
  for (const b of blocks) {
    const toks = b.text.split(/\s+/).filter(Boolean);
    const merged = [];
    for (const t of toks) {
      if (!/[\p{L}\p{N}]/u.test(t) && merged.length) merged[merged.length - 1] += ' ' + t;
      else merged.push(t);
    }
    if (!merged.length) continue;
    const words = [];
    let sentStart = true;
    merged.forEach((raw, i) => {
      const w = mkWord(raw, merged[i + 1], sentStart);
      w.g = g++; w.i = i;
      const n = occ.get(w.lower) || 0; w.occ = n; occ.set(w.lower, n + 1);
      words.push(w);
      sentStart = w.endS;
    });
    words[words.length - 1].endS = true;
    // LIX readability = words per sentence + percentage of long words (>6 letters)
    const sents = Math.max(1, words.filter(w => w.endS).length);
    const long = words.filter(w => w.len > 6).length;
    const lix = words.length / sents + (100 * long) / words.length;
    out.push({ type: b.type, level: b.level || 0, text: b.text, words, lix });
  }
  // number lists: consecutive list items form one list
  for (let k = 0; k < out.length;) {
    if (out[k].type === 'li' || out[k].type === 'ol') {
      let e = k; while (e < out.length && (out[e].type === 'li' || out[e].type === 'ol')) e++;
      for (let j = k; j < e; j++) { out[j].li = j - k + 1; out[j].ln = e - k; }
      k = e;
    } else k++;
  }
  return { blocks: out, total: g };
}

/* Time factor for a word. a = adaptation strength (0–1). */
function wordFactor(w, a) {
  const L = w.len;
  let f = L <= 3 ? 0.85 : L <= 6 ? 1 : Math.min(1.58, 1 + (L - 6) * 0.068);
  if (w.common) f *= 0.9;
  if (w.special === 'num') f *= 1.35;
  else if (w.special === 'url' || w.special === 'mail') f *= 1.7;
  else if (w.special === 'abbr') f *= 1.25;
  if (w.cap) f *= 1.1;                               // likely proper noun
  if (w.hyphen) f *= 1.08;
  if (w.syl >= 4) f *= 1 + Math.min(0.25, (w.syl - 3) * 0.06);
  if (!w.common && L >= 7) {                         // new vs. familiar word in this text
    if (w.occ === 0) f *= 1.1;
    else if (w.occ >= 2) f *= 0.93;
  }
  f = clamp(f, 0.75, 2.2);
  return 1 + a * (f - 1);
}


/* =========================================================
   State
   ========================================================= */
const DEFAULTS = {
  wpm: 300, wpmStep: 20, speedUp: 0,
  mode: 'word', phraseSize: 3, guidedPhrase: true, resumeSentence: true,
  adaptive: 70, punctPause: 100,
  paraRamp: true, paraRampWords: 6, paraRampStrength: 50, warmup: true,
  headingWhole: true, headingTime: 100, pauseAtHeading: false, sectionCard: true,
  structBar: true, sectionColors: true, kindLabels: true,
  breathEvery: 3, breathLen: 3, tempoVar: 4, autoSwitch: 0, anchorShift: false,
  fx: 42, guideMode: 'rails', guideGap: 20, guideLen: 35, guideThick: 2, guideOpacity: 70, guideStyle: 'solid',
  orp: true, orpBold: false, orpColor: 'red',
  theme: 'system', font: 'arial', fontSize: 64, fontWeight: 600, letterSpacing: 0, textStyle: 'fill',
  context: 'pause', zen: true, reduceMotion: false
};
const FONTS = {
  arial: { label: 'Arial (default)', css: 'Arial, Helvetica, sans-serif' },
  atkinson: { label: 'Atkinson Hyperlegible', css: "'Atkinson Hyperlegible', Arial, sans-serif", g: 'Atkinson+Hyperlegible:wght@400;700' },
  lexend: { label: 'Lexend', css: "'Lexend', Arial, sans-serif", g: 'Lexend:wght@300..800' },
  inter: { label: 'Inter', css: "'Inter', Arial, sans-serif", g: 'Inter:wght@300..800' },
  source: { label: 'Source Sans 3', css: "'Source Sans 3', Arial, sans-serif", g: 'Source+Sans+3:wght@300..800' },
  noto: { label: 'Noto Sans', css: "'Noto Sans', Arial, sans-serif", g: 'Noto+Sans:wght@300..800' },
  roboto: { label: 'Roboto Flex', css: "'Roboto Flex', Arial, sans-serif", g: 'Roboto+Flex:wght@300..800' },
  verdana: { label: 'Verdana', css: 'Verdana, Geneva, sans-serif' },
  georgia: { label: 'Georgia (serif)', css: "Georgia, 'Times New Roman', serif" }
};
const SEC_COLORS = ['#0077bc', '#008391', '#5a8b3b', '#674b99', '#d53878', '#3f5564', '#d24723'];
const MODE_NAME = { word: 'Ord', phrase: 'Fras', guided: 'Följ texten' };
const GUIDE_NAME = { rails: 'Rails', ticks: 'Ticks', concept: 'Box and ticks', railsplain: 'Plain rails', dots: 'Dots', box: 'Box', none: 'No guides' };
const KEY = { settings: 'readpace:settings', history: 'readpace:history', recent: 'readpace:recent', pos: 'readpace:pos:' };

let S = Object.assign({}, DEFAULTS, store.get(KEY.settings, {}));

let doc = null, docName = '', docKey = '';
let units = [], durs = [], suffix = new Float64Array(1), headings = [], sections = [], blockUnits = [];
let idx = 0, playing = false, timer = null, nextAt = 0, warmLeft = 0;
let session = null, lastSid = -1, viewMode = S.mode, modeSince = 0, lastBreath = 0, breathing = false, pausedAt = 0;
let gBlock = -1;

const el = {
  body: document.body, status: $('#statusBtn'), docName: $('#docName'), center: $('#centerInfo'),
  reader: $('#reader'), empty: $('#empty'), anchor: $('#anchor'), guides: $('#guides'), word: $('#word'), kind: $('#kind'), tri: $('#tri'),
  breath: $('#breath'), guided: $('#guided'), struct: $('#struct'), stPath: $('#stPath'), stDots: $('#stDots'), stSec: $('#stSec'),
  ctx: $('#context'), play: $('#playBtn'), speed: $('#speedOut'), fill: $('#fill'), progress: $('#progress'), segs: $('#segs'), ptip: $('#ptip'),
  stIdx: $('#stIdx'), stTotal: $('#stTotal'), stActual: $('#stActual'), toast: $('#toast')
};
const ICON_PLAY = '<svg viewBox="0 0 24 24" fill="currentColor"><path d="M8 5v14l11-7z"/></svg>';
const ICON_PAUSE = '<svg viewBox="0 0 24 24" fill="currentColor"><path d="M7 5h3.5v14H7zM13.5 5H17v14h-3.5z"/></svg>';
const motionOff = () => S.reduceMotion || matchMedia('(prefers-reduced-motion: reduce)').matches;

/* =========================================================
   Segments and timing
   ========================================================= */
function chunkSize() {
  if (viewMode === 'phrase') return S.phraseSize;
  if (viewMode === 'guided') return S.guidedPhrase ? S.phraseSize : 1;
  return 1;
}
function makeUnits() {
  const out = []; let sid = 0;
  const N = chunkSize();
  headings = []; blockUnits = [];
  doc.blocks.forEach((b, bi) => {
    const first = out.length;
    if (b.type === 'h' && S.headingWhole) {
      headings.push({ u: out.length, level: b.level, text: b.text });
      out.push({ kind: 'h', level: b.level, words: b.words, text: b.words.map(w => w.raw).join(' '), block: bi, sid: sid++, pos: 0, endS: true, endB: true, punct: '', wStart: b.words[0].g, lix: 40 });
      blockUnits[bi] = [first, out.length - 1];
      return;
    }
    if (b.type === 'h') headings.push({ u: out.length, level: b.level, text: b.text });
    let cur = [];
    const push = () => {
      if (!cur.length) return;
      const last = cur[cur.length - 1];
      out.push({ kind: b.type === 'h' ? 'hw' : 't', level: b.level, words: cur, text: cur.map(w => w.raw).join(' '), block: bi, sid, pos: cur[0].i, endS: last.endS, endB: false, punct: last.punct, wStart: cur[0].g, lix: b.lix });
      if (last.endS) sid++;
      cur = [];
    };
    b.words.forEach(w => {
      if (w.special && cur.length && N > 1) push();
      cur.push(w);
      const chars = cur.reduce((s, x) => s + x.raw.length + 1, 0);
      if (cur.length >= N || w.punct || w.endS || (w.special && N > 1) || (N > 1 && chars >= 24)) push();
    });
    push();
    out[out.length - 1].endB = true;
    blockUnits[bi] = [first, out.length - 1];
  });
  units = out;
  buildSections();
}
function buildSections() {
  const stack = []; let ci = -1;
  sections = headings.map((h, k) => {
    while (stack.length && stack[stack.length - 1].level >= h.level) stack.pop();
    if (h.level <= 2 || ci < 0) ci++;
    const s = { ...h, k, path: [...stack.map(x => x.text), h.text], color: SEC_COLORS[ci % SEC_COLORS.length] };
    stack.push(s);
    return s;
  });
  sections.forEach((s, k) => {
    s.end = k + 1 < sections.length ? sections[k + 1].u : units.length;
    const wEnd = s.end < units.length ? units[s.end].wStart : doc.total;
    s.words = wEnd - units[s.u].wStart;
  });
}
function sectionAt(i) {
  let lo = 0, hi = sections.length - 1, r = null;
  while (lo <= hi) { const m = (lo + hi) >> 1; if (sections[m].u <= i) { r = sections[m]; lo = m + 1; } else hi = m - 1; }
  return r;
}
const blockVar = b => { const x = Math.sin((b + 1) * 12.9898) * 43758.5453; return (x - Math.floor(x)) * 2 - 1; };
function computeTiming() {
  const base = 60000 / S.wpm, a = S.adaptive / 100, p = S.punctPause / 100;
  durs = units.map(u => {
    let d;
    if (u.kind === 'h') {
      d = base * u.words.reduce((s, w) => s + Math.max(1, wordFactor(w, a)), 0) * 1.1 * (S.headingTime / 100) + 250;
      d = Math.max(d, 800 * S.headingTime / 100);
      if (S.sectionCard) d += 350;
      return d + base * 1.5 * p;
    }
    d = base * u.words.reduce((s, w) => s + wordFactor(w, a), 0);
    const lm = clamp(1 + (u.lix - 40) * 0.01, 0.9, 1.25);
    d *= 1 + a * (lm - 1);
    if (S.tempoVar) d *= 1 + (S.tempoVar / 100) * blockVar(u.block);
    if (S.paraRamp && u.pos < S.paraRampWords) d *= 1 + (S.paraRampStrength / 100) * (1 - u.pos / S.paraRampWords);
    if (u.endB) d += base * 2.0 * p;
    else if (u.endS) d += base * 1.2 * p;
    else if (/[,;:]/.test(u.punct)) d += base * 0.4 * p;
    else if (/[–—]/.test(u.punct)) d += base * 0.3 * p;
    return Math.max(d, 40);
  });
  suffix = new Float64Array(durs.length + 1);
  for (let i = durs.length - 1; i >= 0; i--) suffix[i] = suffix[i + 1] + durs[i];
  updateEffNote();
}
/* Gradual speed-up: effective speed grows with minutes read, capped at +50 %. */
function effWpm() {
  if (!S.speedUp || !session) return S.wpm;
  return Math.min(S.wpm * 1.5, S.wpm + S.speedUp * (playedMs() / 60000));
}
const WARM = [1.45, 1.3, 1.18, 1.08, 1.03];
function durFor(i) {
  let d = durs[i] * (S.wpm / effWpm());
  if (warmLeft > 0) { d *= WARM[WARM.length - warmLeft]; warmLeft--; }
  return d;
}
function unitForWord(g) {
  let lo = 0, hi = units.length - 1, r = 0;
  while (lo <= hi) { const m = (lo + hi) >> 1; if (units[m].wStart <= g) { r = m; lo = m + 1; } else hi = m - 1; }
  return r;
}
function rebuildAt(g) {
  if (!doc) return;
  makeUnits(); computeTiming();
  idx = unitForWord(g);
  gBlock = -1; lastSid = -1; lastStructKey = '';
  renderSegs();
}
function rebuild() { rebuildAt(units[idx] ? units[idx].wStart : 0); if (units.length) show(idx); }

/* =========================================================
   Display
   ========================================================= */
function orpIndex(chars) {
  const start = chars.findIndex(c => /[\p{L}\p{N}]/u.test(c));
  if (start < 0) return 0;
  let end = chars.length; while (end > start && !/[\p{L}\p{N}]/u.test(chars[end - 1])) end--;
  const L = end - start;
  const p = L <= 1 ? 0 : L <= 5 ? 1 : L <= 9 ? 2 : L <= 13 ? 3 : 4;
  return start + p;
}
function show(i) {
  if (!units.length) return;
  applyViewMode(false);
  if (viewMode === 'guided') renderGuided();
  else renderRsvp(i);
  updateStruct();
  renderContext();
  updateHud();
}
function renderRsvp(i) {
  const u = units[i];
  const w = el.word;
  w.className = 'word';
  el.anchor.classList.toggle('is-h', u.kind === 'h');
  el.anchor.classList.toggle('phrase', u.kind !== 'h' && u.words.length > 1);
  el.kind.textContent = '';
  if (u.kind === 'h') {
    w.classList.add('is-heading');
    let meta = '';
    if (S.sectionCard) {
      const s = sectionAt(i);
      if (s) meta = `Section ${s.k + 1} of ${sections.length}, ${nf(s.words)} words, about ${fmtTime(suffix[s.u] - suffix[s.end])}`;
    }
    w.innerHTML = `<div class="ht">${esc(u.text)}</div>${meta ? `<div class="hm">${meta}</div>` : ''}`;
  } else if (u.words.length === 1) {
    const chars = [...u.text], o = orpIndex(chars);
    w.innerHTML = `<span class="wi"><span class="pre">${esc(chars.slice(0, o).join(''))}</span><span class="o">${esc(chars[o] || '')}</span><span class="post">${esc(chars.slice(o + 1).join(''))}</span></span>`;
  } else {
    w.innerHTML = `<span class="wi">${esc(u.text)}</span>`;
  }
  if (u.kind === 'hw') w.classList.add('is-hw');
  if (S.kindLabels && u.kind !== 'h') {
    const B = doc.blocks[u.block];
    if (B.type === 'li' || B.type === 'ol') el.kind.textContent = `Punkt ${B.li} av ${B.ln}`;
    else if (B.type === 'q') el.kind.textContent = 'Citat';
    else if (u.kind === 'hw') el.kind.textContent = 'Rubrik';
  }
  layoutWord();
  if (u.sid !== lastSid) {
    if (S.anchorShift && !motionOff() && lastSid !== -1) {
      const W = el.reader.clientWidth, opts = [-0.035, -0.018, 0, 0.018, 0.035];
      el.anchor.style.setProperty('--shift', Math.round(opts[Math.floor(Math.random() * opts.length)] * W) + 'px');
    } else if (!S.anchorShift) el.anchor.style.setProperty('--shift', '0px');
    lastSid = u.sid;
  }
  renderTri();
}
/* Positions the word so the centre of its focus letter sits exactly on the focus point. */
function layoutWord() {
  const w = el.word, u = units[idx];
  if (!u || viewMode === 'guided') return;
  w.style.setProperty('--fit', 1);
  if (u.kind === 'h') {
    const ht = w.querySelector('.ht');
    if (ht && ht.scrollWidth > ht.clientWidth + 1) w.style.setProperty('--fit', (ht.clientWidth / ht.scrollWidth).toFixed(4));
    return;
  }
  const wi = w.querySelector('.wi'); if (!wi) return;
  const W = el.anchor.clientWidth, pad = Math.max(12, W * 0.035), fxPx = W * S.fx / 100;
  const r0 = wi.getBoundingClientRect(), total = r0.width;
  const o = wi.querySelector('.o');
  let s = 1, left;
  if (o) {
    const ro = o.getBoundingClientRect();
    const lead = ro.left - r0.left + ro.width / 2;
    if (lead > fxPx - pad) s = Math.min(s, (fxPx - pad) / lead);
    if (total - lead > W - fxPx - pad) s = Math.min(s, (W - fxPx - pad) / (total - lead));
    left = fxPx - lead * s;
    el.anchor.style.setProperty('--ow', (ro.width * s).toFixed(2) + 'px');
  } else {
    if (total > W - 2 * pad) s = (W - 2 * pad) / total;
    left = clamp(fxPx - total * s / 2, pad, W - pad - total * s);
  }
  if (s < 1) w.style.setProperty('--fit', s.toFixed(4));
  wi.style.left = left.toFixed(2) + 'px';
}
function renderTri() {
  const on = S.context === 'tri' && units.length && viewMode !== 'guided';
  el.tri.hidden = !on;
  if (!on) return;
  const prev = [], next = [];
  for (let k = idx - 1; k >= 0 && prev.length < 3; k--) { if (units[k].kind === 'h' || units[k].endS) break; prev.unshift(units[k].text); }
  for (let k = idx + 1; k < units.length && next.length < 3; k++) { if (units[k].kind === 'h') break; next.push(units[k].text); if (units[k].endS) break; }
  el.tri.querySelector('.prev').innerHTML = `<span>${esc(prev.join(' '))}</span>`;
  el.tri.querySelector('.next').textContent = next.join(' ');
}
function renderGuided() {
  const u = units[idx];
  const c = el.guided;
  let rebuilt = false;
  if (u.block !== gBlock) {
    gBlock = u.block;
    const from = Math.max(0, u.block - 2), to = Math.min(doc.blocks.length - 1, u.block + 3);
    let h = '';
    for (let b = from; b <= to; b++) {
      const r = blockUnits[b]; if (!r) continue;
      const B = doc.blocks[b];
      const cur = b === u.block ? ' cur' : '';
      const inner = units.slice(r[0], r[1] + 1).map((x, k) => `<span data-u="${r[0] + k}"${r[0] + k < idx ? ' class="done"' : ''}>${esc(x.text)}</span>`).join(' ');
      if (B.type === 'h') h += `<div class="gb gh gh${B.level}${cur}">${inner}</div>`;
      else if (B.type === 'li') h += `<div class="gb gli${cur}"><span class="gm">•</span>${inner}</div>`;
      else if (B.type === 'ol') h += `<div class="gb gli${cur}"><span class="gm">${B.li}.</span>${inner}</div>`;
      else if (B.type === 'q') h += `<div class="gb gq${cur}">${inner}</div>`;
      else h += `<p class="gb${cur}">${inner}</p>`;
    }
    c.innerHTML = `<div class="gcol">${h}</div>`;
    rebuilt = true;
  }
  const prev = c.querySelector('span.on');
  if (prev) { prev.classList.remove('on'); if (Number(prev.dataset.u) < idx) prev.classList.add('done'); }
  const cur = c.querySelector(`span[data-u="${idx}"]`);
  if (!cur) return;
  cur.classList.remove('done'); cur.classList.add('on');
  if (!rebuilt) c.querySelectorAll('span.done').forEach(s => { if (Number(s.dataset.u) > idx) s.classList.remove('done'); });
  const r = cur.getBoundingClientRect(), cr = c.getBoundingClientRect();
  const target = cr.top + cr.height * 0.4;
  if (rebuilt || r.top < cr.top + cr.height * 0.25 || r.bottom > cr.top + cr.height * 0.62) {
    c.scrollBy({ top: r.top - target, behavior: rebuilt || motionOff() ? 'auto' : 'smooth' });
  }
}
function renderContext() {
  const mode = S.context;
  const showIt = units.length && viewMode !== 'guided' && ((mode === 'pause' && !playing) || mode === 'always');
  el.ctx.hidden = !showIt;
  if (!showIt) return;
  const u = units[idx];
  let a = idx, b = idx;
  if (mode === 'always') {
    while (a > 0 && units[a - 1].sid === u.sid) a--;
    while (b < units.length - 1 && units[b + 1].sid === u.sid) b++;
  } else {
    while (a > 0 && units[a - 1].block === u.block) a--;
    while (b < units.length - 1 && units[b + 1].block === u.block) b++;
  }
  let html = '';
  for (let k = a; k <= b; k++) {
    const x = units[k];
    const cls = ['cu'];
    if (x.sid === u.sid) cls.push('cs');
    if (k === idx) cls.push('cur');
    if (x.kind !== 't') cls.push('ch');
    html += `<span class="${cls.join(' ')}" data-u="${k}">${esc(x.text)}</span> `;
  }
  el.ctx.innerHTML = html;
  const cur = el.ctx.querySelector('.cur');
  if (cur && el.ctx.scrollHeight > el.ctx.clientHeight) cur.scrollIntoView({ block: 'nearest' });
}
let lastStructKey = '';
function updateStruct() {
  const on = S.structBar && units.length;
  el.struct.hidden = !on;
  const u = units[idx];
  const s = u ? sectionAt(idx) : null;
  document.documentElement.style.setProperty('--sec', S.sectionColors && s ? s.color : 'var(--gs-blue)');
  if (!on) return;
  const key = (s ? s.k : -1) + ':' + u.block + ':' + viewMode;
  if (key === lastStructKey) return;
  lastStructKey = key;
  const path = s ? s.path : ['Introduction'];
  el.stPath.innerHTML = path.map(p => `<span title="${esc(p)}">${esc(p)}</span>`).join('<i>›</i>');
  const a = s ? s.u : 0, b = s ? s.end - 1 : (sections.length ? sections[0].u - 1 : units.length - 1);
  const blocks = [];
  for (let k = a; k <= b && k < units.length; k++) {
    const bi = units[k].block;
    if (doc.blocks[bi].type !== 'h' && blocks[blocks.length - 1] !== bi) blocks.push(bi);
  }
  const pos = blocks.indexOf(u.block);
  if (blocks.length && blocks.length <= 14) {
    el.stDots.innerHTML = blocks.map((bi, k) => `<b class="${k < pos ? 'read' : k === pos ? 'cur' : ''}"></b>`).join('');
    el.stDots.title = pos >= 0 ? `Stycke ${pos + 1} av ${blocks.length}` : `${blocks.length} stycken`;
  } else if (blocks.length) {
    el.stDots.textContent = pos >= 0 ? `Stycke ${pos + 1} av ${blocks.length}` : `${blocks.length} stycken`;
    el.stDots.removeAttribute('title');
  } else el.stDots.innerHTML = '';
  el.stSec.textContent = s ? `${s.k + 1} / ${sections.length}` : '';
  el.stSec.title = s ? `Section ${s.k + 1} of ${sections.length}` : '';
  renderSegs();
}
function renderSegs() {
  if (!doc || !doc.total || !units.length) { el.segs.innerHTML = ''; return; }
  const top = sections.filter(s => s.level <= 2);
  const cur = sectionAt(idx);
  let curTop = null;
  if (cur) curTop = top.filter(t => t.u <= cur.u).pop() || null;
  const parts = [];
  const firstU = top.length ? top[0].u : units.length;
  if (firstU > 0) parts.push({ a: 0, b: firstU, color: 'var(--faint)', cur: !curTop });
  top.forEach((t, k) => {
    const endU = k + 1 < top.length ? top[k + 1].u : units.length;
    parts.push({ a: t.u, b: endU, color: S.sectionColors ? t.color : 'var(--gs-blue)', cur: t === curTop });
  });
  el.segs.innerHTML = parts.map(p => {
    const l = units[p.a].wStart / doc.total * 100;
    const r = (p.b < units.length ? units[p.b].wStart : doc.total) / doc.total * 100;
    return `<i class="${p.cur ? 'cur' : ''}" style="left:${l}%;width:${r - l}%;background:${p.color}"></i>`;
  }).join('');
}
function updateHud() {
  const total = doc ? doc.total : 0;
  const read = units.length ? units[idx].wStart : 0;
  el.center.innerHTML = units.length ? `<b>${fmtTime(suffix[idx] * S.wpm / effWpm())}</b> kvar, ${nf(total - read)} ord` : '';
  el.stIdx.textContent = nf(read);
  el.stTotal.textContent = nf(total);
  const ew = Math.round(effWpm());
  el.speed.textContent = ew !== S.wpm ? `${ew} ord/min` : `${S.wpm} ord/min`;
  el.speed.title = ew !== S.wpm ? `Base ${S.wpm} wpm, sped up to ${ew}` : '';
  const pct = total ? (read / total) * 100 : 0;
  el.fill.style.width = pct + '%';
  el.progress.setAttribute('aria-valuenow', Math.round(pct));
  el.progress.setAttribute('aria-valuetext', `Word ${read} of ${total}`);
  const ms = playedMs();
  el.stActual.textContent = session && ms > 3000 && session.words > 5 ? `actual ${Math.round(session.words / (ms / 60000))} ord/min` : '';
}
function setStatus(state) {
  const labels = { empty: 'Ingen text', ready: 'Redo', reading: 'Läser', paused: 'Pausad', done: 'Färdig' };
  el.status.dataset.state = state;
  el.status.textContent = labels[state];
}
function updateEffNote() {
  const n = $('#effNote');
  if (!doc || !suffix[0]) { n.textContent = 'Öppna en text för att se hur pauser och anpassningar påverkar lästempot.'; return; }
  const eff = doc.total / (suffix[0] / 60000);
  n.textContent = `Med pauser och anpassningar blir tempot cirka ${Math.round(eff)} ord per minut och lästiden cirka ${fmtTime(suffix[0])}.`;
}
function applyViewMode(resetGuided = true) {
  const has = !!doc;
  el.anchor.hidden = !has || (viewMode === 'guided' && !breathing);
  el.guided.hidden = !has || viewMode !== 'guided' || breathing;
  if (resetGuided) gBlock = -1;
  $$('#modes button').forEach(b => b.setAttribute('aria-pressed', String(b.dataset.mode === viewMode)));
}
function setView(mode, atWord) {
  const oldN = chunkSize();
  viewMode = mode;
  const g = atWord != null ? atWord : (units[idx] ? units[idx].wStart : 0);
  if (chunkSize() !== oldN) rebuildAt(g); else idx = unitForWord(g);
  gBlock = -1; lastStructKey = '';
  applyViewMode();
}

/* =========================================================
   Playback
   ========================================================= */
function playedMs() {
  if (!session) return 0;
  return session.ms + (playing && !breathing && session.since ? performance.now() - session.since : 0);
}
function schedule() {
  nextAt = performance.now() + durFor(idx);
  timer = setTimeout(tick, Math.max(0, nextAt - performance.now()));
}
function play() {
  if (!units.length) return;
  if (el.status.dataset.state === 'done' && idx >= units.length - 1) idx = 0;
  // after a real pause, pick up at the start of the sentence for context
  if (S.resumeSentence && pausedAt && performance.now() - pausedAt > 3000 && units[idx].kind !== 'h') idx = sentenceStart(idx);
  pausedAt = 0;
  if (!session) newSession();
  playing = true;
  session.since = performance.now();
  warmLeft = S.warmup ? WARM.length : 0;
  el.body.classList.add('playing');
  el.play.innerHTML = ICON_PAUSE; el.play.setAttribute('aria-label', 'Pausa');
  setStatus('reading');
  show(idx);
  schedule();
  armZen();
}
function pause(count = true) {
  if (!playing) return;
  playing = false;
  clearTimeout(timer);
  if (breathing) endBreathVisual();
  else session.ms += performance.now() - session.since;
  session.since = 0;
  if (count) session.pauses++;
  pausedAt = performance.now();
  el.body.classList.remove('playing');
  el.play.innerHTML = ICON_PLAY; el.play.setAttribute('aria-label', 'Starta');
  setStatus('paused');
  savePos();
  wake();
  show(idx);
}
function toggle() { playing ? pause() : play(); }
let lastSave = 0;
function tick() {
  if (!playing) return;
  const u = units[idx];
  session.words += u.words.length;
  if (idx >= units.length - 1) { finish(); return; }
  const nextW = u.wStart + u.words.length;
  idx++;
  if (u.endB) {
    const played = playedMs();
    if (S.autoSwitch > 0 && played - modeSince >= S.autoSwitch * 1000) {
      modeSince = played;
      const target = viewMode === 'guided' ? (S.mode === 'guided' ? 'word' : S.mode) : 'guided';
      setView(target, nextW);
      warmLeft = S.warmup ? 3 : 0;
    }
    if (S.breathEvery > 0 && played - lastBreath >= S.breathEvery * 60000) {
      lastBreath = played;
      startBreath();
      return;
    }
  }
  if (S.pauseAtHeading && units[idx].kind === 'h') { show(idx); pause(false); return; }
  show(idx);
  const now = performance.now();
  if (nextAt < now - 250) nextAt = now;
  nextAt += durFor(idx);
  timer = setTimeout(tick, Math.max(0, nextAt - performance.now()));
  if (now - lastSave > 3000) { savePos(); lastSave = now; }
}
function startBreath() {
  breathing = true;
  session.ms += performance.now() - session.since;
  const pct = Math.round(units[idx].wStart / doc.total * 100);
  const s = sectionAt(idx);
  $('#breathPos').textContent = `${pct} % read` + (s ? `, section ${s.k + 1} of ${sections.length}` : '');
  el.breath.style.setProperty('--bl', S.breathLen + 's');
  const bar = el.breath.querySelector('.bbar i'); bar.style.animation = 'none'; void bar.offsetWidth; bar.style.animation = '';
  applyViewMode(false);
  el.anchor.classList.add('breathing'); el.breath.hidden = false;
  updateStruct(); updateHud();
  timer = setTimeout(() => {
    endBreathVisual();
    session.since = performance.now();
    warmLeft = S.warmup ? WARM.length : 0;
    show(idx);
    schedule();
  }, S.breathLen * 1000);
}
function endBreathVisual() {
  breathing = false;
  el.anchor.classList.remove('breathing'); el.breath.hidden = true;
  applyViewMode(false);
}
function go(i) {
  if (!units.length) return;
  i = clamp(i, 0, units.length - 1);
  if (i < idx && session) session.back++;
  if (breathing) { clearTimeout(timer); endBreathVisual(); if (playing) session.since = performance.now(); }
  idx = i;
  pausedAt = 0;
  if (el.status.dataset.state === 'done') setStatus('paused');
  show(idx);
  if (playing) {
    clearTimeout(timer);
    warmLeft = S.warmup ? 3 : 0;
    schedule();
  } else savePos();
}
function sentenceStart(i) { const s = units[i].sid; while (i > 0 && units[i - 1].sid === s) i--; return i; }
function prevSentence() {
  if (!units.length) return;
  const st = sentenceStart(idx);
  go(st < idx ? st : (st > 0 ? sentenceStart(st - 1) : 0));
}
function nextSentence() {
  if (!units.length) return;
  const s = units[idx].sid; let i = idx;
  while (i < units.length - 1 && units[i].sid === s) i++;
  go(i);
}
function setWpm(v) {
  S.wpm = clamp(Math.round(v), 100, 1000);
  saveSettings(); computeTiming(); syncSettingsUI(); updateHud();
}
function setMode(m) {
  S.mode = m; saveSettings(); modeSince = playedMs();
  setView(m);
  if (units.length) { show(idx); if (playing) { clearTimeout(timer); warmLeft = S.warmup ? 3 : 0; schedule(); } }
  syncSettingsUI();
}

/* zen: hide the controls while reading, bring them back on mouse movement */
let zenTimer = 0;
function armZen() {
  clearTimeout(zenTimer);
  if (!S.zen || !playing) { el.body.classList.remove('zen'); return; }
  zenTimer = setTimeout(() => { if (playing && S.zen && !document.querySelector('dialog[open]')) el.body.classList.add('zen'); }, 1600);
}
function wake() { el.body.classList.remove('zen'); armZen(); }
let lastMove = 0;
document.addEventListener('pointermove', e => {
  if (e.pointerType === 'touch') return;
  const now = performance.now(); if (now - lastMove < 120) return; lastMove = now;
  if (playing) wake();
});

/* =========================================================
   Sessions and results
   ========================================================= */
function newSession() { session = { start: Date.now(), since: 0, ms: 0, words: 0, pauses: 0, back: 0 }; modeSince = 0; lastBreath = 0; }
function finish() {
  if (!units.length) return;
  if (playing) pause(false);
  setStatus('done');
  savePos(true);
  if (!session || session.words < 1) { session = null; showToast('Börja läsa för att få ett resultat.'); return; }
  const mins = session.ms / 60000;
  const actual = mins > 0 ? session.words / mins : 0;
  const entry = { date: new Date().toISOString(), name: docName || 'Text', words: session.words, ms: Math.round(session.ms), set: S.wpm, actual: Math.round(actual), pauses: session.pauses, back: session.back, mode: S.mode, rating: null };
  const hist = store.get(KEY.history, []);
  hist.unshift(entry); store.set(KEY.history, hist.slice(0, 50));
  $('#sumGrid').innerHTML = [
    [fmtTime(session.ms), 'lästid'], [nf(session.words), 'lästa ord'], [Math.round(actual), 'faktiska ord/min'],
    [S.wpm, 'ord/min i grundtempo'], [session.pauses, 'pauser'], [session.back, 'hopp bakåt']
  ].map(([v, l]) => `<div><b>${v}</b><span>${l}</span></div>`).join('');
  $$('#rating button').forEach(b => b.setAttribute('aria-pressed', 'false'));
  renderHistory();
  session = null;
  updateHud();
  $('#sumDlg').showModal();
}
function renderHistory() {
  const hist = store.get(KEY.history, []).slice(0, 8);
  if (hist.length < 2) { $('#histWrap').innerHTML = ''; return; }
  const rows = hist.map(h => `<tr><td>${new Date(h.date).toLocaleDateString('en-GB', { day: 'numeric', month: 'short' })}</td><td>${esc(h.name).slice(0, 26)}</td><td>${MODE_NAME[h.mode] || '–'}</td><td>${h.actual}</td><td>${h.rating ?? '–'}</td></tr>`).join('');
  $('#histWrap').innerHTML = `<div style="font-weight:600;margin-bottom:6px">Earlier sessions</div><table class="hist"><thead><tr><th>Date</th><th>Text</th><th>Läge</th><th>wpm</th><th>Understood</th></tr></thead><tbody>${rows}</tbody></table>`;
}
$('#rating').addEventListener('click', e => {
  const b = e.target.closest('button[data-r]'); if (!b) return;
  $$('#rating button').forEach(x => x.setAttribute('aria-pressed', String(x === b)));
  const hist = store.get(KEY.history, []);
  if (hist[0]) { hist[0].rating = Number(b.dataset.r); store.set(KEY.history, hist); renderHistory(); }
});
$('#restartBtn').addEventListener('click', () => { $('#sumDlg').close(); idx = 0; setStatus('ready'); show(0); savePos(); });

/* =========================================================
   Loading text and recent documents
   ========================================================= */
function loadText(text, name, remember = true, sourceFile = null) {
  pause(false);
  const parsed = parseMarkdown(text);
  const d = buildDoc(parsed.blocks);
  if (!d.total) { showToast('Ingen läsbar text hittades.'); return; }
  doc = d; docName = name || 'Text'; docKey = KEY.pos + hash(docName + ':' + d.total + ':' + text.length);
  session = null; lastSid = -1; gBlock = -1; lastStructKey = ''; viewMode = S.mode; pausedAt = 0;
  el.docName.textContent = docName; document.title = `${docName} – Läsfokus`;
  el.empty.hidden = true; el.body.classList.remove('no-doc');
  makeUnits(); computeTiming();
  const saved = store.get(docKey, 0);
  idx = saved > 0 && saved < d.total - 5 ? unitForWord(saved) : 0;
  setStatus('ready');
  applyViewMode();
  renderSegs();
  show(idx);
  if (remember) addRecent(text, docName, d.total, sourceFile);
  const sk = parsed.skipped, parts = [];
  if (sk.code) parts.push(`${sk.code} kodblock`);
  if (sk.table) parts.push(`${sk.table} tabell${sk.table > 1 ? 'er' : ''}`);
  if (sk.math) parts.push(`${sk.math} ${sk.math > 1 ? 'formler' : 'formel'}`);
  if (sk.image) parts.push(`${sk.image} bild${sk.image > 1 ? 'er' : ''}`);
  let msg = `${nf(d.total)} ord, cirka ${fmtTime(suffix[0])}.`;
  if (parts.length) msg += ` Hoppade över ${parts.join(', ')}.`;
  if (idx > 0) showToast(msg + ' Fortsätter där du slutade.', 'Börja om', () => { go(0); if (session) session.back = 0; });
  else showToast(msg);
  el.reader.focus();
}
function addRecent(text, name, words, sourceFile) {
  if (name === 'Sample' || text.length > 1500000) return;
  const id = hash(name + ':' + text.length + ':' + text.slice(0, 200));
  let list = store.get(KEY.recent, []).filter(r => r.id !== id);
  list.unshift({ id, name, words, date: Date.now(), text, sourceFile });
  list = list.slice(0, 6);
  while (list.length && !store.set(KEY.recent, list)) list.pop();   // drop oldest if storage is full
}
function renderRecent() {
  const list = store.get(KEY.recent, []);
  $('#recentWrap').hidden = !list.length;
  $('#recentList').innerHTML = list.map(r => `<li><button class="open" data-id="${r.id}"><span>${esc(r.name)}</span><small>${nf(r.words)} words</small></button><button class="icon-btn rm" data-rm="${r.id}" title="Remove from recent" aria-label="Remove ${esc(r.name)} from recent">✕</button></li>`).join('');
}
$('#recentList').addEventListener('click', e => {
  const rm = e.target.closest('[data-rm]');
  if (rm) { store.set(KEY.recent, store.get(KEY.recent, []).filter(r => r.id !== rm.dataset.rm)); renderRecent(); return; }
  const b = e.target.closest('[data-id]'); if (!b) return;
  const r = store.get(KEY.recent, []).find(x => x.id === b.dataset.id);
  if (r) loadText(r.text, r.name, true, r.sourceFile || null);
});
function closeDoc() {
  pause(false);
  doc = null; units = []; sections = []; session = null;
  el.empty.hidden = false; el.body.classList.add('no-doc'); el.toast.classList.remove('show'); el.struct.hidden = true; el.ctx.hidden = true;
  el.docName.textContent = ''; document.title = 'Läsfokus – Kunskapsbasen';
  setStatus('empty'); applyViewMode(); renderSegs(); updateHud(); renderRecent();
}
function savePos(atEnd = false) {
  if (!docKey || !units.length) return;
  store.set(docKey, atEnd ? 0 : units[idx].wStart);
}
function readFile(file) {
  if (!file) return;
  if (!/\.(md|markdown|mdown|txt)$/i.test(file.name) && !/^text\//.test(file.type)) { showToast('Välj en Markdown- eller textfil (.md, .txt).'); return; }
  const r = new FileReader();
  r.onload = () => { if ($('#openDlg').open) $('#openDlg').close(); loadText(String(r.result), file.name.replace(/\.(md|markdown|mdown|txt)$/i, '')); };
  r.onerror = () => showToast('Filen kunde inte läsas.');
  r.readAsText(file, 'utf-8');
}
function buildToc() {
  const toc = $('#toc');
  if (!sections.length) { toc.innerHTML = '<p style="color:var(--muted)">This text has no headings.</p>'; return; }
  const cur = sectionAt(idx);
  toc.innerHTML = sections.map(s => {
    const cls = cur && s.k === cur.k ? 'cur' : (cur && s.k < cur.k ? 'read' : '');
    return `<a data-u="${s.u}" class="${cls}" style="padding-left:${8 + (s.level - 1) * 16}px;${S.sectionColors ? `--sec:${s.color}` : ''}"><span>${esc(s.text)}</span><small>${fmtTime(suffix[s.u] - suffix[s.end])}</small></a>`;
  }).join('');
}

/* =========================================================
   Settings
   ========================================================= */
function saveSettings() { store.set(KEY.settings, S); }
function fontSelect() {
  $('#s-font').innerHTML = Object.entries(FONTS).map(([k, f]) => `<option value="${k}">${f.label}</option>`).join('');
}
const loadedFonts = new Set();
function loadFont(k) {
  const f = FONTS[k]; if (!f || !f.g || loadedFonts.has(k)) return;
  const l = document.createElement('link');
  l.rel = 'stylesheet'; l.href = `https://fonts.googleapis.com/css2?family=${f.g}&display=swap`;
  l.onload = () => { if (document.fonts) document.fonts.ready.then(() => { if (units.length) show(idx); }); };
  document.head.appendChild(l); loadedFonts.add(k);
}
const OUT = {
  wpm: v => v + ' ord/min', wpmStep: v => '±' + v, speedUp: v => v ? `+${v} wpm per min` : 'Off', phraseSize: v => v + ' ord',
  adaptive: v => v + ' %', punctPause: v => v + ' %', paraRampWords: v => v + ' ord', paraRampStrength: v => '+' + v + ' %',
  headingTime: v => v + ' %', fontSize: v => v + ' px', fontWeight: v => v, letterSpacing: v => (v > 0 ? '+' : '') + v + ' %',
  fx: v => v + ' %', guideGap: v => v + ' %', guideLen: v => v + ' %', guideThick: v => v + ' px', guideOpacity: v => v + ' %',
  breathEvery: v => v === 0 ? 'Off' : v === 1 ? 'Every minute' : `Every ${v} minutes`, breathLen: v => v + ' s', tempoVar: v => v ? '±' + v + ' %' : 'Off'
};
function syncSettingsUI() {
  $$('[data-set]').forEach(inp => {
    const k = inp.dataset.set;
    if (inp.type === 'checkbox') inp.checked = !!S[k]; else inp.value = S[k];
  });
  $$('[data-out]').forEach(o => { const k = o.dataset.out; o.textContent = OUT[k] ? OUT[k](S[k]) : S[k]; });
  $$('#guidePresets button').forEach(b => b.setAttribute('aria-pressed', String(b.dataset.g === S.guideMode)));
}
function resolvedTheme() {
  if (S.theme !== 'system') return S.theme;
  return matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light';
}
function applyView() {
  const root = document.documentElement;
  root.dataset.theme = resolvedTheme();
  root.dataset.orp = S.orpColor;
  const st = root.style;
  st.setProperty('--fs', S.fontSize + 'px');
  st.setProperty('--fw', S.fontWeight);
  st.setProperty('--ls', (S.letterSpacing / 100) + 'em');
  st.setProperty('--fx', S.fx);
  st.setProperty('--gg', S.guideGap / 100);
  st.setProperty('--gl', S.guideLen / 100);
  st.setProperty('--gt', S.guideThick + 'px');
  st.setProperty('--gop', S.guideOpacity / 100);
  st.setProperty('--gs', S.guideStyle);
  el.guides.dataset.mode = S.guideMode;
  loadFont(S.font);
  st.setProperty('--reader-font', (FONTS[S.font] || FONTS.arial).css);
  el.body.classList.toggle('outline', S.textStyle === 'outline');
  el.body.classList.toggle('no-orp', !S.orp);
  el.body.classList.toggle('orp-bold', S.orpBold);
  el.body.classList.toggle('reduce-motion', S.reduceMotion);
}
const RETIME = new Set(['wpm', 'adaptive', 'punctPause', 'paraRamp', 'paraRampWords', 'paraRampStrength', 'headingTime', 'tempoVar', 'sectionCard']);
$('#setDlg').addEventListener('input', e => {
  const inp = e.target.closest('[data-set]'); if (!inp) return;
  const k = inp.dataset.set;
  let v = inp.type === 'checkbox' ? inp.checked : inp.value;
  if (typeof DEFAULTS[k] === 'number') v = Number(v);
  if (k === 'mode') { setMode(v); return; }
  S[k] = v; saveSettings();
  const o = $(`[data-out="${k}"]`); if (o) o.textContent = OUT[k] ? OUT[k](v) : v;
  applyView();
  if (k === 'headingWhole' || (k === 'phraseSize' && viewMode !== 'word') || (k === 'guidedPhrase' && viewMode === 'guided')) rebuild();
  else if (RETIME.has(k)) { computeTiming(); if (units.length) show(idx); }
  else if (units.length) { lastStructKey = ''; gBlock = -1; renderSegs(); show(idx); }
  else updateHud();
});
$('#guidePresets').addEventListener('click', e => {
  const b = e.target.closest('button[data-g]'); if (!b) return;
  S.guideMode = b.dataset.g;
  saveSettings(); syncSettingsUI(); applyView();
  if (units.length) show(idx);
});
$('#resetSet').addEventListener('click', () => {
  S = Object.assign({}, DEFAULTS); saveSettings(); syncSettingsUI(); applyView();
  if (doc) { setView(S.mode); rebuild(); }
});
matchMedia('(prefers-color-scheme: dark)').addEventListener('change', () => { if (S.theme === 'system') applyView(); });

/* =========================================================
   Interface
   ========================================================= */
let toastTimer;
function showToast(msg, actionLabel, action) {
  const t = el.toast;
  t.innerHTML = esc(msg) + (actionLabel ? ` <button type="button">${esc(actionLabel)}</button>` : '');
  if (actionLabel) t.querySelector('button').onclick = () => { action(); t.classList.remove('show'); };
  t.classList.add('show');
  clearTimeout(toastTimer);
  toastTimer = setTimeout(() => t.classList.remove('show'), actionLabel ? 7000 : 3000);
}
function openDlg(id) { if (playing) pause(); wake(); const d = $(id); if (!d.open) d.showModal(); }
$$('dialog').forEach(d => {
  d.addEventListener('click', e => { if (e.target === d || e.target.closest('[data-close]')) d.close(); });
  d.addEventListener('close', () => el.reader.focus({ preventScroll: true }));
});
function toggleTheme() {
  S.theme = resolvedTheme() === 'dark' ? 'light' : 'dark';
  saveSettings(); applyView(); syncSettingsUI();
  if (units.length) show(idx);
}
async function toggleFs() {
  try {
    if (!document.fullscreenElement) await document.documentElement.requestFullscreen();
    else await document.exitFullscreen();
  } catch { showToast('Full screen is not available here.'); }
}
document.addEventListener('fullscreenchange', () => { if (units.length) setTimeout(() => show(idx), 80); });

el.status.addEventListener('click', () => { if (units.length) finish(); else openDlg('#openDlg'); });
el.docName.addEventListener('click', () => { if (doc && !playing) closeDoc(); });
el.docName.title = 'Close this text';
el.docName.style.cursor = 'pointer';
$('#openBtn').addEventListener('click', () => openDlg('#openDlg'));
$('#tocBtn').addEventListener('click', () => { buildToc(); openDlg('#tocDlg'); const c = $('#toc a.cur'); if (c) c.scrollIntoView({ block: 'center' }); });
$('#setBtn').addEventListener('click', () => { syncSettingsUI(); updateEffNote(); openDlg('#setDlg'); });
$('#helpBtn').addEventListener('click', () => openDlg('#helpDlg'));
$('#themeBtn').addEventListener('click', toggleTheme);
$('#fsBtn').addEventListener('click', toggleFs);
$('#emptyOpen').addEventListener('click', () => $('#fileInput').click());
$('#emptyDemo').addEventListener('click', () => loadText($('#demoText').textContent, 'Sample'));
$('#demoBtn').addEventListener('click', () => { $('#openDlg').close(); loadText($('#demoText').textContent, 'Sample'); });
$('#pickFile').addEventListener('click', () => $('#fileInput').click());
$('#fileInput').addEventListener('change', e => { readFile(e.target.files[0]); e.target.value = ''; });
$('#pasteBtn').addEventListener('click', () => {
  const t = $('#pasteArea').value;
  if (!t.trim()) { showToast('Klistra in en text först.'); return; }
  $('#openDlg').close();
  const h = t.match(/^\s*#\s+(.+)$/m);
  loadText(t, h ? cleanInline(h[1]).slice(0, 60) : 'Pasted text');
});
$('#toc').addEventListener('click', e => { const a = e.target.closest('a[data-u]'); if (!a) return; $('#tocDlg').close(); go(Number(a.dataset.u)); });
el.ctx.addEventListener('click', e => { const s = e.target.closest('[data-u]'); if (s) go(Number(s.dataset.u)); });
$('#modes').addEventListener('click', e => { const b = e.target.closest('button[data-mode]'); if (b) setMode(b.dataset.mode); });

el.play.addEventListener('click', toggle);
let suppressClick = false;
el.reader.addEventListener('click', e => {
  if (suppressClick) { suppressClick = false; return; }
  if (e.target.closest('.empty')) return;
  const s = e.target.closest('#guided [data-u]');
  if (s) { go(Number(s.dataset.u)); return; }
  toggle();
});
// swipe to step on touch screens
let touch0 = null;
el.reader.addEventListener('touchstart', e => { if (e.touches.length === 1) touch0 = { x: e.touches[0].clientX, y: e.touches[0].clientY }; }, { passive: true });
el.reader.addEventListener('touchend', e => {
  if (!touch0 || !units.length) return;
  const t = e.changedTouches[0], dx = t.clientX - touch0.x, dy = t.clientY - touch0.y;
  touch0 = null;
  if (Math.abs(dx) > 40 && Math.abs(dx) > Math.abs(dy) * 1.5) {
    suppressClick = true;
    setTimeout(() => { suppressClick = false; }, 400);
    dx < 0 ? go(idx + 1) : go(idx - 1);
  }
});
// wheel steps through the text while paused
let wheelAcc = 0;
el.reader.addEventListener('wheel', e => {
  if (!units.length || playing || viewMode === 'guided') return;
  e.preventDefault();
  wheelAcc += e.deltaY;
  if (Math.abs(wheelAcc) >= 40) { go(idx + Math.sign(wheelAcc)); wheelAcc = 0; }
}, { passive: false });

$('#prevU').addEventListener('click', () => go(idx - 1));
$('#nextU').addEventListener('click', () => go(idx + 1));
$('#prevS').addEventListener('click', prevSentence);
$('#nextS').addEventListener('click', nextSentence);
$('#slower').addEventListener('click', () => setWpm(S.wpm - S.wpmStep));
$('#faster').addEventListener('click', () => setWpm(S.wpm + S.wpmStep));

// progress bar: click, drag and section name on hover
function fracFromEvent(e) { const r = el.progress.getBoundingClientRect(); return clamp((e.clientX - r.left) / r.width, 0, 1); }
function seekFromEvent(e) { if (!doc) return; go(unitForWord(Math.floor(fracFromEvent(e) * (doc.total - 1)))); }
el.progress.addEventListener('pointerdown', e => {
  seekFromEvent(e); el.progress.setPointerCapture(e.pointerId);
  const mv = ev => seekFromEvent(ev);
  const up = () => { el.progress.removeEventListener('pointermove', mv); el.progress.removeEventListener('pointerup', up); };
  el.progress.addEventListener('pointermove', mv); el.progress.addEventListener('pointerup', up);
});
el.progress.addEventListener('pointermove', e => {
  if (!doc || !units.length) return;
  const f = fracFromEvent(e);
  const s = sectionAt(unitForWord(Math.floor(f * (doc.total - 1))));
  el.ptip.textContent = s ? s.text : 'Introduction';
  el.ptip.style.left = clamp(f * 100, 8, 92) + '%';
  el.ptip.hidden = false;
});
el.progress.addEventListener('pointerleave', () => { el.ptip.hidden = true; });
el.progress.addEventListener('keydown', e => {
  if (!doc) return;
  if (e.key === 'ArrowLeft' || e.key === 'ArrowRight') {
    e.preventDefault(); e.stopPropagation();
    const step = Math.max(1, Math.round(doc.total * 0.02)) * (e.key === 'ArrowLeft' ? -1 : 1);
    go(unitForWord(clamp(units[idx].wStart + step, 0, doc.total - 1)));
  }
});

// keyboard
document.addEventListener('keydown', e => {
  if (document.querySelector('dialog[open]')) return;
  if (e.target.matches('input, textarea, select')) return;
  if (e.ctrlKey || e.metaKey || e.altKey) return;
  const k = e.key;
  const handled = () => e.preventDefault();
  if (k === ' ' || k === 'Spacebar') { handled(); toggle(); return; }
  if (k === '?') { handled(); openDlg('#helpDlg'); return; }
  if (k === 'o' || k === 'O') { handled(); openDlg('#openDlg'); return; }
  if (k === 's' || k === 'S') { handled(); $('#setBtn').click(); return; }
  if (k === 't' || k === 'T') { handled(); toggleTheme(); return; }
  if (k === 'f' || k === 'F') { handled(); toggleFs(); return; }
  if (!units.length) return;
  if (k === 'ArrowLeft') { handled(); e.shiftKey ? prevSentence() : go(idx - 1); }
  else if (k === 'ArrowRight') { handled(); e.shiftKey ? nextSentence() : go(idx + 1); }
  else if (k === 'ArrowUp') { handled(); setWpm(S.wpm + S.wpmStep); }
  else if (k === 'ArrowDown') { handled(); setWpm(S.wpm - S.wpmStep); }
  else if (k === 'Home') { handled(); go(0); }
  else if (k === 'End') { handled(); go(units.length - 1); }
  else if (k === 'PageUp') { handled(); const s = sectionAt(idx); const t = s && s.u < idx ? s : sections.filter(x => x.u < idx).pop(); go(t ? t.u : 0); }
  else if (k === 'PageDown') { handled(); const h = sections.find(x => x.u > idx); if (h) go(h.u); }
  else if (k === 'c' || k === 'C' || k === 'i' || k === 'I') { handled(); $('#tocBtn').click(); }
  else if (k === 'm' || k === 'M') {
    handled();
    const order = ['word', 'phrase', 'guided'];
    const m = order[(order.indexOf(S.mode) + 1) % order.length];
    setMode(m); showToast(MODE_NAME[m] + ' mode');
  }
  else if (k === 'g' || k === 'G') {
    handled();
    const order = Object.keys(GUIDE_NAME);
    S.guideMode = order[(order.indexOf(S.guideMode) + 1) % order.length];
    saveSettings(); applyView(); showToast(GUIDE_NAME[S.guideMode]);
  }
  else if (k === 'k' || k === 'K') {
    handled();
    const order = ['off', 'pause', 'always', 'tri'];
    S.context = order[(order.indexOf(S.context) + 1) % order.length]; saveSettings();
    const names = { off: 'Context off', pause: 'Context when paused', always: 'Current sentence shown', tri: 'Previous and next phrase' };
    showToast(names[S.context]); show(idx);
  }
});
document.addEventListener('keyup', e => { if (e.key === ' ' && !e.target.matches('input, textarea, select')) e.preventDefault(); });

// drag and drop
let dragDepth = 0;
window.addEventListener('dragenter', e => { if ([...(e.dataTransfer?.types || [])].includes('Files')) { dragDepth++; el.body.classList.add('dragging'); } });
window.addEventListener('dragleave', () => { dragDepth = Math.max(0, dragDepth - 1); if (!dragDepth) el.body.classList.remove('dragging'); });
window.addEventListener('dragover', e => e.preventDefault());
window.addEventListener('drop', e => {
  e.preventDefault(); dragDepth = 0; el.body.classList.remove('dragging');
  const f = e.dataTransfer?.files?.[0]; if (f) readFile(f);
});
const drop = $('#drop');
drop.addEventListener('dragover', () => drop.classList.add('over'));
drop.addEventListener('dragleave', () => drop.classList.remove('over'));
drop.addEventListener('drop', () => drop.classList.remove('over'));

let resizeRaf = 0;
window.addEventListener('resize', () => { cancelAnimationFrame(resizeRaf); resizeRaf = requestAnimationFrame(() => { if (units.length) { layoutWord(); if (viewMode === 'guided') { gBlock = -1; renderGuided(); } } }); });
document.addEventListener('visibilitychange', () => { if (document.hidden && playing) pause(); });
window.addEventListener('beforeunload', () => savePos());
window.addEventListener('pagehide', () => savePos());
if (document.fonts) document.fonts.ready.then(() => { if (units.length) layoutWord(); });

setInterval(() => { if (playing) updateHud(); }, 1000);

/* start */
fontSelect(); syncSettingsUI(); applyView(); applyViewMode(); updateHud(); renderRecent();
el.play.innerHTML = ICON_PLAY;
const initialDocument = <?= json_encode($boot, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_INVALID_UTF8_SUBSTITUTE) ?>;
if (initialDocument) loadText(initialDocument.content, initialDocument.name, true, initialDocument.name);
})();
</script>
</body>
</html>
